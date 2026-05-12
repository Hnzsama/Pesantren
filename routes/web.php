<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', \App\Livewire\Pages\Auth\ForgotPassword::class)->name('password.request');
});

Route::get('dashboard', function () {
    $user = Auth::user();

    if ($user->hasRole('super_admin')) {
        return redirect()->route('super-admin.dashboard');
    }

    if ($user->hasRole('admin') || $user->hasRole('bendahara')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->hasRole('admin_koperasi')) {
        return redirect()->route('koperasi.dashboard');
    }

    if ($user->hasRole('kasir')) {
        return redirect()->route('koperasi.dashboard');
    }

    if ($user->hasRole('guru')) {
        return redirect()->route('guru.dashboard');
    }

    if ($user->hasRole('pengurus')) {
        return redirect()->route('activities.attendance.index');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/email/verify', \App\Livewire\Pages\Auth\VerifyEmail::class)->name('verification.notice');
    Route::post('/verify-otp', [\App\Http\Controllers\Api\Auth\OtpVerificationController::class, 'verify'])->name('verification.otp');

    // --- SUPER ADMIN ROUTES ---
    Route::middleware(['role:super_admin'])->group(function () {
        Route::livewire('super-admin/dashboard', 'pages::dashboard.super-admin-stat')->name('super-admin.dashboard');
        Route::livewire('super-admin/financial', 'pages::dashboard.super-admin')->name('super-admin.financial');
        Route::livewire('schools', 'pages::schools.index')->name('schools.index');
        Route::livewire('roles', 'pages::roles.index')->name('roles.index');
        Route::livewire('payment-methods', 'pages::payment-methods.index')->name('payment-methods.index');
        Route::livewire('permintaan-cetak-kartu', 'pages::student-card-requests.index')->name('student-card-requests.index');
        Route::livewire('desain-kartu-santri', 'pages::student-cards.design')->name('student-cards.design');
        Route::livewire('dashboard/super-admin/kelola-penarikan', 'pages::dashboard.super-admin.withdrawals')->name('dashboard.super-admin.withdrawals');
        Route::livewire('users/global', 'pages::users.index')->name('users.global');
    });

    // --- ADMIN & BENDAHARA SHARED ROUTES (FINANCIAL) ---
    Route::middleware(['role:admin|bendahara|super_admin'])->group(function () {
        Route::livewire('admin/financial', 'pages::dashboard.admin')->name('admin.financial');
        Route::livewire('dashboard/admin/topup-manual', 'pages::dashboard.admin.manual-topup')->name('dashboard.admin.manual-topup');
        Route::livewire('dashboard/admin/bayar-tagihan-manual', 'pages::dashboard.admin.manual-bill-payment')->name('dashboard.admin.manual-bill-payment');
        Route::livewire('transaksi-dompet', 'pages::wallet-transactions.index')->name('wallet-transactions.index');
        Route::livewire('tagihan', 'pages::bills.index')->name('bills.index');
        Route::livewire('kategori-tagihan', 'pages::bill-categories.index')->name('bill-categories.index');
        Route::livewire('tagihan-kegiatan', 'pages::event-bills.index')->name('event-bills.index');
        Route::livewire('dashboard/admin/penarikan-dana', 'pages::dashboard.admin.withdrawals')->name('dashboard.admin.withdrawals');
        Route::livewire('dashboard/admin/penarikan-uang-saku', 'pages::dashboard.admin.student-cash-withdrawal')->name('dashboard.admin.student-cash-withdrawal');
        
        Route::get('transaksi-dompet/export', [\App\Http\Controllers\WalletTransactionExportController::class, 'export'])->name('wallet-transactions.export');
        Route::get('transaksi-dompet/report', [\App\Http\Controllers\WalletTransactionExportController::class, 'report'])->name('wallet-transactions.report');
        Route::get('transaksi-dompet/print/{invoiceCode}', [\App\Http\Controllers\WalletTransactionPrintController::class, 'printReceipt'])->name('wallet-transactions.print');
    });

    // --- ADMIN ONLY ROUTES (ACADEMIC & MASTER) ---
    // --- ADMIN MASTER DATA (STRICT) ---
    Route::middleware(['role:admin|super_admin'])->group(function () {
        Route::livewire('santri', 'pages::students.index')->name('students.index');
        Route::livewire('santri/import', 'pages::students.import')->name('students.import');
        Route::livewire('users', 'pages::users.index')->name('users');
    });

    // --- ADMIN & GURU SHARED (ACADEMIC & STUDENT PROGRESS) ---
    Route::middleware(['role:admin|super_admin|guru'])->group(function () {
        Route::livewire('admin/dashboard', 'pages::dashboard.admin-stat')->name('admin.dashboard');
        Route::livewire('berita', 'pages::news.index')->name('news.index');
        Route::livewire('tahun-ajaran', 'pages::academic-years.index')->name('academic-years.index');
        Route::livewire('semester', 'pages::semesters.index')->name('semesters.index');
        Route::livewire('jenjang', 'pages::departments.index')->name('departments.index');
        Route::livewire('kelas', 'pages::levels.index')->name('levels.index');
        Route::livewire('kegiatan-harian', 'pages::activities.index')->name('activities.index');
        Route::livewire('agenda-kegiatan', 'pages::events.index')->name('events.index');
        Route::livewire('prestasi', 'pages::achievements.index')->name('achievements.index');
        Route::livewire('prestasi/create', 'pages::achievements.create')->name('achievements.create');
        Route::livewire('kajian', 'pages::video-lectures.index')->name('lectures.index');
        Route::livewire('santri/kenaikan-kelas', 'pages::students.promotion')->name('students.promotion');
        Route::livewire('guardians', 'pages::guardians.index')->name('guardians.index');
        Route::livewire('perizinan', 'pages::student-permissions.index')->name('student-permissions.index');
        Route::livewire('jadwal-sowan', 'pages::sowan-schedules.index')->name('sowan-schedules.index');
        Route::livewire('feedback', 'pages::feedback.index')->name('feedback.index');
        Route::livewire('kartu-santri', 'pages::student-cards.index')->name('student-cards.index');
        
        Route::get('/student-cards/{id}/preview', [\App\Http\Controllers\StudentCardPrintController::class, 'preview'])->name('student-cards.preview');
        Route::post('/student-cards/download-zip', [\App\Http\Controllers\StudentCardPrintController::class, 'download'])->name('student-cards.download-zip');
        Route::get('/student-cards/download-file/{filename}', [App\Http\Controllers\StudentCardPrintController::class, 'downloadFile'])->name('student-cards.download-file');
    });

    // --- GURU ROUTES ---
    Route::middleware(['role:guru|admin|super_admin'])->group(function () {
        Route::livewire('guru/dashboard', 'pages::dashboard.guru-stat')->name('guru.dashboard');
        Route::livewire('laporan-santri', 'pages::student-reports.index')->name('student-reports.index');
        Route::livewire('laporan-santri/create', 'pages::student-reports.create')->name('student-reports.create');
        Route::livewire('laporan-santri/{id}/edit', 'pages::student-reports.create')->name('student-reports.edit');
    });

    // --- PENGURUS & GURU SHARED (ATTENDANCE) ---
    Route::middleware(['role:pengurus|guru|admin|super_admin'])->group(function () {
        Route::livewire('presensi-kegiatan', 'pages::activities.attendance.index')->name('activities.attendance.index');
        Route::livewire('presensi-kegiatan/catat', 'pages::activities.attendance.entry')->name('activities.attendance.entry');
        Route::livewire('presensi-kegiatan/scan', 'pages::activities.attendance.scan')->name('activities.attendance.scan');
        Route::livewire('presensi-kegiatan/detail', 'pages::activities.attendance.detail')->name('activities.attendance.detail');
        Route::get('presensi-kegiatan/print', [\App\Http\Controllers\AttendanceReportController::class, 'print'])->name('activities.attendance.print');
        
        Route::livewire('agenda-kegiatan/presensi', 'pages::events.attendance.index')->name('events.attendance.index');
        Route::livewire('agenda-kegiatan/{eventId}/presensi-input', 'pages::events.attendance.entry')->name('events.attendance.entry');
        Route::livewire('agenda-kegiatan/{eventId}/scan', 'pages::events.attendance.scan')->name('events.attendance.scan');
        Route::livewire('agenda-kegiatan/detail', 'pages::events.attendance.detail')->name('events.attendance.detail');
    });

    // --- COOPERATIVE MANAGEMENT ---
    Route::middleware(['role:admin_koperasi|kasir|admin|super_admin'])->prefix('koperasi')->group(function () {
        Route::livewire('dashboard', 'pages::dashboard.cooperative-stat')->name('koperasi.dashboard');
        Route::livewire('riwayat', 'pages::dashboard.cooperative.history')->name('koperasi.history');
    });

    Route::middleware(['role:admin_koperasi|admin|super_admin'])->prefix('koperasi')->group(function () {
        Route::livewire('produk', 'pages::dashboard.cooperative.products')->name('koperasi.products');
        Route::livewire('stok-opname', 'pages::dashboard.cooperative.stock-opname')->name('koperasi.stock-opname');
        Route::livewire('riwayat-stok', 'pages::dashboard.cooperative.stock-history')->name('koperasi.stock-history');
        Route::livewire('kasir', 'pages::dashboard.cooperative.cashiers')->name('koperasi.cashiers');
        Route::get('export', [\App\Http\Controllers\CooperativeReportController::class, 'export'])->name('cooperative.export');
    });

    // --- KASIR KOPERASI (POS) ---
    Route::middleware(['role:kasir|admin_koperasi|admin|super_admin'])->prefix('koperasi')->group(function () {
        Route::livewire('pos', 'pages::dashboard.cooperative.pos')->name('koperasi.pos');
        Route::get('print-struk/{invoiceCode}', [\App\Http\Controllers\CooperativeReportController::class, 'printReceipt'])->name('cooperative.print-receipt');
    });
});

require __DIR__.'/settings.php';
