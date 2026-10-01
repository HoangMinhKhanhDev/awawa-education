<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class NotebookSource extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notebook_id',
        'type',
        'title',
        'url',
        'ref_type',
        'ref_id',
        'file_path',
        'original_name',
        'mime',
        'size',
        'char_count',
        'raw_content',
        'status',
        'error',
        'is_enabled',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'char_count' => 'integer',
            'order' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }

    public function ref(): MorphTo
    {
        return $this->morphTo();
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(NotebookChunk::class, 'source_id')->orderBy('position');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'file' => 'Tệp tải lên',
            'text' => 'Văn bản',
            'document' => 'Tài liệu',
            'question' => 'Câu hỏi',
            'exam' => 'Đề thi',
            'web' => 'Trang web',
            default => $this->type,
        };
    }

    public function typeIcon(): string
    {
        return match ($this->type) {
            'file' => 'file',
            'text' => 'doc',
            'document' => 'book',
            'question' => 'help',
            'exam' => 'cap',
            'web' => 'globe',
            default => 'file',
        };
    }

    /**
     * Màu nền/chữ riêng cho từng loại nguồn, để liếc là phân biệt được.
     */
    public function typeTone(): string
    {
        return match ($this->type) {
            'web' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
            'file' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            'text' => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
            'document' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            'question' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
            'exam' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
        };
    }

    /**
     * Ảnh đại diện của trang web (favicon), chỉ có với nguồn web.
     */
    public function faviconUrl(): ?string
    {
        if ($this->type !== 'web') {
            return null;
        }

        return self::faviconForUrl($this->url);
    }

    public function faviconHost(): ?string
    {
        $url = $this->type === 'web' ? $this->faviconUrl() : null;

        if ($url === null) {
            return null;
        }

        return mb_strtolower((string) parse_url($this->url, PHP_URL_HOST)) ?: null;
    }

    public static function faviconForUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return null;
        }

        return 'https://www.google.com/s2/favicons?domain='.$host.'&sz=64';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'ready' => 'Sẵn sàng',
            'processing' => 'Đang xử lý',
            default => 'Lỗi',
        };
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            'ready' => 'status-success',
            'processing' => 'status-warning',
            default => 'status-signal',
        };
    }

    public function hasOriginal(): bool
    {
        return in_array($this->type, ['file', 'document', 'web'], true);
    }

    public function originalUrl(): ?string
    {
        if ($this->type === 'web') {
            return $this->url;
        }

        // File notebook là private (disk local), không còn URL /storage/... công khai.
        // Muốn tải bản gốc thì đi qua Document liên kết (nếu có).
        return null;
    }

    public function fileDisk(): string
    {
        if (filled($this->file_path) && Storage::disk('local')->exists($this->file_path)) {
            return 'local';
        }

        return 'public';
    }
}
