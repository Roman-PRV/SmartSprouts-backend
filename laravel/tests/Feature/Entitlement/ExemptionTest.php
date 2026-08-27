<?php

namespace Tests\Feature\Entitlement;

use App\Enums\Entitlement\ExemptionReasonEnum;
use App\Enums\Entitlement\TierEnum;
use App\Models\Billing\Subscription;
use App\Models\Entitlement\AccessExemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExemptionTest extends TestCase
{
    use RefreshDatabase;

    // ─── Access ──────────────────────────────────────────────────────────────

    /** @test */
    public function a_guest_cannot_reach_the_exemption_endpoints(): void
    {
        $this->getJson('/api/admin/exemptions')->assertUnauthorized();
        $this->postJson('/api/admin/exemptions', [])->assertUnauthorized();
    }

    /** @test */
    public function an_ordinary_user_cannot_grant_themselves_an_exemption(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/exemptions', [
                'user_id' => $user->id,
                'reason' => ExemptionReasonEnum::TESTER->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('access_exemptions', 0);
    }

    // ─── Granting ────────────────────────────────────────────────────────────

    /** @test */
    public function an_admin_grants_an_exemption(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/exemptions', [
                'user_id' => $user->id,
                'reason' => ExemptionReasonEnum::STAFF->value,
                'note' => 'Content team',
            ])
            ->assertCreated()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('reason', 'staff')
            ->assertJsonPath('granted_by.id', $admin->id);

        $this->assertDatabaseHas('access_exemptions', [
            'user_id' => $user->id,
            'reason' => 'staff',
            'note' => 'Content team',
            'granted_by' => $admin->id,
        ]);
    }

    /** @test */
    public function re_granting_replaces_the_reason_instead_of_adding_a_row(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        AccessExemption::factory()->for($user)->create(['reason' => ExemptionReasonEnum::TESTER]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/exemptions', [
                'user_id' => $user->id,
                'reason' => ExemptionReasonEnum::STAFF->value,
            ])
            ->assertCreated();

        $this->assertDatabaseCount('access_exemptions', 1);
        $this->assertDatabaseHas('access_exemptions', [
            'user_id' => $user->id,
            'reason' => 'staff',
            'granted_by' => $admin->id,
        ]);
    }

    /**
     * The row carries the grant in force, not a history of grants: a restatement
     * replaces every column, so the note and date of the previous decision go.
     *
     * @test
     */
    public function re_granting_overwrites_the_note_and_the_date(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        AccessExemption::factory()->for($user)->create([
            'note' => 'Beta group, invited in June',
            'granted_at' => now()->subMonths(2),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/exemptions', [
                'user_id' => $user->id,
                'reason' => ExemptionReasonEnum::STAFF->value,
            ])
            ->assertCreated()
            ->assertJsonPath('note', null);

        $exemption = AccessExemption::query()->where('user_id', $user->id)->sole();

        $this->assertNull($exemption->note);
        $this->assertTrue($exemption->granted_at->isToday());
    }

    /** FR-008e. @test */
    public function an_exemption_is_refused_while_a_subscription_still_grants_a_tier(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/exemptions', [
                'user_id' => $user->id,
                'reason' => ExemptionReasonEnum::TESTER->value,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_type', 'SUBSCRIPTION_STILL_GRANTS_TIER');

        $this->assertDatabaseCount('access_exemptions', 0);
    }

    /** @test */
    public function cancelling_and_past_due_subscriptions_block_a_grant_too(): void
    {
        $admin = User::factory()->admin()->create();

        $cancelling = User::factory()->create();
        Subscription::factory()->for($cancelling)->cancelling()->create();

        $pastDue = User::factory()->create();
        Subscription::factory()->for($pastDue)->pastDue()->create();

        foreach ([$cancelling, $pastDue] as $user) {
            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/admin/exemptions', [
                    'user_id' => $user->id,
                    'reason' => ExemptionReasonEnum::TESTER->value,
                ])
                ->assertStatus(409);
        }

        $this->assertDatabaseCount('access_exemptions', 0);
    }

    /** @test */
    public function an_ended_subscription_does_not_block_a_grant(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Subscription::factory()->for($user)->ended()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/exemptions', [
                'user_id' => $user->id,
                'reason' => ExemptionReasonEnum::TESTER->value,
            ])
            ->assertCreated();

        $this->assertDatabaseCount('access_exemptions', 1);
    }

    /** Handled by the global ConvertEmptyStringsToNull middleware. @test */
    public function a_blank_note_is_stored_as_null(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/exemptions', [
                'user_id' => $user->id,
                'reason' => ExemptionReasonEnum::TESTER->value,
                'note' => '',
            ])
            ->assertCreated()
            ->assertJsonPath('note', null);

        $this->assertDatabaseHas('access_exemptions', [
            'user_id' => $user->id,
            'note' => null,
        ]);
    }

    /** @test */
    public function granting_validates_its_input(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/exemptions', [
                'user_id' => 999999,
                'reason' => 'vip',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'reason']);
    }

    // ─── Listing ─────────────────────────────────────────────────────────────

    /** @test */
    public function a_paid_unlimited_account_does_not_appear_in_the_listing(): void
    {
        $admin = User::factory()->admin()->create();

        $exempt = User::factory()->create();
        AccessExemption::factory()->for($exempt)->create();

        $customer = User::factory()->create();
        Subscription::factory()->for($customer)->create(['tier' => TierEnum::UNLIMITED]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/exemptions')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.user.id', $exempt->id);
    }

    /** FR-008b: who holds it, why, and who decided. @test */
    public function the_listing_answers_the_audit_questions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        AccessExemption::factory()->for($user)->withoutGrantor()->create([
            'reason' => ExemptionReasonEnum::TESTER,
            'note' => 'Beta group',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/exemptions')
            ->assertOk()
            ->assertJsonPath('0.user.email', $user->email)
            ->assertJsonPath('0.reason', 'tester')
            ->assertJsonPath('0.note', 'Beta group')
            ->assertJsonPath('0.granted_by', null);
    }

    // ─── Revoking ────────────────────────────────────────────────────────────

    /** @test */
    public function an_admin_revokes_an_exemption(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        AccessExemption::factory()->for($user)->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/exemptions/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('access_exemptions', 0);
    }

    /** @test */
    public function revoking_an_account_that_holds_no_exemption_is_a_404(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/exemptions/{$user->id}")
            ->assertNotFound();
    }

    /** @test */
    public function an_ordinary_user_cannot_revoke(): void
    {
        $user = User::factory()->create();
        $holder = User::factory()->create();
        AccessExemption::factory()->for($holder)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/admin/exemptions/{$holder->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('access_exemptions', 1);
    }
}
