<?php

use JetBrains\PhpStorm\NoReturn;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $user;
    // File uploads
    public $profile_picture;
    public $profile_picture_preview;
    public array $event = [];


    public function mount($user, $event = [])
    {
        $this->user = $user;
        $this->event = $event;
    }

    public function updatedProfilePicture()
    {
        // Generate a preview of the uploaded profile picture
        $this->profile_picture_preview = $this->profile_picture->temporaryUrl();
    }

    #[NoReturn]
    public function uploadAvatar()
    {
        try {
            $this->validate([
                'profile_picture' => 'required|image|max:5080', // 5MB Max
            ]);

            // Store profile picture
            $profilePicturePath = $this->profile_picture
                ? $this->profile_picture->store('avatars', 'public')
                : null;

            if ($profilePicturePath) {
                $this->user->update(['photo_path' => explode('/', $profilePicturePath)[1]]);
            }

            $this->reset('profile_picture_preview');

            if(! empty($this->event)){
                $this->dispatch($this->event['event']);
            }

            $this->dispatch('refresh');
            $this->dispatch('success', 'Profile picture updated successfully');
        }Catch (Exception $e) {
            $this->dispatch('error', 'Failed to update profile picture: ' . $e->getMessage());
        }
    }
}; ?>

<div>
    <div class="card">
        <div class="card mb-0">
            <div class="card-header d-flex">
                <h4 class="mb-0 f-w-600">Upload Profile Picture</h4>
                <a href="#"><i class="me-2" data-feather="printer"></i>Print</a>
            </div>

        </div>
        <div class="card-body">

            <div class="row">
                <div class="col-sm-4 ">
                    <div class="profile-title media mb-4">
                        @if($user->photo_path)
                            <img src="{{ asset('storage/avatars/' . $user->photo_path) }}"
                                  class="me-3 rounded-circle" width="80" height="80"
                                 alt="Current Avatar">
                        @endif
                        <div class="media-body ps-3">
                            <h5>{{ ucfirst($user->first_name) }} {{ ucfirst($user->last_name) }}</h5>
                            <p>{{ unSlugify($user->roles[0]->name) }}</p>
                        </div>
                        <hr>

                    </div>
                    <x-button
                        wire:click="uploadAvatar"
                        class="btn-primary-gradien w-100"
                        target="uploadAvatar">
                        Save Uploaded Image
                    </x-button>
                </div>
                <div class="col-sm-8">
                    {{-- Dropzone-style upload area --}}
                    <style>
                        .dropbox {
                            margin-right: auto;
                            margin-left: auto;
                            padding: 50px;
                            border: 2px dashed var(--theme-deafult);
                            border-radius: 15px;
                            -webkit-border-image: none;
                            -o-border-image: none;
                            border-image: none;
                            background: rgba(115, 102, 255, 0.1);
                            box-sizing: border-box;
                            min-height: 150px;
                            position: relative;
                            align-items: center;
                            justify-content: center;
                            flex-direction: column;
                            cursor: pointer;
                            transition: background .2s, border-color .2s;
                            text-align: center;
                        }
                        .dropboxz {
                            width: 100%;
                            max-width: 400px;
                            height: 200px;
                            margin: 0 auto 1.5rem;
                            border: 3px dashed #17a2b8;
                            border-radius: 8px;
                            background: #f0fcff;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            flex-direction: column;
                            cursor: pointer;
                            transition: background .2s, border-color .2s;
                            text-align: center;
                        }
                        .dropbox:hover {
                            background: #e0f8ff;
                            border-color: #0e5e6f;
                        }
                        .dropbox .icon {
                            font-size: 4rem;
                            color: #17a2b8;
                            margin-bottom: .5rem;
                        }
                        .dropbox .label {
                            font-size: 1.125rem;
                            color: #117a8b;
                            margin-bottom: .25rem;
                        }
                        .dropbox .note {
                            font-size: .875rem;
                            color: #6c757d;
                        }
                        .dropbox img.preview {
                            max-width: 100%;
                            max-height: 100%;
                            object-fit: contain;
                            border-radius: 4px;
                        }
                    </style>

                    <div
                        class="dropbox"
                        onclick="document.getElementById('profile_picture').click()"
                    >

                        {{-- Normal dropbox content (preview or prompt) when NOT loading --}}
                        <div wire:target="$profile_picture_preview" class="w-100">
                            @if ($profile_picture_preview)
                                <img
                                    src="{{ $profile_picture_preview }}"
                                    class="preview"
                                    alt="Preview"
                                >
                            @else
                                <i class="icon feather icon-cloud-up"></i>
                                <div class="label">Drop files here or click to upload</div>
                                <div class="note">(JPG, PNG up to 5 MB)</div>
                            @endif
                        </div>

                        <input
                            type="file"
                            id="profile_picture"
                            wire:model="profile_picture"
                            accept="image/*"
                            style="display: none"
                        >
                    </div>

                </div>
            </div>

            {{-- Save button --}}



        </div>
    </div>
</div>




