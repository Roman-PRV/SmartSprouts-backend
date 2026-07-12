<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalControllerTest extends TestCase
{
    /** @test */
    public function guest_can_fetch_legal_versions(): void
    {
        $this->getJson('/api/legal/versions')->assertOk();
    }

    /** @test */
    public function response_returns_the_configured_versions(): void
    {
        config([
            'legal.terms_version' => '2030-01-01',
            'legal.privacy_version' => '2030-02-02',
        ]);

        $this->getJson('/api/legal/versions')
            ->assertOk()
            ->assertExactJson([
                'terms_version' => '2030-01-01',
                'privacy_version' => '2030-02-02',
            ]);
    }

    /** @test */
    public function response_matches_the_committed_config_values(): void
    {
        $this->getJson('/api/legal/versions')
            ->assertOk()
            ->assertExactJson([
                'terms_version' => config('legal.terms_version'),
                'privacy_version' => config('legal.privacy_version'),
            ]);
    }
}
