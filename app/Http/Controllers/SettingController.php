<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


class SettingController extends Controller
{
    //
    public function index()
    {
        $settings = \App\Models\Setting::all();
        return view('app.settings.index', compact('settings'));
    }


}
