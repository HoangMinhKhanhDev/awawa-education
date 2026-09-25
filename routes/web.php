<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return view('landing');
})->name('home');

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

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Đã đăng nhập
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/password/change', ChangePassword::class)->name('password.change');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/so-do', fn () => view('pages.placeholder', [
        'title' => 'Sơ đồ kiến thức',
        'phase' => 'P4',
    ]))->name('map');

    Route::get('/thong-tin', fn () => view('pages.placeholder', [
        'title' => 'Thông tin',
        'phase' => 'P3',
    ]))->name('info');

    Route::get('/ho-so', fn () => view('pages.placeholder', [
        'title' => 'Hồ sơ',
        'phase' => 'P1',
    ]))->name('profile');

    /*
    |----------------------------------------------------------------------
    | Giáo viên
    |----------------------------------------------------------------------
    */
    Route::middleware('role:teacher')->group(function () {
        Route::get('/studio', fn () => view('pages.placeholder', [
            'title' => 'Studio',
            'phase' => 'P2',
        ]))->name('studio');

        Route::get('/quan-ly-hoc-sinh', fn () => view('pages.placeholder', [
            'title' => 'Quản lý học sinh',
            'phase' => 'P2',
        ]))->name('students');
    });

    /*
    |----------------------------------------------------------------------
    | Quản trị viên
    |----------------------------------------------------------------------
    */
    Route::middleware('role:super_admin')->prefix('quan-tri')->name('admin.')->group(function () {
        Route::get('/nguoi-dung', fn () => view('pages.placeholder', [
            'title' => 'Quản lý người dùng',
            'phase' => 'P1',
        ]))->name('users');

        Route::get('/api-key', fn () => view('pages.placeholder', [
            'title' => 'Quản lý API key',
            'phase' => 'P1',
        ]))->name('api-keys');

        Route::get('/thong-ke', fn () => view('pages.placeholder', [
            'title' => 'Thống kê',
            'phase' => 'P5',
        ]))->name('stats');
    });
});
