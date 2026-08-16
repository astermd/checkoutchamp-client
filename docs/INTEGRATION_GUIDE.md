# Integration guide

Getting `astermd/checkoutchamp-client` into a production application, and the
decisions worth making deliberately along the way.

This guide assumes you already have Checkout Champ API credentials. For what
each endpoint accepts and returns, use the provider's own reference:
<https://apidocs.checkoutchamp.com/>.

---

## 1. Install

```bash
composer require astermd/checkoutchamp-client
```

Requires **PHP 8.4 or newer** (8.5 recommended), `ext-curl` and `ext-json`.
There are no other runtime dependencies, so the package will not drag a
framework or an HTTP library into your application.

---

## 2. Read this before you deploy

The Checkout Champ API takes `loginId` and `password` as **URL query string
parameters**, and every other parameter with them. Your account password is in
the URL of every request.

That is the provider's design and this client follows it, but it changes what
"secure deployment" means for you:

| Risk | What to do |
|---|---|
| A forward proxy or egress gateway logs full request URLs | Audit it. Turn URL logging off for this host, or route around it. |
| An APM agent captures outbound HTTP URLs | Check its redaction config before enabling it on these calls. |
| A TLS-inspecting appliance sits in the path | It sees the password in cleartext. Confirm what it retains. |
| Someone enables `debugRedact => false` | Your password and full card numbers land on disk. Keep it off outside local debugging. |

This package always verifies TLS certificates and offers no switch to disable
that — without verification, credentials in a URL are readable by anyone on
the path.

If you can get confirmation from Checkout Champ support that the API accepts
credentials in an `application/x-www-form-urlencoded` POST body, that is a
strictly better arrangement and worth raising as a change request.

---

## 3. Supply credentials

Both credentials are **required constructor arguments that you pass in**. The
package has no credential discovery of any kind: it ships no defaults, reads no
environment variables, opens no config file, and consults no global state.
Whatever you hand the constructor is what it uses.

```php
use AsterMD\CheckoutChampClient\API;

$api = new API($loginId, $password);
```

That is deliberate. Credentials can never arrive from somewhere you did not
intend, and nothing changes behaviour between machines because of an
environment difference.

Where *you* get `$loginId` and `$password` from is entirely your decision — a
secrets manager, your application's configuration, or a per-account record in
your own database. The package neither knows nor cares.

### Multiple Checkout Champ accounts

Because credentials are constructor arguments rather than ambient
configuration, integrating several accounts is just several instances. Nothing
is shared or cached between them:

```php
final class CheckoutChampFactory
{
    /** @param object $account A record from your own store */
    public function forAccount(object $account): API
    {
        return new API($account->loginId, $account->password, [
            'host' => $account->host ?: 'api.checkoutchamp.com',
        ]);
    }
}
```

Build the client at the point of use, from the account you are acting for.
Avoid registering a single process-wide instance unless you genuinely only ever
talk to one account.

### Handling the credentials themselves

If either credential has ever been committed, captured in a proxy log, or
pasted into a ticket, rotate it — removing it from a file does not un-expose
it. Given that this API puts the password in the request URL (see section 2),
treat rotation as routine rather than exceptional.

---

## 4. Choose the host

The client takes a **bare hostname** and builds the scheme and path itself:

```php
new API($id, $pw, ['host' => 'api.checkoutchamp.com']);                     // fine
new API($id, $pw, ['host' => 'https://api.checkoutchamp.com']);             // throws
new API($id, $pw, ['host' => 'api.checkoutchamp.com/v1']);                  // throws
new API($id, $pw, ['host' => 'api.checkoutchamp.com', 'basePath' => 'v1']); // fine
```

Omitting `host` gives you `api.checkoutchamp.com`. If your account is issued a
different hostname, pass it — the client applies no environment branching of
its own, so a test account is reached the same way as production. Keep the host
in configuration so one build can be pointed at either.

---

## 5. Make a call and read the result

Resource calls are chainable and return the client; an accessor produces the
result.

```php
$result = $api->orderQuery(['orderId' => '123'])->getInArray();

$body = $result['response'];   // the provider's own response, unwrapped
```

| Accessor | `response` contains |
|---|---|
| `get()` | the provider's raw JSON string |
| `getInArray()` | that JSON decoded to an associative array |
| `getInObject()` | that JSON decoded to an object graph |

This package adds **no envelope of its own** — what you get back is what
Checkout Champ sent, so consult their reference for the `result` / `message`
shape of each endpoint.

Pass `true` to add a `payload` key with the endpoint called and the parameters
you sent. It never includes your credentials, so it is safe to attach to your
own error reports.

---

## 6. Handle failures

Three distinct failure modes, surfacing differently. Handling all three is the
difference between a robust integration and a fragile one.

### a. Your mistake — a `CheckoutChampException`

Thrown for an empty credential, a host that is not bare, an unknown method, or
a response that is not valid JSON.

```php
use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;

try {
    $result = $api->orderQuery(['orderId' => $id])->getInArray();
} catch (CheckoutChampException $e) {
    report($e);
}
```

Note that a non-JSON response raises this too — an HTML error page from a load
balancer, for example, arrives as an exception rather than as data.

### b. The provider rejected the request

Not an exception. Checkout Champ reports it in its own response body:

```php
$body = $api->orderQuery(['orderId' => $id])->getInArray()['response'];

if (($body['result'] ?? null) !== 'SUCCESS') {
    $message = $body['message'] ?? 'unknown error';
}
```

### c. The request never arrived

A DNS failure, a timeout, a TLS problem. Also not an exception:

```php
$body = $api->orderQuery()->getInArray()['response'];

if (isset($body['curlError'])) {
    // transport failure — safe to retry
}
```

### Timeouts and retries

Defaults are 30 s transfer, 10 s connect. Tighten them for user-facing paths
and widen them for background jobs:

```php
$interactive = new API($id, $pw, ['timeout' => 5,   'connectTimeout' => 2]);
$batch       = new API($id, $pw, ['timeout' => 120, 'connectTimeout' => 10]);
```

The package performs no retries — that policy belongs in your job runner, where
it can be observed and bounded. Retry on `curlError`. **Do not blindly retry
`importOrder`, `preauth`, `importUpsale` or `qa`** — they move money or change
order state and are not idempotent.

---

## 7. Logging in production

Logging is off unless you enable it, and enabling it needs a destination —
either `debugFile` or `debugSink` — or construction throws.

```php
$api = new API($id, $pw, [
    'debug'              => true,
    'debugFile'          => '/var/log/checkoutchamp/client.log',
    'debugRetentionDays' => 7,
    'debugTimezone'      => 'UTC',
]);
```

- Redaction is on by default and **covers the URL**, which is what keeps your
  password out of the log. Leave it on.
- One file per day: `client-2026-08-16.log`, `client-2026-08-17.log`, …
- Files older than the retention window are pruned once per process. Pruning
  matches only this package's own dated pattern and reads the date from the
  filename, never the mtime.
- Point `debugFile` at a directory your web user can write to and your
  application cannot serve. Never under a public document root.

### Shipping elsewhere

A sink closure replaces the file sink entirely — the package then writes no
files, and retention becomes yours:

```php
'debugSink' => static function (string $entry): void {
    $psrLogger->debug($entry);
},
```

Anything throwing inside your sink is caught and discarded; a logging failure
will not break an API call.

### What a redacted entry looks like

```
curl --location --request POST 'https://api.checkoutchamp.com/order/import/?sessionId=sess_1&cardNumber=[REDACTED]&cvv=[REDACTED]&loginId=[REDACTED]&password=[REDACTED]'

# Response: HTTP 200
{"result":"SUCCESS","message":{"orderId":"123"}}
```

The endpoint and the ordinary parameters stay readable, which is what makes the
entry useful for support. Redacted logs are still sensitive — they record who
touched what and when. Encrypt at rest, restrict read access, and retain them
no longer than the order data they describe.

---

## 8. Testing your own code

Inject a fake transport instead of stubbing the client:

```php
use AsterMD\CheckoutChampClient\API;
use AsterMD\CheckoutChampClient\Http\HttpClientInterface;
use AsterMD\CheckoutChampClient\Http\Request;
use AsterMD\CheckoutChampClient\Http\Response;

final class FakeCheckoutChamp implements HttpClientInterface
{
    public $lastRequest;

    public function send(Request $request): Response
    {
        $this->lastRequest = $request;

        return new Response(200, '{"result":"SUCCESS"}', ['http_code' => 200]);
    }
}

$transport = new FakeCheckoutChamp();
$api       = new API('test-login', 'test-password', [], $transport);

$api->orderQuery(['orderId' => '123']);

self::assertStringStartsWith(
    'https://api.checkoutchamp.com/order/query/?',
    $transport->lastRequest->getUrl()
);
```

Use obviously fake credentials. Never a real login, not even an expired one.

---

## 9. Reusing the client

A single `API` instance is safe to reuse across many calls. Per-request state
is cleared after every call, so parameters from one call cannot leak into the
next.

Two caveats:

- `withProxy()` applies to the **next call only** and then clears itself.
- The instance is not designed for concurrent use inside one process. Give each
  worker or coroutine its own.

---

## 10. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `A login ID and password are required` | One credential empty | Check the values you passed to the constructor — nothing is defaulted |
| `The host must be a bare hostname…` | A full URL in `host` | Strip `https://`, move any path to `basePath` |
| `Debug logging needs either a "debugFile"…` | `debug => true` with no destination | Add `debugFile` or `debugSink` |
| `No such method found` | Typo, or a method this client does not implement | `API::supportedMethods()` lists all 14 |
| `API response is not valid JSON` | An HTML error page from a proxy or load balancer | Inspect with `get()` before decoding |
| `curlError` on every call | Network, DNS, TLS or firewall | Verify the host and that egress to it is allowed |
| Auth failures despite correct credentials | A parameter named `loginId`/`password` in `$params` | Harmless — credentials are appended last and always win |
| No log file appears | Directory not writable, or `debug` still off | Check permissions on the `debugFile` directory |

---

## 11. Migrating from a pre-1.0 internal build

| Before | Now |
|---|---|
| The old internal namespace | `use AsterMD\CheckoutChampClient\API;` |
| `new API($endPoint, $userName, $password)` | `new API($loginId, $password, ['host' => $host])` |
| `$endPoint = 'https://api.checkoutchamp.com'` | `$host = 'api.checkoutchamp.com'` — no scheme |
| `get(true)['payload']['password']` | Absent — that was a credential leak |
| `$api->campaignQuery()` with no argument | Now passes `[]`; previously passed `false` |
| `catch (\Exception $e)` | Still works; `CheckoutChampException` extends `\Exception` |

Behaviour that changed and may need attention:

- TLS verification is now enforced. If requests start failing against an
  internal proxy with a self-signed certificate, install the CA properly rather
  than looking for a switch — there is not one.
- Requests now time out. A call that previously hung forever will now fail
  after 30 seconds by default.
- Exception messages are no longer blank. The previous build shipped an empty
  `config/` directory, so every exception carried an empty string; anything
  matching on `$e->getMessage() === ''` needs updating.
