<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function roles(){
        return view('admin.roles.index');
    }
}
