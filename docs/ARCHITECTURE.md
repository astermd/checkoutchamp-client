# Architecture

How a call travels through `astermd/checkoutchamp-client`, why the pieces are
split the way they are, and where to extend it.

---

## The constraint that shapes the design

**Checkout Champ authenticates with `loginId` and `password` as URL query
string parameters, and every other request parameter travels with them.**

Most API clients can treat the URL as non-sensitive: credentials go in a header,
the URL identifies a resource, and logging it verbatim is both safe and useful.
Here that assumption is inverted. The URL carries the account password, the
customer's details and, on the billing endpoints, the card number.

Almost every design decision below follows from that one fact.

---

## Layers

```
        ┌──────────────────────────────────────────────────┐
        │  API                    facade + method dispatch  │
        └───────────────────────┬──────────────────────────┘
                                │ resolves a method name to a resource
        ┌───────────────────────▼──────────────────────────┐
        │  Order  Campaign  Customer                       │
        │  Transaction  Landers              resources     │
        └───────────────────────┬──────────────────────────┘
                                │ set $section, $method, $fields → sendPost()
        ┌───────────────────────▼──────────────────────────┐
        │  CheckoutChamp     URL + credentials, response   │
        └──────┬────────────────────────────────┬──────────┘
               │ Request                        │ Request + Response
    ┌──────────▼──────────┐          ┌──────────▼──────────┐
    │ HttpClientInterface │          │    DebugLogger      │
    │      CurlClient     │          │  Redactor, FileSink │
    └─────────────────────┘          └─────────────────────┘
```

Two rules hold it together:

1. **Everything that reaches the network goes through `HttpClientInterface`.**
   No `curl_*` call exists outside `Http\CurlClient`.
2. **Logging is a read-only observer.** It receives immutable `Request` and
   `Response` objects and returns a string. It cannot alter what is sent or
   what the caller receives.

---

## The lifecycle of one call

Take `$api->importOrder(['sessionId' => 'sess_1'])->getInArray()`.

**1. Dispatch.** `importOrder` is not defined on `API`, so `__call` runs. It
looks the name up in `API::METHOD_MAP`, a compile-time constant mapping all 14
resource methods to their class. An unknown name throws
`CheckoutChampException` here, before anything else happens.

_Why a map rather than reflection:_ the original implementation called
`get_class_methods()` on every resource and diffed against the parent on every
single call. The map is a constant lookup, it is greppable, and a static
analyser can see it.

**2. Resource resolution.** `API` instantiates `Order` once and caches it for
the lifetime of the client, handing it the shared `ClientConfig`,
`HttpClientInterface` and `DebugLogger`. A proxy queued by `withProxy()` is
applied to the resource now, then cleared.

**3. The resource describes the request.** `Order::importOrder()` sets
`$this->section = 'order'`, `$this->method = 'import'` and
`$this->fields = $params`, then calls `sendPost()`. Resource classes hold no
HTTP knowledge whatsoever — that is the whole reason they are thin.

**4. `CheckoutChamp` assembles.** The path becomes `order/import/`, joined to
`ClientConfig::getBaseUrl()`. Credentials are merged into the query **last**:

```php
$query = array_merge($this->fields, [
    'loginId'  => $this->config->getLoginId(),
    'password' => $this->config->getPassword(),
]);
```

Order matters. Appending them last means a caller who passes a `password` key
in `$params` cannot shadow the configured credential — the real one wins. There
is a test for exactly that.

At the same moment, the credential-_free_ snapshot is taken for
`getPayloadInfo()`: the endpoint without its query string, plus the caller's
own fields. That is the only payload view a consumer ever sees.

**5. A `Request` value object is built** and handed to the transport. It is
immutable, which is what makes the logging guarantee in rule 2 structural
rather than a matter of discipline. Body is `null` and headers are empty —
this API wants everything in the query string, and the client does not invent a
shape the provider was not observed to accept.

**6. `CurlClient` sends it.** TLS peer and host verification are on, with no
option to disable them — non-negotiable when the password is in the URL. A
transport failure is _not_ thrown; it is returned as
`Response::getTransportError()`, so the logger can still record the attempt.

**7. `DebugLogger::log()` observes.** No-op unless `debug` is on. Otherwise it
renders the entry — with the URL, headers and body redacted unless you opted
out — and passes the string to the sink. Any failure inside logging is
swallowed.

**8. `CheckoutChamp` records the outcome.** A transport error sets the error
flag and stores the message; otherwise the provider's body is stored verbatim.
No envelope is added.

**9. State resets.** `reset()` clears the section, method, fields and proxy.
This is what makes a cached resource instance safe to reuse.

**10. The caller reads.** `getInArray()` decodes the provider's body.
`getPayloadInfo()` returns the snapshot taken in step 4.

---

## Class responsibilities

| Class                              | Owns                                                   | Never does                    |
| ---------------------------------- | ------------------------------------------------------ | ----------------------------- |
| `API`                              | Method dispatch, resource caching, the three accessors | HTTP, URL building            |
| `ClientConfig`                     | Credentials, host validation, base URL, timeouts       | Anything mutable              |
| `CheckoutChamp`                    | URL assembly, credential injection, state reset        | Direct cURL calls             |
| `Order`…`Landers`                  | Route shape only                                       | HTTP, encoding                |
| `Http\Request` / `Http\Response`   | Immutable descriptions of one exchange                 | Behaviour                     |
| `Http\CurlClient`                  | The only cURL in the package                           | Throwing on transport failure |
| `Logging\DebugLogger`              | Entry formatting, sink dispatch                        | Mutating anything             |
| `Logging\Redactor`                 | Masking copies of the URL, headers and bodies          | Touching the real request     |
| `Logging\FileSink`                 | Dated files, retention, pruning                        | Throwing                      |
| `Config` / `Repository`            | Message strings from `config/`                         | Runtime configuration         |
| `Exception\CheckoutChampException` | Every error the package raises                         | —                             |

---

## Design decisions worth knowing

### The URL is redacted, not logged verbatim

This is the package's signature departure from convention, and it exists
because of the constraint at the top of this document.

`Redactor::redactUrl()` splits the query string, masks any parameter whose name
matches the sensitive list — `loginId`, `password`, card number, CVV, expiry,
bank and government identifiers, every `*_token` — and additionally masks any
value that looks like a card number by shape (13–19 digits passing a Luhn
check, catching a PAN under an unexpected parameter name).

The scheme, host and path survive, as does every non-sensitive parameter. A log
entry still tells you which endpoint was called with which order ID; it just
does not tell you the password.

Values are masked in place rather than percent-encoded, because the result is a
log line, not a URL to be re-issued.

### Credentials are appended last, and stripped from payload info

`ClientConfig` holds them; `CheckoutChamp::sendPost()` is the only place they
are read. They are merged into the query after the caller's fields so they
cannot be shadowed, and the snapshot kept for `getPayloadInfo()` is taken
before they are added.

The previous implementation injected them into `$this->fields` and then
returned that same array from `getPayloadInfo()`, so any caller using
`get(true)` received the live account password. Keeping the credential on
exactly one path is what makes that class of leak structurally impossible
rather than a bug waiting to reappear.

### A bare host, not a URL

`ClientConfig` rejects any host containing a scheme, path, query or space. The
client owns the scheme (always HTTPS) and the path assembly. A consumer able to
pass a full URL could downgrade to plaintext — which, with credentials in the
query string, would publish them.

`basePath` exists for a version prefix and is the only supported way to add a
path.

### No response envelope

The provider's body is returned unchanged. An earlier sibling package wrapped
responses in a `success` / `message` / `data` envelope; this one deliberately
does not, because the original implementation did not and inventing a shape
would misrepresent what Checkout Champ actually returns. Consult their
reference for the `result` / `message` contract.

### The transport is an interface

Three things fall out: the entire test suite runs without a network, consumers
can route calls through their own HTTP stack, and cURL specifics stay in one
small class. `Response` carries transport errors as data rather than throwing,
so a failure is observable by the logger before anyone decides what to do
about it.

### Retention reads the filename, not the mtime

Appending to a log file updates its mtime, so mtime-based pruning keeps a
month-old file alive indefinitely as long as something writes to it. The date
in the filename is the file's real age. Pruning also matches only this
package's own dated pattern for the configured base path, so it can never
delete a neighbouring file, and it runs once per process rather than once per
request.

### No runtime dependencies

The package previously pulled in a full microframework to read message strings
from a `config/` directory that was, in the version handed over, **empty** —
so every exception was thrown with a blank message. `Repository` is now a
static array with dotted-key lookup, and `config/Messages.php` actually exists.

---

## Extension points

| You want to                                        | Do this                                                                                                                                                         |
| -------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Route calls through your own HTTP stack            | Implement `HttpClientInterface`, pass it as the fourth constructor argument                                                                                     |
| Send logs to a PSR-3 logger, queue or object store | Supply a `debugSink` closure; it replaces the file sink entirely                                                                                                |
| Change log retention                               | `debugRetentionDays` (`0` keeps everything), or take it over with your own sink                                                                                 |
| Support a new endpoint                             | Add the method to its resource class, register it in `API::METHOD_MAP` and the `@method` block, document it in the README, test path + method + headers + query |
| Add a redaction rule                               | Extend the key lists in `Redactor`, and add both tests: the log is masked, the real request and response are not                                                |
| Move credentials out of the URL                    | `CheckoutChamp::sendPost()` is the only place the query is built — but confirm with Checkout Champ that a form body is accepted before changing the wire format |

Anything requiring a change to `CheckoutChamp`, `CurlClient` or
`Redactor::redactUrl()` is worth a second look: those three hold the invariants
everything else depends on.
