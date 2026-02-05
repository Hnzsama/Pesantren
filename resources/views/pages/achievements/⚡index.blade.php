<?php

use App\Models\Achievement;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        return [
            'achievements' => Achievement::query()
                ->with('student')
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->orderBy('date_achieved', 'desc')
                ->paginate(10),
        ];
    }

    public $detailAchievement = null;

    public function show($id)
    {
        $this->detailAchievement = Achievement::with('student')->findOrFail($id);
        Flux::modal('achievement-detail')->show();
    }

    public function closeDetail()
    {
        $this->detailAchievement = null;
        Flux::modal('achievement-detail')->close();
    }

    public function delete($id)
    {
        $achievement = Achievement::findOrFail($id);
        $achievement->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data berhasil dihapus.');
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Data Prestasi</flux:heading>
                <flux:subheading size="lg" class="mb-6">Kelola data prestasi santri.</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" href="{{ route('achievements.create') }}" wire:navigate>Tambah
                Prestasi</flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Table -->
    <div class="space-y-4">
        <flux:table :paginate="$achievements">
            <flux:table.columns>
                <flux:table.column>Judul</flux:table.column>
                <flux:table.column>Santri</flux:table.column>
                <flux:table.column>Tanggal</flux:table.column>
                <flux:table.column>Gambar</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($achievements as $achievement)
                    <flux:table.row :key="$achievement->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $achievement->title }}</div>
                            <div class="text-xs text-zinc-500">{{ Str::limit($achievement->description, 50) }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $achievement->student->full_name ?? '-' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            {{ $achievement->date_achieved->format('d M Y') }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex -space-x-2 overflow-hidden">
                                @forelse ($achievement->images ?? [] as $img)
                                    <img class="inline-block h-8 w-8 rounded-full ring-2 ring-white dark:ring-zinc-800 object-cover"
                                        src="{{ Storage::url($img) }}" alt="" />
                                @empty
                                    <span class="text-xs text-zinc-400">No Image</span>
                                @endforelse
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                <flux:menu>
                                    <flux:menu.item wire:click="show('{{ $achievement->id }}')" icon="eye">Detail
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item wire:click="delete('{{ $achievement->id }}')" icon="trash"
                                        variant="danger">Hapus</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8">
                            <div class="text-zinc-500">Belum ada data prestasi.</div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <!-- Detail Flyout -->
    <flux:modal name="achievement-detail" flyout class="space-y-6">
        @if($detailAchievement)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $detailAchievement->title }}</flux:heading>
                    <flux:subheading>
                        {{ $detailAchievement->date_achieved->isoFormat('D MMMM Y') }} •
                        {{ $detailAchievement->student->full_name ?? 'Umum' }}
                    </flux:subheading>
                </div>

                <flux:separator />

                <div>
                    <flux:label class="mb-2">Deskripsi</flux:label>
                    <div
                        class="text-sm text-zinc-700 dark:text-zinc-300 mt-1 whitespace-pre-wrap p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                        {{ $detailAchievement->description }}
                    </div>
                </div>

                @if(!empty($detailAchievement->images))
                    <div>
                        <flux:label class="mb-2">Dokumentasi</flux:label>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach($detailAchievement->images as $img)
                                <a href="{{ Storage::url($img) }}" target="_blank"
                                    class="block overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 hover:shadow-md transition">
                                    <img src="{{ Storage::url($img) }}"
                                        class="w-full h-40 object-cover hover:scale-105 transition duration-300">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

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