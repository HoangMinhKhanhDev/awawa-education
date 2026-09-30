<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use BelongsToSubject, HasFactory;

    /** Tài liệu văn bản lớn hơn mức này thì không nạp vào trang xem. */
    public const MAX_VIEWABLE_BYTES = 2_000_000;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'created_by',
        'title',
        'description',
        'category',
        'file_path',
        'original_name',
        'mime',
        'size',
        'is_public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Disk đang giữ file thật. File mới nằm ở `local` (private), file cũ
     * vẫn có thể nằm ở `public` trong thời gian migrate — đọc thử local trước.
     */
    public function fileDisk(): string
    {
        if (Storage::disk('local')->exists($this->file_path)) {
            return 'local';
        }

        return 'public';
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->fileDisk())->exists($this->file_path);
    }

    public function url(): string
    {
        if (! $this->isViewable()) {
            return route('documents.file', $this);
        }

        return route('documents.show', $this);
    }

    /**
     * URL trực tiếp tới file gốc (PDF/docx...) — luôn qua controller có auth,
     * không còn trỏ thẳng /storage/... để tránh lộ tài liệu riêng tư.
     */
    public function fileUrl(): string
    {
        return route('documents.file', $this);
    }

    /**
     * Tài liệu văn bản thuần có thể hiển thị ngay trong app.
     */
    public function isViewable(): bool
    {
        return in_array(strtolower((string) $this->mime), [
            'text/markdown',
            'text/plain',
            'text/x-markdown',
        ], true) || str_ends_with(strtolower((string) $this->file_path), '.md');
    }

    /**
     * Link mở tài liệu: văn bản thuần thì vào trang đọc trong app, còn lại
     * (PDF, docx, xlsx...) thì qua controller file có kiểm quyền.
     */
    public function viewerUrl(): string
    {
        return $this->url();
    }

    /**
     * Nội dung văn bản thuần, null nếu không đọc được (file mất hoặc quá lớn).
     */
    public function readContent(): ?string
    {
        if (! $this->isViewable()) {
            return null;
        }

        $disk = $this->fileDisk();

        if (! Storage::disk($disk)->exists($this->file_path)) {
            return null;
        }

        // Trần cỡ để một tài liệu lỡ bị sinh ra quá khổng không kéo chết trang.
        if ((int) $this->size > self::MAX_VIEWABLE_BYTES) {
            return null;
        }

        $contents = Storage::disk($this->fileDisk())->get($this->file_path);

        return is_string($contents) ? $contents : null;
    }

    public function sizeForHumans(): string
    {
        $bytes = max(0, (int) $this->size);

        foreach (['GB' => 1073741824, 'MB' => 1048576, 'KB' => 1024] as $unit => $step) {
            if ($bytes >= $step) {
                return round($bytes / $step, $bytes / $step >= 10 ? 0 : 1).' '.$unit;
            }
        }

        return $bytes.' B';
    }
}
