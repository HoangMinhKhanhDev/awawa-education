<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Đổi mật khẩu')]
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (Auth::user() === null) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function updatePassword(): void
    {
        $key = 'password-change:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('current_password', 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau 1 phút.');

            return;
        }

        $this->validate();

        $user = Auth::user();

        if (! Hash::check($this->current_password, $user->password)) {
            RateLimiter::hit($key, 60);
            $this->addError('current_password', 'Mật khẩu hiện tại không đúng.');

            return;
        }

        RateLimiter::clear($key);

        $user->forceFill([
            'password' => $this->password,
            'must_change_password' => false,
        ])->save();

        Auth::logoutOtherDevices($this->password);
        session()->regenerate();

        session()->flash('status', 'Đã cập nhật mật khẩu thành công.');

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.change-password');
    }
}
