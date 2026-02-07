<?php

use App\Models\VideoLecture;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Flux\Flux;
use Illuminate\Support\Str;

new #[Lazy] #[Layout('layouts.app')] class extends Component {
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

    public $title = '';
    public $youtube_url = '';
    public $published_at = '';
    public $description = '';

    public $editingId = null;

    public function mount()
    {
        $this->published_at = now()->format('Y-m-d');
    }

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        return [
            'lectures' => VideoLecture::query()
                ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->orderBy('published_at', 'desc')
                ->paginate(12),
        ];
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'youtube_url' => 'required|url',
            'published_at' => 'required|date',
            'description' => 'nullable|string',
        ]);

        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        // Simple data extraction from model if editing, else create
        if ($this->editingId) {
            $lecture = VideoLecture::find($this->editingId);
            $lecture->update([
                'title' => $this->title,
                'youtube_url' => $this->youtube_url,
                'published_at' => $this->published_at,
                'description' => $this->description,
            ]);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Kajian berhasil diperbarui.');
        } else {
            VideoLecture::create([
                'title' => $this->title,
                'school_id' => $schoolId,
                'youtube_url' => $this->youtube_url,
                'published_at' => $this->published_at,
                'description' => $this->description,
            ]);
            Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Kajian berhasil disimpan.');
        }

        $this->reset(['title', 'youtube_url', 'description', 'editingId']);
        $this->published_at = now()->format('Y-m-d');
        Flux::modal('lecture-form')->close();
    }

    public function edit($id)
    {
        $lecture = VideoLecture::findOrFail($id);
        $this->editingId = $lecture->id;
        $this->title = $lecture->title;
        $this->youtube_url = $lecture->youtube_url;
        $this->published_at = $lecture->published_at->format('Y-m-d');
        $this->description = $lecture->description;

        Flux::modal('lecture-form')->show();
    }

    public function create()
    {
        $this->reset(['title', 'youtube_url', 'description', 'editingId']);
        $this->published_at = now()->format('Y-m-d');
        Flux::modal('lecture-form')->show();
    }

    public function delete($id)
    {
        $lecture = VideoLecture::findOrFail($id);
        $lecture->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Kajian berhasil dihapus.');
    }
}; ?>

<div class="w-full">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Kajian Video</flux:heading>
                <flux:subheading size="lg" class="mb-6">Kumpulan dokumentasi kajian dan ceramah.</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="create">Tambah Kajian</flux:button>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <!-- Grid Layout for Videos -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($lectures as $lecture)
            <div
                class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden hover:shadow-lg transition-all duration-300 flex flex-col h-full group">
                <!-- Thumbnail / Video -->
                <!-- Thumbnail / Video -->
                <div class="relative aspect-video bg-zinc-100 dark:bg-zinc-800 group-hover:bg-zinc-200 dark:group-hover:bg-zinc-700 transition"
                    x-data="{ playing: false }">
                    @if($lecture->embed_url)
                        <template x-if="!playing">
                            <div class="relative w-full h-full cursor-pointer" @click="playing = true">
                                <img src="{{ $lecture->thumbnail_url }}" class="w-full h-full object-cover">
                                <div
                                    class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/40 transition">
                                    <flux:icon.video-camera
                                        class="w-12 h-12 text-white opacity-80 group-hover:opacity-100 drop-shadow-lg" />
                                </div>
                            </div>
                        </template>
                        <template x-if="playing">
                            <iframe src="{{ $lecture->embed_url }}?autoplay=1" class="w-full h-full" frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen>
                            </iframe>
                        </template>
                    @else
                        <div class="w-full h-full flex items-center justify-center text-zinc-400">
                            <flux:icon.video-camera-slash class="w-12 h-12" />
                        </div>
                    @endif
                </div>

                <div class="p-4 flex flex-col flex-1">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="text-xs font-medium text-zinc-500 uppercase tracking-wide">
                            {{ $lecture->published_at->isoFormat('D MMMM Y') }}
                        </div>

                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="-mr-2" />
                            <flux:menu>
                                <flux:menu.item wire:click="edit('{{ $lecture->id }}')" icon="pencil">Edit</flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item wire:click="delete('{{ $lecture->id }}')" icon="trash" variant="danger">
                                    Hapus</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-2 line-clamp-2 leading-tight">
                        {{ $lecture->title }}
                    </h3>

                    <p class="text-sm text-zinc-500 dark:text-zinc-400 line-clamp-3 mb-4">
                        {{ $lecture->description ?? 'Tidak ada deskripsi.' }}
                    </p>

                    <div class="mt-auto pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button variant="ghost" size="sm" class="w-full justify-center"
                            href="{{ $lecture->youtube_url }}" target="_blank">
                            Tonton Video <flux:icon.arrow-top-right-on-square class="w-4 h-4 ml-2" />
                        </flux:button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center">
                <div
                    class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 mb-4">
                    <flux:icon.video-camera class="w-8 h-8" />
                </div>
                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Belum ada kajian</h3>
                <p class="text-zinc-500 mt-1 max-w-sm mx-auto">Kajian yang Anda tambahkan akan muncul di sini.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $lectures->links() }}
    </div>

    <!-- Create/Edit Modal Flyout -->
    <flux:modal name="lecture-form" flyout class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingId ? 'Edit Kajian' : 'Tambah Kajian' }}</flux:heading>
            <flux:subheading>Isi informasi video kajian.</flux:subheading>
        </div>

        <flux:separator />

        <div class="space-y-6">
            <flux:input wire:model="title" label="Judul Kajian" placeholder="Contoh: Kajian Kitab..." />

            <flux:input wire:model="youtube_url" label="Link YouTube" icon="link"
                placeholder="https://youtube.com/watch?v=..." />

            <flux:input type="date" wire:model="published_at" label="Tanggal Tayang" />

            <flux:textarea wire:model="description" label="Deskripsi" placeholder="Ringkasan isi kajian..." rows="4" />

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="$dispatch('close-modal', { name: 'lecture-form' })">Batal
                </flux:button>
                <flux:button variant="primary" wire:click="save">Simpan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>