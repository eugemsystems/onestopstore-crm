<?php

namespace App\Http\Controllers;

use App\Models\User;

class UsersController extends Controller
{

    public function activate(User $user)
    {
        $user->forceFill([
            'account_status' => 'active',
            // add 'activated_at' if you later add this column
        ])->save();

        //notify the user about activation
        $user->notify(new \App\Notifications\UserActivatedNotification($user));

        return back()->with('success', "User {$user->email} activated.");
    }

    public function users(){
        return view('admin.users.index');
    }

    public function edit(User $user)
    {
        // pass the model (and if you really need the raw id, you can still do $user->id)
        return view('admin.users.edit', compact('user'));
    }

    public function show(User $user)
    {
        return view('admin.users.show', compact('user'));
    }

    public function roles(){

    }

    public function permissions(){

    }

}
