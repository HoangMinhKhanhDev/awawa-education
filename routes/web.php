<?php

use App\Http\Controllers\Admin\ApiKeysPageController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PushSubscriptionController;
use App\Livewire\Admin\Stats as AdminStats;
use App\Livewire\Admin\Subjects\Index as AdminSubjects;
use App\Livewire\Admin\Users\Index as AdminUsers;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Dashboard;
use App\Livewire\Info;
use App\Livewire\Maps\Editor as MapEditor;
use App\Livewire\Maps\Index as MapsIndex;
use App\Livewire\Maps\Shared as MapsShared;
use App\Livewire\Notifications\Index as NotificationsIndex;
use App\Livewire\Profile\Show as ProfileShow;
use App\Livewire\Student\Result as StudentResult;
use App\Livewire\Student\Take as StudentTake;
use App\Livewire\Teacher\AiGenerate;
use App\Livewire\Teacher\AnnouncementsIndex;
use App\Livewire\Teacher\AssessmentBuilder;
use App\Livewire\Teacher\AssignmentsIndex;
use App\Livewire\Teacher\DocumentsIndex;
use App\Livewire\Teacher\ExamsIndex;
use App\Livewire\Teacher\GradingIndex;
use App\Livewire\Teacher\QuestionsIndex;
use App\Livewire\Teacher\StudentsIndex;
use App\Livewire\Teacher\Studio;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
|--------------------------------------------------------------------------
| Khách (chưa đăng nhập)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');

Route::get('/so-do/xem/{token}', MapsShared::class)->name('maps.shared');

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Đã đăng nhập
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/password/change', ChangePassword::class)->name('password.change');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/so-do', MapsIndex::class)
        ->middleware('feature:knowledge_map')->name('map');

    Route::get('/so-do/{map}/sua', MapEditor::class)
        ->middleware('feature:knowledge_map')->name('maps.edit');

    Route::get('/thong-tin', Info::class)->name('info');

    Route::get('/thong-bao', NotificationsIndex::class)->name('notifications.index');

    Route::get('/ho-so', ProfileShow::class)->name('profile');

    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    /*
    |----------------------------------------------------------------------
    | Học sinh (chỉ thành viên đội tuyển)
    |----------------------------------------------------------------------
    */
    Route::middleware(['role:student', 'team'])->group(function () {
        Route::get('/bai-thi/{exam}/lam', StudentTake::class)->name('student.take');
        Route::get('/bai-thi/{exam}/ket-qua', StudentResult::class)->name('student.result');
    });

    /*
    |----------------------------------------------------------------------
    | Giáo viên
    |----------------------------------------------------------------------
    */
    Route::middleware('role:teacher')->group(function () {
        Route::get('/studio', Studio::class)->name('studio');

        Route::get('/studio/ngan-hang-cau-hoi', QuestionsIndex::class)
            ->middleware('feature:question_bank')->name('studio.questions');

        Route::get('/studio/de-thi', ExamsIndex::class)
            ->middleware('feature:exams')->name('studio.exams');

        Route::get('/studio/bai-tap', AssignmentsIndex::class)
            ->middleware('feature:assignments')->name('studio.assignments');

        Route::get('/studio/tai-lieu', DocumentsIndex::class)
            ->middleware('feature:documents')->name('studio.documents');

        Route::get('/studio/thong-bao', AnnouncementsIndex::class)
            ->middleware('feature:announcements')->name('studio.announcements');

        Route::get('/studio/ai', AiGenerate::class)
            ->middleware('feature:ai_tools')->name('studio.ai');

        Route::get('/studio/{exam}/soan', AssessmentBuilder::class)->name('studio.builder');

        Route::get('/studio/{exam}/bai-lam', GradingIndex::class)->name('studio.grading');

        Route::get('/quan-ly-hoc-sinh', StudentsIndex::class)->name('students');
    });

    /*
    |----------------------------------------------------------------------
    | Quản trị viên
    |----------------------------------------------------------------------
    */
    Route::middleware('role:super_admin')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/nguoi-dung', AdminUsers::class)->name('users');

        Route::get('/mon-hoc', AdminSubjects::class)->name('subjects');

        Route::get('/api-key', ApiKeysPageController::class)->name('api-keys');

        Route::get('/thong-ke', AdminStats::class)->name('stats');
    });
});
