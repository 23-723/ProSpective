<?php

use App\Services\AcademicMetrics;

return [
    'sete_min_responses' => filter_var(
        env('ACALYTICS_SETE_MIN_RESPONSES', AcademicMetrics::MIN_RATING_RESPONSES),
        FILTER_VALIDATE_INT,
    ) ?: AcademicMetrics::MIN_RATING_RESPONSES,
];
