<?php

declare(strict_types=1);

namespace AsterMD\CheckoutChampClient\Logging;

/**
 * Masks credentials and sensitive fields in copies of headers and bodies.
 *
 * Every method returns new values. Nothing here mutates the request that is
 * actually sent or the response that is actually returned to the caller — the
 * redacted text exists only inside a log entry.
 *
 * Scope note: this API carries every parameter — credentials, personal data and
 * cardholder data alike — in the URL query string. Logging the URL verbatim
 * would therefore write the account password to disk on every call, so
 * {@see self::redactUrl()} masks sensitive query parameters too. The scheme,
 * host and path are still logged verbatim, so the endpoint being called and
 * every non-sensitive parameter remain visible.
 *
 * @package AsterMD\CheckoutChampClient
 */
final class Redactor
{
    /**
     * Replacement written in place of a sensitive value.
     */
    public const MASK = '[REDACTED]';

    /**
     * Placeholder used when a body cannot be parsed and therefore cannot be
     * field-masked. Dropping it whole is the safe direction.
     */
    public const UNPARSEABLE = '[REDACTED: body is not JSON]';

    /**
     * Header names whose value is replaced entirely.
     *
     * Compared after normalisation (lowercased, separators stripped).
     *
     * @var string[]
     */
    private const SENSITIVE_HEADERS = [
        'apikey',
        'xapikey',
        'xauthtoken',
        'xaccesstoken',
        'xsessiontoken',
        'cookie',
        'setcookie',
    ];

    /**
     * Header names whose value keeps its scheme, e.g. "Bearer [REDACTED]".
     *
     * @var string[]
     */
    private const SCHEME_PRESERVING_HEADERS = [
        'authorization',
        'proxyauthorization',
    ];

    /**
     * Body keys whose value is replaced entirely.
     *
     * Compared after normalisation (lowercased, separators stripped).
     *
     * @var string[]
     */
    private const SENSITIVE_KEYS = [
        // Credentials
        'apikey', 'xapikey', 'apisecret', 'secret', 'clientsecret', 'password',
        'passwd', 'pwd', 'passphrase', 'signature', 'auth', 'authorization',
        'loginid', 'login', 'username',
        // Tokens
        'token', 'accesstoken', 'refreshtoken', 'idtoken', 'bearertoken',
        'sessiontoken', 'paymenttoken', 'paymenttokenid', 'carttoken',
        // Cardholder data
        'cardnumber', 'cardno', 'ccnumber', 'creditcard', 'creditcardnumber',
        'pan', 'cvv', 'cvv2', 'cvc', 'cvc2', 'csc', 'cardcode', 'securitycode',
        'expmonth', 'expyear', 'expirationmonth', 'expirationyear',
        'expirationdate', 'expirydate', 'cardexpiry',
        // Bank data
        'accountnumber', 'bankaccount', 'bankaccountnumber', 'routingnumber',
        'aba', 'abanumber', 'iban', 'swift', 'swiftcode', 'sortcode',
        // Government / identity data
        'ssn', 'socialsecuritynumber', 'taxid', 'ein', 'nationalid',
        'passportnumber', 'driverslicense', 'driverslicensenumber',
        'dob', 'dateofbirth', 'birthdate',
    ];

    /**
     * Parent keys under which otherwise-generic child keys become sensitive.
     *
     * Catches shapes like {"card": {"number": "...", "cvv": "..."}} where the
     * child key alone would be too generic to mask safely.
     *
     * @var string[]
     */
    private const SENSITIVE_PARENTS = [
        'card', 'creditcard', 'debitcard', 'paymentcard', 'paymentmethod',
        'paymentsource', 'bankaccount', 'ach', 'billingsource',
    ];

    /**
     * Child keys masked when nested under one of SENSITIVE_PARENTS.
     *
     * @var string[]
     */
    private const SENSITIVE_CHILDREN = [
        'number', 'code', 'month', 'year', 'expiry', 'expiration', 'last4',
    ];

    /**
     * Redact a set of raw "Name: value" header lines.
     *
     * @param string[] $headers
     *
     * @return string[] A new array; the input is untouched.
     */
    public function redactHeaders(array $headers): array
    {
        $redacted = [];
        foreach ($headers as $line) {
            $redacted[] = $this->redactHeaderLine((string) $line);
        }

        return $redacted;
    }

    /**
     * Mask sensitive parameters in a URL's query string.
     *
     * This API puts credentials, personal data and cardholder data in the query
     * string, so logging a URL verbatim would leak all three. The scheme, host
     * and path are preserved, as are parameter names and any value that is not
     * sensitive, which keeps a log entry readable and reproducible once the
     * reader substitutes their own credentials.
     *
     * Values are masked in place rather than percent-encoded, because the result
     * is a log line rather than a URL to be re-issued.
     *
     * @param string $url
     *
     * @return string A new string; the input is untouched.
     */
    public function redactUrl(string $url): string
    {
        $separator = strpos($url, '?');
        if ($separator === false) {
            return $url;
        }

        $prefix = substr($url, 0, $separator);
        $query  = substr($url, $separator + 1);
        if ($query === '') {
            return $url;
        }

        $masked = [];
        foreach (explode('&', $query) as $pair) {
            $equals = strpos($pair, '=');
            if ($equals === false) {
                $masked[] = $pair;
                continue;
            }

            $name  = substr($pair, 0, $equals);
            $value = substr($pair, $equals + 1);

            $sensitive = $this->isSensitiveKey(urldecode($name), null)
                || $this->looksLikeCardNumber(urldecode($value));

            $masked[] = $sensitive ? $name . '=' . self::MASK : $pair;
        }

        return $prefix . '?' . implode('&', $masked);
    }

    /**
     * Redact a JSON body.
     *
     * Bodies that are not decodable JSON cannot be field-masked, so they are
     * replaced wholesale rather than logged on the chance they are harmless.
     *
     * @param string|null $body
     *
     * @return string|null A new string; the input is untouched.
     */
    public function redactBody(?string $body): ?string
    {
        if ($body === null || trim($body) === '') {
            return $body;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return self::UNPARSEABLE;
        }

        /** @var mixed $clean */
        $clean = $this->redactValue($decoded, null);

        $encoded = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? self::UNPARSEABLE : $encoded;
    }

    /**
     * Redact one header line, preserving the scheme on Authorization headers.
     *
     * @param string $line
     *
     * @return string
     */
    private function redactHeaderLine(string $line): string
    {
        $separator = strpos($line, ':');
        if ($separator === false) {
            return $line;
        }

        $name      = substr($line, 0, $separator);
        $value     = substr($line, $separator + 1);
        $normalise = $this->normalise($name);

        if (in_array($normalise, self::SCHEME_PRESERVING_HEADERS, true)) {
            $trimmed = trim($value);
            $space   = strpos($trimmed, ' ');
            $masked  = $space === false
                ? self::MASK
                : substr($trimmed, 0, $space) . ' ' . self::MASK;

            return $name . ': ' . $masked;
        }

        if (in_array($normalise, self::SENSITIVE_HEADERS, true)) {
            return $name . ': ' . self::MASK;
        }

        return $line;
    }

    /**
     * Recursively mask sensitive entries in a decoded JSON value.
     *
     * @param mixed       $value
     * @param string|null $parentKey Normalised key of the containing object.
     *
     * @return mixed
     */
    private function redactValue($value, ?string $parentKey)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && $this->isSensitiveKey($key, $parentKey)) {
                    $result[$key] = self::MASK;
                    continue;
                }

                $result[$key] = $this->redactValue(
                    $item,
                    is_string($key) ? $this->normalise($key) : $parentKey
                );
            }

            return $result;
        }

        if (is_string($value) && $this->looksLikeCardNumber($value)) {
            return self::MASK;
        }

        return $value;
    }

    /**
     * @param string      $key
     * @param string|null $parentKey Already normalised.
     *
     * @return bool
     */
    private function isSensitiveKey(string $key, ?string $parentKey): bool
    {
        $normalised = $this->normalise($key);

        if (in_array($normalised, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return $parentKey !== null
            && in_array($parentKey, self::SENSITIVE_PARENTS, true)
            && in_array($normalised, self::SENSITIVE_CHILDREN, true);
    }

    /**
     * Catch card numbers that arrive under an unexpected key name.
     *
     * Requires both a plausible PAN length and a valid Luhn check digit, which
     * keeps ordinary numeric identifiers out of the match.
     *
     * @param string $value
     *
     * @return bool
     */
    private function looksLikeCardNumber(string $value): bool
    {
        $digits = preg_replace('/[ \-]/', '', $value);
        if (!is_string($digits) || preg_match('/^\d{13,19}$/', $digits) !== 1) {
            return false;
        }

        $sum     = 0;
        $double  = false;
        $length  = strlen($digits);
        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];
            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum   += $digit;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }

    /**
     * Lowercase and strip separators so "X-Api-Key", "api_key" and "apiKey"
     * all compare equal.
     *
     * @param string $name
     *
     * @return string
     */
    private function normalise(string $name): string
    {
        $stripped = str_replace(['-', '_', ' ', '.'], '', trim($name));

        return strtolower($stripped);
    }
}
