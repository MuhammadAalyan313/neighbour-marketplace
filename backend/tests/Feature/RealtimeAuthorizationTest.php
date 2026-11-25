<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

 
beforeEach(function () {
    /*
    |--------------------------------------------------------------------------
    | Configure Actual Broadcasting Driver For Tests
    |--------------------------------------------------------------------------
    */

    config()->set('broadcasting.default', 'reverb');

    config()->set('broadcasting.connections.reverb', [
        'driver' => 'reverb',
        'key' => 'test-reverb-key',
        'secret' => 'test-reverb-secret',
        'app_id' => 'test-reverb-app',
        'options' => [
            'host' => '127.0.0.1',
            'port' => 8080,
            'scheme' => 'http',
            'useTLS' => false,
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Reset Broadcaster And Register Channel Authorization
    |--------------------------------------------------------------------------
    */

    app(\Illuminate\Broadcasting\BroadcastManager::class)
        ->forgetDrivers();

    // IMPORTANT:
    // Reload channel callbacks on the newly configured
    // Reverb broadcaster, otherwise valid users get 403.
    require base_path('routes/channels.php');

    /*
    |--------------------------------------------------------------------------
    | Sellers
    |--------------------------------------------------------------------------
    */

    $this->sellerA = User::create([
        'name' => 'Realtime Seller A',
        'email' => 'realtime-seller-a@example.test',
        'phone' => '03001112221',
        'password' => 'Password123!',
        'role' => UserRole::SELLER,
        'is_active' => true,
    ]);

    $this->sellerB = User::create([
        'name' => 'Realtime Seller B',
        'email' => 'realtime-seller-b@example.test',
        'phone' => '03001112222',
        'password' => 'Password123!',
        'role' => UserRole::SELLER,
        'is_active' => true,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Buyers
    |--------------------------------------------------------------------------
    */

    $this->buyerA = User::create([
        'name' => 'Realtime Buyer A',
        'email' => 'realtime-buyer-a@example.test',
        'phone' => '03001112223',
        'password' => 'Password123!',
        'role' => UserRole::BUYER,
        'is_active' => true,
    ]);

    $this->buyerB = User::create([
        'name' => 'Realtime Buyer B',
        'email' => 'realtime-buyer-b@example.test',
        'phone' => '03001112224',
        'password' => 'Password123!',
        'role' => UserRole::BUYER,
        'is_active' => true,
    ]);
});


function authorizeRealtimeChannel(
    $test,
    string $channel
) {
    return $test->postJson('/api/broadcasting/auth', [
        'socket_id' => '12345.67890',
        'channel_name' => 'private-' . $channel,
    ]);
}

it('allows sellers to authorize their own private channel', function () {
    Sanctum::actingAs($this->sellerA);

    authorizeRealtimeChannel(
        $this,
        'seller.' . $this->sellerA->id
    )
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('prevents sellers from accessing another sellers channel', function () {
    Sanctum::actingAs($this->sellerA);

    authorizeRealtimeChannel(
        $this,
        'seller.' . $this->sellerB->id
    )->assertForbidden();
});

it('allows buyers to authorize their own private channel', function () {
    Sanctum::actingAs($this->buyerA);

    authorizeRealtimeChannel(
        $this,
        'buyer.' . $this->buyerA->id
    )
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('prevents buyers from accessing another buyers channel', function () {
    Sanctum::actingAs($this->buyerA);

    authorizeRealtimeChannel(
        $this,
        'buyer.' . $this->buyerB->id
    )->assertForbidden();
});

it('prevents buyers from subscribing to seller channels', function () {
    Sanctum::actingAs($this->buyerA);

    authorizeRealtimeChannel(
        $this,
        'seller.' . $this->sellerA->id
    )->assertForbidden();
});

it('prevents sellers from subscribing to buyer channels', function () {
    Sanctum::actingAs($this->sellerA);

    authorizeRealtimeChannel(
        $this,
        'buyer.' . $this->buyerA->id
    )->assertForbidden();
});

it('prevents inactive sellers from accessing their own channel', function () {
    $this->sellerA->update([
        'is_active' => false,
    ]);

    Sanctum::actingAs($this->sellerA);

    authorizeRealtimeChannel(
        $this,
        'seller.' . $this->sellerA->id
    )->assertForbidden();
});

it('prevents inactive buyers from accessing their own channel', function () {
    $this->buyerA->update([
        'is_active' => false,
    ]);

    Sanctum::actingAs($this->buyerA);

    authorizeRealtimeChannel(
        $this,
        'buyer.' . $this->buyerA->id
    )->assertForbidden();
});

it('rejects unauthenticated private channel requests', function () {
    authorizeRealtimeChannel(
        $this,
        'seller.' . $this->sellerA->id
    )->assertUnauthorized();
});
