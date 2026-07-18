<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;

class ComingSoonController extends Controller
{
    /**
     * Sidebar entries with no backing feature yet. Resolved through this
     * fixed whitelist (rather than echoing the route parameter directly)
     * since the slug becomes on-page content.
     */
    private const FEATURES = [
        'marketplace-ad'   => 'Marketplace/Ad',
        'event'            => 'Event',
        'interest-hub'     => 'Interest Hub',
        'courier'          => 'Courier',
        'cms'              => 'CMS',
        'admin-management' => 'Admin Management',
    ];

    public function index(string $feature)
    {
        abort_unless(array_key_exists($feature, self::FEATURES), 404);

        return view('backend.layouts.coming_soon.index', [
            'feature' => self::FEATURES[$feature],
        ]);
    }
}
