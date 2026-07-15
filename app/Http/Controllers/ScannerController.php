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
        // On Windows, start background execution of php artisan scan:altcoins
        $cmd = "start /B php " . base_path('artisan') . " scan:altcoins > NUL 2>&1";
        
        try {
            pclose(popen($cmd, "r"));
            return response()->json([
                'success' => true,
                'message' => 'Scan triggered in the background.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to trigger scan: ' . $e->getMessage()
            ], 500);
        }
    }
}
