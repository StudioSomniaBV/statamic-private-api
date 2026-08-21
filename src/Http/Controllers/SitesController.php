<?php

namespace Tv2regionerne\StatamicPrivateApi\Http\Controllers;

use Statamic\Facades\Site;

class SitesController
{
    /**
     * GET /sites
     *
     * Lists every configured site, so clients can discover the handles to use
     * as the `site` parameter on the localization endpoints.
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
