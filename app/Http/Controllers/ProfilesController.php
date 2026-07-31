<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfilesController extends Controller
{
    public function profile()
    {
        $user = getCachedAuthUser();
        return view('admin.profile.user-profile', compact('user'));
    }
}
