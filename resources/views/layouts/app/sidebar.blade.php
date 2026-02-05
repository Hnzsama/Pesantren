<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')">
                @role('admin')
                <flux:sidebar.item icon="building-library" :href="route('dashboard.admin')" :current="request()->routeIs('dashboard.admin')"
                    wire:navigate>
                    {{ __('Dashboard Pesantren') }}
                </flux:sidebar.item>
                @else
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                @endrole

                @role('super_admin')
                <flux:sidebar.item icon="presentation-chart-line" :href="route('dashboard.super-admin')"
                    :current="request()->routeIs('dashboard.super-admin')" wire:navigate>
                    {{ __('Super Admin') }}
                </flux:sidebar.item>
                @endrole
            </flux:sidebar.group>

            @role('admin')
            <flux:sidebar.group :heading="__('Akademik')">
                <flux:sidebar.item icon="calendar-days" :href="route('academic-years.index')"
                    :current="request()->routeIs('academic-years.*')" wire:navigate>
                    {{ __('Tahun Ajaran') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="calendar" :href="route('semesters.index')"
                    :current="request()->routeIs('semesters.*')" wire:navigate>
                    {{ __('Semester') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="building-office-2" :href="route('departments.index')"
                    :current="request()->routeIs('departments.*')" wire:navigate>
                    {{ __('Jenjang Pendidikan') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="rectangle-stack" :href="route('levels.index')"
                    :current="request()->routeIs('levels.*')" wire:navigate>
                    {{ __('Data Kelas') }}
                </flux:sidebar.item>



                <flux:sidebar.item icon="clipboard-document-list" :href="route('activities.index')"
                    :current="request()->routeIs('activities.index')" wire:navigate>
                    {{ __('Kegiatan Harian') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="calendar" :href="route('events.index')"
                    :current="request()->routeIs('events.index')" wire:navigate>
                    {{ __('Agenda Kegiatan') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="calendar-days" :href="route('events.attendance.index')"
                    :current="request()->routeIs('events.attendance.*')" wire:navigate>
                    {{ __('Presensi Agenda') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="users" :href="route('activities.attendance.index')"
                    :current="request()->routeIs('activities.attendance.*')" wire:navigate>
                    {{ __('Presensi Harian') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="document-text" :href="route('student-reports.index')"
                    :current="request()->routeIs('student-reports.*')" wire:navigate>
                    {{ __('Rapor Santri') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Kesiswaan')">
                <flux:sidebar.item icon="trophy" :href="route('achievements.index')"
                    :current="request()->routeIs('achievements.*')" wire:navigate>
                    {{ __('Prestasi') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="video-camera" :href="route('lectures.index')"
                    :current="request()->routeIs('lectures.*')" wire:navigate>
                    {{ __('Kajian') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="academic-cap" :href="route('students.index')"
                    :current="request()->routeIs('students.index') || request()->routeIs('students.import')"
                    wire:navigate>
                    {{ __('Santri') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="arrow-trending-up" :href="route('students.promotion')"
                    :current="request()->routeIs('students.promotion')" wire:navigate>
                    {{ __('Kenaikan Kelas') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="users" :href="route('guardians.index')"
                    :current="request()->routeIs('guardians.*')" wire:navigate>
                    {{ __('Wali Santri') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="document-check" :href="route('student-permissions.index')"
                    :current="request()->routeIs('student-permissions.*')" wire:navigate>
                    {{ __('Perizinan Santri') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="chat-bubble-bottom-center-text" :href="route('sowan-schedules.index')"
                    :current="request()->routeIs('sowan-schedules.*')" wire:navigate>
                    {{ __('Jadwal Sowan') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="chat-bubble-left-right" :href="route('feedback.index')"
                    :current="request()->routeIs('feedback.*')" wire:navigate>
                    {{ __('Feedback') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="identification" :href="route('student-cards.index')"
                    :current="request()->routeIs('student-cards.*')" wire:navigate>
                    {{ __('Kartu Santri') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
            @endrole

            @hasanyrole('super_admin|admin')
            <flux:sidebar.group :heading="__('Administrasi')">
                @role('super_admin')
                <flux:sidebar.item icon="printer" :href="route('student-card-requests.index')"
                    :current="request()->routeIs('student-card-requests.*')" wire:navigate>
                    {{ __('Cetak Kartu') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="building-library" :href="route('schools.index')"
                    :current="request()->routeIs('schools.*')" wire:navigate>
                    {{ __('Sekolah') }}
                </flux:sidebar.item>
                @endrole

                <flux:sidebar.item icon="users" :href="route('users')" :current="request()->routeIs('users')"
                    wire:navigate>
                    {{ __('Pengguna') }}
                </flux:sidebar.item>

                @role('super_admin')
                <flux:sidebar.item icon="key" :href="route('roles.index')" :current="request()->routeIs('roles.*')"
                    wire:navigate>
                    {{ __('Hak Akses') }}
                </flux:sidebar.item>
                @endrole
            </flux:sidebar.group>
            @endhasanyrole

            @hasanyrole('super_admin|admin')
            <flux:sidebar.group :heading="__('Keuangan')">
                @role('super_admin')
                <flux:sidebar.item icon="credit-card" :href="route('payment-methods.index')"
                    :current="request()->routeIs('payment-methods.*')" wire:navigate>
                    {{ __('Metode Pembayaran') }}
                </flux:sidebar.item>
                @endrole

                @role('admin')
                <flux:sidebar.item icon="banknotes" :href="route('wallet-transactions.index')"
                    :current="request()->routeIs('wallet-transactions.*')" wire:navigate>
                    {{ __('Transaksi Dompet') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="document-text" :href="route('bills.index')"
                    :current="request()->routeIs('bills.*')" wire:navigate>
                    {{ __('Tagihan') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="tag" :href="route('bill-categories.index')"
                    :current="request()->routeIs('bill-categories.*')" wire:navigate>
                    {{ __('Kategori Tagihan') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="ticket" :href="route('event-bills.index')"
                    :current="request()->routeIs('event-bills.*')" wire:navigate>
                    {{ __('Tagihan Kegiatan') }}
                </flux:sidebar.item>
                @endrole
            </flux:sidebar.group>
            @endhasanyrole
        </flux:sidebar.nav>

        <flux:spacer />

        {{-- <flux:sidebar.nav>
            <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit"
                target="_blank">
                {{ __('Repository') }}
            </flux:sidebar.item>

            <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire"
                target="_blank">
                {{ __('Documentation') }}
            </flux:sidebar.item>
        </flux:sidebar.nav> --}}

        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>


    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    <flux:toast />

    @fluxScripts
</body>

</html>