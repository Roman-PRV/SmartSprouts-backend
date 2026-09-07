<?php

namespace Tests\Feature\Documentation;

use App\Enums\Billing\SubscriptionStatusEnum;
use App\Enums\Entitlement\TierEnum;
use OpenApi\Generator;
use Tests\TestCase;

class SwaggerEntitlementEnumTest extends TestCase
{
    /**
     * Tier and subscription-status values are hand-written `@OA` literals
     * (swagger-php cannot dereference a backing enum) in three places at once.
     * This guard fails loudly the moment any of them drifts from its enum —
     * SubscriptionStatusEnum's own docblock already announces a future
     * `paused` status the provider will send.
     */
    public function test_documented_entitlement_enums_match_the_enums(): void
    {
        $spec = json_decode(Generator::scan([app_path()])->toJson(), true);
        $schemas = $spec['components']['schemas'];

        $cases = [
            'Entitlement.tier' => [TierEnum::class, $schemas['Entitlement']['properties']['tier']['enum']],
            'Entitlement.Tier.key' => [TierEnum::class, $schemas['Entitlement.Tier']['properties']['key']['enum']],
            'Entitlement.Subscription.status' => [SubscriptionStatusEnum::class, $schemas['Entitlement.Subscription']['properties']['status']['enum']],
        ];

        foreach ($cases as $where => [$enumClass, $documented]) {
            $expected = array_column($enumClass::cases(), 'value');
            sort($expected);
            sort($documented);

            $this->assertSame($expected, $documented, "{$where} drifted from ".class_basename($enumClass).'.');
        }
    }
}
