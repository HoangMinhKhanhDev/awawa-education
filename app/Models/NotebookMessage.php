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
        'source_ids',
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
            'source_ids' => 'array',
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
     * @return array<int, array<string, mixed>>
     */
    public function citationList(): array
    {
        return array_values($this->citations ?? []);
    }
}
