<div x-data="{ show: false }" x-cloak>
    @can('view user')
        {{-- show --}}
        <a x-show="!show" href="{{ route('app.users.show', $value) }}" style="font-size:20px;">
            <i class="icon-eye"></i>
        </a>
    @endcan

    @can('update user')
        {{-- Edit link stays always visible --}}
        <a x-show="!show" href="{{ route('app.users.edit', $value) }}" style="font-size:20px;">
            <i class="icon-pencil-alt"></i>
        </a>

        {{-- Status indicator + toggle --}}
        @php($isActive = strtolower((string)($row->account_status ?? '')) === 'active')
        @if($isActive)
            <a x-show="!show" href="#" wire:click="unverify('{{ $value }}')" title="Active (click to unverify)" style="font-size:20px; color:green;">
                <i class="icon-check"></i>
            </a>
        @else
            <a x-show="!show" href="#" wire:click="verify('{{ $value }}')" title="Not active (click to activate)" style="font-size:20px; color:#b45050;">
                <i class="icon-close"></i>
            </a>
        @endif
    @endcan

    @can('delete user')
            {{-- Trash icon: only when show is false; clicking sets show = true --}}
            <a
                href="#"
                x-show="!show"
                @click.prevent="show = true"
                style="font-size:20px; color:darkred;"
            >
                <i class="icon-trash"></i>
            </a>

            {{-- Confirmation buttons: only when show is true --}}
            <div x-show="show" style="display: inline-block;">
                <div class="btn-group" role="group" aria-label="Confirm delete?">
                    <button
                        type="button"
                        class="btn btn-danger-gradien"
                        @click.prevent="show = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary-gradien"
                        {{-- example Livewire delete call --}}
                        wire:click="delete('{{ $value }}')"
                    >
                        Delete
                    </button>
                </div>
            </div>
    @endcan
</div>
