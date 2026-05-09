<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', \App\Livewire\Pages\Auth\ForgotPassword::class)->name('password.request');
});

Route::get('dashboard', function () {
    $user = auth()->user();

    if ($user->hasRole('super_admin')) {
        return redirect()->route('super-admin.dashboard');
    }

    if ($user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->hasRole('admin_koperasi')) {
        return redirect()->route('koperasi.dashboard');
    }

    if ($user->hasRole('kasir')) {
        return redirect()->route('koperasi.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/email/verify', \App\Livewire\Pages\Auth\VerifyEmail::class)->name('verification.notice');
    Route::post('/verify-otp', [\App\Http\Controllers\Api\Auth\OtpVerificationController::class, 'verify'])->name('verification.otp');

    // Dashboards - Statistics
    Route::livewire('super-admin/dashboard', 'pages::dashboard.super-admin-stat')->name('super-admin.dashboard');
    Route::livewire('admin/dashboard', 'pages::dashboard.admin-stat')->name('admin.dashboard');

    // Dashboards - Financials
    Route::livewire('super-admin/financial', 'pages::dashboard.super-admin')->name('super-admin.financial');
    Route::livewire('admin/financial', 'pages::dashboard.admin')->name('admin.financial');

    Route::livewire('users', 'pages::users.index')->name('users');
    Route::livewire('schools', 'pages::schools.index')->name('schools.index');
    Route::livewire('roles', 'pages::roles.index')->name('roles.index');
    Route::livewire('payment-methods', 'pages::payment-methods.index')->name('payment-methods.index');
    Route::livewire('guardians', 'pages::guardians.index')->name('guardians.index');
    Route::livewire('santri/import', 'pages::students.import')->name('students.import');
    Route::livewire('santri/kenaikan-kelas', 'pages::students.promotion')->name('students.promotion');
    Route::livewire('santri', 'pages::students.index')->name('students.index');
    Route::livewire('jenjang', 'pages::departments.index')->name('departments.index');
    Route::livewire('kelas', 'pages::levels.index')->name('levels.index');
    Route::livewire('tahun-ajaran', 'pages::academic-years.index')->name('academic-years.index');
    Route::livewire('semester', 'pages::semesters.index')->name('semesters.index');
    Route::livewire('transaksi-dompet', 'pages::wallet-transactions.index')->name('wallet-transactions.index');
    Route::livewire('tagihan', 'pages::bills.index')->name('bills.index');
    Route::livewire('kategori-tagihan', 'pages::bill-categories.index')->name('bill-categories.index');
    Route::livewire('agenda-kegiatan', 'pages::events.index')->name('events.index');
    Route::livewire('tagihan-kegiatan', 'pages::event-bills.index')->name('event-bills.index');
    Route::livewire('kegiatan-harian', 'pages::activities.index')->name('activities.index');

    // Attendance (Event)
    Route::livewire('agenda-kegiatan/presensi', 'pages::events.attendance.index')->name('events.attendance.index');
    Route::livewire('agenda-kegiatan/{eventId}/presensi-input', 'pages::events.attendance.entry')->name('events.attendance.entry');
    Route::livewire('agenda-kegiatan/{eventId}/scan', 'pages::events.attendance.scan')->name('events.attendance.scan');
    Route::livewire('agenda-kegiatan/detail', 'pages::events.attendance.detail')->name('events.attendance.detail');

    // Attendance (Activity)
    Route::livewire('presensi-kegiatan', 'pages::activities.attendance.index')->name('activities.attendance.index');
    Route::livewire('presensi-kegiatan/catat', 'pages::activities.attendance.entry')->name('activities.attendance.entry');
    Route::livewire('presensi-kegiatan/scan', 'pages::activities.attendance.scan')->name('activities.attendance.scan');
    Route::livewire('presensi-kegiatan/detail', 'pages::activities.attendance.detail')->name('activities.attendance.detail');
    Route::get('presensi-kegiatan/print', [\App\Http\Controllers\AttendanceReportController::class, 'print'])->name('activities.attendance.print');

    // Achievements
    Route::livewire('jadwal-sowan', 'pages::sowan-schedules.index')->name('sowan-schedules.index');
    Route::livewire('perizinan', 'pages::student-permissions.index')->name('student-permissions.index');
    Route::livewire('kajian', 'pages::video-lectures.index')->name('lectures.index');
    Route::livewire('prestasi/create', 'pages::achievements.create')->name('achievements.create');
    Route::livewire('prestasi', 'pages::achievements.index')->name('achievements.index');

    // Student Reports
    Route::livewire('laporan-santri', 'pages::student-reports.index')->name('student-reports.index');
    Route::livewire('laporan-santri/create', 'pages::student-reports.create')->name('student-reports.create');
    Route::livewire('laporan-santri/{id}/edit', 'pages::student-reports.create')->name('student-reports.edit');

    // News
    Route::livewire('berita', 'pages::news.index')->name('news.index');

    // Feedback
    Route::livewire('feedback', 'pages::feedback.index')->name('feedback.index');

    // Student Cards
    Route::livewire('kartu-santri', 'pages::student-cards.index')->name('student-cards.index');
    Route::get('/student-cards/{id}/preview', [\App\Http\Controllers\StudentCardPrintController::class, 'preview'])->name('student-cards.preview');
    Route::post('/student-cards/download-zip', [\App\Http\Controllers\StudentCardPrintController::class, 'download'])->name('student-cards.download-zip');
    Route::get('/student-cards/download-file/{filename}', [App\Http\Controllers\StudentCardPrintController::class, 'downloadFile'])->name('student-cards.download-file');
    Route::livewire('desain-kartu-santri', 'pages::student-cards.design')->name('student-cards.design');
    Route::livewire('permintaan-cetak-kartu', 'pages::student-card-requests.index')->name('student-card-requests.index');

    // Withdrawals
    Route::livewire('dashboard/admin/penarikan-dana', 'pages::dashboard.admin.withdrawals')->name('dashboard.admin.withdrawals');
    Route::livewire('dashboard/admin/penarikan-uang-saku', 'pages::dashboard.admin.student-cash-withdrawal')->name('dashboard.admin.student-cash-withdrawal');
    Route::livewire('dashboard/super-admin/kelola-penarikan', 'pages::dashboard.super-admin.withdrawals')->name('dashboard.super-admin.withdrawals');

    // Cooperative
    Route::prefix('koperasi')->group(function () {
        Route::livewire('dashboard', 'pages::dashboard.cooperative-stat')->name('koperasi.dashboard');
        Route::livewire('pos', 'pages::dashboard.cooperative.pos')->name('koperasi.pos');
        Route::livewire('riwayat', 'pages::dashboard.cooperative.history')->name('koperasi.history');
        Route::livewire('produk', 'pages::dashboard.cooperative.products')->name('koperasi.products');
        Route::livewire('stok-opname', 'pages::dashboard.cooperative.stock-opname')->name('koperasi.stock-opname');
        Route::livewire('riwayat-stok', 'pages::dashboard.cooperative.stock-history')->name('koperasi.stock-history');
        Route::get('export', [\App\Http\Controllers\CooperativeReportController::class, 'export'])->name('cooperative.export');
        Route::get('print-struk/{invoiceCode}', [\App\Http\Controllers\CooperativeReportController::class, 'printReceipt'])->name('cooperative.print-receipt');
        Route::livewire('kasir', 'pages::dashboard.cooperative.cashiers')->name('koperasi.cashiers');
    });
});

require __DIR__.'/settings.php';
