<?php

namespace App\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\User;

class UserDatatable extends DataTableComponent
{
    protected $model = User::class;

    public function builder(): Builder
    {
        return User::query();
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
        $this->setDefaultSort('created_at', 'desc');
    }

    protected function resolveUser($key): ?User
    {
        if (is_numeric($key)) {
            return User::find((int)$key);
        }
        return User::where('uuid', (string)$key)->first();
    }

    public function delete($key)
    {
        $user = $this->resolveUser($key);
        if ($user) {
            $user->delete();
            $this->dispatch('success', 'User deleted successfully.');
        } else {
            $this->dispatch('error', 'User not found.');
        }
    }

    public function verify($key)
    {
        if (!auth()->user()?->can('update user')) {
            $this->dispatch('error', 'Unauthorized');
            return;
        }
        $user = $this->resolveUser($key);
        if (!$user) { $this->dispatch('error', 'User not found.'); return; }
        $user->forceFill([
            'email_verified_at' => now(),
            'account_status' => 'active',
        ])->save();
        $this->dispatch('success', 'User activated.');
    }

    public function unverify($key)
    {
        if (!auth()->user()?->can('update user')) {
            $this->dispatch('error', 'Unauthorized');
            return;
        }
        $user = $this->resolveUser($key);
        if (!$user) { $this->dispatch('error', 'User not found.'); return; }
        $user->forceFill([
            'email_verified_at' => null,
            'account_status' => 'pending',
        ])->save();
        $this->dispatch('success', 'User unverified.');
    }

    public function columns(): array
    {
        return [
            Column::make("Action", "uuid")
                ->view('actions.users')
                ->sortable(),
            Column::make("Avatar", "photo_path")
                ->format(function ($row) {
                    return '<img height=40 width=40 src="' . asset('storage/avatars/' . $row) . '" alt="Avatar" class="img-thumbnail img-fix">';
                })->html(),
            Column::make("First name", "first_name")
                ->sortable()->searchable(),
            Column::make("Last name", "last_name")
                ->sortable()->searchable(),
            Column::make("Cell number", "phone_number")
                ->sortable()->searchable(),
            Column::make("Email", "email")
                ->sortable()->searchable(),
            Column::make("Acc Status", "account_status")
                ->sortable()->searchable(),
            Column::make("Created at", "created_at")
                ->sortable()->isHidden(),
            Column::make("Updated at", "updated_at")
                ->sortable()->isHidden(),
        ];
    }
}
