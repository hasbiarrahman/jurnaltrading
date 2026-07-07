<?php

namespace App\Http\Controllers;

use App\Models\Trade;
use App\Services\TokocryptoService;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    protected $tokocryptoService;

    public function __construct(TokocryptoService $tokocryptoService)
    {
        $this->tokocryptoService = $tokocryptoService;
    }

    /**
     * Display portfolio page and detailed asset statistics.
     */
    public function index()
    {
        // Fetch current balances (either from API or mock database balances)
        $portfolio = $this->tokocryptoService->getPortfolio();
        
        // Fetch all prices to help convert currencies
        $prices = $this->tokocryptoService->getAllPrices();
        $usdtIdr = $portfolio['usdt_idr_rate'];

        $detailedAssets = [];
        $totalCostUsdt = 0.0;
        $totalCurrentValuationUsdt = 0.0;

        foreach ($portfolio['assets'] as $assetData) {
            $assetName = $assetData['asset'];
            $totalAmount = $assetData['total'];
            $currentPrice = $assetData['price'];
            $valueUsdt = $assetData['value_usdt'];

            // Find all BUY trades in database for this asset to calculate average buy price
            $buyTrades = Trade::where('symbol', 'like', $assetName . '%')
                ->where('type', 'BUY')
                ->get();

            $totalBuyAmount = 0.0;
            $totalBuyCostUsdt = 0.0;

            foreach ($buyTrades as $trade) {
                $tradeSymbol = $trade->symbol;
                $tradePrice = (float)$trade->price;
                $tradeAmount = (float)$trade->amount;

                // Detect quote currency of trade (e.g. BIDR, USDT, IDRT)
                $quote = 'USDT';
                foreach (['USDT', 'BIDR', 'IDRT', 'BUSD'] as $q) {
                    if (str_ends_with($tradeSymbol, $q)) {
                        $quote = $q;
                        break;
                    }
                }

                // Standardize trade price to USDT
                $priceInUsdt = $tradePrice;
                if ($quote === 'BIDR' || $quote === 'IDRT' || $quote === 'IDR') {
                    // Try to use active USDTIDR rate first as BIDR is stale on Tokocrypto
                    $rate = $prices['USDTIDR'] ?? ($prices['USDT' . $quote] ?? ($prices['USDTIDRT'] ?? 16400.0));
                    if ($rate > 100) {
                        $priceInUsdt = $tradePrice / $rate;
                    } else {
                        $priceInUsdt = $tradePrice / 16400.0;
                    }
                }

                $totalBuyAmount += $tradeAmount;
                $totalBuyCostUsdt += ($priceInUsdt * $tradeAmount);
            }

            // Weighted average buy price in USDT
            $avgBuyPriceUsdt = $totalBuyAmount > 0 ? ($totalBuyCostUsdt / $totalBuyAmount) : 0.0;

            // If we have no BUY trades logged, fallback average price to current price
            if ($avgBuyPriceUsdt === 0.0) {
                $avgBuyPriceUsdt = $currentPrice;
            }

            // Calculate cost and PNL for current holdings
            $assetCostUsdt = $totalAmount * $avgBuyPriceUsdt;
            $unrealizedPnlUsdt = $valueUsdt - $assetCostUsdt;
            $pnlPercent = $avgBuyPriceUsdt > 0 ? (($currentPrice - $avgBuyPriceUsdt) / $avgBuyPriceUsdt) * 100 : 0.0;

            $totalCostUsdt += $assetCostUsdt;
            $totalCurrentValuationUsdt += $valueUsdt;

            $detailedAssets[] = [
                'asset' => $assetName,
                'free' => $assetData['free'],
                'locked' => $assetData['locked'],
                'total' => $totalAmount,
                'avg_buy_price_usdt' => $avgBuyPriceUsdt,
                'avg_buy_price_idr' => $avgBuyPriceUsdt * $usdtIdr,
                'current_price_usdt' => $currentPrice,
                'current_price_idr' => $currentPrice * $usdtIdr,
                'cost_usdt' => $assetCostUsdt,
                'cost_idr' => $assetCostUsdt * $usdtIdr,
                'value_usdt' => $valueUsdt,
                'value_idr' => $valueUsdt * $usdtIdr,
                'pnl_usdt' => $unrealizedPnlUsdt,
                'pnl_idr' => $unrealizedPnlUsdt * $usdtIdr,
                'pnl_percent' => $pnlPercent,
                'source' => $assetData['source']
            ];
        }

        // Calculate overall portfolio PNL
        $totalPnlUsdt = $totalCurrentValuationUsdt - $totalCostUsdt;
        $totalPnlPercent = $totalCostUsdt > 0 ? ($totalPnlUsdt / $totalCostUsdt) * 100 : 0.0;

        return view('portfolio.index', [
            'assets' => $detailedAssets,
            'total_valuation_usdt' => $totalCurrentValuationUsdt,
            'total_valuation_idr' => $totalCurrentValuationUsdt * $usdtIdr,
            'total_cost_usdt' => $totalCostUsdt,
            'total_cost_idr' => $totalCostUsdt * $usdtIdr,
            'total_pnl_usdt' => $totalPnlUsdt,
            'total_pnl_idr' => $totalPnlUsdt * $usdtIdr,
            'total_pnl_percent' => $totalPnlPercent,
            'usdt_idr_rate' => $usdtIdr,
            'is_live' => $portfolio['is_live']
        ]);
    }
}
