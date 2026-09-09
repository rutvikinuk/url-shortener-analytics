<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LinkController extends Controller
{
    /**
     * List all links.
     */
    public function index()
    {
        return Link::latest()->paginate(20);
    }

    /**
     * Create a new short link.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'destination_url' => ['required', 'url', 'max:2048'],
            'slug' => ['nullable', 'alpha_dash', 'max:32', Rule::unique('links', 'slug')],
        ]);

        $link = Link::create([
            'destination_url' => $validated['destination_url'],
            'slug' => $validated['slug'] ?? $this->generateUniqueSlug(),
        ]);

        return response()->json($link, 201);
    }

    /**
     * Show a link by slug and log the click before redirecting.
     */
    public function show(Request $request, string $slug)
    {
        $link = Link::where('slug', $slug)->firstOrFail();

        $link->clicks()->create([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
        ]);

        $link->increment('clicks_count');

        return redirect()->away($link->destination_url);
    }

    /**
     * Generate a unique random slug for a new link.
     */
    private function generateUniqueSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(6));
        } while (Link::where('slug', $slug)->exists());

        return $slug;
    }
}
