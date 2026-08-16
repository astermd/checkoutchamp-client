# Security Policy

## Reporting a vulnerability

Please report security issues privately. **Do not open a public GitHub issue.**

Email **info@astermd.com** with:

- a description of the issue and the impact you believe it has,
- the package version and PHP version affected,
- steps to reproduce, ideally a minimal snippet.

We aim to acknowledge a report within 5 business days and to agree a
disclosure timeline with you before any public write-up.

You may also use GitHub's private vulnerability reporting on
<https://github.com/astermd/checkoutchamp-client/security/advisories/new>.

## Supported versions

| Version | Supported |
|---------|-----------|
| 0.0.x   | Yes       |

This package is pre-1.0. Security fixes are released against the latest
published version only; there are no maintained older branches yet. Pin with
`^0.0.1` and expect breaking changes between patch releases until 1.0.

## Handling credentials

This package ships **no** credentials, no defaults and no environment lookups.
Your login ID and password are required arguments you pass at construction:

```php
$api = new API($loginId, $password, ['host' => 'api.checkoutchamp.com']);
```

### Read this before you deploy

**The Checkout Champ API authenticates with `loginId` and `password` sent as
URL query string parameters.** That is the provider's design, not a choice this
package makes, and it has consequences you must plan for:

- **Your account password appears in the URL of every request.** Anything that
  records outbound request URLs — a forward proxy, an egress gateway, an APM
  agent, a packet capture, a corporate TLS-inspecting appliance — will record
  the password in cleartext.
- Audit what sits between your application and the internet before enabling
  this in production, and confirm none of it logs full request URLs.
- This package always verifies TLS certificates and offers no switch to
  disable that. Without verification, credentials in a URL are readable by any
  on-path attacker.
- Rotate the password immediately if it is ever committed, captured in a proxy
  log, pasted into a ticket, or shared outside your team.
- `getPayloadInfo()` and the `payload` key returned by `get(true)` deliberately
  exclude credentials, so they are safe to surface in your own diagnostics.

## Handling sensitive data in logs

Debug logging is off by default. When you turn it on:

- Entries are **redacted by default**, and here redaction covers the **URL** as
  well as headers and bodies — because this API carries credentials, personal
  data and cardholder data as query parameters. `loginId`, `password`, card
  numbers, CVV, expiry, bank and government identifiers are masked in the
  logged URL.
- The scheme, host and path are still logged verbatim, so you can always see
  which endpoint was called.
- `debugRedact => false` disables masking entirely and will write your account
  password and full card numbers to disk. It exists for local debugging.
  **Never enable it in production.**
- **Redacted logs are still sensitive.** They record which account touched
  which order, customer and transaction, and when. Store them on encrypted
  volumes, restrict read access, ship them only to systems cleared for that
  data, and apply a retention period at least as strict as the rest of your
  order data.
- The built-in file sink prunes its own dated files after 7 days by default.
  If you supply your own sink closure, retention becomes entirely your
  responsibility.

## Compliance

Using this package does not by itself make your application PCI DSS, HIPAA or
GDPR compliant. It is one component in your system. Scoping, encryption at
rest, access control, audit logging, breach procedures and your agreements
with Checkout Champ and your processors remain your responsibility.

Note in particular that endpoints such as `importOrder`, `preauth` and
`importUpsale` transmit cardholder data as query parameters. Determine with
your assessor whether that places the systems handling those requests, and any
component that logs their URLs, inside your PCI DSS scope.
