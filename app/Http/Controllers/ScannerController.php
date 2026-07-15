<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ScannerController extends Controller
{
    /**
     * Get the last scanned results.
     */
    public function getResults()
    {
        $path = storage_path('app/altcoin_scan_results.json');
        
        if (!File::exists($path)) {
            return response()->json([
                'last_updated' => null,
                'scanned_count' => 0,
                'matches_count' => 0,
                'matches' => []
            ]);
        }

        $data = json_decode(File::get($path), true);
        return response()->json($data);
    }

    /**
     * Trigger a new scan in the background.
     */
    public function startScan()
    {
        try {
            // Run the Artisan command synchronously in the PHP request process
            // This is 100% compatible with Hostinger and does not use shell exec() or node
            \Illuminate\Support\Facades\Artisan::call('scan:altcoins');

            return response()->json([
                'success' => true,
                'message' => 'Altcoin scan completed successfully.'
            ]);
        } catch (\Exception $e) {
            \Log::error("Altcoin scan failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run scan: ' . $e->getMessage()
            ], 500);
        }
    }
}
