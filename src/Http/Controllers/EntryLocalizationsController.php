<?php

namespace Tv2regionerne\StatamicPrivateApi\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades;
use Statamic\Http\Resources\API\EntryResource;
use Tv2regionerne\StatamicPrivateApi\Traits\VerifiesPrivateAPI;

/**
 * Read and write a single localization of an entry.
 *
 * Entry localizations are separate entries linked to an origin via origin().
 * makeLocalization() creates one when it doesn't exist yet.
 */
class EntryLocalizationsController extends ApiController
{
    use VerifiesPrivateAPI;

    /**
     * GET /collections/{collection}/entries/{entry}/localizations
     *
     * Lists which sites this entry exists in. Accepts the id of any
     * localization; resolves to the origin first.
     */
    public function index($collection, $entry)
    {
        $root = $this->root($collection, $entry);

        $this->authorize('view', $root);

        $sites = collect([$root])->merge($root->descendants())
            ->map(fn ($e) => [
                'site' => $e->locale(),
                'id' => $e->id(),
                'slug' => $e->slug(),
                'published' => $e->published(),
                'is_origin' => $e->id() === $root->id(),
            ])->values();

        return response()->json(['data' => $sites]);
    }

    /**
     * GET /collections/{collection}/entries/{entry}/localizations/{site}
     */
    public function show($collection, $entry, $site)
    {
        $root = $this->root($collection, $entry);
        $site = $this->siteFromHandle($site);

        $localized = $root->in($site->handle());

        abort_unless($localized, 404, 'No localization for that site.');

        $this->authorize('view', $localized);

        return app(EntryResource::class)::make($localized);
    }

    /**
     * PATCH /collections/{collection}/entries/{entry}/localizations/{site}
     *
     * Body: { "values": {...}, "published": true, "slug": "..." }
     *
     * Creates the localization when it doesn't exist yet, otherwise merges
     * into it. Only the keys present in `values` are touched; anything else
     * keeps falling back to the origin.
     */
    public function update(Request $request, $collection, $entry, $site)
    {
        $root = $this->root($collection, $entry);
        $site = $this->siteFromHandle($site);

        $this->authorize('edit', $root);

        $localized = $root->in($site->handle()) ?? $root->makeLocalization($site);

        if ($values = (array) $request->input('values', [])) {
            $localized->merge($values);
        }

        if ($request->has('published')) {
            $localized->published($request->boolean('published'));
        }

        if ($request->filled('slug')) {
            $localized->slug($request->input('slug'));
        }

        $localized->save();

        return app(EntryResource::class)::make($localized->fresh());
    }

    /**
     * Resolve the collection + entry, and return the origin entry.
     */
    private function root($collectionHandle, $entryId)
    {
        $collection = Facades\Collection::find($collectionHandle);

        abort_unless($collection, 404);
        abort_if(! $this->resourcesAllowed('collections', $collection->handle()), 404);

        $entry = Facades\Entry::find($entryId);

        abort_unless($entry, 404);
        abort_if($entry->collectionHandle() !== $collection->handle(), 404);

        return $entry->origin() ?? $entry;
    }

    private function siteFromHandle($handle)
    {
        abort_unless($handle, 422, 'A `site` handle is required. See GET /sites.');

        $site = Facades\Site::get($handle);

        abort_unless($site, 422, "Unknown site handle: {$handle}");

        return $site;
    }
}
