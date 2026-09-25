<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $exception) {
            Log::warning('Google OAuth thất bại', ['message' => $exception->getMessage()]);

            return redirect()->route('login')->withErrors([
                'email' => 'Không thể xác thực với Google. Vui lòng thử lại.',
            ]);
        }

        $email = mb_strtolower((string) $googleUser->getEmail());

        if ($email === '' || ! $this->isEmailAllowed($email)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Email này không nằm trong danh sách được phép truy cập.',
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'avatar' => $user->avatar ?: $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'last_login_at' => now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(40),
                'role' => Role::Student,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ]);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route('dashboard'));
    }

    protected function isEmailAllowed(string $email): bool
    {
        $allowedEmails = config('awawa.google.allowed_emails', []);
        $allowedDomains = config('awawa.google.allowed_domains', []);

        if ($allowedEmails === [] && $allowedDomains === []) {
            return true;
        }

        if (in_array($email, $allowedEmails, true)) {
            return true;
        }

        $domain = Str::after($email, '@');

        return in_array($domain, $allowedDomains, true);
    }
}
