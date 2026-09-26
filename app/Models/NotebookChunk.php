<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotebookChunk extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notebook_id',
        'source_id',
        'position',
        'content',
        'is_highlight',
        'char_start',
        'char_end',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_highlight' => 'boolean',
            'char_start' => 'integer',
            'char_end' => 'integer',
        ];
    }

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NotebookSource::class, 'source_id');
    }
}
