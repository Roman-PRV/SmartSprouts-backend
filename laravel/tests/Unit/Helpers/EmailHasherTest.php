<?php

namespace Tests\Unit\Helpers;

use App\Helpers\EmailHasher;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EmailHasherTest extends TestCase
{
    /**
     * The literal formula is written out once, here, on purpose. Everywhere
     * else calls the helper, so this test is the independent statement of what
     * the value must be — a second copy in production code would drift without
     * failing, and this one fails the moment it does.
     */
    public function test_the_hash_is_the_keyed_hmac_of_the_lowercased_address(): void
    {
        $this->assertSame(
            hash_hmac('sha256', 'someone@example.com', 'testing-legal-hash-key'),
            EmailHasher::of('someone@example.com'),
        );
    }

    /**
     * Case folding is the property everything else rests on: a person types
     * their address back with different capitalisation than they registered
     * with, and the purchase or consent row still has to be found. Losing the
     * fold breaks nothing loudly — the lookup simply returns nothing.
     */
    public function test_capitalisation_does_not_change_the_hash(): void
    {
        $this->assertSame(
            EmailHasher::of('someone@example.com'),
            EmailHasher::of('SoMeOne@Example.COM'),
        );
    }

    /**
     * Proves the key is actually part of the formula. Without this, dropping it
     * for a bare digest would still satisfy the two assertions above — and turn
     * the pseudonym into something a dictionary of addresses can reverse.
     */
    public function test_a_different_key_produces_a_different_hash(): void
    {
        $withDefaultKey = EmailHasher::of('someone@example.com');

        Config::set('legal.email_hash_key', 'another-key');

        $this->assertNotSame($withDefaultKey, EmailHasher::of('someone@example.com'));
    }
}
