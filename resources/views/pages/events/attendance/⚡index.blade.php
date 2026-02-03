<?php

use App\Models\Event;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Flux\Flux;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $events = Event::query()
            ->withCount('attendances')
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->when($this->search, fn($q) => $q->where('title', 'like', '%' . $this->search . '%'))
            ->orderBy('event_date', 'desc')
            ->paginate(10);

        return [
            'events' => $events,
        ];
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">Presensi Agenda Kegiatan</flux:heading>
        <flux:subheading size="lg" class="mb-6">Kelola kehadiran santri pada agenda kegiatan.</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="flex justify-between items-center mb-6">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari kegiatan..."
            class="max-w-xs" />
    </div>

    <!-- Table -->
    <div class="space-y-4">
        <flux:table :paginate="$events">
            <flux:table.columns>
                <flux:table.column>Nama Kegiatan</flux:table.column>
                <flux:table.column>Tanggal</flux:table.column>
                <flux:table.column>Kehadiran Tercatat</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($events as $event)
                    <flux:table.row :key="$event->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $event->title }}</div>
                            <div class="text-xs text-zinc-500">{{ Str::limit($event->description, 50) }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:icon.calendar-days class="w-4 h-4 text-zinc-400" />
                                {{ $event->event_date->format('d M Y') }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:badge size="sm" color="{{ $event->attendances_count > 0 ? 'green' : 'zinc' }}">
                                {{ $event->attendances_count }} Santri
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:button href="{{ route('events.attendance.entry', $event->id) }}"
                                icon="clipboard-document-check" size="sm" wire:navigate>
                                Input Presensi
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center py-8">
                            <div class="text-zinc-500">Belum ada agenda kegiatan.</div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>