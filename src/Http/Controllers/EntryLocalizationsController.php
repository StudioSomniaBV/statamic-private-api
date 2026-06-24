<?php

namespace Tv2regionerne\StatamicPrivateApi\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Http\Resources\API\EntryResource;
use Tv2regionerne\StatamicPrivateApi\Traits\VerifiesPrivateAPI;

class EntryLocalizationsController
{
    use VerifiesPrivateAPI;

    /**
     * GET /collections/{collection}/entries/{entry}/localizations
     * List which sites this entry already exists in.
     */
    public function index($collection, $entry)
    {
        [$collection, $origin] = $this->resolve($collection, $entry);

        $root = $origin->origin() ?? $origin;

        $sites = collect([$root])
            ->merge($root->descendants())
            ->map(fn ($e) => [
                'site' => $e->locale(),
                'id' => $e->id(),
                'published' => $e->published(),
                'is_origin' => $e->id() === $root->id(),
            ])->values();

        return response()->json(['data' => $sites]);
    }

    /**
     * POST /collections/{collection}/entries/{entry}/localizations
     * Body: { "site": "pt", "values": { ... }, "published": true }
     */
    public function store(Request $request, $collection, $entry)
    {
        [$collection, $origin] = $this->resolve($collection, $entry);

        $site = $this->siteOrFail($request->input('site'));
        $values = (array) $request->input('values', []);

        $root = $origin->origin() ?? $origin;

        $localized = $root->in($site->handle());

        if (! $localized) {
            $localized = $root->makeLocalization($site);
        }

        if (! empty($values)) {
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
        [$collection, $origin] = $this->resolve($collection, $entry);

        $site = $this->siteOrFail($site);
        $root = $origin->origin() ?? $origin;

        abort_if($site->handle() === $root->locale(), 422, 'Refusing to delete the origin localization.');

        $localized = $root->in($site->handle());
        abort_unless($localized, 404, 'No localization for that site.');

        $localized->delete();

        return response('', 204);
    }

    private function resolve($collection, $entry): array
    {
        $collection = Collection::find($collection);
        abort_unless($collection, 404);
        abort_unless($this->resourcesAllowed('collections', $collection->handle()), 404);

        $entry = Entry::find($entry);
        abort_unless($entry, 404);
        abort_if($entry->collectionHandle() !== $collection->handle(), 404);

        return [$collection, $entry];
    }

    private function siteOrFail($handle)
    {
        abort_unless($handle, 422, 'A `site` handle is required. Call GET /sites to list them.');

        $site = Site::get($handle);
        abort_unless($site, 422, "Unknown site handle: {$handle}");

        return $site;
    }
}
