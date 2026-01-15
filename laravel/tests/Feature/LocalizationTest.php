<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define a route for testing the locale setting
        Route::middleware('api')->get('/test-locale', function () {
            return response()->json(['locale' => app()->getLocale()]);
        });
    }

    public function test_locale_middleware_sets_correct_locale_from_accept_language_header()
    {
        // Route is defined in setUp()

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

        // Test Unsupported + Supported
        // We request 'fr' (unsupported) with higher quality (1.0) and 'es' (supported) with lower quality (0.5).
        // Standard Symfony negotiation ignores the unsupported 'fr' and picks the best *supported* match ('es').
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

        // Verify that validation error messages are properly translated based on the Accept-Language header,
        // using the Spanish ('es') localization for the 'required' rule on the 'missing field' attribute.

        // Spanish
        $response = $this->postJson('/test-validation-loc', [], ['Accept-Language' => 'es']);
        $response->assertStatus(422);

        // Verify that the error message contains the specific localized substring
        // We dynamically generate the expected message to make the test robust against copy changes
        $expectedMessage = trans('validation.required', ['attribute' => 'missing field'], 'es');

        $response->assertJsonFragment([
            'missing_field' => [$expectedMessage],
        ]);

        // Ukrainian
        $response = $this->postJson('/test-validation-loc', [], ['Accept-Language' => 'uk']);
        $response->assertStatus(422);

        $expectedMessage = trans('validation.required', ['attribute' => 'missing field'], 'uk');

        $response->assertJsonFragment([
            'missing_field' => [$expectedMessage],
        ]);

        // We do not check 'message' here because it depends on whether the exception is thrown
        // from a FormRequest (which we customized) or generic validate() (which uses default).
    }

    public function test_locale_middleware_handles_empty_header()
    {
        $response = $this->getJson('/test-locale', ['Accept-Language' => '']);
        $response->assertJson(['locale' => 'en']);
    }

    public function test_locale_middleware_handles_whitespace_header()
    {
        $response = $this->getJson('/test-locale', ['Accept-Language' => '   ']);
        $response->assertJson(['locale' => 'en']);
    }

    public function test_locale_middleware_handles_case_insensitivity()
    {
        $response = $this->getJson('/test-locale', ['Accept-Language' => 'EN']);
        $response->assertJson(['locale' => 'en']);
    }

    public function test_locale_middleware_handles_regional_variants_en_gb()
    {
        $response = $this->getJson('/test-locale', ['Accept-Language' => 'en-GB']);
        $response->assertJson(['locale' => 'en']);
    }

    public function test_locale_middleware_handles_regional_variants_es_mx()
    {
        $response = $this->getJson('/test-locale', ['Accept-Language' => 'es-MX']);
        $response->assertJson(['locale' => 'es']);
    }

    public function test_confirmed_rule_is_translated()
    {
        Route::middleware('api')->post('/test-validation-confirmed', function (Request $request) {
            $request->validate([
                'password' => 'confirmed',
            ]);
        });

        // Spanish
        $response = $this->postJson('/test-validation-confirmed', [
            'password' => 'secret',
            'password_confirmation' => 'wrong',
        ], ['Accept-Language' => 'es']);

        $response->assertStatus(422);

        // Retrieve the localized attribute name for 'password'
        $attribute = trans('validation.attributes.password', [], 'es');
        if ($attribute === 'validation.attributes.password') {
            $attribute = 'password';
        }

        $expectedMessage = trans('validation.confirmed', ['attribute' => $attribute], 'es');
        $response->assertJsonFragment(['password' => [$expectedMessage]]);

        // Ukrainian
        $response = $this->postJson('/test-validation-confirmed', [
            'password' => 'secret',
            'password_confirmation' => 'wrong',
        ], ['Accept-Language' => 'uk']);

        $response->assertStatus(422);

        $attribute = trans('validation.attributes.password', [], 'uk');
        if ($attribute === 'validation.attributes.password') {
            $attribute = 'password';
        }

        $expectedMessage = trans('validation.confirmed', ['attribute' => $attribute], 'uk');
        $response->assertJsonFragment(['password' => [$expectedMessage]]);
    }
}
