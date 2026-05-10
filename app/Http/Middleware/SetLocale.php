<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $supportedLocales = config('localization.supported_locales', ['en']);
        $aliases = config('localization.aliases', []);
        $queryParameter = (string) config('localization.query_parameter', 'locale');
        $header = (string) config('localization.header', 'X-Locale');

        $candidates = array_filter([
            $request->query($queryParameter),
            $request->header($header),
            $this->preferredLanguageFromHeader($request),
        ]);

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim((string) $candidate));
            $locale = $aliases[$normalized] ?? null;

            if (is_string($locale) && in_array($locale, $supportedLocales, true)) {
                return $locale;
            }
        }

        return (string) config('localization.default_locale', config('app.locale', 'en'));
    }

    private function preferredLanguageFromHeader(Request $request): ?string
    {
        $header = $request->header('Accept-Language');

        if (! is_string($header) || trim($header) === '') {
            return null;
        }

        $primary = explode(',', $header)[0] ?? null;

        if (! is_string($primary)) {
            return null;
        }

        return explode(';', trim($primary))[0] ?: null;
    }
}
