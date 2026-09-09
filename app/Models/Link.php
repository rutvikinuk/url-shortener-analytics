<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Link extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'destination_url',
        'clicks_count',
    ];

    protected $casts = [
        'clicks_count' => 'integer',
    ];

    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class);
    }
}
