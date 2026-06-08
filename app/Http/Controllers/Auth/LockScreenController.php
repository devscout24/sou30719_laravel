<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LockScreenController extends Controller
{
    public function lock()
    {
        session(['screen_locked' => true]);

        return redirect()->route('lock.screen');
    }

    public function show()
    {
        if (! session('screen_locked')) {
            return redirect()->route('dashboard');
        }

        return view('auth.lock-screen');
    }

    public function unlock(Request $request)
    {
        $request->validate(['password' => 'required']);

        if (! Hash::check($request->password, Auth::user()->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        session()->forget('screen_locked');

        return redirect()->intended(route('dashboard'));
    }
}
