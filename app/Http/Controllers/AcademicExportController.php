<?php

namespace App\Http\Controllers;

use App\Services\AcademicAnalyticsService;
use App\Services\AcademicMetrics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicExportController extends Controller
{
    public function __invoke(Request $request, AcademicAnalyticsService $analytics): StreamedResponse
    {
        $filters = $analytics->normalize($request->query());
        $records = $analytics->select($analytics->repository->records(), $filters);
        $rows = $analytics->table($records, $request->query())['rows'];

        return response()->streamDownload(function () use ($rows) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Data Type', 'Academic Year', 'Semester', 'Year Level', 'Section', 'Subject Code', 'Subject Name', 'Faculty', 'Total Students', 'Graded Records', 'Passing Records', 'Failing Records', 'Passing Rate (%)', 'Required SETE', 'Submitted SETE', 'SETE Completion (%)', 'Grade Submission Status', 'Grade Deadline', 'SETE Rating (/5)', 'Rating Note', 'Valid SETE Responses', 'Grades Submitted On'], ',', '"', '');
            foreach ($rows as $row) {
                $cells = [
                    'FICTIONAL DEMONSTRATION DATA', $row['academic_year'], $row['semester_label'], $row['year_label'],
                    $row['section'], $row['subject'], $row['subject_name'], $row['faculty_name'], $row['total_students'],
                    $row['graded_count'], $row['passing_count'], $row['failing_count'],
                    AcademicMetrics::display('passing', $row['passing_rate']), $row['sete_required'], $row['sete_submitted'],
                    AcademicMetrics::display('sete', $row['sete_rate']), $row['grade_status'], $row['grade_deadline'],
                    AcademicMetrics::display('rating', $row['rating']), $row['rating_note'], $row['rating_responses'] ?? 'N/A',
                    $row['grades_submitted_at'] ?? 'N/A',
                ];
                $cells = array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value, $cells);
                fputcsv($file, $cells, ',', '"', '');
            }
            fclose($file);
        }, 'prospective-academic-report-'.$filters['academic_year'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
