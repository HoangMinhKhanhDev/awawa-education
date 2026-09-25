<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\Subject;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Trang chủ')]
class Dashboard extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        $context = app(SubjectContext::class);

        return view('livewire.dashboard', [
            'user' => $user,
            'currentSubject' => $context->subject(),
            'subjectCount' => $user->isSuperAdmin() ? Subject::query()->count() : null,
            'roleCounts' => $user->isSuperAdmin() ? [
                'teacher' => User::query()->where('role', Role::Teacher->value)->count(),
                'student' => User::query()->where('role', Role::Student->value)->count(),
                'subject' => Subject::query()->count(),
            ] : null,
        ]);
    }
}
