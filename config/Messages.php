<?php

declare(strict_types=1);

return [
    'methodNotFound'    => 'No such method found',
    'invalidApiAuth'    => 'A login ID and password are required',
    'apiInvokedFailure' => 'No API method has been invoked yet',
    'jsonFormatError'   => 'API response is not valid JSON',
    'invalidHost'       => 'The host must be a bare hostname such as "api.checkoutchamp.com", '
        . 'without a scheme or path',
    'debugFileRequired' => 'Debug logging needs either a "debugFile" base path or a "debugSink" callable',
    'invalidDebugSink'  => 'The "debugSink" option must be callable',
    'invalidTimezone'   => 'The configured timezone is not a recognised IANA identifier',
];
