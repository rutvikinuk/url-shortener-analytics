<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Link extends Model
{
    use HasFactory;

    /**
     * Slugs that would collide with real routes or look official.
     * Compared case-insensitively.
     */
    public const RESERVED_SLUGS = [
        'api', 'up', 'admin', 'login', 'logout', 'register', 'dashboard',
        'links', 'analytics', 'health', 'static', 'assets', 'storage', 'vendor',
    ];

    public const SLUG_PATTERN = '[A-Za-z0-9_-]{3,32}';

    protected $fillable = [
        'slug',
        'destination_url',
        'clicks_count',
        'expires_at',
    ];

    protected $casts = [
        'clicks_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class);
    }

    /** A link with no expires_at never expires. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function shortUrl(): string
    {
        return url('/'.$this->slug);
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(Str::lower($slug), self::RESERVED_SLUGS, true);
    }

    /**
     * Generate a random slug that is neither taken nor reserved.
     */
    public static function generateUniqueSlug(int $length = 6): string
    {
        do {
            $slug = Str::lower(Str::random($length));
        } while (self::isReserved($slug) || self::where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Log one visit and bump the denormalised counter atomically.
     */
    public function recordClick(Request $request): LinkClick
    {
        return DB::transaction(function () use ($request) {
            $click = $this->clicks()->create([
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'referrer' => Str::limit((string) $request->header('referer'), 255, '') ?: null,
            ]);

            $this->increment('clicks_count');

            return $click;
        });
    }
}
