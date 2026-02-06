<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
    //
}; ?>

<div class="p-6 text-center bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
    <flux:heading size="lg">Selamat Datang, {{ auth()->user()->name }}!</flux:heading>
    <flux:subheading class="mt-2">Silakan pilih menu di samping untuk memulai.</flux:subheading>
</div>