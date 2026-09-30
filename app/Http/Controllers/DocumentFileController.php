<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentFileController extends Controller
{
    public function show(Document $document): BinaryFileResponse|Response
    {
        Gate::authorize('read', $document);

        $disk = $document->fileDisk();

        if (! Storage::disk($disk)->exists($document->file_path)) {
            abort(404, 'File không còn tồn tại.');
        }

        $path = Storage::disk($disk)->path($document->file_path);
        $downloadName = $document->original_name ?: basename($document->file_path);

        // Chỉ inline các loại trình duyệt render an toàn cùng origin (PDF/ảnh).
        // Office và các loại khác ép download để tránh XSS qua file polyglot.
        $inlineMimes = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp', 'image/gif'];
        $disposition = in_array(strtolower((string) $document->mime), $inlineMimes, true) ? 'inline' : 'attachment';

        return response()->file($path, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.$this->safeFilename($downloadName).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }

    protected function safeFilename(string $name): string
    {
        $name = preg_replace('/[\r\n"]+/', '_', $name) ?? 'tai-lieu';

        return mb_substr($name, 0, 180);
    }
}
