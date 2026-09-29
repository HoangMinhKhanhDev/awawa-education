<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\User;
use App\Services\StudentExportService;
use App\Support\SubjectContext;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class StudentExportController extends Controller
{
    protected const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    protected const CSV = 'text/csv; charset=UTF-8';

    public function team(StudentExportService $exporter, SubjectContext $context): Response
    {
        Gate::authorize('manageStudents', User::class);

        $subject = $context->subject();
        abort_if($subject === null, 404);

        $file = $exporter->teamWorkbook($subject->id, $subject->name);

        return $this->download($file);
    }

    public function teamCsv(StudentExportService $exporter, SubjectContext $context): Response
    {
        Gate::authorize('manageStudents', User::class);

        $subject = $context->subject();
        abort_if($subject === null, 404);

        $file = $exporter->teamCsv($subject->id, $subject->name);

        return $this->download($file, self::CSV);
    }

    public function examGrades(Exam $exam, StudentExportService $exporter): Response
    {
        Gate::authorize('view', $exam);

        return $this->download($exporter->examGradesWorkbook($exam));
    }

    public function examGradesCsv(Exam $exam, StudentExportService $exporter): Response
    {
        Gate::authorize('view', $exam);

        return $this->download($exporter->examGradesCsv($exam), self::CSV);
    }

    /**
     * @param  array{filename: string, content: string}  $file
     */
    protected function download(array $file, string $contentType = self::XLSX): Response
    {
        return response($file['content'], 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
        ]);
    }
}
