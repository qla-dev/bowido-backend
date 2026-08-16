<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

$frontendDist = static function () {
    $frontendPath = (string) config('frontend.path');
    $isAbsolutePath = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\\\\\\\\|\/)/', $frontendPath) === 1;
    $resolvedFrontendPath = realpath($isAbsolutePath ? $frontendPath : base_path($frontendPath));

    return $resolvedFrontendPath === false
        ? false
        : realpath($resolvedFrontendPath.DIRECTORY_SEPARATOR.'dist');
};

$serveFrontendFile = static function (string $relativePath) use ($frontendDist) {
    $frontendDist = $frontendDist();

    abort_if($frontendDist === false, 404, 'Frontend build not found. Run npm run build in the frontend directory.');

    $normalizedRelativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    $filePath = realpath($frontendDist.DIRECTORY_SEPARATOR.$normalizedRelativePath);

    abort_if(
        $filePath === false
        || ($filePath !== $frontendDist && ! str_starts_with($filePath, $frontendDist.DIRECTORY_SEPARATOR))
        || ! is_file($filePath),
        404,
    );

    $response = response()->file($filePath);

    // The HTML document is the pointer to Vite's fingerprinted JS/CSS files.
    // It must be revalidated on a normal browser reload; otherwise a phone can
    // keep serving an older document that still references a previous bundle
    // until the user performs a hard refresh. Fingerprinted assets themselves
    // remain safe to cache because their names change for every build.
    if ($relativePath === 'index.html') {
        return $response->header('Cache-Control', 'no-cache, must-revalidate');
    }

    if (str_starts_with(str_replace('\\', '/', $relativePath), 'assets/')) {
        return $response->header('Cache-Control', 'public, max-age=31536000, immutable');
    }

    return $response;
};

$proxyFrontendDevServer = static function (Request $request) {
    $devServerUrl = rtrim((string) config('frontend.dev_server_url'), '/');
    $requestPath = ltrim($request->path(), '/');
    $targetUrl = $devServerUrl.($requestPath === '' ? '/' : '/'.$requestPath);

    if ($request->getQueryString() !== null) {
        $targetUrl .= '?'.$request->getQueryString();
    }

    try {
        $frontendResponse = Http::timeout(10)
            ->withHeaders([
                'Accept' => $request->header('Accept', '*/*'),
            ])
            ->send($request->method(), $targetUrl, [
                'body' => $request->getContent(),
            ]);
    } catch (Throwable) {
        abort(502, 'Frontend dev server is not available yet. Wait a moment and refresh, or run npm run dev in the frontend directory.');
    }

    $headers = [];

    foreach ($frontendResponse->headers() as $name => $value) {
        if (in_array(strtolower($name), ['content-type', 'cache-control', 'etag'], true)) {
            $headers[$name] = is_array($value) ? implode(', ', $value) : $value;
        }
    }

    return response($frontendResponse->body(), $frontendResponse->status())->withHeaders($headers);
};

$serveFrontendDevServerIndex = static function (Request $request) {
    $devServerUrl = rtrim((string) config('frontend.dev_server_url'), '/');
    $requestPath = ltrim($request->path(), '/');
    $targetUrl = $devServerUrl.($requestPath === '' ? '/' : '/'.$requestPath);

    if ($request->getQueryString() !== null) {
        $targetUrl .= '?'.$request->getQueryString();
    }

    try {
        $frontendResponse = Http::timeout(10)
            ->withHeaders([
                'Accept' => 'text/html',
            ])
            ->get($targetUrl);
    } catch (Throwable) {
        abort(502, 'Frontend dev server is not available yet. Wait a moment and refresh, or run npm run dev in the frontend directory.');
    }

    $body = str_replace(
        [
            'from "/@react-refresh"',
            'src="/@vite/client"',
            'src="/src/',
            'href="/src/',
        ],
        [
            'from "'.$devServerUrl.'/@react-refresh"',
            'src="'.$devServerUrl.'/@vite/client"',
            'src="'.$devServerUrl.'/src/',
            'href="'.$devServerUrl.'/src/',
        ],
        $frontendResponse->body()
    );

    return response($body, $frontendResponse->status())
        ->header('Content-Type', 'text/html; charset=utf-8');
};

Route::any('/{path?}', function (Request $request, ?string $path = null) use ($proxyFrontendDevServer, $serveFrontendDevServerIndex, $serveFrontendFile) {
    if (config('frontend.dev_server_enabled')) {
        if (
            $path !== null
            && (
                str_starts_with($path, '@vite/')
                || str_starts_with($path, '@react-refresh')
                || str_starts_with($path, 'src/')
                || str_starts_with($path, 'node_modules/')
                || str_starts_with($path, '__vite_ping')
            )
        ) {
            return $proxyFrontendDevServer($request);
        }

        return $serveFrontendDevServerIndex($request);
    }

    if ($path !== null && $path !== '') {
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $frontendDist = $frontendDist();
        $requestedFile = $frontendDist === false ? false : realpath($frontendDist.DIRECTORY_SEPARATOR.$normalizedPath);

        if ($requestedFile !== false && is_file($requestedFile)) {
            return $serveFrontendFile($path);
        }
    }

    return $serveFrontendFile('index.html');
})->where('path', '^(?!api(?:/|$)).*$');
