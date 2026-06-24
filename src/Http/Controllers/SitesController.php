<?php

namespace Tv2regionerne\StatamicPrivateApi\Http\Controllers;

use Statamic\Facades\Site;

class SitesController
{
    /**
     * GET /sites
     * Returns every configured site so you can discover the handles to use
     * as the `site` parameter when creating localizations.
     */
    public function index()
    {
        return response()->json([
            'data' => Site::all()->map(fn ($site) => [
                'handle' => $site->handle(),
                'name' => $site->name(),
                'locale' => $site->locale(),
                'url' => $site->url(),
                'default' => $site->handle() === Site::default()->handle(),
            ])->values(),
        ]);
    }
}
