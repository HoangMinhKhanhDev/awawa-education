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

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
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
     * (PDF, docx, xlsx...) thì mở file thật vì trình duyệt không hiển thị được.
     */
    public function viewerUrl(): string
    {
        return $this->isViewable()
            ? route('documents.show', $this)
            : $this->url();
    }

    /**
     * Nội dung văn bản thuần, null nếu không đọc được (file mất hoặc quá lớn).
     */
    public function readContent(): ?string
    {
        if (! $this->isViewable() || ! Storage::disk('public')->exists($this->file_path)) {
            return null;
        }

        // Trần cỡ để một tài liệu lỡ bị sinh ra quá khổng không kéo chết trang.
        if ((int) $this->size > self::MAX_VIEWABLE_BYTES) {
            return null;
        }

        $contents = Storage::disk('public')->get($this->file_path);

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
