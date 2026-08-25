<?php

namespace Tests\Unit\Config;

use App\Helpers\ConfigHelper;
use Tests\TestCase;

class BillingConfigTest extends TestCase
{
    /**
     * `billing.provider` names a key in config/services.php, and nothing but
     * this test holds the two spellings together. A provider with no
     * credentials block reads as null rather than as an error, which is how a
     * deploy ends up with an empty API key and a webhook that silently never
     * verifies.
     */
    public function test_the_configured_provider_has_a_credentials_block(): void
    {
        $provider = ConfigHelper::getRequiredString('billing.provider');

        $this->assertIsArray(
            config("services.{$provider}"),
            "config/services.php declares no `{$provider}` block for the configured billing provider",
        );
    }
}
