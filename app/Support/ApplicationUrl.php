<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ApplicationUrl
{
    /**
     * Keep generated asset/Livewire URLs on the same host as the browser request.
     */
    public static function syncFromRequest(?Request $request = null): void
    {
        $request ??= request();

        if ($request === null) {
            return;
        }

        $host = strtolower($request->getHost());

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return;
        }

        $root = $request->getSchemeAndHttpHost();

        URL::forceRootUrl($root);

        if ($request->isSecure()) {
            URL::forceScheme('https');
        }

        config([
            'app.url' => $root,
            'app.asset_url' => null,
        ]);
    }
}
