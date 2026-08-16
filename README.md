# astermd/checkoutchamp-client

A small, dependency-free PHP client for the Checkout Champ API — orders, leads,
upsales, campaigns, customers, transactions and lander clicks — with opt-in
request logging that is redacted by default.

> **Unofficial.** This is an independent client library. It is not the official
> Checkout Champ PHP SDK and is not affiliated with, endorsed by or supported by
> Checkout Champ. "Checkout Champ" and related marks belong to their owner,
> <https://checkoutchamp.com/>. The name is used here only to identify the API
> this library talks to. See [LICENSE](LICENSE) for the full notice.

API reference: **<https://apidocs.checkoutchamp.com/>**

## Requirements

- PHP **8.4 minimum**, **8.5 recommended**
- `ext-curl`, `ext-json`

No runtime dependencies. CI runs the full gate on 8.4 and 8.5.

## Installation

```bash
composer require astermd/checkoutchamp-client
```

## Quick start

```php
use AsterMD\CheckoutChampClient\API;

$api = new API($loginId, $password);      // host defaults to api.checkoutchamp.com

$orders = $api->orderQuery(['orderId' => '123'])->getInArray();

if ($orders['response']['result'] === 'SUCCESS') {
    // ...
}
```

The login ID and password are **required arguments**. This package ships no
default credentials and reads none from the environment.

## ⚠️ Credentials travel in the URL

The Checkout Champ API authenticates by taking `loginId` and `password` as
**query string parameters**. That is the provider's design, and this client
follows it — but it means your account password appears in the URL of every
request.

Before deploying, read the [security policy](SECURITY.md). In short: anything
between your application and the internet that records outbound request URLs —
a forward proxy, an egress gateway, an APM agent, a TLS-inspecting appliance —
will record your password in cleartext. Audit that path first.

What this package does about it:

- TLS certificate verification is always on, with no option to disable it.
- Debug logging **masks credentials in the logged URL** rather than writing it
  verbatim (see below).
- `getPayloadInfo()` never returns credentials.

## Configuration

```php
$api = new API($loginId, $password, [
    'host'               => 'api.checkoutchamp.com',
    'basePath'           => '',
    'timeout'            => 30,
    'connectTimeout'     => 10,
    'debug'              => false,
    'debugRedact'        => true,
    'debugFile'          => null,
    'debugRetentionDays' => 7,
    'debugTimezone'      => 'UTC',
    'debugSink'          => null,
]);
```

| Option | Type | Default | Purpose |
|---|---|---|---|
| `host` | string | `api.checkoutchamp.com` | **Bare hostname only.** A scheme, path, query or space is rejected. |
| `basePath` | string | `''` | Optional path prefix below the host. |
| `timeout` | int | `30` | Transfer timeout in seconds. |
| `connectTimeout` | int | `10` | Connection timeout in seconds. |
| `debug` | bool | `false` | Master switch for request/response logging. |
| `debugRedact` | bool | `true` | Mask credentials and sensitive fields in the logged URL, headers and body. |
| `debugFile` | string | — | Base path for the built-in dated file sink. Required when `debug` is on and no `debugSink` is given. |
| `debugRetentionDays` | int | `7` | Days of log history to keep. `0` keeps everything. |
| `debugTimezone` | string | `UTC` | IANA timezone for log timestamps and dated filenames. |
| `debugSink` | callable | — | Replaces the file sink entirely. |

If your account is issued a different hostname, pass it — the client treats
every host identically and applies no environment branching of its own:

```php
$api = new API($loginId, $password, ['host' => 'crm-host-from-your-account']);
```

Full URLs are rejected on purpose, so the scheme and path assembly stay inside
the client:

```php
new API($id, $pw, ['host' => 'https://api.checkoutchamp.com']);          // throws
new API($id, $pw, ['host' => 'api.checkoutchamp.com/v1']);               // throws
new API($id, $pw, ['host' => 'api.checkoutchamp.com', 'basePath' => 'v1']); // correct
```

## Reading a response

Every resource call is chainable and returns the client. Three accessors read
the result, which is the provider's own response — this package adds no
envelope of its own:

```php
$api->orderQuery()->get();          // raw JSON string
$api->orderQuery()->getInArray();   // decoded to an array
$api->orderQuery()->getInObject();  // decoded to an object
```

Pass `true` to also receive the endpoint and the parameters you sent:

```php
$api->orderQuery(['orderId' => '123'])->getInArray(true);
```

```php
[
    'response' => [ /* the provider's body, verbatim */ ],
    'payload'  => [
        'endPoint' => 'https://api.checkoutchamp.com/order/query/',
        'orderId'  => '123',
    ],
]
```

`payload` never contains your credentials — it is safe to surface in your own
diagnostics.

If the request never reached the provider, the array accessor returns
`['curlError' => '...']` instead. A response that is not valid JSON raises a
`CheckoutChampException`.

## Available methods

Consult the [Checkout Champ API reference](https://apidocs.checkoutchamp.com/)
for the fields each endpoint accepts. Every call is a `POST`, and `$params` is
sent as query string parameters.

### Orders, leads and upsales

| Method | Request |
|---|---|
| `orderQuery(array $params = [])` | `POST /order/query/` |
| `importLeads(array $params = [])` | `POST /leads/import/` |
| `updateOrder(array $params = [])` | `POST /order/update/` |
| `preauth(array $params = [])` | `POST /order/preauth/` |
| `importOrder(array $params = [])` | `POST /order/import/` |
| `importUpsale(array $params = [])` | `POST /upsale/import/` |
| `confirm(array $params = [])` | `POST /order/confirm/` |
| `qa(array $params = [])` | `POST /order/qa/` |

### Campaigns

| Method | Request |
|---|---|
| `campaignQuery(array $params = [])` | `POST /campaign/query/` |

### Customers

| Method | Request |
|---|---|
| `customerQuery(array $params = [])` | `POST /customer/query/` |
| `addnote(array $params = [])` | `POST /customer/addnote/` |

### Transactions

| Method | Request |
|---|---|
| `transactionsQuery(array $params = [])` | `POST /transactions/query/` |

### Landers

| Method | Request |
|---|---|
| `importClick(array $params = [])` | `POST /landers/clicks/import/` |
| `confirmPaypal(array $params = [])` | `POST /transactions/confirmPaypal/` |

Examples:

```php
$api->importLeads([
    'campaignId'   => '7',
    'emailAddress' => 'buyer@example.test',
])->getInArray();

$api->importOrder(['sessionId' => 'sess_1', 'product1_id' => '9'])->getInArray();

$api->qa(['orderId' => '123', 'qaStatus' => 'APPROVED'])->getInObject();
```

Parameters you supply cannot shadow the credentials — they are appended last,
so a `password` key in `$params` is overridden by the configured value.

## Errors

Everything the package raises is an
`AsterMD\CheckoutChampClient\Exception\CheckoutChampException`, which extends
`\Exception`:

```php
use AsterMD\CheckoutChampClient\Exception\CheckoutChampException;

try {
    $result = $api->orderQuery(['orderId' => $id])->getInArray();
} catch (CheckoutChampException $e) {
    // empty credentials, non-bare host, unknown method, or a non-JSON response
}
```

Provider-side rejections come back in the response body as the provider's own
`result` / `message` fields, and transport failures come back as `curlError` —
neither is an exception.

## Debug logging

Logging is off unless you turn it on. When on, entries are redacted by default
and written as copy-pasteable cURL commands with the response beneath.

```php
$api = new API($loginId, $password, [
    'debug'              => true,
    'debugFile'          => '/var/log/checkoutchamp/client.log',
    'debugRetentionDays' => 7,
]);
```

Which produces `/var/log/checkoutchamp/client-2026-08-16.log` containing:

```
[2026-08-16 09:14:02.481930 UTC]
curl --location --request POST 'https://api.checkoutchamp.com/order/import/?sessionId=sess_1&cardNumber=[REDACTED]&cvv=[REDACTED]&loginId=[REDACTED]&password=[REDACTED]'

# Response: HTTP 200
{"result":"SUCCESS","message":{"orderId":"123"}}
```

### The URL is redacted, not logged verbatim

This is a deliberate departure from how most API clients log. Because Checkout
Champ puts **everything** in the query string — credentials, customer details
and cardholder data alike — logging the URL verbatim would write your account
password and full card numbers to disk on every call.

So redaction covers the URL too:

- **Masked:** `loginId`, `password`, and every sensitive field name — card
  number, CVV/CVC, expiry, bank account and routing numbers, IBAN, SSN, tax ID,
  date of birth, and every `*_token`.
- **Also masked by shape:** any 13–19 digit value that passes a Luhn check, so a
  card number is caught even under an unexpected parameter name.
- **Preserved:** the scheme, host and path, plus every parameter that is not
  sensitive. You can always see which endpoint was called with which order ID.

Headers and response bodies are redacted by the same field rules.

**Redaction never changes what is sent or what you receive.** The logger reads
from immutable request and response objects and produces a string; the wire
request and the value returned to your code are untouched. The test suite
asserts this directly.

### Turning redaction off

```php
$api = new API($loginId, $password, [
    'debug'       => true,
    'debugRedact' => false,   // writes your password and full card numbers to disk
    'debugFile'   => '/tmp/checkoutchamp-debug.log',
]);
```

Local debugging only. **Never enable it in production.**

### File rotation and retention

The built-in sink writes one file per calendar day, deriving the name from your
base path: `/var/log/checkoutchamp/client.log` becomes
`client-2026-08-16.log`, `client-2026-08-17.log`, and so on.

Pruning removes files older than `debugRetentionDays` (default 7; `0` keeps
everything). It only ever matches this package's own dated filename pattern for
your base path — other files in the directory are never touched — and it reads
the age from the filename rather than the modification time, so an appended-to
or restored file keeps its true age. It runs once per process, not once per
request.

### Sending logs somewhere else

Supply a closure and the file sink is replaced entirely. The package then
writes no files, and retention becomes your responsibility:

```php
$api = new API($loginId, $password, [
    'debug'     => true,
    'debugSink' => static function (string $entry): void {
        $myLogger->debug($entry);
    },
]);
```

The closure receives the finished entry, already redacted unless you opted out.
This is the extension point for any external destination — a PSR-3 logger, a
queue, a log shipper, an object store. A failure inside your sink is caught and
discarded: logging must never break an API call.

### Logs stay sensitive after redaction

A redacted entry still records which account touched which order, customer and
transaction, and when. Store logs on encrypted volumes, restrict read access,
ship them only to systems cleared for that data, and apply a retention period
at least as strict as the rest of your order data.

## Proxy support

```php
$api->withProxy('proxy.example.test:8080', 'user', 'password')
    ->orderQuery(['orderId' => '123'])
    ->getInArray();
```

The setting applies to the next call only.

## Custom transport

Pass anything implementing `HttpClientInterface` as the fourth constructor
argument to route requests through your own stack, or to test without a network:

```php
use AsterMD\CheckoutChampClient\Http\HttpClientInterface;
use AsterMD\CheckoutChampClient\Http\Request;
use AsterMD\CheckoutChampClient\Http\Response;

final class MyTransport implements HttpClientInterface
{
    public function send(Request $request): Response
    {
        return new Response(200, $body, ['http_code' => 200]);
    }
}

$api = new API($loginId, $password, [], new MyTransport());
```

## Further documentation

- [Integration guide](docs/INTEGRATION_GUIDE.md) — setup, error handling,
  production logging, troubleshooting.
- [Architecture](docs/ARCHITECTURE.md) — how a call flows through the package
  and where to extend it.
- [Security policy](SECURITY.md) — private disclosure and credential handling.
- [Changelog](CHANGELOG.md)

## Development

```bash
composer install
composer gate     # phpcs → phpstan (max) → phpunit
```

See [CLAUDE.md](CLAUDE.md) for the conventions the gate enforces. No test in
this suite makes a network call.

## Support

Email **info@astermd.com**, or open an issue at
<https://github.com/astermd/checkoutchamp-client/issues>.

Report security issues privately — see [SECURITY.md](SECURITY.md). Do not open
a public issue for a vulnerability.

## Compliance

Using this package does not by itself make your application PCI DSS, HIPAA or
GDPR compliant. It is one component in your system. Scoping, encryption at
rest, access control, audit logging, breach procedures and your agreements with
Checkout Champ and your payment processors remain your responsibility.

Note in particular that `importOrder`, `preauth` and `importUpsale` transmit
cardholder data as query parameters. Determine with your assessor whether that
places the systems handling those requests — and anything that logs their URLs
— inside your PCI DSS scope.

## License

MIT — see [LICENSE](LICENSE), including the trademark and affiliation notice.
