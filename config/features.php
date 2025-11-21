<?php

return [
    'email_verification' => in_array(
        strtolower((string) env('EMAIL_VERIFICATION', 'off')),
        ['on', 'true', '1'],
        true,
    ),
    'email_verification_ttl_minutes' => (int) env('EMAIL_VERIFICATION_TTL', 60 * 24),
];
