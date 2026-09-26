<?php

namespace App\Http\Controllers;

use App\Models\NotebookArtifact;
use App\Services\Notebook\ArtifactExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NotebookArtifactExportController extends Controller
{
    public function __invoke(NotebookArtifact $artifact, string $format, ArtifactExporter $exporter): BinaryFileResponse
    {
        abort_unless($format === 'docx', 404);
        abort_unless($artifact->notebook->isOwnedBy(auth()->user()), 404);

        return $exporter->downloadDocx($artifact);
    }
}
