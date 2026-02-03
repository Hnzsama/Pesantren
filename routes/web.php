<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::livewire('users', 'pages::users.index')->name('users');
    Route::livewire('schools', 'pages::schools.index')->name('schools.index');
    Route::livewire('roles', 'pages::roles.index')->name('roles.index');
    Route::livewire('payment-methods', 'pages::payment-methods.index')->name('payment-methods.index');
    Route::livewire('guardians', 'pages::guardians.index')->name('guardians.index');
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
    Route::livewire('agenda-kegiatan/presensi', 'pages::events.attendance.⚡index')->name('events.attendance.index');
    Route::livewire('agenda-kegiatan/{eventId}/presensi-input', 'pages::events.attendance.⚡entry')->name('events.attendance.entry');

    // Attendance (Activity)
    Route::livewire('presensi-kegiatan', 'pages::activities.attendance.⚡index')->name('activities.attendance.index');
    Route::livewire('presensi-kegiatan/catat', 'pages::activities.attendance.⚡entry')->name('activities.attendance.entry');

    // Achievements
    Route::livewire('jadwal-sowan', 'pages::sowan-schedules.⚡index')->name('sowan-schedules.index');
    Route::livewire('perizinan', 'pages::student-permissions.⚡index')->name('student-permissions.index');
    Route::livewire('kajian', 'pages::video-lectures.⚡index')->name('lectures.index');
    Route::livewire('prestasi/create', 'pages::achievements.⚡create')->name('achievements.create');
    Route::livewire('prestasi', 'pages::achievements.⚡index')->name('achievements.index');

    // Student Reports
    Route::livewire('laporan-santri', 'pages::student-reports.index')->name('student-reports.index');
    Route::livewire('laporan-santri/create', 'pages::student-reports.⚡create')->name('student-reports.create');
    Route::livewire('laporan-santri/{id}/edit', 'pages::student-reports.⚡create')->name('student-reports.edit');

    // Feedback
    Route::livewire('feedback', 'pages::feedback.index')->name('feedback.index');

});

require __DIR__.'/settings.php';
