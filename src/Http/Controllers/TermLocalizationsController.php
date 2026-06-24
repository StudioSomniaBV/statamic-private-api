<?php

namespace Tv2regionerne\StatamicPrivateApi\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Tv2regionerne\StatamicPrivateApi\Traits\VerifiesPrivateAPI;

class TermLocalizationsController
{
    use VerifiesPrivateAPI;

    /**
     * GET /taxonomies/{taxonomy}/terms/{slug}/localizations
     */
    public function index($taxonomy, $slug)
    {
        [$taxonomy, $term] = $this->resolve($taxonomy, $slug);

        $sites = $term->localizations()
            ->map(fn ($localized, $handle) => [
                'site' => $handle,
                'is_origin' => $handle === $term->defaultLocale(),
            ])->values();

        return response()->json(['data' => $sites]);
    }

    /**
     * POST /taxonomies/{taxonomy}/terms/{slug}/localizations
     * Body: { "site": "pt", "values": { ... } }
     */
    public function store(Request $request, $taxonomy, $slug)
    {
        [$taxonomy, $term] = $this->resolve($taxonomy, $slug);

        $site = $this->siteOrFail($request->input('site'));
        $values = (array) $request->input('values', []);

        $origin = $term->in($term->defaultLocale()) ?? $term;

        $localized = $origin->in($site->handle());

        if (! $localized) {
            $localized = $origin->makeLocalization($site);
        }

        if (! empty($values)) {
            $localized->merge($values);
        }

        $localized->save();

        return response()->json([
            'data' => [
                'slug' => $localized->slug(),
                'site' => $site->handle(),
                'data' => $localized->data()->all(),
            ],
        ]);
    }

    /**
     * DELETE /taxonomies/{taxonomy}/terms/{slug}/localizations/{site}
     */
    public function destroy($taxonomy, $slug, $site)
    {
        [$taxonomy, $term] = $this->resolve($taxonomy, $slug);

        $site = $this->siteOrFail($site);
        $origin = $term->in($term->defaultLocale()) ?? $term;

        abort_if($site->handle() === $origin->locale(), 422, 'Refusing to delete the origin localization.');

        $localized = $origin->in($site->handle());
        abort_unless($localized, 404, 'No localization for that site.');

        $localized->delete();

        return response('', 204);
    }

    private function resolve($taxonomy, $slug): array
    {
        $taxonomy = Taxonomy::find($taxonomy);
        abort_unless($taxonomy, 404);
        abort_unless($this->resourcesAllowed('taxonomies', $taxonomy->handle()), 404);

        $term = Term::find($taxonomy->handle().'::'.$slug);
        abort_unless($term, 404);

        return [$taxonomy, $term];
    }

    private function siteOrFail($handle)
    {
        abort_unless($handle, 422, 'A `site` handle is required. Call GET /sites to list them.');

        $site = Site::get($handle);
        abort_unless($site, 422, "Unknown site handle: {$handle}");

        return $site;
    }
}
