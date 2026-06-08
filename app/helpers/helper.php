<?php

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

if (!function_exists('getSystemSetting')) {
    /**
     * Get system setting value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function getSystemSetting($key, $default = null)
    {
        $settings = app('system.settings');

        if ($settings && isset($settings->$key)) {
            return $settings->$key;
        }

        return $default;
    }
}

if (!function_exists('getSystemSettings')) {
    /**
     * Get all system settings as object
     *
     * @return SystemSetting|null
     */
    function getSystemSettings()
    {
        return app('system.settings');
    }
}

if (!function_exists('systemTitle')) {
    /**
     * Get system title
     *
     * @return string
     */
    function systemTitle()
    {
        return getSystemSetting('system_title', config('app.name', 'Laravel'));
    }
}

if (!function_exists('systemShortTitle')) {
    /**
     * Get system short title
     *
     * @return string
     */
    function systemShortTitle()
    {
        return getSystemSetting('system_short_title', systemTitle());
    }
}

if (!function_exists('systemLogo')) {
    /**
     * Get system logo URL
     *
     * @return string
     */
    function systemLogo()
    {
        $settings = getSystemSettings();

        if ($settings && $settings->system_logo) {
            // Check if file exists using public_path (matching your controller logic)
            if (file_exists(public_path($settings->system_logo))) {
                return asset($settings->system_logo);
            }
        }

        // Return default logo
        return asset('backend/assets/images/default-logo.png');
    }
}

if (!function_exists('systemFavicon')) {
    /**
     * Get system favicon URL
     *
     * @return string
     */
    function systemFavicon()
    {
        $settings = getSystemSettings();

        if ($settings && $settings->system_favicon) {
            // Check if file exists using public_path (matching your controller logic)
            if (file_exists(public_path($settings->system_favicon))) {
                return asset($settings->system_favicon);
            }
        }

        // Return default favicon
        return asset('backend/assets/images/default-favicon.ico');
    }
}

if (!function_exists('companyName')) {
    /**
     * Get company name
     *
     * @return string
     */
    function companyName()
    {
        return getSystemSetting('company_name', 'Your Company');
    }
}

if (!function_exists('companyAddress')) {
    /**
     * Get company address
     *
     * @return string
     */
    function companyAddress()
    {
        return getSystemSetting('company_address', '');
    }
}

if (!function_exists('companyPhone')) {
    /**
     * Get company phone
     *
     * @return string
     */
    function companyPhone()
    {
        return getSystemSetting('phone', '');
    }
}

if (!function_exists('companyEmail')) {
    /**
     * Get company email
     *
     * @return string
     */
    function companyEmail()
    {
        return getSystemSetting('email', '');
    }
}

if (!function_exists('systemTagline')) {
    /**
     * Get system tagline
     *
     * @return string
     */
    function systemTagline()
    {
        return getSystemSetting('tagline', '');
    }
}

if (!function_exists('systemTimezone')) {
    /**
     * Get system timezone
     *
     * @return string
     */
    function systemTimezone()
    {
        return getSystemSetting('timezone', config('app.timezone', 'UTC'));
    }
}

if (!function_exists('systemLanguage')) {
    /**
     * Get system language
     *
     * @return string
     */
    function systemLanguage()
    {
        return getSystemSetting('language', config('app.locale', 'en'));
    }
}

if (!function_exists('copyrightText')) {
    /**
     * Get copyright text
     *
     * @return string
     */
    function copyrightText()
    {
        return getSystemSetting('copyright_text', '© ' . date('Y') . ' ' . companyName() . '. All rights reserved.');
    }
}

if (!function_exists('clearSystemSettingsCache')) {
    /**
     * Clear system settings cache
     *
     * @return void
     */
    function clearSystemSettingsCache()
    {
        Cache::forget('system_settings');
    }
}


if (!function_exists('mask_secret')) {

    function mask_secret($value, $visible = 4)
    {
        if (!$value) return null;

        $len = strlen($value);

        if ($len <= $visible) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - $visible) . substr($value, -$visible);
    }
}
