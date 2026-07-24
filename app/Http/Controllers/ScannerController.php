<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ScannerController extends Controller
{
    /**
     * Get the last scanned results.
     */
    public function getResults(Request $request)
    {
        $timeframe = $request->query('timeframe', '1day');
        if (!in_array($timeframe, ['1day', '4hour'])) {
            $timeframe = '1day';
        }
        $path = storage_path("app/altcoin_scan_results_{$timeframe}.json");
        
        // Fallback to legacy file name if timeframe is 1day and the new file doesn't exist
        if ($timeframe === '1day' && !File::exists($path)) {
            $legacyPath = storage_path('app/altcoin_scan_results.json');
            if (File::exists($legacyPath)) {
                $path = $legacyPath;
            }
        }
        
        if (!File::exists($path)) {
            return response()->json([
                'last_updated' => null,
                'scanned_count' => 0,
                'matches_count' => 0,
                'matches' => []
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->header('Pragma', 'no-cache')
              ->header('Expires', '0');
        }

        $data = json_decode(File::get($path), true);
        return response()->json($data)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Trigger a new scan in the background.
     */
    public function startScan(Request $request)
    {
        $timeframe = $request->input('timeframe', '1day');
        if (!in_array($timeframe, ['1day', '4hour'])) {
            $timeframe = '1day';
        }
        try {
            // Run the Artisan command synchronously in the PHP request process
            // This is 100% compatible with Hostinger and does not use shell exec() or node
            \Illuminate\Support\Facades\Artisan::call('scan:altcoins', [
                '--timeframe' => $timeframe
            ]);

            return response()->json([
                'success' => true,
                'message' => "Altcoin scan for {$timeframe} completed successfully."
            ]);
        } catch (\Exception $e) {
            \Log::error("Altcoin scan failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run scan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Render the Altcoin Scanner page.
     */
    public function index()
    {
        return view('scanner.index');
    }

    /**
     * Get all scanned results.
     */
    public function getAllResults(Request $request)
    {
        $timeframe = $request->query('timeframe', '1day');
        if (!in_array($timeframe, ['1day', '4hour'])) {
            $timeframe = '1day';
        }
        $path = storage_path("app/altcoin_scan_all_{$timeframe}.json");
        
        // Fallback to legacy file name if timeframe is 1day and the new file doesn't exist
        if ($timeframe === '1day' && !File::exists($path)) {
            $legacyPath = storage_path('app/altcoin_scan_all.json');
            if (File::exists($legacyPath)) {
                $path = $legacyPath;
            }
        }
        
        if (!File::exists($path)) {
            return response()->json([
                'last_updated' => null,
                'scanned_count' => 0,
                'items' => []
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->header('Pragma', 'no-cache')
              ->header('Expires', '0');
        }

        $data = json_decode(File::get($path), true);
        return response()->json($data)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * API Endpoint to analyse a symbol's swing setup (support/resistance and Risk-to-Reward ratio).
     */
    public function analyseSymbol($symbol, \App\Services\TechnicalAnalysisService $service)
    {
        $analysis = $service->calculateSwingSetup($symbol);
        
        if (!$analysis) {
            return response()->json([
                'success' => false,
                'message' => "Gagal menghitung analisis swing untuk {$symbol}."
            ], 400);
        }

        return response()->json([
            'success' => true,
            'analysis' => $analysis
        ]);
    }
}
