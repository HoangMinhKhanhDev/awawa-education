<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'user_id',
        'provider_key',
        'model',
        'purpose',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_micros',
        'price_prompt_micros',
        'price_completion_micros',
        'cached_prompt_tokens',
        'cache_creation_tokens',
        'latency_ms',
        'is_success',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_success' => 'boolean',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost_micros' => 'integer',
            'price_prompt_micros' => 'integer',
            'price_completion_micros' => 'integer',
            'cached_prompt_tokens' => 'integer',
            'cache_creation_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    /**
     * Chi phí dạng USD, null khi chưa có giá của model.
     */
    public function costUsd(): ?float
    {
        return $this->cost_micros === null ? null : $this->cost_micros / 1000000;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
