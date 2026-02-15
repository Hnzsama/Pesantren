<?php

use App\Models\School;
use App\Models\StudentCard;
use Flux\Flux;
use Livewire\Attributes\Url;
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

    #[Url]
    public $search = '';

    #[Url]
    public $statusFilter = 'request_print';

    #[Url]
    public $schoolFilter = '';

    public function with(): array
    {
        $query = StudentCard::query()
            ->with(['student', 'student.school', 'student.level', 'issuer'])
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->schoolFilter, fn($q) => $q->whereHas('student', fn($s) => $s->where('school_id', $this->schoolFilter)))
            ->when($this->search, fn($q) => $q->where(function ($sub) {
                $sub->where('card_number', 'like', '%' . $this->search . '%')
                    ->orWhereHas('student', fn($s) => $s->where('full_name', 'like', '%' . $this->search . '%')
                        ->orWhere('nis', 'like', '%' . $this->search . '%')
                        ->orWhereHas('school', fn($sc) => $sc->where('name', 'like', '%' . $this->search . '%')));
            }))
            ->orderBy('updated_at', 'desc');

        return [
            'requests' => $query->paginate(15),
            'schools' => School::orderBy('name')->get(),
        ];
    }

    public $cardIdToPrint = null;

    public function confirmMarkAsPrinted($id)
    {
        $this->cardIdToPrint = $id;
        $this->modal('confirm-print-modal')->show();
    }

    public function markAsPrinted()
    {
        $card = StudentCard::find($this->cardIdToPrint);
        if ($card) {
            $card->update([
                'status' => 'printed',
                'expires_at' => now()->addYears(3),
            ]);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Status kartu diubah menjadi Sudah Dicetak.');
        }
        $this->modal('confirm-print-modal')->close();
        $this->reset('cardIdToPrint');
    }

    public function getStatusColor($status)
    {
        return match ($status) {
            'generated' => 'zinc',
            'request_print' => 'orange',
            'printed' => 'green',
            default => 'zinc',
        };
    }

    public function getStatusLabel($status)
    {
        return match ($status) {
            'generated' => 'Generated',
            'request_print' => 'Menunggu Cetak',
            'printed' => 'Sudah Dicetak',
            default => $status,
        };
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Permintaan Cetak Kartu</flux:heading>
                <flux:subheading size="lg" class="mb-6">Kelola permintaan pencetakan kartu santri dari semua sekolah
                </flux:subheading>
            </div>
            

        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari..." />

        <flux:select wire:model.live="schoolFilter" variant="listbox" placeholder="Filter Sekolah" clearable>
            <flux:select.option value="">Semua Sekolah</flux:select.option>
            @foreach ($schools as $school)
                <flux:select.option value="{{ $school->id }}">{{ $school->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="statusFilter" variant="listbox" placeholder="Filter Status" clearable>
            <flux:select.option value="request_print">Menunggu Cetak</flux:select.option>
            <flux:select.option value="printed">Sudah Dicetak</flux:select.option>
            <flux:select.option value="generated">Generated</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$requests">
        <flux:table.columns>
            <flux:table.column>Sekolah</flux:table.column>
            <flux:table.column>Santri</flux:table.column>
            <flux:table.column>Nomor Kartu</flux:table.column>
            <flux:table.column>Diajukan Oleh</flux:table.column>
            <flux:table.column>Waktu</flux:table.column>
            <flux:table.column>Expired</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($requests as $card)
                <flux:table.row :key="$card->id">
                    <flux:table.cell>
                        <div class="font-medium">{{ $card->student->school->name ?? '-' }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar :name="$card->student->full_name" :src="$card->student->photo_url" size="sm" />
                            <div>
                                <div class="font-medium">{{ $card->student->full_name }}</div>
                                <div class="text-xs text-zinc-500 font-mono">{{ $card->student->nis }}</div>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="font-mono">{{ $card->card_number }}</flux:table.cell>

                    <flux:table.cell>{{ $card->issuer->name ?? '-' }}</flux:table.cell>

                    <flux:table.cell>
                        <div class="text-sm">{{ $card->updated_at->format('d M Y, H:i') }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div
                            class="text-sm {{ $card->expires_at && $card->expires_at->isPast() ? 'text-red-500 font-bold' : '' }}">
                            {{ $card->expires_at ? $card->expires_at->format('d M Y') : '-' }}
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$this->getStatusColor($card->status)">
                            {{ $this->getStatusLabel($card->status) }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button variant="ghost" size="sm" icon="eye" href="{{ route('student-cards.preview', $card->id) }}" target="_blank">
                                Preview
                            </flux:button>
                        
                            @if($card->status === 'request_print')
                                <flux:button variant="primary" size="sm" icon="check"
                                    wire:click="confirmMarkAsPrinted('{{ $card->id }}')">
                                    Selesai Manual
                                </flux:button>
                            @else
                                <flux:icon name="check-circle" class="text-green-500" />
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center py-12">
                        <div class="flex flex-col items-center gap-3">
                            <flux:icon.inbox class="w-12 h-12 text-zinc-300 dark:text-zinc-600" />
                            <div>
                                <flux:heading size="lg" class="text-zinc-500">Tidak ada permintaan</flux:heading>
                                <flux:subheading>Belum ada permintaan cetak kartu.</flux:subheading>
                            </div>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <!-- Confirm Print Modal -->
    <flux:modal name="confirm-print-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Konfirmasi Cetak Selesai?</flux:heading>
                <flux:text class="mt-2 text-sm">
                    Apakah Anda yakin ingin menandai kartu ini sudah dicetak?<br>
                    Tanggal kedaluwarsa akan otomatis diatur <strong>3 tahun</strong> dari sekarang.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="markAsPrinted" variant="primary">Ya, Selesai Cetak</flux:button>
            </div>
        </div>
    </flux:modal>
    

</div>