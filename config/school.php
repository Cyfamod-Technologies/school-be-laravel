<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Skill Rating Grace Period
    |--------------------------------------------------------------------------
    |
    | Number of days after a term's end date when skill ratings can still
    | be created/updated. Set to -1 for no time-based restriction.
    |
    */
    'skill_rating_grace_days' => env('SKILL_RATING_GRACE_DAYS', -1),

    /*
    |--------------------------------------------------------------------------
    | Locked Term Statuses
    |--------------------------------------------------------------------------
    |
    | Skill ratings cannot be modified when a term's status matches one of
    | these values. Provide a comma-separated list via the environment
    | variable SKILL_RATING_LOCK_STATUSES.
    |
    */
    'skill_rating_lock_statuses' => array_filter(array_map(
        'trim',
        explode(',', (string) env('SKILL_RATING_LOCK_STATUSES', 'archived'))
    )),
];

