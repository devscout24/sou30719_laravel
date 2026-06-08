<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

class SystemController extends Controller
{
    // systemSettings view
    public function systemSettings()
    {
        $settings = CompanySetting::firstOrFail();
        return view('backend.layouts.system.settings', compact('settings'));
    }

    // systemSettingsUpdate
    public function systemSettingsUpdate(Request $request)
    {
        $validate = Validator::make($request->all(), [
            // Company Info
            'companyname' => 'nullable|string|max:255',
            'cwebsite' => 'nullable|url',
            'cemail' => 'nullable|email',
            'chotline' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'description' => 'nullable|string',

            // Logo
            'company_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            // App Links
            'downloadLinkPlay' => 'nullable|url',
            'downloadLinkApple' => 'nullable|url',

            // Social
            'social_fb' => 'nullable|url',
            'social_ln' => 'nullable|url',
            'social_yt' => 'nullable|url',
            'social_tw' => 'nullable|string|max:255',
            'social_tt' => 'nullable|string|max:255',
            'social_th' => 'nullable|string|max:255',

            // Password
            'current_password' => 'required',
        ]);

        if ($validate->fails()) {
            return back()->with('error', $validate->errors()->first())->withInput();
        }

        // 2. Verify Current Password
        if (!Hash::check($request->current_password, Auth::user()->password)) {

            return back()->with('error', 'The provided password was incorrect.')->withInput();
        }


        // 3. Get Settings (Single Row)
        $settings = CompanySetting::firstOrCreate(['id' => 1]);


        // 4. Handle Logo Upload
        if ($request->hasFile('company_logo')) {
            $oldImage = $settings->logo != 'logo.png' ? $settings->logo : null;
            $logo = $this->uploadImage($request->file('company_logo'), $oldImage, 'uploads/company/', 150, 150, 'logo-' . time());
        }


        // 5. Update Database
        $settings->update([

            // Company Info
            'company_name' => $request->companyname,
            'website'      => $request->cwebsite,
            'email'        => $request->cemail,
            'hotline'      => $request->chotline,
            'address'      => $request->address,
            'description'  => $request->description,

            'logo' => $logo ?? $settings->logo,

            // App Links
            'play_store_link'  => $request->downloadLinkPlay,
            'apple_store_link' => $request->downloadLinkApple,

            // Social
            'facebook' => $request->social_fb,
            'linkedin' => $request->social_ln,
            'youtube'  => $request->social_yt,
            'twitter'  => $request->social_tw,
            'tiktok'   => $request->social_tt,
            'threads'  => $request->social_th,
        ]);


        // 6. Redirect with Success
        return redirect()
            ->back()
            ->with('success', 'System settings updated successfully!');
    }

    // Credential Settings View
    public function credentialSettings($type)
    {
        if ($type == null || !in_array($type, ['Mail', 'Stripe', 'GoogleCloud', 'Reverb'])) {
            return redirect()->route('dashboard')->with('error', 'Invalid Credential Type');
        }

        $data = [];

        switch ($type) {

            case 'Mail':

                $data['mail'] = [
                    'mailer'     => config('mail.default'),
                    'host'       => config('mail.mailers.smtp.host'),
                    'port'       => config('mail.mailers.smtp.port'),
                    'username'   => config('mail.mailers.smtp.username'),
                    'password'   => config('mail.mailers.smtp.password'),
                    'encryption' => config('mail.mailers.smtp.encryption'),
                    'from_addr'  => config('mail.from.address'),
                    'from_name'  => config('mail.from.name'),
                ];

                break;


            case 'Stripe':

                $data['stripe'] = [
                    'key'    => config('services.stripe.key'),
                    'secret' => config('services.stripe.secret'),
                ];

                break;


            case 'GoogleCloud':

                $data['google'] = [
                    'key' => config('services.google.key'),
                ];

                break;


            case 'Reverb':

                $data['reverb'] = [
                    'id'     => config('broadcasting.connections.reverb.app_id'),
                    'key'    => config('broadcasting.connections.reverb.key'),
                    'secret' => config('broadcasting.connections.reverb.secret'),
                ];

                break;
        }

        return view('backend.layouts.system.credential', compact('type', 'data'));
    }

    public function credentialSettingsUpdate(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'type' => 'required|string',
            'current_password' => 'required',
        ]);

        if ($validate->fails()) {
            return back()->with('error', $validate->errors()->first())->withInput();
        }

        // Verify Current Password
        if (!Hash::check($request->current_password, Auth::user()->password)) {
            return back()->with('error', 'The provided password was incorrect.')->withInput();
        }

        $type = $request->type;

        switch ($type) {

            case 'Mail':

                $request->validate([
                    'mail_mailer'       => 'required|string',
                    'mail_host'         => 'required|string',
                    'mail_port'         => 'required|integer',
                    'mail_username'     => 'nullable|string',
                    'mail_password'     => 'nullable|string',
                    'mail_encryption'   => 'nullable|string',
                    'mail_from_address' => 'required|email',
                    'mail_from_name'    => 'required|string',
                ]);

                $envData = [
                    'MAIL_MAILER'       => $request->mail_mailer,
                    'MAIL_HOST'         => $request->mail_host,
                    'MAIL_PORT'         => $request->mail_port,
                    'MAIL_USERNAME'     => $request->mail_username,
                    'MAIL_PASSWORD'     => $request->mail_password,
                    'MAIL_ENCRYPTION'   => $request->mail_encryption,
                    'MAIL_FROM_ADDRESS' => '"' . $request->mail_from_address . '"',
                    'MAIL_FROM_NAME'    => '"' . $request->mail_from_name . '"',
                ];

                break;


            case 'Stripe':

                $request->validate([
                    'stripe_key'    => 'required|string',
                    'stripe_secret' => 'required|string',
                ]);

                $envData = [
                    'STRIPE_KEY'    => $request->stripe_key,
                    'STRIPE_SECRET' => $request->stripe_secret,
                ];

                break;


            case 'GoogleCloud':

                $request->validate([
                    'google_app_key' => 'required|string',
                ]);

                $envData = [
                    'GOOGLE_CLOUD_KEY' => $request->google_app_key,
                ];

                break;


            case 'Reverb':

                $request->validate([
                    'reverb_app_id'     => 'required|string',
                    'reverb_app_key'    => 'required|string',
                    'reverb_app_secret' => 'required|string',
                ]);

                $envData = [
                    'REVERB_APP_ID'     => $request->reverb_app_id,
                    'REVERB_APP_KEY'    => $request->reverb_app_key,
                    'REVERB_APP_SECRET' => $request->reverb_app_secret,
                ];

                break;


            default:
                return back()->with('error', 'Invalid Credential Type');
        }


        // Update .env
        $this->setEnvValues($envData);

        // Clear cache after update
        Artisan::call('config:clear');
        Artisan::call('cache:clear');

        return back()->with('success', $type . ' credentials updated successfully!');
    }

    private function setEnvValues(array $values)
    {
        $envFile = base_path('.env');

        // Backup
        copy($envFile, base_path('.env.backup'));

        $envContent = file_get_contents($envFile);

        foreach ($values as $key => $value) {

            if (preg_match("/^{$key}=.*/m", $envContent)) {

                $envContent = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}={$value}",
                    $envContent
                );
            } else {

                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envFile, $envContent);
    }
}
