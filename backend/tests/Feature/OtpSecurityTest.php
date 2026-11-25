
<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::create([
        'name' => 'OTP Test Buyer',
        'email' => 'otp-buyer@example.test',
        'phone' => '03006666666',
        'password' => 'OldPassword123!',
        'role' => UserRole::BUYER,
        'is_active' => true,
        'house_number' => 'House 20',
    ]);

    $this->otpService = app(EmailOtpService::class);
});

/*
|--------------------------------------------------------------------------
| Test 1: OTP is generated and stored as a hash
|--------------------------------------------------------------------------
*/

it('generates a six digit email OTP and stores only its hash', function () {
    $code = null;

    Mail::shouldReceive('raw')
        ->once()
        ->andReturnUsing(
            function ($message, $callback) use (&$code) {
                preg_match(
                    '/code is:\s*(\d{6})/',
                    $message,
                    $matches
                );

                $code = $matches[1] ?? null;
            }
        );

    $this->otpService->send($this->user);

    expect($code)->not->toBeNull();
    expect(strlen($code))->toBe(6);

    $record = DB::table('verification_codes')
        ->where('identifier', $this->user->email)
        ->where('purpose', 'email_verification')
        ->first();

    expect($record)->not->toBeNull();

    expect($record->code)->not->toBe($code);

    expect(Hash::check($code, $record->code))
        ->toBeTrue();

    expect((int) $record->attempts)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Test 2: Correct OTP verifies email and consumes the code
|--------------------------------------------------------------------------
*/

it('verifies email using the correct OTP and consumes the code', function () {
    $code = null;

    Mail::shouldReceive('raw')
        ->once()
        ->andReturnUsing(
            function ($message, $callback) use (&$code) {
                preg_match(
                    '/code is:\s*(\d{6})/',
                    $message,
                    $matches
                );

                $code = $matches[1] ?? null;
            }
        );

    $this->otpService->send($this->user);

    expect($code)->not->toBeNull();

    $verified = $this->otpService->verify(
        $this->user,
        $code
    );

    expect($verified)->toBeTrue();

    expect($this->user->fresh()->email_verified_at)
        ->not->toBeNull();

    $this->assertDatabaseMissing('verification_codes', [
        'identifier' => $this->user->email,
        'purpose' => 'email_verification',
    ]);
});

/*
|--------------------------------------------------------------------------
| Test 3: Expired OTP is rejected
|--------------------------------------------------------------------------
*/

it('rejects an expired email verification OTP', function () {
    $code = null;

    Mail::shouldReceive('raw')
        ->once()
        ->andReturnUsing(
            function ($message, $callback) use (&$code) {
                preg_match(
                    '/code is:\s*(\d{6})/',
                    $message,
                    $matches
                );

                $code = $matches[1] ?? null;
            }
        );

    $this->otpService->send($this->user);

    DB::table('verification_codes')
        ->where('identifier', $this->user->email)
        ->where('purpose', 'email_verification')
        ->update([
            'expires_at' => now()->subMinute(),
        ]);

    $verified = $this->otpService->verify(
        $this->user,
        $code
    );

    expect($verified)->toBeFalse();

    expect($this->user->fresh()->email_verified_at)
        ->toBeNull();

    $this->assertDatabaseMissing('verification_codes', [
        'identifier' => $this->user->email,
        'purpose' => 'email_verification',
    ]);
});

/*
|--------------------------------------------------------------------------
| Test 4: Wrong OTP attempts are limited
|--------------------------------------------------------------------------
*/

it('locks an OTP after five incorrect attempts', function () {
    Mail::shouldReceive('raw')->once();

    $this->otpService->send($this->user);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        expect(
            $this->otpService->verify(
                $this->user,
                '000000'
            )
        )->toBeFalse();
    }

    $record = DB::table('verification_codes')
        ->where('identifier', $this->user->email)
        ->where('purpose', 'email_verification')
        ->first();

    expect($record)->not->toBeNull();
    expect((int) $record->attempts)->toBe(5);

    // Next verification removes the locked code.
    expect(
        $this->otpService->verify(
            $this->user,
            '000000'
        )
    )->toBeFalse();

    $this->assertDatabaseMissing('verification_codes', [
        'identifier' => $this->user->email,
        'purpose' => 'email_verification',
    ]);

    expect($this->user->fresh()->email_verified_at)
        ->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Test 5: OTP resend cooldown
|--------------------------------------------------------------------------
*/

it('rejects requesting another OTP within sixty seconds', function () {
    Mail::shouldReceive('raw')->once();

    $this->otpService->send($this->user);

    expect(
        fn () => $this->otpService->send($this->user)
    )->toThrow(ValidationException::class);

    expect(
        DB::table('verification_codes')
            ->where('identifier', $this->user->email)
            ->where('purpose', 'email_verification')
            ->count()
    )->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Test 6: Password reset OTP changes password and revokes sessions
|--------------------------------------------------------------------------
*/

it('resets password with a valid OTP and revokes existing API tokens', function () {
    $oldPassword = 'OldPassword123!';
    $newPassword = 'NewPassword456!';

    $this->user->createToken('old-device');

    expect($this->user->tokens()->count())->toBe(1);

    $code = null;

    Mail::shouldReceive('raw')
        ->once()
        ->andReturnUsing(
            function ($message, $callback) use (&$code) {
                preg_match(
                    '/code is:\s*(\d{6})/',
                    $message,
                    $matches
                );

                $code = $matches[1] ?? null;
            }
        );

    $this->otpService->sendPasswordReset(
        $this->user
    );

    expect($code)->not->toBeNull();

    $reset = $this->otpService->resetPassword(
        $this->user,
        $code,
        $newPassword
    );

    expect($reset)->toBeTrue();

    $freshUser = $this->user->fresh();

    expect(
        Hash::check($newPassword, $freshUser->password)
    )->toBeTrue();

    expect(
        Hash::check($oldPassword, $freshUser->password)
    )->toBeFalse();

    expect($freshUser->tokens()->count())->toBe(0);

    $this->assertDatabaseMissing('verification_codes', [
        'identifier' => $this->user->email,
        'purpose' => 'password_reset',
    ]);

    // A consumed reset code cannot reset the password again.
    expect(
        $this->otpService->resetPassword(
            $freshUser,
            $code,
            'AnotherPassword789!'
        )
    )->toBeFalse();
});
