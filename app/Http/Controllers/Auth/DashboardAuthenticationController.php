<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;


class DashboardAuthenticationController extends Controller
{
    // Display the login view.
    public function create()
    {
        return view('auth.login');
    }

    // Handle an incoming authentication request.
    public function store(Request $request)
    {

        $validate = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:1',
        ]);

        if ($validate->fails()) {
            dd($validate->errors()->first());
            return redirect()->back()->with('error', $validate->errors()->first())->withInput();
        }

        $credentials = request()->only('email', 'password');

        if (Auth()->attempt($credentials)) {
            return redirect()->route('dashboard');
        } else {
            return redirect()->back()->with(['error' => 'Invalid Credentials'])->withInput();
        }
    }

    // Handle forgot password request
    public function forgot()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->with('error', 'No user found with that email address.');
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $token,
                'created_at' => Carbon::now()
            ]
        );

        $resetLink = route('password.reset', $token);

        Mail::to($request->email)->send(
            new PasswordResetMail($resetLink, $user)
        );

        return back()->with('status', 'Password reset link sent to your email.');
    }

    public function showResetForm($token)
    {
        $record = DB::table('password_reset_tokens')
            ->where('token', $token)
            ->first();

        if (! $record) {
            abort(404);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $record->email
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'token' => 'required'
        ]);

        $record = DB::table('password_reset_tokens')
            ->where([
                'email' => $request->email,
                'token' => $request->token
            ])
            ->first();

        if (! $record) {
            return back()->with('error', 'Invalid token or email address.');
        }

        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password)
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return redirect()->route('login')
            ->with('status', 'Password reset successfully. You can now login.');
    }

    // Destroy an authenticated session.
    public function destroy(Request $request)
    {
        Auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
