<?php

namespace App\Services;

final class AcademicMetrics
{
    public const MIN_RATING_RESPONSES = 5;

    public const RATING_MIN = 1;

    public const RATING_MAX = 5;

    public const DEFINITIONS = [
        'sete' => ['title' => 'SETE Completion Rate', 'target' => 90, 'critical' => 80, 'icon' => 'clipboard', 'unit' => '%', 'decimals' => 1],
        'passing' => ['title' => 'Student Passing Rate', 'target' => 85, 'critical' => 75, 'icon' => 'graduation', 'unit' => '%', 'decimals' => 1],
        'grades' => ['title' => 'Grade Submission Rate', 'target' => 100, 'critical' => 90, 'icon' => 'calendar', 'unit' => '%', 'decimals' => 1],
        'improvement' => ['title' => 'Faculty Teaching Improvement', 'target' => 70, 'critical' => 50, 'icon' => 'trend', 'unit' => '%', 'decimals' => 1],
        'rating' => ['title' => 'Average Faculty Evaluation', 'target' => 4.0, 'critical' => 3.5, 'icon' => 'star', 'unit' => '/ 5.0', 'decimals' => 2],
    ];

    public static function status(string $metric, ?float $value, bool $criticalEnabled = true): string
    {
        if ($value === null) {
            return 'N/A';
        }

        $definition = self::DEFINITIONS[$metric];
        if ($value >= $definition['target']) {
            return 'Meets Target';
        }

        return $criticalEnabled && $value < $definition['critical'] ? 'Critical' : 'Below Target';
    }

    public static function boundary(string $metric, string $level): string
    {
        $definition = self::DEFINITIONS[$metric];

        return $metric === 'rating'
            ? number_format($definition[$level], 1).' / '.number_format(self::RATING_MAX, 1)
            : $definition[$level].'%';
    }

    public static function targetLabel(string $metric): string
    {
        return 'Target '.($metric === 'grades' ? '' : '≥ ').self::boundary($metric, 'target');
    }

    public static function warningLabel(string $metric): string
    {
        return 'Critical below '.self::boundary($metric, 'critical').($metric === 'grades' ? ' after the submission deadline' : '');
    }

    public static function display(string $metric, ?float $value): string
    {
        return $value === null ? 'N/A' : number_format($value, self::DEFINITIONS[$metric]['decimals'], '.', '');
    }
}
