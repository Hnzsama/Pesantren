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

    public $attendees = null;
    public $selectedEvent = null;

    public function viewAttendees($eventId)
    {
        $this->selectedEvent = Event::find($eventId);
        $this->attendees = \App\Models\EventAttendance::query()
            ->with(['student.level'])
            ->where('event_id', $eventId)
            ->where('status', 'present')
            ->orderByDesc('check_in_time')
            ->get();

        $this->modal('event-detail-modal')->show();
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Presensi Agenda Kegiatan</flux:heading>
                <flux:subheading size="lg" class="mb-6">Kelola kehadiran santri pada agenda kegiatan.</flux:subheading>
            </div>
            <flux:button href="{{ route('events.attendance.detail') }}" icon="document-text" wire:navigate>
                Detail Agenda
            </flux:button>
        </div>
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
                            <div class="flex gap-2">
                                <flux:button href="{{ route('events.attendance.scan', $event->id) }}" icon="qr-code"
                                    size="sm" variant="filled" target="_blank">
                                    Scan
                                </flux:button>
                                <flux:button wire:click="viewAttendees('{{ $event->id }}')" icon="eye" size="sm">
                                    Detail
                                </flux:button>
                                {{-- Legacy Entry Page if needed, but Scan is preferred --}}
                                <flux:button href="{{ route('events.attendance.entry', $event->id) }}" icon="pencil-square"
                                    size="sm" wire:navigate variant="ghost">
                                    Edit
                                </flux:button>
                            </div>
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

    {{-- Detail Flyout --}}
    <flux:modal name="event-detail-modal" flyout class="space-y-6">
        <div>
            <flux:heading size="lg">Detail Kehadiran</flux:heading>
            @if($selectedEvent)
                <flux:subheading>{{ $selectedEvent->title }}</flux:subheading>
                <div class="mt-1 text-sm text-zinc-500">
                    {{ \Carbon\Carbon::parse($selectedEvent->event_date)->isoFormat('dddd, D MMMM Y') }}
                </div>
            @endif
        </div>

        @if($attendees)
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        Total Hadir: {{ $attendees->count() }}
                    </div>
                </div>

                <div class="max-h-[60vh] overflow-y-auto">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Nama Santri</flux:table.column>
                            <flux:table.column>Waktu</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($attendees as $attendance)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <div class="font-medium">{{ $attendance->student->full_name }}</div>
                                        <div class="text-xs text-zinc-500">{{ $attendance->student->level->name ?? '-' }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" color="green">
                                            {{ $attendance->check_in_time ? $attendance->check_in_time->format('H:i') : '-' }}
                                        </flux:badge>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="2" class="text-center text-zinc-500">
                                        Belum ada data kehadiran.
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
</div>