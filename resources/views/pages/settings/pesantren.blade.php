<?php

use App\Models\School;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;

    public School $school;

    public $name;
    public $address;
    public $phone;
    public $email;
    public $website;
    
    public $tempImage; // For cropper
    public $profile_photo_path; // The actual path in DB

    public function mount()
    {
        $this->school = auth()->user()->school;
        $this->name = $this->school->name;
        $this->address = $this->school->address;
        $this->phone = $this->school->phone;
        $this->email = $this->school->email;
        $this->website = $this->school->website;
        $this->profile_photo_path = $this->school->profile_photo_path;
    }

    public function updatedTempImage()
    {
        $this->validate([
            'tempImage' => 'image|max:2048',
        ]);
    }

    public function save()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
        ]);

        if ($this->tempImage) {
            // Delete old photo if exists
            if ($this->school->profile_photo_path) {
                Storage::disk('public')->delete($this->school->profile_photo_path);
            }
            $validated['profile_photo_path'] = $this->tempImage->store('school-profiles', 'public');
        }

        $this->school->update($validated);
        $this->profile_photo_path = $this->school->profile_photo_path;
        $this->reset('tempImage');

        Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Data pesantren berhasil diperbarui.');
    }
}; ?>

<section class="w-full" x-data="{ 
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
                let image = document.getElementById('image-to-crop-pesantren');
                this.cropper = new Cropper(image, {
                    aspectRatio: 1, 
                    viewMode: 1,
                    autoCropArea: 1,
                });
            }, 100);
        };
        reader.readAsDataURL(file);
    },
    
    crop() {
        if(!this.cropper) return;
        
        this.cropper.getCroppedCanvas().toBlob((blob) => {
            if (!blob) return;
            
            let file = new File([blob], 'profile_' + Date.now() + '.webp', { type: 'image/webp' });
            
            $wire.upload('tempImage', file, () => {
                this.closeCropper();
            }, (error) => {
                console.error(error);
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
        document.getElementById('profile-file-input').value = ''; 
    }
}">
    @include('partials.settings-heading', [
        'title' => __('Pesantren Settings'),
        'description' => __('Manage your pesantren public information and profile photo.'),
    ])

    <x-pages::settings.layout :heading="__('Pesantren')" :subheading="__('Update pesantren data and profile photo')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <flux:field>
                <flux:label>{{ __('Profile Photo') }}</flux:label>
                <div class="flex items-center gap-4">
                    <div class="relative group">
                        @if ($tempImage)
                            <img src="{{ $tempImage->temporaryUrl() }}" class="size-24 rounded-2xl object-cover border border-zinc-200 dark:border-zinc-700">
                        @elseif ($profile_photo_path)
                            <img src="{{ Storage::url($profile_photo_path) }}" class="size-24 rounded-2xl object-cover border border-zinc-200 dark:border-zinc-700">
                        @else
                            <div class="size-24 rounded-2xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center border border-dashed border-zinc-300 dark:border-zinc-700">
                                <flux:icon name="building-office-2" class="size-8 text-zinc-400" />
                            </div>
                        @endif
                        
                        <label for="profile-file-input" class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl cursor-pointer">
                            <flux:icon name="camera" class="text-white size-6" />
                        </label>
                    </div>

                    <div class="flex-1">
                        <input type="file" id="profile-file-input" class="hidden" accept="image/*"
                            @change="initCropper($event.target.files[0])">
                        <flux:button variant="ghost" size="sm" @click="document.getElementById('profile-file-input').click()">
                            {{ __('Ganti Foto') }}
                        </flux:button>
                        <flux:text size="sm" class="mt-1">{{ __('JPEG, PNG or WebP. Max 2MB.') }}</flux:text>
                    </div>
                </div>
                <flux:error name="tempImage" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Nama Pesantren') }}</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Alamat') }}</flux:label>
                <flux:input wire:model="address" />
                <flux:error name="address" />
            </flux:field>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Telepon') }}</flux:label>
                    <flux:input wire:model="phone" />
                    <flux:error name="phone" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input wire:model="email" type="email" />
                    <flux:error name="email" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Website') }}</flux:label>
                <flux:input wire:model="website" placeholder="https://..." />
                <flux:error name="website" />
            </flux:field>

            <div class="flex items-center gap-4">
                <flux:button type="submit" variant="primary" class="w-full md:w-auto">{{ __('Save Changes') }}</flux:button>
                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-pages::settings.layout>

    <!-- Cropper Modal -->
    <div x-show="cropping" style="display: none;"
        class="fixed inset-0 z-[50] flex items-center justify-center bg-black/80 p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                <h3 class="text-lg font-semibold">Potong Foto Profil</h3>
                <button @click="closeCropper()" class="text-zinc-500 hover:text-zinc-700">
                    <flux:icon.x-mark class="w-5 h-5" />
                </button>
            </div>

            <div class="p-4 flex-1 overflow-auto bg-black flex justify-center">
                <div class="max-w-full max-h-[60vh]">
                    <img id="image-to-crop-pesantren" :src="tempImageUrl" class="max-w-full block shadow-2xl">
                </div>
            </div>

            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700 flex justify-end gap-2">
                <flux:button @click="closeCropper()" variant="ghost">Batal</flux:button>
                <flux:button @click="crop()" variant="primary">Potong & Simpan</flux:button>
            </div>
        </div>
    </div>
</section>
