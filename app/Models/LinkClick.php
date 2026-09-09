<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkClick extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'link_id',
        'ip',
        'user_agent',
        'referrer',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }
}
