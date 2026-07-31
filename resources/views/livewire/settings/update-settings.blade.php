<?php

use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

use App\Models\Setting;

new class extends Component
{
    public array $general = [];
    public array $contact = [];
    public array $finance = [];
    public array $other = [];
    public string $activeTab = 'General';

    public function mount()
    {
        $this->general = [
            'app_name' => Setting::value('app_name') ?? '',
            'app_description' => Setting::value('app_description') ?? '',
            'app_version' => Setting::value('app_version') ?? '',
            'app_timezone' => Setting::value('app_timezone') ?? '',
            'app_currency' => Setting::value('app_currency') ?? '',
            'app_currency_symbol' => Setting::value('app_currency_symbol') ?? '',
        ];

        $this->contact = [
            'app_contact_email' => Setting::value('app_contact_email') ?? '',
            'app_contact_phone' => Setting::value('app_contact_phone') ?? '',
            'app_contact_address' => Setting::value('app_contact_address') ?? '',
        ];

        $this->finance = [
            'vat_percentage' => Setting::value('vat_percentage') ?? '',
        ];

        $this->other = [
            'send_emails' => Setting::value('send_emails') == '1',
            'send_sms' => Setting::value('send_sms') == '1',
            'send_slack' => Setting::value('send_slack') == '1',
            'send_telegram' => Setting::value('send_telegram') == '1',
        ];
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function saveGeneral()
    {
        $validated = $this->validate([
            'general.app_name'        => 'required|string|max:255',
            'general.app_description' => 'nullable|string',
            'general.app_version'     => 'nullable|string|max:20',
            'general.app_timezone'    => 'nullable|string',
            'general.app_currency'    => 'required|string|max:10',
            'general.app_currency_symbol' => 'nullable|string|max:5',
        ]);
        foreach ($this->general as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        session()->flash('general_saved', 'General settings updated.');
    }

    public function saveContact()
    {
        $validated = $this->validate([
            'contact.app_contact_email'     => 'nullable|email',
            'contact.app_contact_phone'     => 'nullable|string',
            'contact.app_contact_address'   => 'nullable|string',
        ]);
        foreach ($this->contact as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        session()->flash('contact_saved', 'Contact settings updated.');
    }

    public function saveFinance()
    {
        $validated = $this->validate([
            'finance.vat_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        foreach ($this->finance as $key => $value) {
            // Store booleans as '1'/'0'
            $val = is_bool($value) ? ($value ? '1' : '0') : $value;
            Setting::updateOrCreate(['key' => $key], ['value' => $val]);
        }
        session()->flash('finance_saved', 'Finance settings updated.');
    }

    public function saveOther()
    {
        $validated = $this->validate([
            'other.send_emails' => 'nullable|boolean',
            'other.send_sms' => 'nullable|boolean',
            'other.send_slack' => 'nullable|boolean',
            'other.send_telegram' => 'nullable|boolean',
        ]);
        foreach ($this->other as $key => $value) {
            // Store booleans as '1'/'0'
            $val = is_bool($value) ? ($value ? '1' : '0') : $value;
            Setting::updateOrCreate(['key' => $key], ['value' => $val]);
        }
        session()->flash('other_saved', 'Other settings updated.');
    }

};
?>
<div>
    <div class="cardx">
        <div class="card-bodyx">
            <div class="row setting-page-con">
                <!-- Sidebar -->
                <div class="col-xxl-3 box-col-12 left-sidebar">
                    <div class="md-sidebar sticky-sidebar">
                        <div class="job-left-aside custom-scrollbar">
                            <div class="file-sidebar">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                                            <ul class="custom-scrollbar">
                                                <li>
                                                    <a class="nav-link btn main {{ $activeTab === 'General' ? 'active' : '' }}"
                                                       wire:click="setTab('General')">
                                                        <i data-feather="settings"></i> General
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="nav-link btn main {{ $activeTab === 'Contact' ? 'active' : '' }}"
                                                       wire:click="setTab('Contact')">
                                                        <i data-feather="phone"></i> Contact
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="nav-link btn main {{ $activeTab === 'Finance' ? 'active' : '' }}"
                                                       wire:click="setTab('Finance')">
                                                        <i data-feather="dollar-sign"></i> Finance
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="nav-link btn main {{ $activeTab === 'Other' ? 'active' : '' }}"
                                                       wire:click="setTab('Other')">
                                                        <i data-feather="settings"></i> Other
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-xxl-9 col-md-12 box-col-12">
                    {{-- GENERAL TAB --}}
                    @if($activeTab === 'General')
                        <form wire:submit.prevent="saveGeneral" enctype="multipart/form-data" class="needs-validation user-add">
                            @if(session('general_saved'))
                                <div class="alert alert-success mb-3">{{ session('general_saved') }}</div>
                            @endif
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-group row">
                                        <label class="col-md-2">App Name <span class="text-danger">*</span></label>
                                        <div class="col-md-10 mb-3">
                                            <input class="form-control" type="text" wire:model.defer="general.app_name">
                                            @error('general.app_name')
                                                <span class="invalid-feedback d-block" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-2">Description</label>
                                        <div class="col-md-10 mb-3">
                                            <input class="form-control" type="text" wire:model.defer="general.app_description">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-2">Version</label>
                                        <div class="col-md-10 mb-3">
                                            <input class="form-control" type="text" wire:model.defer="general.app_version">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-2">Timezone</label>
                                        <div class="col-md-10 mb-3">
                                            <input class="form-control" type="text" wire:model.defer="general.app_timezone">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-2">Currency</label>
                                        <div class="col-md-10 mb-3">
                                            <input class="form-control" type="text" wire:model.defer="general.app_currency">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-2">Symbol</label>
                                        <div class="col-md-10 mb-3">
                                            <input class="form-control" type="text" wire:model.defer="general.app_currency_symbol">
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <x-button target="saveGeneral" class="btn btn-primary spinner-btn" type="submit">Save</x-button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif

                    {{-- CONTACT TAB --}}
                    @if($activeTab === 'Contact')
                        <form wire:submit.prevent="saveContact" class="needs-validation user-add">
                            @if(session('contact_saved'))
                                <div class="alert alert-success mb-3">{{ session('contact_saved') }}</div>
                            @endif
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-group row">
                                        <label class="col-md-6">Contact Email</label>
                                        <div class="col-md-6 mb-3">
                                            <input class="form-control" type="email" wire:model.defer="contact.app_contact_email">
                                            @error('contact.app_contact_email')
                                                <span class="invalid-feedback d-block" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-6">Contact Phone</label>
                                        <div class="col-md-6 mb-3">
                                            <input class="form-control" type="text" wire:model.defer="contact.app_contact_phone">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-6">Contact Address</label>
                                        <div class="col-md-6 mb-3">
                                            <input class="form-control" type="text" wire:model.defer="contact.app_contact_address">
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <x-button target="saveContact" class="btn btn-primary spinner-btn" type="submit">Save</x-button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif

                    {{-- FINANCE TAB --}}
                    @if($activeTab === 'Finance')
                        <form wire:submit.prevent="saveFinance" class="needs-validation user-add">
                            @if(session('finance_saved'))
                                <div class="alert alert-success mb-3">{{ session('finance_saved') }}</div>
                            @endif
                            <div class="card">
                                <div class="card-body">

                                    <div class="form-group row">
                                        <label class="col-md-6">VAT (%)</label>
                                        <div class="col-md-6 mb-3">
                                            <input class="form-control" type="number" wire:model.defer="finance.vat_percentage">
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <x-button target="saveFinance" class="btn btn-primary spinner-btn" type="submit">Save</x-button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif

                    {{-- OTHER TAB --}}
                    @if($activeTab === 'Other')
                        <form wire:submit.prevent="saveOther" class="needs-validation user-add">
                            @if(session('other_saved'))
                                <div class="alert alert-success mb-3">{{ session('other_saved') }}</div>
                            @endif
                            <div class="card">
                                <div class="card-body">

                                    <div class="form-group row">
                                        <label class="col-md-2">Send Emails</label>
                                        <div class="col-md-10 mb-3">
                                            <input type="checkbox" wire:model.defer="other.send_emails">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-2">Send SMS</label>
                                        <div class="col-md-10 mb-3">
                                            <input type="checkbox" wire:model.defer="other.send_sms">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-md-2">Send Slack</label>
                                        <div class="col-md-10 mb-3">
                                            <input type="checkbox" wire:model.defer="other.send_slack">
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-md-2">Send Telegram</label>
                                        <div class="col-md-10 mb-3">
                                            <input type="checkbox" wire:model.defer="other.send_telegram">
                                        </div>
                                    </div>


                                    <div class="text-end">
                                        <x-button target="saveOther" class="btn btn-primary spinner-btn" type="submit">Save</x-button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif

                </div>

            </div>
        </div>
    </div>
</div>
