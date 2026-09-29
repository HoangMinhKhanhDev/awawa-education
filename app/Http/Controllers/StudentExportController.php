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

    public function team(StudentExportService $exporter, SubjectContext $context): Response
    {
        Gate::authorize('manageStudents', User::class);

        $subject = $context->subject();
        abort_if($subject === null, 404);

        $file = $exporter->teamWorkbook($subject->id, $subject->name);

        return response($file['content'], 200, [
            'Content-Type' => self::XLSX,
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
        ]);
    }

    public function examGrades(Exam $exam, StudentExportService $exporter): Response
    {
        Gate::authorize('view', $exam);

        $file = $exporter->examGradesWorkbook($exam);

        return response($file['content'], 200, [
            'Content-Type' => self::XLSX,
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
        ]);
    }
}
