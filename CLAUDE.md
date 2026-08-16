# Contributing notes for AI agents and humans

This file describes how to work in this repository. It is public; keep it that
way. Anything internal belongs in `CLAUDE.local.md`, which is gitignored.

## What this package is

A small, dependency-free PHP client for the Checkout Champ API. It has one job:
turn a method call into a well-formed HTTP request, and hand the provider's
response back unchanged. It holds no state between calls and no credentials of
its own.

## The constraint that shapes everything

**Checkout Champ authenticates with `loginId` and `password` as URL query
string parameters, and every request parameter goes in the query string too.**

That single fact drives most of the design decisions here. The URL carries
credentials, personal data and cardholder data on every call, so:

- Debug logging **redacts the URL**. Do not "fix" this to log verbatim.
- `getPayloadInfo()` must never return credentials.
- TLS verification is not negotiable.

If you are changing anything in `Logging/`, re-read that list first.

## Language and tooling

- PHP **8.4 minimum**, 8.5 recommended. CI runs the gate on both.
- Every file starts with `declare(strict_types=1);`.
- No runtime dependencies beyond `ext-curl` and `ext-json`. Adding one needs a
  strong argument — consumers install this alongside their own framework.
- Raising the minimum PHP version is a breaking change — it goes in the
  changelog and in `composer.json`, never quietly.

## Style

- PSR-12, enforced by `phpcs`. Lines wrap at 120 columns.
- Full docblocks on every class, method and property, including `@param`,
  `@return` and `@throws`. PHPStan runs at max level and relies on them.
- Class and method names in `camelCase`/`StudlyCaps`; API parameter keys stay
  in whatever case the provider uses (`orderId`, `campaignId`, `qaStatus`).
- Comments explain *why*, not *what*.

## Architecture rules

- `API` is the only entry point a consumer touches. It resolves a method name
  to a resource class through `METHOD_MAP` and delegates.
- Resource classes (`Order`, `Customer`, …) extend `CheckoutChamp`, set
  `$this->section`, `$this->method` and `$this->fields`, then call
  `sendPost()`. They contain no HTTP logic.
- `CheckoutChamp` owns URL assembly, credential injection and per-request state
  reset. **Always reset state after a request** — instances are reused.
- Credentials are appended to the query *last*, so a caller-supplied key cannot
  shadow them. Keep it that way, and keep the test that proves it.
- All network access goes through `HttpClientInterface`. Never call `curl_*`
  outside `Http\CurlClient`.
- Never accept a full URL from a consumer. `ClientConfig` takes a bare host and
  rejects anything containing a scheme, path, query or space.
- Never disable TLS verification, and never add an option to.
- Logging must not be able to change behaviour. `DebugLogger` reads from
  `Request`/`Response` value objects and returns a string; it mutates nothing
  and swallows its own failures.
- Adding a resource method means adding it to `API::METHOD_MAP`, to the
  `@method` docblock on `API`, and to the README.

## Testing rules

- Every public method has a test.
- **No test may touch the network.** Use `Tests\Support\MockHttpClient`.
- Resource tests assert the path, the HTTP method, the headers and the decoded
  query parameters — `ClientTestCase::assertRequest()` covers all four, and
  checks the credentials are present exactly once.
- Credentials in tests are obviously fake placeholders. Never a real login,
  not even an expired one.
- New redaction rules need a test proving the real request and response are
  unchanged, alongside the test proving the log entry is masked.

## Running the gate

```bash
composer install
composer gate
```

which runs, in order and stopping at the first failure:

```bash
vendor/bin/phpcs                        # PSR-12
vendor/bin/phpstan analyse              # max level
vendor/bin/phpunit                      # full suite
```

`vendor/bin/phpcbf` fixes most style findings automatically. All three must
pass on every supported PHP version before anything is tagged.
