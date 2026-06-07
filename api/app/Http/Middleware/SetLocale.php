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
        App::setLocale($this->preferredLocale($request));

        return $next($request);
    }

    private function preferredLocale(Request $request): string
    {
        $supportedLocales = config('app.supported_locales', ['sk']);
        $defaultLocale = config('app.fallback_locale', $supportedLocales[0] ?? 'sk');

        foreach ($request->getLanguages() as $language) {
            $locale = strtolower(str_replace('_', '-', $language));
            $primaryLocale = explode('-', $locale)[0];

            if (in_array($locale, $supportedLocales, true)) {
                return $locale;
            }

            if (in_array($primaryLocale, $supportedLocales, true)) {
                return $primaryLocale;
            }
        }

        return $defaultLocale;
    }
}
