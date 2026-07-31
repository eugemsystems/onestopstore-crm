<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $user->getRoleNames()->first();

        // --- Stats for each role ---
        $stats = [];

        // --- Notifications (cache per user) ---
        $notifications = \Cache::remember('user_notifications_' . $user->id, 30, function () use($user) {
            return $user->notifications()->latest()->limit(5)->get();
        });

        // --- Return everything needed for the view ---
        return view('dashboard', [
            'role'                  => $role,
            'stats'                 => $stats,
            'notifications'         => $notifications,
        ]);
    }

    public function index_(Request $request)
    {
        return view('dashboard', [
            'user' => $request->user(),
        ]);
    }

}
