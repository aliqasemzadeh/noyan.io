<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'journal_line_id',
    'level',
    'gl_detail_id',
])]
class JournalLineDetail extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class);
    }

    public function glDetail(): BelongsTo
    {
        return $this->belongsTo(GlDetail::class);
    }
}
