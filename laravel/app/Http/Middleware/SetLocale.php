<?php

namespace App\Http\Middleware;

use App\Helpers\ConfigHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that sets the application locale based on the request's Accept-Language header.
 *
 * This middleware determines the locale by using Symfony's
 * {@see \Illuminate\Http\Request::getPreferredLanguage()} method to select
 * the best matching language from the configured supported locales.
 *
 * When no supported locale can be matched from the Accept-Language header,
 * the application will fall back to the configured fallback locale.
 *
 * The list of supported locales and the fallback locale are read from the
 * `app.supported_locales` and `app.fallback_locale` configuration values.
 */
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
		$supportedLocales = ConfigHelper::getStringList('app.supported_locales', []);

		$fallbackLocale = ConfigHelper::getString('app.fallback_locale', 'en');

		$orderedLocales = array_unique(array_merge([$fallbackLocale], $supportedLocales));

		$locale = $request->getPreferredLanguage($orderedLocales);

		App::setLocale($locale ?? $fallbackLocale);

		return $next($request);
	}
}
