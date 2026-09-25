<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_push_subscription(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), [
                'endpoint' => 'https://push.example.com/abc',
                'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
                'contentEncoding' => 'aesgcm',
            ])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
            'public_key' => 'public-key',
            'auth_token' => 'auth-token',
        ]);
    }

    public function test_user_can_remove_push_subscription(): void
    {
        $user = User::factory()->student()->create();

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.com/abc',
        ]);

        $this->actingAs($user)
            ->postJson(route('push.unsubscribe'), ['endpoint' => 'https://push.example.com/abc'])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.com/abc']);
    }

    public function test_guest_cannot_register_push_subscription(): void
    {
        $this->post(route('push.subscribe'), [])->assertRedirect(route('login'));
    }
}
