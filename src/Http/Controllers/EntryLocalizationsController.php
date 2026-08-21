<?php

namespace Tv2regionerne\StatamicPrivateApi\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Http\Resources\API\EntryResource;
use Tv2regionerne\StatamicPrivateApi\Traits\VerifiesPrivateAPI;

/**
 * Read and write a single localization of an entry.
 *
 * Entry localizations are separate entries linked to an origin via origin().
 * makeLocalization() creates one when it doesn't exist yet.
 */
class EntryLocalizationsController
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
        $site = $this->siteOrFail($site);

        $localized = $root->in($site->handle());

        abort_unless($localized, 404, 'No localization for that site.');

        return app(EntryResource::class)::make($localized);
    }

    /**
     * POST /collections/{collection}/entries/{entry}/localizations
     *
     * Body: { "site": "pl", "values": {...}, "published": true, "slug": "..." }
     *
     * Creates the localization when it doesn't exist, otherwise merges into it.
     * Only the keys present in `values` are touched; everything else keeps
     * falling back to the origin.
     */
    public function store(Request $request, $collection, $entry)
    {
        $root = $this->root($collection, $entry);
        $site = $this->siteOrFail($request->input('site'));

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
     * DELETE /collections/{collection}/entries/{entry}/localizations/{site}
     */
    public function destroy($collection, $entry, $site)
    {
        $root = $this->root($collection, $entry);
        $site = $this->siteOrFail($site);

        abort_if(
            $site->handle() === $root->locale(),
            422,
            'Refusing to delete the origin localization.'
        );

        $localized = $root->in($site->handle());

        abort_unless($localized, 404, 'No localization for that site.');

        $localized->delete();

        return response('', 204);
    }

    /**
     * Resolve the collection + entry, and return the origin entry.
     */
    private function root($collectionHandle, $entryId)
    {
        $collection = Collection::find($collectionHandle);

        abort_unless($collection, 404);
        abort_unless($this->resourcesAllowed('collections', $collection->handle()), 404);

        $entry = Entry::find($entryId);

        abort_unless($entry, 404);
        abort_if($entry->collectionHandle() !== $collection->handle(), 404);

        return $entry->origin() ?? $entry;
    }

    private function siteOrFail($handle)
    {
        abort_unless($handle, 422, 'A `site` handle is required. See GET /sites.');

        $site = Site::get($handle);

        abort_unless($site, 422, "Unknown site handle: {$handle}");

        return $site;
    }
}
