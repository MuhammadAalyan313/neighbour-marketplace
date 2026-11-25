<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('registers a buyer and returns a sanctum bearer token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Test Buyer',
        'email' => 'buyer@example.test',
        'phone' => '03001234567',
        'password' => 'StrongPassword!123',
        'password_confirmation' => 'StrongPassword!123',
        'house_number' => 'A-12',
    ]);

    $response->assertCreated()->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer');
    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
    expect(User::where('email', 'buyer@example.test')->first()?->role)->toBe(UserRole::BUYER);
});

it('rejects inactive user login', function () {
    User::query()->create([
        'name' => 'Inactive', 'email' => 'inactive@example.test',
        'phone' => '03007654321', 'password' => Hash::make('TestPassword!123'),
        'role' => UserRole::BUYER, 'is_active' => false,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'inactive@example.test',
        'password' => 'TestPassword!123',
    ])->assertForbidden();
});

it('does not allow anonymous users to list API sessions', function () {
    $this->getJson('/api/v1/auth/sessions')->assertUnauthorized();
});
