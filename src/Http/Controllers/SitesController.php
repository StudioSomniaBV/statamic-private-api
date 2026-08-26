<?php

namespace Tv2regionerne\StatamicPrivateApi\Http\Controllers;

use Statamic\Facades;

class SitesController extends ApiController
{
    /**
     * GET /sites
     *
     * Lists every configured site, so clients can discover the handles to use
     * as the `{site}` parameter on the localization endpoints.
     */
    public function index()
    {
        $default = Facades\Site::default()->handle();

        return response()->json([
            'data' => Facades\Site::all()->map(fn ($site) => [
                'handle' => $site->handle(),
                'name' => $site->name(),
                'locale' => $site->locale(),
                'url' => $site->url(),
                'default' => $site->handle() === $default,
            ])->values(),
        ]);
    }
}
