<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
}
