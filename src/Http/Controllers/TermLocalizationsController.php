<?php

namespace Tv2regionerne\StatamicPrivateApi\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Http\Resources\API\TermResource;
use Tv2regionerne\StatamicPrivateApi\Traits\VerifiesPrivateAPI;

/**
 * Read and write a single localization of a taxonomy term.
 *
 * Terms behave differently from entries:
 *
 *  - Term::in($site) always returns a LocalizedTerm, even for a site that has
 *    no data yet. It never returns null, so there is no "create" step and no
 *    null check to make.
 *  - makeLocalization() does not exist on Term or LocalizedTerm.
 *  - All localizations live in one file, keyed per locale in dataForLocale().
 *  - LocalizedTerm::delete() deletes the whole term file, not just that
 *    locale. destroy() below therefore clears the locale instead.
 */
class TermLocalizationsController
{
    use VerifiesPrivateAPI;

    /**
     * GET /taxonomies/{taxonomy}/terms/{slug}/localizations
     *
     * Lists the sites this taxonomy is enabled for, and whether each one
     * already has its own content.
     */
    public function index($taxonomy, $slug)
    {
        [$taxonomy, $term] = $this->resolve($taxonomy, $slug);

        $sites = $taxonomy->sites()->map(fn ($handle) => [
            'site' => $handle,
            'has_content' => $term->dataForLocale($handle)->isNotEmpty(),
            'is_origin' => $handle === $term->defaultLocale(),
        ])->values();

        return response()->json(['data' => $sites]);
    }

    /**
     * GET /taxonomies/{taxonomy}/terms/{slug}/localizations/{site}
     */
    public function show($taxonomy, $slug, $site)
    {
        [$taxonomy, $term] = $this->resolve($taxonomy, $slug);
        $site = $this->siteOrFail($site, $taxonomy);

        return app(TermResource::class)::make($term->in($site->handle()));
    }

    /**
     * POST /taxonomies/{taxonomy}/terms/{slug}/localizations
     *
     * Body: { "site": "pl", "values": {...}, "slug": "..." }
     *
     * merge() writes through to dataForLocale($site), so only that locale is
     * touched. Keys not present in `values` keep falling back to the origin.
     */
    public function store(Request $request, $taxonomy, $slug)
    {
        [$taxonomy, $term] = $this->resolve($taxonomy, $slug);
        $site = $this->siteOrFail($request->input('site'), $taxonomy);

        $localized = $term->in($site->handle());

        if ($values = (array) $request->input('values', [])) {
            $localized->merge($values);
        }

        if ($request->filled('slug')) {
            $localized->slug($request->input('slug'));
        }

        $localized->save();

        return app(TermResource::class)::make(
            $term->fresh()->in($site->handle())
        );
    }

    /**
     * DELETE /taxonomies/{taxonomy}/terms/{slug}/localizations/{site}
     *
     * Clears the localized data for one site. Does NOT delete the term file —
     * LocalizedTerm::delete() would remove every language at once.
     */
    public function destroy($taxonomy, $slug, $site)
    {
        [$taxonomy, $term] = $this->resolve($taxonomy, $slug);
        $site = $this->siteOrFail($site, $taxonomy);

        abort_if(
            $site->handle() === $term->defaultLocale(),
            422,
            'Refusing to clear the origin localization.'
        );

        $term->dataForLocale($site->handle(), collect());
        $term->save();

        return response('', 204);
    }

    /**
     * Resolve the taxonomy + term.
     *
     * Accepts both the bare slug (hamburg) and the composite id from the index
     * endpoint (cities::hamburg), so clients can pass through whatever the list
     * gave them.
     */
    private function resolve($taxonomyHandle, $slug): array
    {
        $taxonomy = Taxonomy::find($taxonomyHandle);

        abort_unless($taxonomy, 404);
        abort_unless($this->resourcesAllowed('taxonomies', $taxonomy->handle()), 404);

        if (str_contains($slug, '::')) {
            $slug = explode('::', $slug, 2)[1];
        }

        $term = Term::find($taxonomy->handle().'::'.$slug);

        abort_unless($term, 404);

        return [$taxonomy, $term];
    }

    private function siteOrFail($handle, $taxonomy)
    {
        abort_unless($handle, 422, 'A `site` handle is required. See GET /sites.');

        $site = Site::get($handle);

        abort_unless($site, 422, "Unknown site handle: {$handle}");

        abort_unless(
            $taxonomy->sites()->contains($site->handle()),
            422,
            "Taxonomy {$taxonomy->handle()} is not enabled for site {$site->handle()}."
        );

        return $site;
    }
}
