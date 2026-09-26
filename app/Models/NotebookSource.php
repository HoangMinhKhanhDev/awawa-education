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

        if (in_array($this->type, ['file', 'document'], true) && filled($this->file_path)) {
            return Storage::disk('public')->url($this->file_path);
        }

        return null;
    }
}
