<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotebookArtifact extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notebook_id',
        'subject_id',
        'user_id',
        'type',
        'title',
        'payload',
        'text_content',
        'status',
        'ref_type',
        'ref_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ref(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isGenerating(): bool
    {
        return $this->status === 'generating';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function failedReason(): ?string
    {
        if (! $this->isFailed()) {
            return null;
        }

        $reason = $this->payload['_error'] ?? null;

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : 'AI không tạo được nội dung.';
    }
}
