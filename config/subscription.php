<?php

return [
    // Subscription pricing
    'price_per_student' => env('PRICE_PER_STUDENT', 500),
    'invoice_generation_days_before' => env('INVOICE_GENERATION_DAYS_BEFORE', 14),
    'free_trial_enabled' => env('SUBSCRIPTION_FREE_TRIAL_ENABLED', false),
    'free_trial_terms' => env('SUBSCRIPTION_FREE_TRIAL_TERMS', 1),
    'free_trial_optional_per_school' => env('SUBSCRIPTION_FREE_TRIAL_OPTIONAL_PER_SCHOOL', true),
    'free_trial_default_for_new_school' => env('SUBSCRIPTION_FREE_TRIAL_DEFAULT_FOR_NEW_SCHOOL', false),

    // Commission settings
    'percentage' => env('COMMISSION_PERCENTAGE', 12),
    'payment_count' => env('COMMISSION_PAYMENT_COUNT', 1),
    'min_payout_threshold' => env('MIN_PAYOUT_THRESHOLD', 5000),
];
