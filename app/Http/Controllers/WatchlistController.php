<?php

namespace App\Http\Controllers;

use App\Models\Watchlist;
use App\Services\TechnicalAnalysisService;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    protected $technicalAnalysisService;

    public function __construct(TechnicalAnalysisService $technicalAnalysisService)
    {
        $this->technicalAnalysisService = $technicalAnalysisService;
    }

    /**
     * Display the watchlist page.
     */
    public function index()
    {
        $watchlist = Watchlist::orderBy('symbol', 'asc')->get();
        return view('watchlist.index', compact('watchlist'));
    }

    /**
     * API Endpoint to fetch live metrics asynchronously.
     */
    public function getMetrics($symbol)
    {
        $metrics = $this->technicalAnalysisService->getMetrics($symbol);

        if (!$metrics) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch metrics'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'metrics' => $metrics
        ]);
    }

    /**
     * Add a symbol to the watchlist.
     */
    public function store(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string|max:20',
            'notes' => 'nullable|string|max:500',
        ]);

        $symbol = strtoupper(str_replace(' ', '', trim($request->symbol)));

        // Basic verification: Check if symbol format is valid (letters and digits, e.g. BTCUSDT)
        if (!preg_match('/^[A-Z0-9]{3,20}$/', $symbol)) {
            return back()->withErrors(['symbol' => 'Format symbol tidak valid (contoh: BTCUSDT).']);
        }

        // Check if already in watchlist
        if (Watchlist::where('symbol', $symbol)->exists()) {
            return back()->withErrors(['symbol' => "Symbol {$symbol} sudah ada di watchlist."]);
        }

        Watchlist::create([
            'symbol' => $symbol,
            'notes' => $request->notes,
        ]);

        return redirect()->route('watchlist.index')->with('success', "Symbol {$symbol} berhasil ditambahkan ke watchlist.");
    }

    /**
     * Remove a symbol from the watchlist.
     */
    public function destroy($id)
    {
        $item = Watchlist::findOrFail($id);
        $symbol = $item->symbol;
        $item->delete();

        return redirect()->route('watchlist.index')->with('success', "Symbol {$symbol} berhasil dihapus dari watchlist.");
    }
}
