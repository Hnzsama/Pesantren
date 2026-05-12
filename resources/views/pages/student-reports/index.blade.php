<?php

use App\Models\School;
use App\Models\StudentReport;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component {
    public function placeholder()
    {
        return <<<'HTML'
        <div class="space-y-6 w-full animate-pulse">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div class="space-y-2">
                    <div class="h-8 w-64 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
                    <div class="h-4 w-48 bg-zinc-100 dark:bg-zinc-900 rounded-lg"></div>
                </div>
                <div class="h-10 w-32 bg-zinc-200 dark:bg-zinc-800 rounded-lg"></div>
            </div>
            <div class="space-y-4">
                <div class="h-12 w-full bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-100 dark:border-zinc-800"></div>
                <div class="h-64 w-full bg-zinc-50 dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800"></div>
            </div>
        </div>
        HTML;
    }

    use WithPagination;

    // Filters
    public $search = '';

    public $school_id = '';

    public $perPage = 10;

    // Detail View State
    public $detailReport = null;

    public function mount()
    {
        // Default to user's school if available
        if (Auth::user()->school_id) {
            $this->school_id = Auth::user()->school_id;
        }
    }

    #[Computed]
    public function reports()
    {
        return StudentReport::query()
            ->with(['student', 'semester.academicYear', 'school', 'uploader'])
            ->when($this->search, function ($query) {
                $query->whereHas('student', function ($q) {
                    $q->where('full_name', 'like', '%' . $this->search . '%')
                        ->orWhere('nis', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->school_id, fn($q) => $q->where('school_id', $this->school_id))
            ->latest()
            ->paginate($this->perPage);
    }

    #[Computed]
    public function schools()
    {
        return School::all();
    }

    public function show($id)
    {
        $this->detailReport = StudentReport::with(['student', 'semester.academicYear', 'uploader', 'school'])->findOrFail($id);
        Flux::modal('report-detail')->show();
    }

    public function closeDetail()
    {
        $this->detailReport = null;
        Flux::modal('report-detail')->close();
    }

    public function delete($id)
    {
        $report = StudentReport::findOrFail($id);
        // Optional: delete file logic if not handled by model events
        $report->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Rapor berhasil dihapus.');
    }
};
?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex flex-col items-start gap-4">
            <div>
                <flux:heading size="xl" level="1">Rapor Santri</flux:heading>
                <flux:subheading size="lg">Kelola data rapor hasil belajar santri.</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" href="{{ route('student-reports.create') }}" wire:navigate>Tambah
                Rapor</flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari santri..." />

        <flux:select variant="listbox" wire:model.live="school_id" placeholder="Pilih Sekolah" clearable>
            @foreach ($this->schools as $school)
                <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <!-- Table -->
    <flux:table :paginate="$this->reports">
        <flux:table.columns>
            <flux:table.column>Santri</flux:table.column>
            <flux:table.column>Tahun Ajaran</flux:table.column>
            <flux:table.column>Semester</flux:table.column>
            <flux:table.column>File</flux:table.column>
            <flux:table.column>Diunggah Oleh</flux:table.column>
            <flux:table.column>Tanggal</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->reports as $report)
                <flux:table.row wire:key="{{ $report->id }}">
                    <flux:table.cell>
                        <div class="font-medium">{{ $report->student_name ?? $report->student?->full_name ?? '-' }}</div>
                        <div class="text-xs text-zinc-500">{{ $report->student_nis ?? $report->student?->nis ?? '-' }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $report->semester->academicYear->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="{{ $report->semester->type === 'GANJIL' ? 'orange' : 'green' }}">
                            {{ $report->semester->type }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex -space-x-2 overflow-hidden">
                            @forelse ($report->files as $fileUrl)
                                <img class="inline-block h-8 w-8 rounded-full ring-2 ring-white dark:ring-zinc-800 object-cover"
                                    src="{{ $fileUrl }}" alt="" />
                            @empty
                                <span class="text-xs text-zinc-400">No Image</span>
                            @endforelse
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $report->uploader->name ?? '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $report->created_at->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                            <flux:menu>
                                <flux:menu.item wire:click="show('{{ $report->id }}')" icon="eye">Detail</flux:menu.item>
                                <flux:menu.item href="{{ route('student-reports.edit', $report->id) }}" wire:navigate
                                    icon="pencil-square">Edit</flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item wire:click="delete('{{ $report->id }}')" icon="trash" variant="danger">Hapus
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-6 text-zinc-500">
                        Tidak ada data rapor ditemukan.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Detail Modal -->
    <flux:modal name="report-detail" class="space-y-6">
        @if($detailReport)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Detail Rapor</flux:heading>
                    <flux:subheading>
                        {{ $detailReport->student_name ?? $detailReport->student?->full_name ?? '-' }} • {{ $detailReport->semester->academicYear->name }}
                        {{ $detailReport->semester->type }}
                    </flux:subheading>
                </div>

                <flux:separator />

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:label class="mb-1">Santri</flux:label>
                        <div class="font-medium">{{ $detailReport->student_name ?? $detailReport->student?->full_name ?? '-' }}</div>
                        <div class="text-sm text-zinc-500">{{ $detailReport->student_nis ?? $detailReport->student?->nis ?? '-' }}</div>
                    </div>
                    <div>
                        <flux:label class="mb-1">Sekolah</flux:label>
                        <div class="font-medium">{{ $detailReport->school->name ?? '-' }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:label class="mb-1">Tahun Ajaran</flux:label>
                        <div class="font-medium">{{ $detailReport->semester->academicYear->name }}</div>
                    </div>
                    <div>
                        <flux:label class="mb-1">Semester</flux:label>
                        <flux:badge size="sm" color="{{ $detailReport->semester->type === 'GANJIL' ? 'orange' : 'green' }}">
                            {{ $detailReport->semester->type }}
                        </flux:badge>
                    </div>
                </div>

                <div>
                    <flux:label class="mb-2">File Rapor</flux:label>

                    @if(count($detailReport->files) > 0)
                        <div class="grid grid-cols-1 gap-4 max-h-[60vh] overflow-y-auto">
                            @foreach($detailReport->files as $index => $fileUrl)
                                <div class="relative group">
                                    <div class="absolute top-2 left-2 bg-black/50 text-white px-2 py-1 rounded text-xs z-10">
                                        Halaman {{ $index + 1 }}
                                    </div>
                                    <a href="{{ $fileUrl }}" target="_blank"
                                        class="block overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 hover:shadow-md transition">
                                        <img src="{{ $fileUrl }}"
                                            class="w-full h-auto max-h-[300px] object-contain bg-zinc-50 dark:bg-zinc-900">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-zinc-500 italic">Tidak ada file yang diunggah.</div>
                    @endif
                </div>

                <div class="text-xs text-zinc-400">
                    Diunggah oleh: {{ $detailReport->uploader->name ?? 'System' }} pada
                    {{ $detailReport->created_at->format('d M Y H:i') }}
                </div>

                <div class="flex justify-end pt-4">
                    <flux:button variant="ghost" wire:click="closeDetail">Tutup</flux:button>
                </div>
            </div>
        @else
            <div class="flex justify-center py-12">
                <flux:icon.loading class="w-8 h-8 animate-spin text-zinc-400" />
            </div>
        @endif
    </flux:modal>
</div>