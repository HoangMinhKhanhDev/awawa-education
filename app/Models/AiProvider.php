<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'label',
        'base_url',
        'api_key',
        'default_model',
        'is_enabled',
        'is_default',
        'config',
        'order',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'config' => 'array',
            'order' => 'integer',
        ];
    }

    public function hasCredentials(): bool
    {
        return filled($this->api_key) && filled($this->base_url);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true)->orderBy('order');
    }
}
