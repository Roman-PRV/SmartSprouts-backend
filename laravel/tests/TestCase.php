<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use MockeryPHPUnitIntegration;

    /**
     * Fake the queue by default so model observers that dispatch jobs (TTS,
     * etc.) don't trigger synchronous job execution under QUEUE_CONNECTION=sync.
     * Tests that assert on dispatched jobs (Queue::assertPushed) work as-is
     * because Queue::fake() can be called repeatedly; tests that need real
     * queue behavior would have to opt out explicitly.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }
}
