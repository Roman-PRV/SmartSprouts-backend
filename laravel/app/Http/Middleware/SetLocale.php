<?php

namespace App\Http\Middleware;

use App\Helpers\ConfigHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<string> $supportedLocales */
        $supportedLocales = (array) config('app.supported_locales', []);

        $fallbackLocale = ConfigHelper::getString('app.fallback_locale', 'en');

        $orderedLocales = array_unique(array_merge([$fallbackLocale], $supportedLocales));

        $locale = $request->getPreferredLanguage($orderedLocales);

        App::setLocale($locale ?? $fallbackLocale);

        return $next($request);
    }
}
