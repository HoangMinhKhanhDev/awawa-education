<?php

namespace App\Models;

use App\Enums\SubjectFeature as FeatureEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SubjectFeature extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject_id',
        'feature',
        'is_enabled',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature' => FeatureEnum::class,
            'is_enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    protected static function booted(): void
    {
        $forget = function (SubjectFeature $feature): void {
            Cache::forget('subject:'.$feature->subject_id.':features');
        };

        static::saved($forget);
        static::deleted($forget);
    }
}
