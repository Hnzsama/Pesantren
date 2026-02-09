<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        \Illuminate\Support\Facades\Gate::define('koperasi-only', function ($user) {
            return $user->hasAnyRole(['admin_koperasi', 'admin', 'super_admin']);
        });

        \App\Models\AcademicYear::observe(\App\Observers\AcademicYearObserver::class);
        \App\Models\News::observe(\App\Observers\NewsObserver::class);
        \App\Models\Bill::observe(\App\Observers\BillObserver::class);
        \App\Models\Achievement::observe(\App\Observers\AchievementObserver::class);
        \App\Models\StudentPermission::observe(\App\Observers\StudentPermissionObserver::class);
        \App\Models\Attendance::observe(\App\Observers\AttendanceObserver::class);
        \App\Models\ActivityAttendance::observe(\App\Observers\ActivityAttendanceObserver::class);
        \App\Models\VideoLecture::observe(\App\Observers\VideoLectureObserver::class);
        \App\Models\StudentReport::observe(\App\Observers\StudentReportObserver::class);
        \App\Models\SowanSchedule::observe(\App\Observers\SowanScheduleObserver::class);

        if (app()->environment('local')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));

            // Fix for Livewire/Signed URLs behind Ngrok
            if (str_contains(config('app.url'), 'https://')) {
                $this->app['request']->server->set('HTTPS', 'on');
            }
        }
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
