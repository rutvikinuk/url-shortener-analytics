<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Rules\ValidSlug;
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
            'destination_url' => ['required', 'url:http,https', 'max:2048'],
            'slug' => ['nullable', new ValidSlug, 'unique:links,slug'],
        ]);

        $link = Link::create([
            'destination_url' => $validated['destination_url'],
            'slug' => $validated['slug'] ?? Link::generateUniqueSlug(),
        ]);

        return response()->json($link, 201);
    }

    /**
     * Log the click and redirect (also reachable as /{slug}).
     */
    public function show(Request $request, string $slug)
    {
        $link = Link::where('slug', Str::lower($slug))->firstOrFail();

        $link->recordClick($request);

        return redirect()->away($link->destination_url);
    }
}
