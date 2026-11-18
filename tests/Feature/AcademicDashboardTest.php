<?php

namespace Tests\Feature;

use App\Services\AcademicAnalyticsService;
use App\Services\AcademicMetrics;
use Tests\TestCase;

class AcademicDashboardTest extends TestCase
{
    public function test_dashboard_loads_as_the_only_dashboard_page_without_a_sidebar_or_database(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Academic Analytics Overview · ProSpective')
            ->assertSee('An Integrated Academic Performance and Teaching Effectiveness Analytics and Decision Support System for Program Chair</p>', false)
            ->assertSee('prospective-logo.png')
            ->assertSee('Academic Filters')
            ->assertSee('Academic Performance Monitoring')
            ->assertSee('Decision Support and Insights')
            ->assertDontSee('Teaching and Academic Insights')
            ->assertDontSee('class="kpi-info"', false)
            ->assertSee('Student-subject evaluations submitted')
            ->assertSee('SETE results should be interpreted alongside other academic and professional performance indicators')
            ->assertSee('© 2026 ProSpective. All rights reserved.')
            ->assertSee('Version 1.0.0')
            ->assertDontSee('Turning Academic Data into Meaningful Insights.')
            ->assertDontSee('Academic Case Study Prototype')
            ->assertDontSee('CICS, BSIT')
            ->assertDontSee('Fictional student-subject evaluations submitted')
            ->assertDontSee('<aside', false);
        $this->assertSame('none', config('database.default'));
        $this->assertSame([], app('db')->getConnections());
        $this->assertFileDoesNotExist(database_path('database.sqlite'));
    }

    public function test_all_academic_filters_change_the_same_dashboard_scope(): void
    {
        foreach ([['academic_year' => '2023-2024'], ['semester' => '2'], ['year_level' => '3'], ['section' => 'BSIT 3A'], ['subject' => 'IT 312'], ['faculty' => 'F02'], ['college' => 'CICS'], ['program' => 'BSIT']] as $filter) {
            $data = $this->getJson('/dashboard/data?'.http_build_query($filter))->assertOk()->json();
            $this->assertNotEmpty($data['records']);
            foreach ($data['records'] as $record) {
                foreach ($filter as $key => $value) {
                    $this->assertSame($value, $record[$key]);
                }
            }
            $this->assertSame(count($data['records']), $data['summary']['classes']);
            $this->assertSame(count($data['records']), $data['table']['total']);
            $this->assertCount(5, $data['kpis']);
            $this->assertCount(4, $data['charts']['years']);
            $this->assertCount(6, $data['charts']['trend']);
        }
    }

    public function test_sorting_search_and_csv_export_use_the_same_complete_matching_result_set(): void
    {
        $query = ['year_level' => '3', 'search' => 'IT 31', 'sort' => 'passing_rate', 'direction' => 'desc'];
        $uri = http_build_query($query);
        $data = $this->getJson('/dashboard/data?'.$uri)->assertOk()->json();
        $this->getJson('/dashboard/table?'.$uri)->assertOk()->assertExactJson($data['table']);
        $rates = array_column($data['table']['rows'], 'passing_rate');
        $sorted = $rates;
        rsort($sorted, SORT_NUMERIC);
        $this->assertSame($sorted, $rates);
        $export = $this->get('/dashboard/export?'.$uri)->assertOk()->assertDownload('prospective-academic-report-2025-2026.csv');
        $csv = $export->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount($data['table']['total'] + 1, $lines);
        $sections = array_column($data['table']['rows'], 'section');
        foreach (array_slice($lines, 1) as $index => $line) {
            $row = str_getcsv($line, ',', '"', '');
            $this->assertSame('Third Year', $row[3]);
            $this->assertSame($sections[$index], $row[4]);
            $this->assertSame(AcademicMetrics::display('passing', $rates[$index]), $row[12]);
            $this->assertSame(AcademicMetrics::display('sete', $data['table']['rows'][$index]['sete_rate']), $row[15]);
            $this->assertSame(AcademicMetrics::display('rating', $data['table']['rows'][$index]['rating']), $row[18]);
            $this->assertSame('FICTIONAL DEMONSTRATION DATA', $row[0]);
        }
        $all = $this->get('/dashboard/export?page=2')->assertOk()->streamedContent();
        $this->assertCount(25, array_filter(explode("\n", trim($all))));
    }

    public function test_csv_and_json_suppress_low_response_ratings_and_handle_missing_grades(): void
    {
        $uri = 'semester=2&section=BSIT%204B&subject=IT%20412';
        $data = $this->getJson('/dashboard/data?'.$uri)->assertOk()->json();
        $this->assertNull($data['records'][0]['rating_responses']);
        $this->assertNull($data['records'][0]['rating']);
        $this->assertNull($data['kpis'][4]['value']);
        $this->assertStringNotContainsString('rating_sum', json_encode($data, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('excluded_responses', json_encode($data, JSON_THROW_ON_ERROR));
        $csv = $this->get('/dashboard/export?'.$uri)->assertOk()->streamedContent();
        $cells = str_getcsv(explode("\n", $csv)[1], ',', '"', '');
        $this->assertSame(['N/A', 'Insufficient responses for confidential reporting.', 'N/A'], array_slice($cells, 18, 3));
        $unassigned = $this->getJson('/dashboard/data?semester=2&section=BSIT%204B&subject=IT%20413')->assertOk()->json();
        $this->assertNull($unassigned['records'][0]['passing_rate']);
        $this->assertSame('Unassigned faculty', $unassigned['records'][0]['faculty_name']);
    }

    public function test_configured_response_threshold_applies_to_dashboard_table_trend_and_csv(): void
    {
        config(['academic.sete_min_responses' => 39]);
        $query = 'section=BSIT%201A&subject=IT%20111';
        $data = $this->getJson('/dashboard/data?'.$query)->assertOk()->json();
        $this->assertSame(39, $data['metadata']['minimum_responses']);
        $this->assertNull($data['summary']['rating']['value']);
        $this->assertNull($data['kpis'][4]['value']);
        $this->assertStringContainsString('39 valid responses', $data['kpis'][4]['formula']);
        $this->assertNull(collect($data['charts']['trend'])->firstWhere('selected', true)['rating']['value']);
        $this->assertSame(0, $data['improvement']['comparable']);
        $this->assertNull($data['drilldown'][0]['sections'][0]['records'][0]['rating_responses']);
        $alert = collect($data['alerts'])->firstWhere('id', 'sete-privacy');
        $this->assertStringContainsString('39 valid responses', $alert['threshold']);
        $this->assertNotContains('rating', array_column($data['alerts'], 'id'));
        $this->getJson('/dashboard/table?'.$query)->assertOk()->assertJsonPath('rows.0.rating_responses', null);
        $csv = $this->get('/dashboard/export?'.$query)->assertOk()->streamedContent();
        $this->assertStringContainsString('N/A,"Insufficient responses for confidential reporting.",N/A', $csv);
    }

    public function test_empty_search_and_unknown_scope_return_real_empty_states(): void
    {
        $this->getJson('/dashboard/data?search=no-such-faculty')->assertOk()->assertJsonPath('table.total', 0)->assertJsonPath('summary.classes', 24);
        $this->getJson('/dashboard/table?search=no-such-faculty')->assertOk()->assertJsonPath('total', 0)->assertJsonPath('rows', []);
        $data = $this->getJson('/dashboard/data?college=UNKNOWN')->assertOk()->json();
        $this->assertSame(0, $data['summary']['classes']);
        $this->assertSame([], $data['alerts']);
        foreach ($data['kpis'] as $kpi) {
            $this->assertNull($kpi['value']);
        }
    }

    public function test_alerts_use_the_same_thresholds_and_link_to_the_affected_records(): void
    {
        $analytics = app(AcademicAnalyticsService::class);
        $data = $this->getJson('/dashboard/data')->assertOk()->json();
        $indexed = array_column($analytics->select($analytics->repository->records(), $data['filters']), null, 'id');
        $this->assertNotEmpty($data['alerts']);
        foreach ($data['alerts'] as $alert) {
            $this->assertContains($alert['severity'], ['Critical', 'Below Target', 'Advisory']);
            $this->assertSame('First Semester · 2025–2026', $alert['period']);
            $this->assertNotEmpty($alert['record_ids']);
            $this->assertNotEmpty($alert['action']);
            $this->assertNotEmpty($alert['threshold']);
            $records = array_map(fn ($id) => $indexed[$id], $alert['record_ids']);
            if (in_array($alert['metric'], ['sete', 'passing'], true)) {
                $summary = $analytics->summarize($records);
                $this->assertSame(AcademicMetrics::status($alert['metric'], $alert['value']), $alert['severity']);
                $this->assertEquals($summary[$alert['metric'] === 'sete' ? 'sete_rate' : 'passing_rate'], $alert['value']);
            }
            if ($alert['kind'] === 'submission_status') {
                $status = $alert['id'] === 'grades-overdue' ? 'Overdue' : 'Submitted late';
                $this->assertEquals(count($records), $alert['value']);
                $this->assertSame([$status], array_values(array_unique(array_map($analytics->gradeStatus(...), $records))));
            }
        }
        $this->assertContains('grades-overdue', array_column($data['alerts'], 'id'));
        $this->assertContains('grades-late', array_column($data['alerts'], 'id'));
        $this->assertNotContains('grades-rate', array_column($data['alerts'], 'id'));
        $this->assertEquals(22 / 24 * 100, $data['summary']['grade_rate']);
        $this->assertEquals(23 / 24 * 100, $data['summary']['grade_completion_rate']);
    }
}
