<?php

namespace App\Services;

use App\Repositories\DemoAcademicRepository;

class AcademicAnalyticsService
{
    public const YEAR_LABELS = ['1' => 'First Year', '2' => 'Second Year', '3' => 'Third Year', '4' => 'Fourth Year'];

    public const FILTER_KEYS = ['academic_year', 'semester', 'college', 'program', 'year_level', 'section', 'subject', 'faculty'];

    public const SORT_KEYS = ['year_level', 'section', 'subject', 'subject_name', 'faculty_name', 'total_students', 'passing_rate', 'sete_rate', 'grade_status'];

    public function __construct(public DemoAcademicRepository $repository) {}

    public function defaults(): array
    {
        return ['academic_year' => '2025-2026', 'semester' => '1', 'college' => DemoAcademicRepository::COLLEGE, 'program' => DemoAcademicRepository::PROGRAM, 'year_level' => '', 'section' => '', 'subject' => '', 'faculty' => ''];
    }

    /** Resolve dependent selections in a consistent parent-to-child order. */
    public function normalize(array $input): array
    {
        $defaults = $this->defaults();
        $filters = $defaults;
        foreach (self::FILTER_KEYS as $key) {
            if (array_key_exists($key, $input) && is_scalar($input[$key])) {
                $filters[$key] = (string) $input[$key];
            }
        }
        $periods = $this->repository->periods();
        foreach (['academic_year', 'semester'] as $key) {
            if (! in_array($filters[$key], array_column($periods, $key), true)) {
                $filters[$key] = $defaults[$key];
            }
        }
        // Empty scope parameters cannot turn into unrestricted program filters.
        foreach (['college', 'program'] as $key) {
            if ($filters[$key] === '') {
                $filters[$key] = $defaults[$key];
            }
        }
        if (! isset(self::YEAR_LABELS[$filters['year_level']])) {
            $filters['year_level'] = '';
        }
        $candidates = $this->select($this->repository->records(), array_merge($filters, ['section' => '', 'subject' => '', 'faculty' => '']));
        foreach (['section', 'subject', 'faculty'] as $key) {
            if ($filters[$key] !== '' && ! in_array($filters[$key], array_column($candidates, $key), true)) {
                $filters[$key] = '';
            }
            if ($filters[$key] !== '') {
                $candidates = array_values(array_filter($candidates, fn ($r) => $r[$key] === $filters[$key]));
            }
        }

        return $filters;
    }

    public function select(array $records, array $filters): array
    {
        return array_values(array_filter($records, function ($record) use ($filters) {
            foreach (self::FILTER_KEYS as $key) {
                if (isset($filters[$key]) && $filters[$key] !== '' && (string) $record[$key] !== (string) $filters[$key]) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function options(array $filters): array
    {
        $all = $this->repository->records();
        $base = array_merge($filters, ['section' => '', 'subject' => '', 'faculty' => '']);
        $sections = $this->select($all, $base);
        $subjects = $this->select($all, array_merge($base, ['section' => $filters['section']]));
        $faculties = $this->select($all, array_merge($base, ['section' => $filters['section'], 'subject' => $filters['subject']]));
        $unique = function ($rows, $valueKey, $labelKey) {
            $out = [];
            foreach ($rows as $row) {
                if ($row[$valueKey] !== '') {
                    $out[$row[$valueKey]] = ['value' => $row[$valueKey], 'label' => $row[$labelKey]];
                }
            }
            uasort($out, fn ($a, $b) => strnatcasecmp($a['label'], $b['label']));

            return array_values($out);
        };

        $periods = $this->repository->periods();

        return [
            'academic_year' => array_map(fn ($year) => ['value' => $year, 'label' => str_replace('-', '–', $year)], array_values(array_unique(array_column($periods, 'academic_year')))),
            'semester' => $unique($periods, 'semester', 'semester_label'),
            'college' => [['value' => DemoAcademicRepository::COLLEGE, 'label' => DemoAcademicRepository::COLLEGE]],
            'program' => [['value' => DemoAcademicRepository::PROGRAM, 'label' => 'BS Information Technology']],
            'year_level' => array_map(fn ($v, $l) => ['value' => (string) $v, 'label' => $l], array_keys(self::YEAR_LABELS), array_values(self::YEAR_LABELS)),
            'section' => $unique($sections, 'section', 'section'),
            'subject' => $unique($subjects, 'subject', 'subject_name'),
            'faculty' => $unique($faculties, 'faculty', 'faculty_name'),
        ];
    }

    public function rate(int|float $numerator, int|float $denominator): ?float
    {
        return $denominator > 0 ? $numerator / $denominator * 100 : null;
    }

    public function rating(array $records): array
    {
        $minimum = $this->minimumResponses();
        $reportable = array_filter($records, fn ($record) => $record['rating_responses'] >= $minimum);
        $responses = (int) array_sum(array_column($reportable, 'rating_responses'));
        $withheld = array_sum(array_column($records, 'rating_responses')) > $responses;

        // Exclude small class groups from totals so their scores cannot be recovered by subtraction.
        return [
            'value' => $responses > 0 ? (float) array_sum(array_column($reportable, 'rating_sum')) / $responses : null,
            'responses' => $responses,
            'withheld' => $withheld,
            'confidential' => $responses === 0 && $withheld,
        ];
    }

    public function minimumResponses(): int
    {
        return $this->repository->metadata()['minimum_responses'];
    }

    public function gradeStatus(array $record): string
    {
        $asOf = substr($this->repository->metadata()['updated_at'], 0, 10);
        if ($record['grades_submitted_at'] && $record['grades_submitted_at'] <= $asOf) {
            return $record['grades_submitted_at'] <= $record['grade_deadline'] ? 'On time' : 'Submitted late';
        }

        return $record['grade_deadline'] < $asOf ? 'Overdue' : 'Pending';
    }

    public function summarize(array $records): array
    {
        $sum = fn ($key) => (int) array_sum(array_column($records, $key));
        $grades = array_count_values(array_map($this->gradeStatus(...), $records)) + ['On time' => 0, 'Submitted late' => 0, 'Overdue' => 0, 'Pending' => 0];
        $gradeSubmitted = $grades['On time'] + $grades['Submitted late'];
        $gradeRate = $this->rate($grades['On time'], count($records));
        $asOf = substr($this->repository->metadata()['updated_at'], 0, 10);
        $gradeDeadlinesPassed = $records !== [] && max(array_column($records, 'grade_deadline')) < $asOf;
        $passingRate = $this->rate($sum('passing_count'), $sum('graded_count'));
        $seteRate = $this->rate($sum('sete_submitted'), $sum('sete_required'));
        $rating = $this->rating($records);

        return [
            'classes' => count($records), 'students' => $sum('total_students'),
            'graded' => $sum('graded_count'), 'passing' => $sum('passing_count'), 'failing' => $sum('failing_count'),
            'required' => $sum('sete_required'), 'submitted' => $sum('sete_submitted'),
            'passing_rate' => $passingRate, 'passing_status' => AcademicMetrics::status('passing', $passingRate),
            'sete_rate' => $seteRate, 'sete_status' => AcademicMetrics::status('sete', $seteRate),
            'grades_required' => count($records), 'grades_submitted' => $gradeSubmitted,
            'grade_rate' => $gradeRate,
            'grade_completion_rate' => $this->rate($gradeSubmitted, count($records)),
            'grade_status' => AcademicMetrics::status('grades', $gradeRate, $gradeDeadlinesPassed),
            'on_time' => $grades['On time'], 'late' => $grades['Submitted late'], 'overdue' => $grades['Overdue'], 'pending' => $grades['Pending'],
            'rating' => $rating, 'rating_status' => AcademicMetrics::status('rating', $rating['value']),
        ];
    }

    public function improvement(array $records, array $filters): array
    {
        $periods = $this->repository->periods();
        $periodIndex = array_search($filters['academic_year'].'/'.$filters['semester'], array_map(fn ($p) => $p['academic_year'].'/'.$p['semester'], $periods), true);
        $previous = $periodIndex !== false && $periodIndex > 0 ? $periods[$periodIndex - 1] : null;
        $previousRows = $previous ? $this->select($this->repository->records(), array_merge($filters, ['academic_year' => $previous['academic_year'], 'semester' => $previous['semester']])) : [];
        $details = [];
        $counts = ['improved' => 0, 'unchanged' => 0, 'declined' => 0];
        foreach ($this->repository->faculty() as $id => $name) {
            $current = $this->rating(array_values(array_filter($records, fn ($r) => $r['faculty'] === $id)));
            $prior = $this->rating(array_values(array_filter($previousRows, fn ($r) => $r['faculty'] === $id)));
            if ($current['value'] !== null && $prior['value'] !== null) {
                $difference = $current['value'] - $prior['value'];
                // Treat floating-point noise in otherwise equal weighted means as equality.
                $comparison = abs($difference) < 1e-9 ? 'unchanged' : ($difference > 0 ? 'improved' : 'declined');
                $counts[$comparison]++;
                $details[] = ['faculty' => $name, 'previous' => $prior['value'], 'current' => $current['value'], 'comparison' => $comparison];
            }
        }
        return $counts + ['value' => $this->rate($counts['improved'], count($details)), 'comparable' => count($details), 'previous_period' => $previous['label'] ?? null, 'previous_rating' => $this->rating($previousRows), 'details' => $details];
    }

    private function kpis(array $summary, array $improvement): array
    {
        $values = [
            'sete' => [$summary['sete_rate'], $summary['submitted'].' of '.$summary['required'].' required student-subject evaluations submitted.', 'Submitted SETE evaluations ÷ required evaluations × 100.'],
            'passing' => [$summary['passing_rate'], $summary['passing'].' of '.$summary['graded'].' student-subject records with final grades passed.', 'Passing student-subject records ÷ records with final grades × 100. Missing grades are excluded.'],
            'grades' => [$summary['grade_rate'], $summary['on_time'].' of '.$summary['grades_required'].' required class submissions were on time. '.$summary['grades_submitted'].' completed ('.$summary['late'].' late), '.$summary['pending'].' pending, '.$summary['overdue'].' overdue.', 'Classes with grades submitted on time ÷ classes required to submit grades × 100.'],
            'improvement' => [$improvement['value'], $improvement['improved'].' of '.$improvement['comparable'].' comparable faculty had a higher rating.', 'Faculty with a strictly higher unrounded weighted SETE rating ÷ faculty with reportable ratings in both comparable periods × 100.'],
            'rating' => [$summary['rating']['value'], $summary['rating']['responses'].' valid responses in reportable class groups.', 'Sum of reportable SETE scores ÷ valid response count. Each class needs at least '.$this->minimumResponses().' valid responses.'],
        ];

        $kpis = [];
        foreach (AcademicMetrics::DEFINITIONS as $key => $definition) {
            [$value, $description, $formula] = $values[$key];
            $targetLabel = AcademicMetrics::targetLabel($key);
            $criticalLabel = AcademicMetrics::warningLabel($key);
            $kpis[] = $definition + compact('key', 'value', 'description', 'formula') + [
                'status' => $key === 'grades' ? $summary['grade_status'] : AcademicMetrics::status($key, $value),
                'display' => AcademicMetrics::display($key, $value),
                'target_label' => $targetLabel,
                'critical_label' => $criticalLabel,
                'tooltip' => $formula.' '.$targetLabel.'. '.$criticalLabel.'.',
            ];
        }

        return $kpis;
    }

    public function publicRecord(array $record): array
    {
        $rating = $this->rating([$record]);
        $passingRate = $this->rate($record['passing_count'], $record['graded_count']);
        $seteRate = $this->rate($record['sete_submitted'], $record['sete_required']);
        // Never send raw score sums or hidden low-response averages to the browser/export.
        unset($record['rating_sum']);
        if ($rating['confidential']) {
            $record['rating_responses'] = null;
        }

        return $record + [
            'year_label' => self::YEAR_LABELS[$record['year_level']],
            'passing_rate' => $passingRate,
            'passing_status' => AcademicMetrics::status('passing', $passingRate),
            'sete_rate' => $seteRate,
            'sete_status' => AcademicMetrics::status('sete', $seteRate),
            'grade_status' => $this->gradeStatus($record),
            'rating' => $rating['value'],
            'rating_status' => AcademicMetrics::status('rating', $rating['value']),
            'rating_note' => $rating['confidential'] ? 'Insufficient responses for confidential reporting.' : ($rating['value'] === null ? 'No valid evaluation responses.' : 'Weighted class SETE rating.'),
        ];
    }

    public function table(array $records, array $query): array
    {
        $rows = array_map(fn ($r) => $this->publicRecord($r), $records);
        $search = mb_strtolower(trim(is_scalar($query['search'] ?? '') ? (string) ($query['search'] ?? '') : ''));
        if ($search !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => str_contains(mb_strtolower($r['section'].' '.$r['subject'].' '.$r['subject_name'].' '.$r['faculty_name']), $search)));
        }
        $sort = in_array($query['sort'] ?? '', self::SORT_KEYS, true) ? $query['sort'] : 'section';
        $direction = ($query['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        usort($rows, function ($a, $b) use ($sort, $direction) {
            $aValue = $a[$sort];
            $bValue = $b[$sort];
            // Missing rates always sort last, in either direction.
            if ($aValue === null || $bValue === null) {
                return ($aValue === null ? 1 : 0) <=> ($bValue === null ? 1 : 0);
            }
            $comparison = in_array($sort, ['year_level', 'total_students', 'passing_rate', 'sete_rate'], true) ? (float) $aValue <=> (float) $bValue : strnatcasecmp($aValue, $bValue);

            return ($direction === 'desc' ? -$comparison : $comparison) ?: strcmp($a['id'], $b['id']);
        });

        return ['rows' => $rows, 'total' => count($rows), 'search' => $search, 'sort' => $sort, 'direction' => $direction];
    }

    public function dashboard(array $query): array
    {
        $filters = $this->normalize($query);
        $records = $this->select($this->repository->records(), $filters);
        $summary = $this->summarize($records);
        $improvement = $this->improvement($records, $filters);
        $catalog = $this->select($this->repository->records(), ['college' => $filters['college'], 'program' => $filters['program']]);
        $years = [];
        foreach (self::YEAR_LABELS as $year => $label) {
            $group = array_values(array_filter($records, fn ($r) => $r['year_level'] === (string) $year));
            $years[] = ['value' => (string) $year, 'label' => $label, ...$this->summarize($group)];
        }
        $trend = [];
        foreach ($this->repository->periods() as $period) {
            $historical = $this->select($this->repository->records(), array_merge($filters, ['academic_year' => $period['academic_year'], 'semester' => $period['semester']]));
            $selected = $period['academic_year'] === $filters['academic_year'] && $period['semester'] === $filters['semester'];
            $historicalSummary = $this->summarize($historical);
            $trend[] = $period + ['rating' => $historicalSummary['rating'], 'summary' => $historicalSummary, 'selected' => $selected];
        }

        return [
            'metadata' => $this->repository->metadata(), 'filters' => $filters, 'defaults' => $this->defaults(),
            'catalog' => array_map(fn ($r) => array_intersect_key($r, array_flip([...self::FILTER_KEYS, 'subject_name', 'faculty_name'])), $catalog),
            'options' => $this->options($filters), 'summary' => $summary,
            'kpis' => $this->kpis($summary, $improvement),
            'alerts' => (new AcademicAlertService($this))->generate($records, $filters, $summary, $improvement),
            'charts' => ['years' => $years, 'trend' => $trend],
            'table' => $this->table($records, $query),
            'records' => array_map(fn ($r) => $this->publicRecord($r), $records),
            'improvement' => $improvement,
            'drilldown' => $this->drilldown($records),
        ];
    }

    public function drilldown(array $records): array
    {
        $tree = [];
        foreach (self::YEAR_LABELS as $year => $label) {
            $yearRecords = array_values(array_filter($records, fn ($r) => $r['year_level'] === (string) $year));
            if (! $yearRecords) {
                continue;
            }
            $sections = [];
            foreach (array_unique(array_column($yearRecords, 'section')) as $section) {
                $sectionRecords = array_values(array_filter($yearRecords, fn ($r) => $r['section'] === $section));
                $sections[] = ['label' => $section, 'summary' => $this->summarize($sectionRecords), 'records' => array_map(fn ($r) => $this->publicRecord($r), $sectionRecords)];
            }
            $tree[] = ['label' => $label, 'value' => (string) $year, 'summary' => $this->summarize($yearRecords), 'sections' => $sections];
        }

        return $tree;
    }
}
