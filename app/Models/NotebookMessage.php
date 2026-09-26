<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotebookMessage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notebook_id',
        'user_id',
        'role',
        'content',
        'citations',
        'provider_key',
        'model',
        'tokens',
        'is_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'tokens' => 'integer',
            'is_error' => 'boolean',
        ];
    }

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tách câu trả lời thành các phần: văn bản và marker trích dẫn [n].
     *
     * @return array<int, array{type: string, value?: string, index?: int}>
     */
    public function segments(): array
    {
        $parts = preg_split('/(\[\d+\])/', (string) $this->content, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        $segments = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\[(\d+)\]$/', $part, $matches)) {
                $segments[] = ['type' => 'citation', 'index' => (int) $matches[1]];
            } else {
                $segments[] = ['type' => 'text', 'value' => $part];
            }
        }

        return $segments;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function citationList(): array
    {
        return array_values($this->citations ?? []);
    }
}
