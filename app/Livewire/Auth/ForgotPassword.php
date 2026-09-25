<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Quên mật khẩu')]
class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $status = null;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    public function sendResetLink(): void
    {
        $this->validate();

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->status = 'Nếu email tồn tại trong hệ thống, liên kết đặt lại mật khẩu đã được gửi.';
            $this->email = '';

            return;
        }

        $this->addError('email', 'Không thể gửi liên kết đặt lại mật khẩu. Vui lòng thử lại.');
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
