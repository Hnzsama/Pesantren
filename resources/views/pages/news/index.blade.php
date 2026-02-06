<?php

use App\Models\News;
use App\Services\ImageCompressionService;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads, WithPagination;

    public $title = '';

    public $content = '';

    public $target_role = '';

    public $images = [];

    public $is_published = true;

    public $editingId = null;

    public $selectedNews = null;

    public function mount()
    {
        $this->setDefaultTargetRole();
    }

    protected function setDefaultTargetRole()
    {
        $user = auth()->user();
        if ($user->hasRole('super_admin')) {
            $this->target_role = 'admin';
        } elseif ($user->hasRole('admin')) {
            $this->target_role = 'guardian';
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $schoolId = $user->school_id;

        return [
            'newsList' => News::query()
                ->when($user->hasRole('admin'), fn($q) => $q->where('school_id', $schoolId))
                ->latest()
                ->paginate(10),
        ];
    }

    public function save(ImageCompressionService $compressionService)
    {
        try {
            $this->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'target_role' => 'required|string',
                'images.*' => 'image|max:10240', // 10MB Max per image, will be compressed
                'is_published' => 'boolean',
            ]);

            $user = auth()->user();
            $schoolId = $user->hasRole('admin') ? $user->school_id : null;

            $storedImages = [];
            if ($this->images) {
                foreach ($this->images as $image) {
                    $filename = Str::random(40);
                    $storedImages[] = $compressionService->storeAsWebP($image, 'news', $filename);
                }
            }

            if ($this->editingId) {
                $news = News::find($this->editingId);

                $data = [
                    'title' => $this->title,
                    'content' => $this->content,
                    'target_role' => $this->target_role,
                    'is_published' => $this->is_published,
                ];

                if (!empty($storedImages)) {
                    $data['images'] = $storedImages;
                }

                $news->update($data);
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Berita berhasil diperbarui.');
            } else {
                Log::info('Creating news', [
                    'title' => $this->title,
                    'author_id' => $user->id,
                    'school_id' => $schoolId,
                ]);

                News::create([
                    'title' => $this->title,
                    'content' => $this->content,
                    'target_role' => $this->target_role,
                    'author_id' => $user->id,
                    'school_id' => $schoolId,
                    'images' => $storedImages,
                    'is_published' => $this->is_published,
                ]);
                Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Berita berhasil diterbitkan.');
            }

            $this->reset(['title', 'content', 'images', 'editingId']);
            $this->setDefaultTargetRole();
            Flux::modal('news-form')->close();

        } catch (\Exception $e) {
            Log::error('Gagal simpan berita: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            Flux::toast(variant: 'danger', heading: 'Error', text: 'Gagal menyimpan berita: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $news = News::findOrFail($id);
        $this->editingId = $news->id;
        $this->title = $news->title;
        $this->content = $news->content;
        $this->target_role = $news->target_role;
        $this->is_published = $news->is_published;
        $this->images = []; // Reset file input

        Flux::modal('news-form')->show();
    }

    public function create()
    {
        $this->reset(['title', 'content', 'images', 'editingId']);
        $this->setDefaultTargetRole();
        Flux::modal('news-form')->show();
    }

    public function delete($id)
    {
        $news = News::findOrFail($id);
        $news->delete();
        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Berita berhasil dihapus.');
    }

    public function removeImage($index)
    {
        array_splice($this->images, $index, 1);
    }

    public function viewDetail($id)
    {
        $this->selectedNews = News::with('author')->findOrFail($id);
        Flux::modal('news-detail')->show();
    }
}; ?>

<div class="w-full">
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">Berita & Pengumuman</flux:heading>
            <flux:subheading size="lg">Kelola informasi untuk
                {{ auth()->user()->hasRole('super_admin') ? 'Admin' : 'Wali Santri' }}.
            </flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">Tambah Berita</flux:button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($newsList as $news)
            <div
                class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden hover:shadow-lg transition-all duration-300 flex flex-col h-full group">
                @if($news->images && count($news->images) > 0)
                    <div class="relative aspect-video bg-zinc-100 dark:bg-zinc-800">
                        <img src="{{ asset('storage/' . $news->images[0]) }}" class="w-full h-full object-cover">
                        @if(count($news->images) > 1)
                            <div class="absolute bottom-2 right-2 bg-black/60 text-white text-xs px-2 py-1 rounded">
                                +{{ count($news->images) - 1 }} foto lainnya
                            </div>
                        @endif
                    </div>
                @else
                    <div
                        class="relative aspect-video bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400">
                        <flux:icon.newspaper class="w-12 h-12" />
                    </div>
                @endif

                <div class="p-4 flex flex-col flex-1">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="text-xs font-medium text-zinc-500 uppercase tracking-wide">
                            {{ $news->created_at->isoFormat('D MMMM Y') }} • Untuk: {{ ucfirst($news->target_role) }}
                        </div>

                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="-mr-2" />
                            <flux:menu>
                                <flux:menu.item wire:click="edit('{{ $news->id }}')" icon="pencil">Edit</flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item wire:click="delete('{{ $news->id }}')" icon="trash" variant="danger">Hapus
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-2 line-clamp-2 leading-tight">
                        {{ $news->title }}
                    </h3>

                    <p class="text-sm text-zinc-500 dark:text-zinc-400 line-clamp-3 mb-4">
                        {{ $news->content }}
                    </p>

                    <div class="mt-auto flex flex-col gap-4">
                        <flux:button variant="ghost" size="sm" class="w-full justify-center"
                            wire:click="viewDetail('{{ $news->id }}')">
                            Lihat Selengkapnya
                        </flux:button>

                        <div
                            class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex justify-between items-center text-xs text-zinc-400">
                            <span>Oleh: {{ $news->author->name }}</span>
                            @if(!$news->is_published)
                                <flux:badge variant="warning" size="sm">Draft</flux:badge>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-zinc-500">
                Belum ada berita yang diterbitkan.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $newsList->links() }}
    </div>

    {{-- Modal Detail --}}
    <flux:modal name="news-detail" class="md:w-[800px] w-full space-y-6">
        @if($selectedNews)
            <div class="space-y-6">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <flux:badge size="sm" variant="neutral">{{ ucfirst($selectedNews->target_role) }}</flux:badge>
                        <span
                            class="text-xs text-zinc-400">{{ $selectedNews->created_at->isoFormat('dddd, D MMMM Y • HH:mm') }}</span>
                    </div>
                    <flux:heading size="xl">{{ $selectedNews->title }}</flux:heading>
                    <flux:subheading>Oleh: {{ $selectedNews->author->name }}</flux:subheading>
                </div>

                <flux:separator />

                <div class="prose prose-zinc dark:prose-invert max-w-none">
                    {!! nl2br(e($selectedNews->content)) !!}
                </div>

                @if(!empty($selectedNews->images))
                    <div class="space-y-4">
                        <flux:label>Dokumentasi / Gambar</flux:label>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach($selectedNews->images as $imagePath)
                                <div
                                    class="rounded-xl overflow-hidden border border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800">
                                    <img src="{{ asset('storage/' . $imagePath) }}"
                                        class="w-full h-auto object-cover hover:scale-105 transition-transform duration-300">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex justify-end pt-6 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="primary">Tutup</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="news-form" class="md:w-[600px] w-full space-y-6">
        <div>
            <flux:heading size="lg">{{ $editingId ? 'Edit Berita' : 'Tambah Berita' }}</flux:heading>
            <flux:subheading>Sampaikan informasi penting ke
                {{ $target_role === 'admin' ? 'Seluruh Admin' : 'Wali Santri' }}.
            </flux:subheading>
        </div>

        <flux:separator />

        <div class="space-y-6" x-data="{ uploading: false }" x-on:livewire-upload-start="uploading = true"
            x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false">
            <flux:field>
                <flux:input wire:model="title" label="Judul Berita" placeholder="Contoh: Pengumuman Libur..." />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:editor wire:model="content" label="Isi Berita" description="Tuliskan berita secara detail..." />
                <flux:error name="content" />
            </flux:field>

            @role('super_admin')
            <flux:field>
                <flux:label>Target Penerima</flux:label>
                <flux:select wire:model="target_role">
                    <flux:select.option value="admin">Admin Pesantren</flux:select.option>
                    <flux:select.option value="guardian">Wali Santri (Global)</flux:select.option>
                </flux:select>
                <flux:error name="target_role" />
            </flux:field>
            @endrole

            <flux:field>
                <flux:label class="mb-2">Upload Gambar (Bisa banyak)</flux:label>

                <div class="mt-3 text-sm">
                    <input type="file" wire:model="images" multiple class="block w-full text-zinc-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-full file:border-0
                        file:text-sm file:font-semibold
                        file:bg-zinc-100 file:text-zinc-700
                        hover:file:bg-zinc-200 transition-colors
                        cursor-pointer">
                </div>
                <flux:error name="images.*" />

                <div x-show="uploading" class="text-xs text-blue-500 mt-3 italic flex items-center gap-2">
                    <flux:icon.arrow-path class="w-3 h-3 animate-spin" /> Sedang mengunggah...
                </div>

                @if ($images)
                    <div class="grid grid-cols-6 gap-2 mt-4 max-w-sm">
                        @foreach ($images as $image)
                            <div
                                class="relative aspect-square rounded-lg overflow-hidden shadow-sm border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                                <img src="{{ $image->temporaryUrl() }}" class="w-full h-full object-cover">
                                <button type="button" wire:click="removeImage({{ $loop->index }})"
                                    class="absolute top-1 right-1 bg-black/50 text-white rounded-full p-0.5 hover:bg-black/70 transition">
                                    <flux:icon.x-mark class="w-3 h-3" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:field>

            <div class="pt-4 mt-2">
                <flux:checkbox wire:model="is_published" label="Terbitkan Sekarang" />
            </div>

            <div class="flex justify-end gap-2 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="save">Simpan Berita</flux:button>
            </div>
        </div>
    </flux:modal>
</div>