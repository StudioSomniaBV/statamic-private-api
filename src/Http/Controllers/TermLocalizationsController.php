<?php

namespace Tv2regionerne\StatamicPrivateApi\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Statamic\Facades;
use Statamic\Http\Controllers\CP\Taxonomies\TermsController as CpController;
use Statamic\Http\Resources\API\TermResource;
use Tv2regionerne\StatamicPrivateApi\Traits\VerifiesPrivateAPI;

/**
 * Read and write a single localization of a taxonomy term.
 *
 * Terms behave differently from entries: Term::in($site) always returns a
 * LocalizedTerm, even for a site that has no data yet, and all localizations
 * live in one file keyed per locale. There is no makeLocalization() step.
 *
 * Writes are delegated to the CP controller, which already accepts the site as
 * a parameter, so blueprint validation, processing and authorization all apply.
 * This mirrors TaxonomyTermsController, which passes Site::current() where this
 * controller passes the requested site.
 */
class TermLocalizationsController extends ApiController
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

        // Term::find() returns a LocalizedTerm; dataForLocale() and
        // defaultLocale() live on the underlying Term, so go through term().
        $sites = $taxonomy->sites()->map(fn ($handle) => [
            'site' => $handle,
            'has_content' => $term->term()->dataForLocale($handle)->isNotEmpty(),
            'is_origin' => $handle === $term->term()->defaultLocale(),
        ])->values();

        return response()->json(['data' => $sites]);
    }

    /**
     * GET /taxonomies/{taxonomy}/terms/{slug}/localizations/{site}
     */
    public function show($taxonomy, $slug, $site)
    {
        [$taxonomy, $term] = $this->resolve($taxonomy, $slug);
        $site = $this->siteFromHandle($site, $taxonomy);

        return app(TermResource::class)::make($term->in($site->handle()));
    }

    /**
     * PATCH /taxonomies/{taxonomy}/terms/{slug}/localizations/{site}
     *
     * The payload is merged over the current values for that site before it
     * goes through the CP controller, so a partial payload only changes the
     * keys it contains.
     */
    public function update(Request $request, $taxonomy, $slug, $site)
    {
        [$taxonomy, $term] = $this->resolve($taxonomy, $slug);
        $site = $this->siteFromHandle($site, $taxonomy);

        $localized = $term->in($site->handle());
        $payloadKeys = collect($request->except(['id', '_localized']))->keys();

        try {
            $data = json_decode($this->show($taxonomy->handle(), $slug, $site->handle())->toJson(), true);

            $request->merge(collect($data)->merge($request->all())->all());

            // The CP controller sets slug and published unconditionally from
            // the request, so fall back to the current values when the payload
            // doesn't provide them.
            if (! $request->filled('slug')) {
                $request->merge(['slug' => $localized->slug()]);
            }

            if (! $request->has('published')) {
                $request->merge(['published' => $localized->published()]);
            }

            // The CP controller replaces a localization's data with only the
            // keys listed in `_localized`, so it must contain the existing
            // overrides for this site plus the keys in the payload — otherwise
            // fields would be reset to the origin value.
            $request->merge(['_localized' => $term->term()->dataForLocale($site->handle())->keys()
                ->merge($payloadKeys)
                ->unique()->values()->all()]);

            (new CpController($request))->update($request, $taxonomy, $term, $site);

            return app(TermResource::class)::make($term->in($site->handle()));
        } catch (ValidationException $e) {
            return $this->returnValidationErrors($e);
        }
    }

    private function resolve($taxonomyHandle, $slug): array
    {
        $taxonomy = Facades\Taxonomy::find($taxonomyHandle);

        if (! $taxonomy) {
            abort(404);
        }

        abort_if(! $this->resourcesAllowed('taxonomies', $taxonomy->handle()), 404);

        $term = Facades\Term::find($taxonomy->handle().'::'.$slug);

        if (! $term || $term->taxonomy()->handle() !== $taxonomy->handle()) {
            abort(404);
        }

        return [$taxonomy, $term];
    }

    /**
     * A site can exist globally while a taxonomy is only enabled for a subset
     * of sites, so check against the taxonomy rather than Site::all().
     */
    private function siteFromHandle($handle, $taxonomy)
    {
        $site = Facades\Site::get($handle);

        abort_unless($site, 422, "Unknown site handle: {$handle}");

        abort_if(
            ! $taxonomy->sites()->contains($site->handle()),
            422,
            "Taxonomy {$taxonomy->handle()} is not enabled for site {$site->handle()}."
        );

        return $site;
    }
}
