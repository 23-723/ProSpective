<?php

namespace App\Services;

class AcademicAlertService
{
    public function __construct(private AcademicAnalyticsService $analytics) {}

    public function generate(array $records, array $filters, ?array $summary = null, ?array $improvement = null): array
    {
        if (! $records) {
            return [];
        }

        $alerts = [];
        $summary ??= $this->analytics->summarize($records);
        $improvement ??= $this->analytics->improvement($records, $filters);
        $period = $records[0]['semester_label'].' · '.str_replace('-', '–', $filters['academic_year']);

        foreach (AcademicAnalyticsService::YEAR_LABELS as $year => $label) {
            $group = array_filter($records, fn ($record) => $record['year_level'] === (string) $year);
            $counts = $this->analytics->summarize($group);
            if ($this->needsAttention('sete', $counts['sete_rate'])) {
                $alerts[] = $this->metricAlert('sete-'.$year, 'Low SETE participation', $label, 'sete', $counts['sete_rate'],
                    $counts['submitted'].' of '.$counts['required'].' required student-subject evaluations were submitted.',
                    'Coordinate with assigned faculty and remind students to complete their evaluations.', $group, $period);
            }
        }
        foreach (array_unique(array_column($records, 'subject')) as $subject) {
            $group = array_filter($records, fn ($record) => $record['subject'] === $subject);
            $counts = $this->analytics->summarize($group);
            if ($this->needsAttention('passing', $counts['passing_rate'])) {
                $alerts[] = $this->metricAlert('passing-'.$subject, 'Academic support needed', $subject, 'passing', $counts['passing_rate'],
                    $counts['passing'].' of '.$counts['graded'].' student-subject records with final grades passed.',
                    'Review subject-level results and coordinate targeted academic support.', $group, $period);
            }
        }

        foreach ([
            'overdue' => ['Overdue', 'Overdue grade submissions', 'Critical', 'The deadline passed with no completed submission.', 'Follow up with the assigned faculty and confirm a submission date.'],
            'pending' => ['Pending', 'Pending grade submissions', 'Advisory', 'The submissions are outstanding and their deadlines have not passed.', 'Remind the assigned faculty of the upcoming deadlines.'],
            'late' => ['Submitted late', 'Late grade submissions', 'Advisory', 'These submissions were completed after their deadlines and count toward the completion rate.', 'Review the causes of missed deadlines with the assigned faculty.'],
        ] as $key => [$status, $title, $severity, $explanation, $action]) {
            if ($summary[$key] === 0) {
                continue;
            }
            $group = array_filter($records, fn ($record) => $this->analytics->gradeStatus($record) === $status);
            $alerts[] = $this->alert('grades-'.$key, $title, implode(', ', array_unique(array_column($group, 'section'))), 'grades',
                count($group), count($group).(count($group) === 1 ? ' class' : ' classes'), $severity,
                'Class deadlines evaluated as of '.substr($this->analytics->repository->metadata()['updated_at'], 0, 10),
                $explanation, $action, $group, $period, 'submission_status');
        }

        if ($this->needsAttention('improvement', $improvement['value'])) {
            $alerts[] = $this->metricAlert('improvement', 'Teaching development review', 'Comparable faculty', 'improvement', $improvement['value'],
                $improvement['improved'].' of '.$improvement['comparable'].' comparable faculty had a higher weighted rating than in '.$improvement['previous_period'].'.',
                'Review response coverage and discuss development opportunities using several sources of evidence.', $records, $period);
        }
        $rating = $summary['rating'];
        if ($this->needsAttention('rating', $rating['value'])) {
            $alerts[] = $this->metricAlert('rating', 'Review evaluation results', 'Current selection', 'rating', $rating['value'],
                'The weighted SETE rating is based on '.$rating['responses'].' responses from reportable class groups.',
                'Review participation and other teaching evidence before deciding on any action.', $records, $period);
        }

        $priorRating = $improvement['previous_rating'];
        if ($rating['value'] !== null && $priorRating['value'] !== null && $rating['value'] < $priorRating['value']) {
            $alerts[] = $this->alert('rating-decline', 'Lower SETE rating than prior period', 'Current selection', 'rating',
                $rating['value'], AcademicMetrics::display('rating', $rating['value']).' / 5.0', 'Advisory',
                AcademicMetrics::display('rating', $priorRating['value']).' / 5.0 in '.$improvement['previous_period'],
                'The same filters were applied to both periods. The ratings use '.$rating['responses'].' current and '.$priorRating['responses'].' prior reportable responses.',
                'Review differences in participation and academic context before drawing conclusions.', $records, $period);
        }

        $minimumResponses = $this->analytics->minimumResponses();
        $lowResponses = array_filter($records, fn ($record) => $record['sete_required'] > 0 && $record['rating_responses'] < $minimumResponses);
        if ($lowResponses) {
            $count = count($lowResponses);
            $alerts[] = $this->alert('sete-privacy', 'Insufficient SETE responses', 'Current selection', 'rating',
                $count, $count.($count === 1 ? ' class' : ' classes'), 'Advisory',
                'At least '.$minimumResponses.' valid responses per class to report a rating',
                'Small class groups are excluded from published ratings. Submission totals still include their participation.',
                'Encourage participation and revisit the rating once enough responses are available.', $lowResponses, $period);
        }

        $priority = ['Critical' => 0, 'Below Target' => 1, 'Advisory' => 2];
        usort($alerts, fn ($first, $second) => $priority[$first['severity']] <=> $priority[$second['severity']]);

        return $alerts;
    }

    private function needsAttention(string $metric, ?float $value): bool
    {
        return $value !== null && AcademicMetrics::status($metric, $value) !== 'Meets Target';
    }

    private function metricAlert(string $id, string $title, string $scope, string $metric, float $value, string $explanation, string $action, array $records, string $period): array
    {
        $suffix = $metric === 'rating' ? ' / 5.0' : '%';
        $threshold = AcademicMetrics::targetLabel($metric).' · '.AcademicMetrics::warningLabel($metric);

        return $this->alert($id, $title, $scope, $metric, $value, AcademicMetrics::display($metric, $value).$suffix,
            AcademicMetrics::status($metric, $value), $threshold, $explanation, $action, $records, $period, 'metric');
    }

    private function alert(string $id, string $title, string $scope, string $metric, float $value, string $display, string $severity, string $threshold, string $explanation, string $action, array $records, string $period, string $kind = 'advisory'): array
    {
        return compact('id', 'title', 'scope', 'metric', 'value', 'display', 'severity', 'threshold', 'explanation', 'action', 'period', 'kind') + [
            'kpi' => AcademicMetrics::DEFINITIONS[$metric]['title'],
            'record_ids' => array_column($records, 'id'),
        ];
    }
}
