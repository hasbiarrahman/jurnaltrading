<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Display the settings form.
     */
    public function index()
    {
        $apiKey = Setting::where('key', 'tokocrypto_api_key')->value('value') ?? '';
        $apiSecret = Setting::where('key', 'tokocrypto_api_secret')->value('value') ?? '';

        return view('setting.index', compact('apiKey', 'apiSecret'));
    }

    /**
     * Update the settings in database.
     */
    public function update(Request $request)
    {
        $request->validate([
            'tokocrypto_api_key' => 'nullable|string|max:255',
            'tokocrypto_api_secret' => 'nullable|string|max:255',
        ]);

        Setting::updateOrCreate(
            ['key' => 'tokocrypto_api_key'],
            ['value' => trim($request->tokocrypto_api_key ?? '')]
        );

        Setting::updateOrCreate(
            ['key' => 'tokocrypto_api_secret'],
            ['value' => trim($request->tokocrypto_api_secret ?? '')]
        );

        return redirect()->route('setting.index')->with('success', 'Konfigurasi API Tokocrypto berhasil diperbarui.');
    }
}
