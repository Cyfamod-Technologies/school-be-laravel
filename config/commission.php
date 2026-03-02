<?php

return [
    // Commission percentage on qualifying payments
    'percentage' => env('COMMISSION_PERCENTAGE', 12),

    // Number of payments that trigger commission (1 = only first payment, 4 = first 4 payments, etc)
    'payment_count' => env('COMMISSION_PAYMENT_COUNT', 1),

    // Minimum amount in wallet before agent can request payout
    'min_payout_threshold' => env('MIN_PAYOUT_THRESHOLD', 5000),

    // Days to process payout
    'payout_processing_days' => env('PAYOUT_PROCESSING_DAYS', 3),
];
