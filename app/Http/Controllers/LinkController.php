<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Rules\SafeDestination;
use App\Rules\ValidSlug;
use App\Services\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        // Slugs are case-insensitive: normalise before validating uniqueness.
        if (is_string($request->input('slug'))) {
            $request->merge(['slug' => Str::lower($request->input('slug'))]);
        }

        $validated = $request->validate([
            'destination_url' => ['required', 'url:http,https', 'max:2048', new SafeDestination],
            'slug' => ['nullable', new ValidSlug, 'unique:links,slug'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $link = Link::create([
            'destination_url' => $validated['destination_url'],
            'slug' => $validated['slug'] ?? Link::generateUniqueSlug(),
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return response()->json($link, 201);
    }

    /**
     * Log the click and redirect (also reachable as /{slug}).
     */
    public function show(Request $request, string $slug)
    {
        $link = Link::where('slug', Str::lower($slug))->firstOrFail();

        // Expired links: 410 Gone, and the visit is NOT counted as a click.
        abort_if($link->isExpired(), 410, 'This link has expired.');

        $link->recordClick($request);

        return redirect()->away($link->destination_url);
    }

    /**
     * GET /api/links/{slug}/qr -> SVG QR code pointing at the short URL.
     */
    public function qr(string $slug)
    {
        $link = Link::where('slug', Str::lower($slug))->firstOrFail();
        abort_if($link->isExpired(), 410, 'This link has expired.');

        return response(QrCode::svg($link->shortUrl()), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
