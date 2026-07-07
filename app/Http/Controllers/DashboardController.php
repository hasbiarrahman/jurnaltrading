<?php

namespace App\Http\Controllers;

use App\Models\Trade;
use App\Models\Watchlist;
use App\Services\TokocryptoService;
use App\Services\TechnicalAnalysisService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $tokocryptoService;
    protected $technicalAnalysisService;

    public function __construct(TokocryptoService $tokocryptoService, TechnicalAnalysisService $technicalAnalysisService)
    {
        $this->tokocryptoService = $tokocryptoService;
        $this->technicalAnalysisService = $technicalAnalysisService;
    }

    public function index()
    {
        // 1. Get Portfolio Metrics
        $portfolio = $this->tokocryptoService->getPortfolio();
        
        // 2. Count metrics
        $watchlistCount = Watchlist::count();
        $tradeCount = Trade::count();
        
        // 3. Get Recent Trades
        $recentTrades = Trade::orderBy('trade_time', 'desc')->take(5)->get();

        // 4. Prepare allocation chart data
        $chartLabels = [];
        $chartValues = [];
        foreach ($portfolio['assets'] as $asset) {
            $chartLabels[] = $asset['asset'];
            $chartValues[] = round($asset['value_usdt'], 2);
        }

        // 5. Watchlist Highlights (load metrics asynchronously via JS in view)
        $watchlistData = Watchlist::take(4)->get();

        return view('dashboard.index', compact(
            'portfolio',
            'watchlistCount',
            'tradeCount',
            'recentTrades',
            'chartLabels',
            'chartValues',
            'watchlistData'
        ));
    }
}
