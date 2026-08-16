# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.0.1] - 2026-08-16

First public release.

### Added

- `API` client covering 14 Checkout Champ endpoints across orders, leads,
  upsales, campaigns, customers, transactions and lander clicks, reached
  through a chainable call followed by `get()`, `getInArray()` or
  `getInObject()`.
- The provider's response is returned as-is; the package adds no envelope of
  its own. Transport failures surface as `curlError` and a non-JSON response
  raises `CheckoutChampException`.
- `getPayloadInfo()`, exposing the endpoint and the caller's own parameters
  with credentials deliberately excluded, so it is safe to surface in a
  consumer's diagnostics.
- Credentials supplied entirely by the caller: login ID and password are
  required constructor arguments. The package ships no defaults and reads no
  environment variables. Caller parameters cannot shadow them.
- `host` and `basePath` options taking a bare hostname, defaulting to
  `api.checkoutchamp.com`; full URLs are rejected so the scheme and path
  assembly stay inside the client.
- `timeout` and `connectTimeout` options, defaulting to 30 and 10 seconds.
- TLS peer and host verification, always on, with no option to disable it —
  which matters especially here, since this API carries credentials in the URL.
- Opt-in debug logging that is redacted by default. Because Checkout Champ
  transmits credentials, personal data and cardholder data as query string
  parameters, redaction covers the **URL** as well as headers and bodies:
  `loginId`, `password`, card numbers, CVV, expiry, bank and government
  identifiers are masked, while the scheme, host, path and ordinary parameters
  stay readable.
- Card numbers additionally caught by shape — any 13–19 digit value passing a
  Luhn check is masked wherever it appears, including in the URL.
- Redaction proven by test not to alter the real request or response.
- `debugRedact => false` escape hatch for verbatim local debugging, documented
  as never-in-production.
- A built-in file sink writing one file per day from a caller-supplied base
  path, with a retention setting (default 7 days, `0` keeps everything).
  Pruning matches only the package's own dated filenames, reads the date from
  the filename rather than the mtime, and runs once per process.
- A `debugSink` option accepting a closure that replaces the file sink
  entirely, as the vendor-neutral extension point for any external log
  destination.
- `HttpClientInterface` transport seam with a `CurlClient` default, so requests
  can be routed through a consumer's own HTTP stack and the test suite can run
  without touching the network.
- `withProxy()` for per-call HTTP proxy routing.
- `CheckoutChampException` as the single exception type, extending
  `\Exception`.
- `API::supportedMethods()` listing every dispatchable resource method.
- Documentation: README, integration guide, architecture notes, security
  policy, and contributor conventions.
- PSR-12 style, PHPStan at max level, and a PHPUnit suite covering every public
  method and asserting the URL, HTTP method, headers and query parameters,
  running on PHP 8.4 and 8.5 in CI.

[0.0.1]: https://github.com/astermd/checkoutchamp-client/releases/tag/v0.0.1
