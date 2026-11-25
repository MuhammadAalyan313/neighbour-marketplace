<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::create([
        'name' => 'API OTP Buyer',
        'email' => 'api-otp@example.test',
        'phone' => '03007777777',
        'password' => 'OldPassword123!',
        'role' => UserRole::BUYER,
        'is_active' => true,
        'house_number' => 'House 30',
    ]);
});

/*
|--------------------------------------------------------------------------
| Test 1: Email Verification Through Actual API
|--------------------------------------------------------------------------
*/

it('sends and verifies email OTP through authenticated endpoints', function () {
    Sanctum::actingAs($this->user);

    $code = null;

    Mail::shouldReceive('raw')
        ->once()
        ->andReturnUsing(
            function ($body, $callback) use (&$code) {
                preg_match(
                    '/code is:\s*(\d{6})/',
                    $body,
                    $matches
                );

                $code = $matches[1] ?? null;
            }
        );

    $this->postJson(
        '/api/v1/auth/email/send-otp'
    )
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($code)->not->toBeNull();

    $this->postJson(
        '/api/v1/auth/email/verify-otp',
        ['code' => $code]
    )
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($this->user->fresh()->email_verified_at)
        ->not->toBeNull();

    $this->assertDatabaseMissing('verification_codes', [
        'identifier' => $this->user->email,
        'purpose' => 'email_verification',
    ]);
});

/*
|--------------------------------------------------------------------------
| Test 2: Invalid OTP Is Rejected
|--------------------------------------------------------------------------
*/

it('rejects wrong verification OTP and keeps email unverified', function () {
    Sanctum::actingAs($this->user);

    Mail::shouldReceive('raw')->once();

    $this->postJson(
        '/api/v1/auth/email/send-otp'
    )->assertOk();

    $this->postJson(
        '/api/v1/auth/email/verify-otp',
        ['code' => '000000']
    )
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect($this->user->fresh()->email_verified_at)
        ->toBeNull();

    $record = DB::table('verification_codes')
        ->where('identifier', $this->user->email)
        ->where('purpose', 'email_verification')
        ->first();

    expect($record)->not->toBeNull();
    expect((int) $record->attempts)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Test 3: Expired OTP Cannot Verify Email
|--------------------------------------------------------------------------
*/

it('rejects expired email OTP through the API', function () {
    Sanctum::actingAs($this->user);

    Mail::shouldReceive('raw')->once();

    $this->postJson(
        '/api/v1/auth/email/send-otp'
    )->assertOk();

    DB::table('verification_codes')
        ->where('identifier', $this->user->email)
        ->where('purpose', 'email_verification')
        ->update([
            'expires_at' => now()->subMinute(),
        ]);

    $this->postJson(
        '/api/v1/auth/email/verify-otp',
        ['code' => '123456']
    )
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect($this->user->fresh()->email_verified_at)
        ->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Test 4: Forgot Password Does Not Reveal Account Existence
|--------------------------------------------------------------------------
*/

it('returns the same forgot-password response for known and unknown emails', function () {
    $code = null;

    Mail::shouldReceive('raw')
        ->once()
        ->andReturnUsing(
            function ($body, $callback) use (&$code) {
                preg_match(
                    '/code is:\s*(\d{6})/',
                    $body,
                    $matches
                );

                $code = $matches[1] ?? null;
            }
        );

    $known = $this->postJson(
        '/api/v1/auth/password/forgot',
        ['email' => $this->user->email]
    );

    $unknown = $this->postJson(
        '/api/v1/auth/password/forgot',
        ['email' => 'nonexistent@example.test']
    );

    $known
        ->assertOk()
        ->assertJsonPath('success', true);

    $unknown
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($known->json('message'))
        ->toBe($unknown->json('message'));

    expect($code)->not->toBeNull();

    expect(
        DB::table('verification_codes')
            ->where('identifier', 'nonexistent@example.test')
            ->count()
    )->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 5: Password Reset Revokes API Tokens
|--------------------------------------------------------------------------
*/

it('resets password using API OTP and revokes old device tokens', function () {
    $this->user->createToken('old-device');

    expect($this->user->tokens()->count())->toBe(1);

    $code = null;

    Mail::shouldReceive('raw')
        ->once()
        ->andReturnUsing(
            function ($body, $callback) use (&$code) {
                preg_match(
                    '/code is:\s*(\d{6})/',
                    $body,
                    $matches
                );

                $code = $matches[1] ?? null;
            }
        );

    $this->postJson(
        '/api/v1/auth/password/forgot',
        ['email' => $this->user->email]
    )->assertOk();

    expect($code)->not->toBeNull();

    $newPassword = 'NewPassword456!';

    $this->postJson(
        '/api/v1/auth/password/reset',
        [
            'email' => $this->user->email,
            'code' => $code,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]
    )
        ->assertOk()
        ->assertJsonPath('success', true);

    $freshUser = $this->user->fresh();

    expect(
        Hash::check($newPassword, $freshUser->password)
    )->toBeTrue();

    expect($freshUser->tokens()->count())->toBe(0);

    $this->assertDatabaseMissing('verification_codes', [
        'identifier' => $this->user->email,
        'purpose' => 'password_reset',
    ]);

    // The same OTP must not reset the password twice.
    $this->postJson(
        '/api/v1/auth/password/reset',
        [
            'email' => $this->user->email,
            'code' => $code,
            'password' => 'AnotherPassword789!',
            'password_confirmation' => 'AnotherPassword789!',
        ]
    )
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

/*
|--------------------------------------------------------------------------
| Test 6: Anonymous Visitors Cannot Request Verification OTP
|--------------------------------------------------------------------------
*/

it('blocks anonymous email verification requests', function () {
    $this->postJson(
        '/api/v1/auth/email/send-otp'
    )->assertUnauthorized();

    $this->postJson(
        '/api/v1/auth/email/verify-otp',
        ['code' => '123456']
    )->assertUnauthorized();
});
