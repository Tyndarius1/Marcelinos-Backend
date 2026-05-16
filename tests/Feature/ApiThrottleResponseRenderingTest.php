<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Named rate limiters with Limit::response() throw HttpResponseException; the embedded response must surface
 * (429) instead of being mis-handled as HTTP 500 by the global API exception renderer.
 */
final class ApiThrottleResponseRenderingTest extends TestCase
{
    #[Test]
    public function named_limiter_throttle_returns_429_json(): void
    {
        $payload = ['message' => 'test throttle fixture'];

        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/client-errors', $payload)->assertOk();
        }

        $this->postJson('/api/client-errors', $payload)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests. Please slow down and try again later.');
    }
}
