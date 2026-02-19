<?php

return [
    // Subscription pricing
    'price_per_student' => env('PRICE_PER_STUDENT', 500),
    'invoice_generation_days_before' => env('INVOICE_GENERATION_DAYS_BEFORE', 14),

    // Commission settings
    'percentage' => env('COMMISSION_PERCENTAGE', 12),
    'payment_count' => env('COMMISSION_PAYMENT_COUNT', 1),
    'min_payout_threshold' => env('MIN_PAYOUT_THRESHOLD', 5000),
];
