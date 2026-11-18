<?php

namespace App\Repositories;

use App\Services\AcademicMetrics;

/** Deterministic, fictional class summaries; no individual student responses. */
class DemoAcademicRepository
{
    public const COLLEGE = 'CICS';

    public const PROGRAM = 'BSIT';

    private ?array $records = null;

    private readonly int $minimumResponses;

    public function __construct(int $minimumResponses = AcademicMetrics::MIN_RATING_RESPONSES)
    {
        $this->minimumResponses = max(2, $minimumResponses);
    }

    public function metadata(): array
    {
        return [
            'updated_at' => '2026-06-25T09:00:00+08:00',
            'updated_label' => '25 Jun 2026, 9:00 AM PHT',
            'fictional' => true,
            'minimum_responses' => $this->minimumResponses,
            'rating_scale' => ['min' => AcademicMetrics::RATING_MIN, 'max' => AcademicMetrics::RATING_MAX],
            'institution' => 'Batangas State University – The National Engineering University, ARASOF-Nasugbu Campus',
        ];
    }

    public function periods(): array
    {
        $periods = [];
        foreach (['2023-2024', '2024-2025', '2025-2026'] as $year) {
            foreach (['1' => 'First Semester', '2' => 'Second Semester'] as $semester => $label) {
                $endYear = substr($year, 5);
                $periods[] = [
                    'academic_year' => $year,
                    'semester' => (string) $semester,
                    'semester_label' => $label,
                    'label' => "$label $year",
                    'short_label' => ($semester == 1 ? '1st' : '2nd').' · '.$year,
                    'grade_deadline' => $endYear.($semester == 1 ? '-01-15' : '-06-15'),
                ];
            }
        }

        return $periods;
    }

    public function faculty(): array
    {
        return [
            'F01' => 'Alyssa Mendoza', 'F02' => 'Gabriel Santos',
            'F03' => 'Sofia Reyes', 'F04' => 'Miguel Navarro',
            'F05' => 'Isabella Cruz', 'F06' => 'Daniel Flores',
            'F07' => 'Camille Torres', 'F08' => 'Rafael Garcia',
        ];
    }

    public function subjects(): array
    {
        return [
            1 => ['IT 111' => 'Introduction to Computing', 'IT 112' => 'Computer Programming I', 'MATH 101' => 'Mathematics in the Modern World'],
            2 => ['IT 211' => 'Data Structures and Algorithms', 'IT 212' => 'Database Management Systems', 'IT 213' => 'Object-Oriented Programming'],
            3 => ['IT 311' => 'Web Systems and Technologies', 'IT 312' => 'Information Assurance and Security', 'IT 313' => 'Systems Integration and Architecture'],
            4 => ['IT 411' => 'Capstone Project II', 'IT 412' => 'IT Service Management', 'IT 413' => 'Professional Elective'],
        ];
    }

    public function records(): array
    {
        if ($this->records !== null) {
            return $this->records;
        }

        $records = [];
        $faculty = $this->faculty();
        foreach ($this->periods() as $periodIndex => $period) {
            foreach ($this->subjects() as $yearLevel => $subjects) {
                foreach (['A', 'B'] as $sectionIndex => $sectionLetter) {
                    foreach (array_keys($subjects) as $subjectIndex => $subjectCode) {
                        $index = ($yearLevel - 1) * 6 + $sectionIndex * 3 + $subjectIndex;
                        $students = 32 + (($index * 7 + $periodIndex * 3) % 17);
                        $missingGrades = $periodIndex === 5 && $index === 23;
                        $graded = $missingGrades ? 0 : $students - ($index % 9 === 0 ? 2 : 0);
                        $passingRatio = [1 => .94, 2 => .89, 3 => .85, 4 => .92][$yearLevel];
                        if ($subjectCode === 'IT 312') {
                            $passingRatio = .68 + $sectionIndex * .03;
                        }
                        $passing = (int) round($graded * min(.99, $passingRatio + ($periodIndex - 4) * .006));
                        $completionRatio = [1 => .91, 2 => .84, 3 => .69, 4 => .75][$yearLevel];
                        $submitted = (int) floor($students * min(1, $completionRatio + ($sectionIndex ? .02 : -.02) + ($subjectIndex - 1) * .015 + ($periodIndex - 4) * .01));
                        $facultyId = 'F'.str_pad((string) (($index % 8) + 1), 2, '0', STR_PAD_LEFT);
                        $unassigned = $periodIndex === 5 && $index === 23;
                        if ($unassigned) {
                            $submitted = 0;
                        }
                        $responses = $submitted;
                        if ($periodIndex === 5 && $index === 22) {
                            $responses = 4;
                            $submitted = 4;
                        }
                        // Six of eight faculty improve; two decline, with weighted totals.
                        $slope = in_array($facultyId, ['F03', 'F07'], true) ? -.035 : .055;
                        $rating = 3.85 + (($index % 8) * .045) + $periodIndex * $slope;
                        $deadline = $period['grade_deadline'];
                        $submittedAt = $index === 23 ? null : ($index === 22 ? date('Y-m-d', strtotime($deadline.' +3 days')) : date('Y-m-d', strtotime($deadline.' -2 days')));
                        $records[] = [
                            'id' => 'DEMO-'.$periodIndex.'-'.$index,
                            'academic_year' => $period['academic_year'], 'semester' => $period['semester'],
                            'semester_label' => $period['semester_label'],
                            'college' => self::COLLEGE, 'program' => self::PROGRAM,
                            'year_level' => (string) $yearLevel, 'section' => "BSIT {$yearLevel}{$sectionLetter}",
                            'subject' => $subjectCode, 'subject_name' => $subjects[$subjectCode],
                            'faculty' => $unassigned ? '' : $facultyId,
                            'faculty_name' => $unassigned ? 'Unassigned faculty' : $faculty[$facultyId],
                            'total_students' => $students, 'graded_count' => $graded,
                            'passing_count' => $passing, 'failing_count' => $graded - $passing,
                            'sete_required' => $students, 'sete_submitted' => $submitted,
                            'rating_sum' => round($rating * $responses, 3), 'rating_responses' => $responses,
                            'grade_deadline' => $deadline, 'grades_submitted_at' => $submittedAt,
                        ];
                    }
                }
            }
        }

        return $this->records = $records;
    }
}
