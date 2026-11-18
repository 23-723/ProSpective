<?php

namespace Tests\Unit;

use App\Repositories\DemoAcademicRepository;
use App\Services\AcademicAlertService;
use App\Services\AcademicAnalyticsService;
use App\Services\AcademicMetrics;
use PHPUnit\Framework\TestCase;

class AcademicAnalyticsTest extends TestCase
{
    private function analytics(): AcademicAnalyticsService
    {
        return new AcademicAnalyticsService(new DemoAcademicRepository);
    }

    private function record(array $overrides = []): array
    {
        return array_replace((new DemoAcademicRepository)->records()[0], $overrides);
    }

    public function test_fictional_dataset_is_consistent_across_all_six_periods(): void
    {
        $repository = new DemoAcademicRepository;
        $this->assertCount(6, $repository->periods());
        $records = $repository->records();
        $this->assertCount(144, $records);
        $this->assertSame($records, (new DemoAcademicRepository)->records());
        $this->assertCount(144, array_unique(array_column($records, 'id')));
        $relationships = [];
        foreach ($records as $record) {
            $key = implode('|', [$record['academic_year'], $record['semester'], $record['section'], $record['subject']]);
            $this->assertArrayNotHasKey($key, $relationships);
            $relationships[$key] = true;
            $this->assertSame('CICS', $record['college']);
            $this->assertSame('BSIT', $record['program']);
            $this->assertMatchesRegularExpression('/^BSIT '.$record['year_level'].'[AB]$/', $record['section']);
            $this->assertSame($repository->subjects()[$record['year_level']][$record['subject']], $record['subject_name']);
            $this->assertSame($repository->faculty()[$record['faculty']] ?? 'Unassigned faculty', $record['faculty_name']);
            $this->assertSame($record['graded_count'], $record['passing_count'] + $record['failing_count']);
            $this->assertGreaterThanOrEqual(0, $record['passing_count']);
            $this->assertGreaterThanOrEqual(0, $record['failing_count']);
            $this->assertGreaterThanOrEqual(0, $record['sete_submitted']);
            $this->assertSame($record['total_students'], $record['sete_required']);
            $this->assertLessThanOrEqual($record['sete_required'], $record['sete_submitted']);
            $this->assertLessThanOrEqual($record['total_students'], $record['graded_count']);
            $this->assertLessThanOrEqual($record['sete_submitted'], $record['rating_responses']);
            $this->assertGreaterThanOrEqual($record['rating_responses'] * AcademicMetrics::RATING_MIN, $record['rating_sum']);
            $this->assertLessThanOrEqual($record['rating_responses'] * AcademicMetrics::RATING_MAX, $record['rating_sum']);
            if ($record['faculty'] === '') {
                $this->assertSame(0, $record['sete_submitted']);
                $this->assertNull($record['grades_submitted_at']);
            }
        }
    }

    public function test_rates_and_ratings_use_weighted_totals_instead_of_averaging_group_percentages(): void
    {
        $summary = $this->analytics()->summarize([
            $this->record(['total_students' => 10, 'passing_count' => 1, 'failing_count' => 1, 'graded_count' => 2, 'sete_required' => 10, 'sete_submitted' => 5, 'rating_responses' => 5, 'rating_sum' => 15]),
            $this->record(['total_students' => 100, 'passing_count' => 99, 'failing_count' => 1, 'graded_count' => 100, 'sete_required' => 100, 'sete_submitted' => 95, 'rating_responses' => 95, 'rating_sum' => 475]),
        ]);
        $this->assertSame(100 / 102 * 100, $summary['passing_rate']);
        $this->assertSame(100 / 110 * 100, $summary['sete_rate']);
        $this->assertEqualsWithDelta(4.9, $summary['rating']['value'], .001);
    }

    public function test_privacy_threshold_is_enforced_and_raw_rating_totals_are_not_public(): void
    {
        $analytics = $this->analytics();
        $record = $this->record(['rating_responses' => 4, 'rating_sum' => 19]);
        $this->assertNull($analytics->rating([$record])['value']);
        $public = $analytics->publicRecord($record);
        $this->assertNull($public['rating']);
        $this->assertNull($public['rating_responses']);
        $this->assertArrayNotHasKey('rating_sum', $public);
        $this->assertSame('Insufficient responses for confidential reporting.', $public['rating_note']);
        $reportable = $this->record(['rating_responses' => 5, 'rating_sum' => 15]);
        $this->assertSame(3.0, $analytics->rating([$reportable])['value']);
        $this->assertSame(3.0, $analytics->rating([$record, $reportable])['value']);
        $this->assertTrue($analytics->rating([$record, $reportable])['withheld']);
        $this->assertSame(5, $analytics->rating([$record, $reportable])['responses']);
    }

    public function test_missing_denominators_return_na_and_do_not_create_false_alerts(): void
    {
        $analytics = $this->analytics();
        $summary = $analytics->summarize([]);
        foreach (['passing_rate', 'sete_rate', 'grade_rate', 'grade_completion_rate'] as $key) {
            $this->assertNull($summary[$key]);
        }
        $this->assertNull($analytics->rating([])['value']);
        $this->assertNull($analytics->improvement([], $analytics->defaults())['value']);
        $this->assertSame([], (new AcademicAlertService($analytics))->generate([], $analytics->defaults()));
        $this->assertNull($analytics->rate(0, 0));
    }

    public function test_status_boundaries_and_grade_deadlines_are_not_conflated(): void
    {
        $analytics = $this->analytics();
        foreach (['sete' => [90, 80], 'passing' => [85, 75], 'grades' => [100, 90], 'improvement' => [70, 50], 'rating' => [4.0, 3.5]] as $metric => [$target, $warning]) {
            $this->assertSame($target, AcademicMetrics::DEFINITIONS[$metric]['target']);
            $this->assertSame($warning, AcademicMetrics::DEFINITIONS[$metric]['critical']);
            $this->assertSame('Meets Target', AcademicMetrics::status($metric, $target));
            $this->assertSame('Below Target', AcademicMetrics::status($metric, $target - .001));
            $this->assertSame('Below Target', AcademicMetrics::status($metric, $warning));
            $this->assertSame('Critical', AcademicMetrics::status($metric, $warning - .001));
            $this->assertSame('N/A', AcademicMetrics::status($metric, null));
        }
        $this->assertSame('Below Target', AcademicMetrics::status('grades', 20, false));
        $future = $this->record(['grade_deadline' => '2026-12-31', 'grades_submitted_at' => null]);
        $past = $this->record(['grade_deadline' => '2026-01-15', 'grades_submitted_at' => null]);
        $this->assertSame('Pending', $analytics->gradeStatus($future));
        $this->assertSame('Overdue', $analytics->gradeStatus($past));
        $futureAlerts = (new AcademicAlertService($analytics))->generate([$future], $analytics->defaults());
        $this->assertContains('grades-pending', array_column($futureAlerts, 'id'));
        $this->assertNotContains('grades-overdue', array_column($futureAlerts, 'id'));
        $this->assertNotContains('grades-rate', array_column($futureAlerts, 'id'));
        $pastAlerts = (new AcademicAlertService($analytics))->generate([$past], $analytics->defaults());
        $this->assertContains('grades-overdue', array_column($pastAlerts, 'id'));
        $dueToday = $this->record(['grade_deadline' => '2026-06-25', 'grades_submitted_at' => null]);
        $onTime = $this->record(['grade_deadline' => '2026-01-15', 'grades_submitted_at' => '2026-01-15']);
        $late = $this->record(['grade_deadline' => '2026-01-15', 'grades_submitted_at' => '2026-01-16']);
        $this->assertSame('Pending', $analytics->gradeStatus($dueToday));
        $this->assertSame('Submitted late', $analytics->gradeStatus($late));
        $summary = $analytics->summarize([$past, $dueToday, $onTime, $late]);
        $this->assertSame(25.0, $summary['grade_rate']);
        $this->assertSame(50.0, $summary['grade_completion_rate']);
        $this->assertSame('Below Target', $summary['grade_status']);
        $this->assertSame(2, $summary['grades_submitted']);
        foreach (['on_time', 'late', 'pending', 'overdue'] as $status) {
            $this->assertSame(1, $summary[$status]);
        }
        $lateOnlyAlerts = array_column((new AcademicAlertService($analytics))->generate([$late], $analytics->defaults()), null, 'id');
        $this->assertSame('Critical', $analytics->summarize([$late])['grade_status']);
        $this->assertSame('Advisory', $lateOnlyAlerts['grades-late']['severity']);
        $this->assertArrayNotHasKey('grades-overdue', $lateOnlyAlerts);
    }

    public function test_improvement_excludes_non_comparable_and_low_response_faculty(): void
    {
        $current = $this->record(['academic_year' => '2025-2026', 'semester' => '1', 'faculty' => 'F01', 'rating_sum' => 45, 'rating_responses' => 10]);
        $previous = array_replace($current, ['academic_year' => '2024-2025', 'semester' => '2', 'rating_sum' => 40]);
        $unreportable = array_replace($current, ['faculty' => 'F02', 'rating_sum' => 20, 'rating_responses' => 4]);
        $unchanged = array_replace($current, ['faculty' => 'F03', 'rating_sum' => 40]);
        $declined = array_replace($current, ['faculty' => 'F04', 'rating_sum' => 35]);
        $repo = $this->createMock(DemoAcademicRepository::class);
        $demo = new DemoAcademicRepository;
        $repo->method('metadata')->willReturn($demo->metadata());
        $repo->method('records')->willReturn([$current, $previous, $unreportable, $unchanged, $declined, ...array_map(
            fn ($faculty) => array_replace($previous, ['faculty' => $faculty]),
            ['F02', 'F03', 'F04'],
        )]);
        $repo->method('periods')->willReturn($demo->periods());
        $repo->method('faculty')->willReturn($demo->faculty());
        $analytics = new AcademicAnalyticsService($repo);
        $result = $analytics->improvement([$current, $unreportable, $unchanged, $declined], $analytics->defaults());
        $this->assertSame(3, $result['comparable']);
        $this->assertSame(1, $result['improved']);
        $this->assertSame(1, $result['unchanged']);
        $this->assertSame(1, $result['declined']);
        $this->assertSame(1 / 3 * 100, $result['value']);
        $this->assertSame(['improved', 'unchanged', 'declined'], array_column($result['details'], 'comparison'));
    }

    public function test_invalid_dependent_selections_reset_and_historical_periods_are_preserved(): void
    {
        $analytics = $this->analytics();
        $result = $analytics->dashboard(['year_level' => '3', 'section' => 'BSIT 1A', 'subject' => 'IT 111', 'faculty' => 'unknown']);
        $this->assertSame('', $result['filters']['section']);
        $this->assertSame('', $result['filters']['subject']);
        $this->assertSame('', $result['filters']['faculty']);
        $this->assertCount(6, $result['charts']['trend']);
        $this->assertCount(1, array_filter($result['charts']['trend'], fn ($p) => $p['selected']));
        $this->assertSame(['BSIT 3A', 'BSIT 3B'], array_column($result['options']['section'], 'value'));
        $this->assertSame(['3'], array_values(array_unique(array_column($result['records'], 'year_level'))));
    }

    public function test_all_valid_combinations_resolve_to_the_expected_class_records(): void
    {
        $analytics = $this->analytics();
        $records = $analytics->repository->records();
        $combinations = [];
        $keys = ['year_level', 'section', 'subject', 'faculty'];
        foreach ($records as $record) {
            for ($mask = 0; $mask < 16; $mask++) {
                $scope = ['academic_year' => $record['academic_year'], 'semester' => $record['semester']];
                foreach ($keys as $index => $key) {
                    if (($mask & (1 << $index)) && $record[$key] !== '') {
                        $scope[$key] = $record[$key];
                    }
                }
                $combinations[http_build_query($scope)] = $scope;
            }
        }
        $this->assertCount(1607, $combinations);
        $periods = $analytics->repository->periods();
        $periodKeys = array_map(fn ($period) => $period['academic_year'].'/'.$period['semester'], $periods);
        $asOf = substr($analytics->repository->metadata()['updated_at'], 0, 10);
        $average = function ($rows) {
            $eligible = array_filter($rows, fn ($row) => $row['rating_responses'] >= 5);
            $responses = array_sum(array_column($eligible, 'rating_responses'));

            return $responses ? array_sum(array_column($eligible, 'rating_sum')) / $responses : null;
        };
        foreach ($combinations as $scope) {
            $expected = array_filter($records, fn ($record) => array_intersect_key($record, $scope) == $scope);
            $data = $analytics->dashboard($scope);
            $this->assertSame($scope, array_intersect_key($data['filters'], $scope));
            $this->assertSame(array_column($expected, 'id'), array_column($data['records'], 'id'));
            $metrics = array_column($data['kpis'], 'value', 'key');
            $sum = fn ($key) => array_sum(array_column($expected, $key));
            $rate = fn ($n, $d): ?float => $d ? $n / $d * 100 : null;
            $this->assertSame($rate($sum('sete_submitted'), $sum('sete_required')), $metrics['sete']);
            $this->assertSame($rate($sum('passing_count'), $sum('graded_count')), $metrics['passing']);
            $this->assertSame($average($expected), $metrics['rating']);

            $onTime = $late = $pending = $overdue = 0;
            foreach ($expected as $row) {
                $submitted = $row['grades_submitted_at'];
                if ($submitted && $submitted <= $asOf) {
                    $submitted <= $row['grade_deadline'] ? $onTime++ : $late++;
                } else {
                    $row['grade_deadline'] < $asOf ? $overdue++ : $pending++;
                }
            }
            $this->assertSame($rate($onTime, count($expected)), $metrics['grades']);
            $this->assertSame($rate($onTime + $late, count($expected)), $data['summary']['grade_completion_rate']);
            $this->assertSame(['on_time' => $onTime, 'late' => $late, 'overdue' => $overdue, 'pending' => $pending], array_intersect_key($data['summary'], array_flip(['on_time', 'late', 'overdue', 'pending'])));

            $periodIndex = array_search($scope['academic_year'].'/'.$scope['semester'], $periodKeys, true);
            $counts = ['improved' => 0, 'unchanged' => 0, 'declined' => 0];
            if ($periodIndex > 0) {
                $previousScope = ['academic_year' => $periods[$periodIndex - 1]['academic_year'], 'semester' => $periods[$periodIndex - 1]['semester']] + $scope;
                foreach (array_unique(array_filter(array_column($expected, 'faculty'))) as $faculty) {
                    $current = $average(array_filter($expected, fn ($row) => $row['faculty'] === $faculty));
                    $prior = $average(array_filter($records, fn ($row) => $row['faculty'] === $faculty && array_intersect_key($row, $previousScope) == $previousScope));
                    if ($current !== null && $prior !== null) {
                        $change = round($current - $prior, 9) <=> 0;
                        $counts[[-1 => 'declined', 0 => 'unchanged', 1 => 'improved'][$change]]++;
                    }
                }
            }
            $this->assertSame($counts, array_intersect_key($data['improvement'], $counts));
            $this->assertSame($rate($counts['improved'], array_sum($counts)), $metrics['improvement']);
        }
    }

    public function test_kpis_reconcile_with_charts_class_rows_and_historical_periods(): void
    {
        $analytics = $this->analytics();
        foreach ($analytics->repository->periods() as $period) {
            foreach ([[], ['year_level' => '3', 'section' => 'BSIT 3A', 'subject' => 'IT 312', 'faculty' => 'F06']] as $scope) {
                $data = $analytics->dashboard($scope + $period);
                $summary = $data['summary'];
                $years = $data['charts']['years'];
                $metrics = array_column($data['kpis'], null, 'key');
                $selected = array_values(array_filter($data['charts']['trend'], fn ($point) => $point['selected']));
                $this->assertCount(1, $selected);
                $this->assertSame($metrics['rating']['value'], $selected[0]['rating']['value']);
                $this->assertSame($summary['rating']['responses'], $selected[0]['rating']['responses']);
                $this->assertSame(array_column($analytics->repository->periods(), 'label'), array_column($data['charts']['trend'], 'label'));
                foreach (['required', 'submitted', 'passing', 'graded', 'grades_submitted', 'grades_required'] as $key) {
                    $this->assertSame($summary[$key], array_sum(array_column($years, $key)));
                }
                foreach ([
                    'sete' => ['sete_submitted', 'sete_required'],
                    'passing' => ['passing_count', 'graded_count'],
                ] as $metric => [$numerator, $denominator]) {
                    $sum = fn ($key) => array_sum(array_column($data['table']['rows'], $key));
                    $this->assertSame($analytics->rate($sum($numerator), $sum($denominator)), $metrics[$metric]['value']);
                }
                $gradeCounts = array_count_values(array_column($data['table']['rows'], 'grade_status'));
                $onTime = $gradeCounts['On time'] ?? 0;
                $completed = $onTime + ($gradeCounts['Submitted late'] ?? 0);
                $this->assertSame($analytics->rate($onTime, $data['table']['total']), $metrics['grades']['value']);
                $this->assertSame($analytics->rate($completed, $data['table']['total']), $summary['grade_completion_rate']);
                $this->assertSame($summary['grade_status'], $metrics['grades']['status']);
                $this->assertSame($analytics->rate($data['improvement']['improved'], $data['improvement']['comparable']), $metrics['improvement']['value']);
            }
        }
    }
}
