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
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows (Local XAMPP)
                $cmd = "start /B C:\\xampp\\php\\php.exe " . base_path('artisan') . " scan:altcoins > NUL 2>&1";
                pclose(popen($cmd, "r"));
            } else {
                // Linux / Unix (Production Cloud Hosting / VPS)
                // Resolve PHP binary path dynamically or fallback to 'php'
                $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
                
                // Some shared hostings block PHP_BINARY, fallback to standard locations if needed
                if (strpos($phpBinary, 'php-fpm') !== false) {
                    $phpBinary = 'php';
                }
                
                $cmd = "nohup {$phpBinary} " . base_path('artisan') . " scan:altcoins > /dev/null 2>&1 &";
                exec($cmd);
            }

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
