<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotebookArtifactRefine extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_DISMISSED = 'dismissed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'artifact_id',
        'user_id',
        'instruction',
        'summary',
        'proposal',
        'note',
        'status',
        'provider_key',
        'model',
        'tokens',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposal' => 'array',
            'tokens' => 'integer',
        ];
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(NotebookArtifact::class, 'artifact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }
}
