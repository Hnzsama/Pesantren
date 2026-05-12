<?php

use App\Models\Achievement;
use App\Models\Student;
use App\Services\ImageCompressionService;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithFileUploads;
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

    use WithFileUploads;

    public $images = []; // Array of TemporaryUploadedFile
    public $tempImage; // Holder for current upload

    public $title = '';
    public $date_achieved = '';
    public $description = '';
    public $student_id = '';

    // For Cropper
    public $croppingImage = null;
    public $croppedImageBlob = null;

    public $students = [];

    public function mount()
    {
        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $this->students = Student::query()
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('full_name')
            ->get();

        $this->date_achieved = now()->format('Y-m-d');
    }

    public function updatedTempImage()
    {
        try {
            $this->validate([
                'tempImage' => 'image|max:10240', // 10MB limit per file
            ]);

            $this->images[] = $this->tempImage;

        } catch (\Exception $e) {
            Flux::toast(variant: 'danger', heading: 'Gagal Upload', text: $e->getMessage());
        }

        // Reset temp variable
        $this->reset('tempImage');
    }

    public function save(ImageCompressionService $compressor)
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'date_achieved' => 'required|date',
            'description' => 'required',
            'student_id' => 'nullable|exists:students,id',
            'images' => 'required|array|min:1',
        ]);

        $user = auth()->user();
        $schoolId = $user->hasRole('admin') ? $user->school_id : null;

        $imagePaths = [];
        foreach ($this->images as $imgData) {
            // Handle TemporaryUploadedFile
            if ($imgData instanceof \Illuminate\Http\UploadedFile) {
                $path = $compressor->storeAsWebP($imgData, 'achievements', Str::uuid());
                $imagePaths[] = $path;
            }
        }

        Achievement::create([
            'title' => $this->title,
            'school_id' => $schoolId,
            'student_id' => $this->student_id ?: null,
            'student_name' => $student?->full_name,
            'student_nis' => $student?->nis,
            'date_achieved' => $this->date_achieved,
            'description' => $this->description,
            'images' => $imagePaths,
        ]);

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Prestasi berhasil disimpan.');
        $this->redirect(route('achievements.index'), navigate: true);
    }

    public function removeImage($index)
    {
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }
}; ?>

<div class="w-full" x-data="{ 
    cropping: false, 
    cropper: null, 
    tempImageUrl: null,
    
    initCropper(file) {
        if (!file) return;
        this.cropping = true;
        
        let reader = new FileReader();
        reader.onload = (e) => {
            this.tempImageUrl = e.target.result;
            
            if(this.cropper) {
                this.cropper.destroy();
            }
            
            setTimeout(() => {
                let image = document.getElementById('image-to-crop');
                this.cropper = new Cropper(image, {
                    aspectRatio: NaN, 
                    viewMode: 1,
                    autoCropArea: 1,
                });
            }, 100);
        };
        reader.readAsDataURL(file);
    },
    
    crop() {
        if(!this.cropper) return;
        
        // Convert cropped canvas to Blob instead of Data URL (Base64)
        // to avoid Livewire payload size limits
        this.cropper.getCroppedCanvas().toBlob((blob) => {
            if (!blob) {
                alert('Crop failed'); 
                return;
            }
            
            let file = new File([blob], 'cropped_' + Date.now() + '.webp', { type: 'image/webp' });
            
            // Upload using Livewire upload API
            $wire.upload('tempImage', file, () => {
                // Success
                this.closeCropper();
            }, (error) => {
                console.error(error);
                alert('Upload gagal: ' + JSON.stringify(error));
                this.closeCropper();
            });
            
        }, 'image/webp', 0.8);
    },
    
    closeCropper() {
        this.cropping = false;
        if(this.cropper) {
            this.cropper.destroy();
            this.cropper = null;
        }
        this.tempImageUrl = null;
        document.getElementById('file-input').value = ''; 
    }
}">
    <div class="relative mb-6 w-full">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Tambah Prestasi</flux:heading>
                <flux:subheading size="lg" class="mb-6">Isi detail prestasi santri.</flux:subheading>
            </div>
        </div>
        <flux:separator variant="subtle" />
    </div>

    <div class="space-y-6 max-w-3xl">
        <flux:input wire:model="title" label="Judul Prestasi" placeholder="Juara 1 Lomba..." />

        <div class="grid grid-cols-2 gap-4">
            <flux:input type="date" wire:model="date_achieved" label="Tanggal" />
            <flux:select wire:model="student_id" label="Santri (Opsional)" placeholder="Pilih Santri..."
                variant="listbox" searchable>
                <flux:select.option value="">-- Tidak Ada --</flux:select.option>
                @foreach($students as $student)
                    <flux:select.option value="{{ $student->id }}">{{ $student->full_name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:textarea wire:model="description" label="Deskripsi" placeholder="Keterangan lengkap..." />

        <!-- Image Upload Section -->
        <div>
            <flux:label>Dokumentasi (Gambar)</flux:label>

            <div class="flex flex-wrap gap-2 mb-2">
                @foreach($images as $index => $imgData)
                    <div class="relative group w-20 h-20 rounded overflow-hidden border border-zinc-200">
                        @if(is_object($imgData) && method_exists($imgData, 'temporaryUrl'))
                            <img src="{{ $imgData->temporaryUrl() }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full bg-zinc-100 flex items-center justify-center text-xs">Error</div>
                        @endif
                        <button wire:click="removeImage({{ $index }})"
                            class="absolute top-0 right-0 bg-red-500 text-white p-0.5 rounded-bl opacity-0 group-hover:opacity-100 transition">
                            <flux:icon.x-mark class="w-3 h-3" />
                        </button>
                    </div>
                @endforeach

                <label
                    class="w-20 h-20 flex flex-col items-center justify-center border-2 border-dashed border-zinc-300 rounded hover:bg-zinc-50 cursor-pointer transition text-zinc-400 hover:text-zinc-600">
                    <flux:icon.plus class="w-6 h-6" />
                    <span class="text-[10px] mt-1">Add</span>
                    <input type="file" id="file-input" class="hidden" accept="image/*"
                        @change="initCropper($event.target.files[0])">
                </label>
            </div>
            <flux:error name="images" />
            <flux:error name="tempImage" />
        </div>

        <div class="flex justify-end gap-2 mt-8">
            <flux:button href="{{ route('achievements.index') }}" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button wire:click="save" variant="primary">Simpan</flux:button>
        </div>
    </div>

    <!-- Cropper Modal (Custom Overlay) -->
    <div x-show="cropping" style="display: none;"
        class="fixed inset-0 z-[50] flex items-center justify-center bg-black/80 p-4">
        <div
            class="bg-white dark:bg-zinc-900 rounded-lg shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Crop Image</h3>
                <button @click="closeCropper()" class="text-zinc-500 hover:text-zinc-700">
                    <flux:icon.x-mark class="w-5 h-5" />
                </button>
            </div>

            <div class="p-4 flex-1 overflow-auto bg-black flex justify-center">
                <div class="max-w-full max-h-[60vh]">
                    <img id="image-to-crop" :src="tempImageUrl" class="max-w-full block">
                </div>
            </div>

            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700 flex justify-end gap-2">
                <flux:button @click="closeCropper()" variant="ghost">Cancel</flux:button>
                <flux:button @click="crop()" variant="primary">Crop & Add</flux:button>
            </div>
        </div>
    </div>
</div>