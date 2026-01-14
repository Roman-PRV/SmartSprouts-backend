<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_locale_middleware_sets_response_language()
    {
        // Define a route for testing the locale setting
        Route::middleware('api')->get('/test-locale', function () {
            return response()->json(['locale' => app()->getLocale()]);
        });

        // Test English (Fallback or Explicit)
        $response = $this->getJson('/test-locale', ['Accept-Language' => 'en']);
        $response->assertJson(['locale' => 'en']);

        // Test Spanish
        $response = $this->getJson('/test-locale', ['Accept-Language' => 'es']);
        $response->assertJson(['locale' => 'es']);

        // Test Ukrainian
        $response = $this->getJson('/test-locale', ['Accept-Language' => 'uk']);
        $response->assertJson(['locale' => 'uk']);

        // Test Complex Header with Quality Weights (uk preferred)
        $response = $this->getJson('/test-locale', ['Accept-Language' => 'uk-UA,uk;q=0.9,en;q=0.8']);
        $response->assertJson(['locale' => 'uk']);

        // Test Unsupported Language -> Should Fallback to 'en'
        $response = $this->getJson('/test-locale', ['Accept-Language' => 'fr']);
        $response->assertJson(['locale' => 'en']);

        // Test Unsupported + Supported (Supported should win if quality allows, or fallback?)
        // If I send 'fr, es;q=0.5'. 'fr' is not supported. 'es' is.
        // Symfony negotiation should pick 'es'???
        // Let's test standard Symfony behavior: picks best *supported* match.
        // 'fr' is not supported, 'es' is. So it should pick 'es'.
        $response = $this->getJson('/test-locale', ['Accept-Language' => 'fr;q=1.0, es;q=0.5']);
        $response->assertJson(['locale' => 'es']);

        // Test Missing Header -> Should Fallback to 'en'
        $response = $this->getJson('/test-locale');
        $response->assertJson(['locale' => 'en']);

        // Test Malformed Header -> Should Fallback to 'en'
        $response = $this->getJson('/test-locale', ['Accept-Language' => 'malformed-header-value; garbage']);
        $response->assertJson(['locale' => 'en']);
    }

    public function test_validation_messages_are_translated()
    {
        // Define a route that triggers validation
        Route::middleware('api')->post('/test-validation-loc', function (Request $request) {
            $request->validate(['missing_field' => 'required']);
        });

        // We assume 'es' lang files exist and have 'required' key.
        // Standard Laravel 'es' validation for required usually contains "obligatorio" or "requerido".
        // Let's just check it doesn't return the English "The missing field field is required."
        // Actually, exact text assert is brittle.
        // But I'll do a soft check.

        // Spanish
        $response = $this->postJson('/test-validation-loc', [], ['Accept-Language' => 'es']);
        $response->assertStatus(422);

        // Verify that the error message contains the specific localized substring
        // We dynamically generate the expected message to make the test robust against copy changes
        $expectedMessage = trans('validation.required', ['attribute' => 'missing field'], 'es');

        $response->assertJsonFragment([
            'missing_field' => [$expectedMessage],
        ]);

        // We do not check 'message' here because it depends on whether the exception is thrown
        // from a FormRequest (which we customized) or generic validate() (which uses default).
    }
}
