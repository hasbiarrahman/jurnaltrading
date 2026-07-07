<?php

namespace App\Http\Controllers;

use App\Models\Trade;
use App\Services\TechnicalAnalysisService;
use App\Services\TokocryptoService;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    protected $technicalAnalysisService;
    protected $tokocryptoService;

    public function __construct(
        TechnicalAnalysisService $technicalAnalysisService,
        TokocryptoService $tokocryptoService
    ) {
        $this->technicalAnalysisService = $technicalAnalysisService;
        $this->tokocryptoService = $tokocryptoService;
    }

    /**
     * Display a listing of trades with filters.
     */
    public function index(Request $request)
    {
        $query = Trade::orderBy('trade_time', 'desc');

        // Filter: Symbol
        if ($request->filled('symbol')) {
            $query->where('symbol', 'like', '%' . strtoupper(trim($request->symbol)) . '%');
        }

        // Filter: Type (BUY/SELL)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter: Divergence
        if ($request->filled('divergence')) {
            $query->where('divergence', $request->divergence);
        }

        $trades = $query->paginate(10)->withQueryString();

        // Get unique symbols for filter dropdown suggestion
        $symbols = Trade::select('symbol')->distinct()->pluck('symbol');

        return view('trade.index', compact('trades', 'symbols'));
    }

    /**
     * Store a newly created trade.
     */
    public function store(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string|max:20',
            'type' => 'required|in:BUY,SELL',
            'price' => 'required|numeric|min:0.00000001',
            'amount' => 'required|numeric|min:0.00000001',
            'trade_time' => 'required|date',
            'auto_metrics' => 'nullable|boolean',
            'stoch_rsi_k' => 'nullable|numeric|min:0|max:100',
            'stoch_rsi_d' => 'nullable|numeric|min:0|max:100',
            'divergence' => 'required|string|in:None,Bullish,Bearish',
            'notes' => 'nullable|string|max:1000',
        ]);

        $symbol = strtoupper(str_replace(' ', '', trim($request->symbol)));
        $price = (float)$request->price;
        $amount = (float)$request->amount;
        $total = $price * $amount;

        $stochK = $request->filled('stoch_rsi_k') ? (float)$request->stoch_rsi_k : null;
        $stochD = $request->filled('stoch_rsi_d') ? (float)$request->stoch_rsi_d : null;
        $divergence = $request->divergence;

        // If the user selected "auto_metrics" and they were empty, we fetch them automatically
        if ($request->boolean('auto_metrics') && (is_null($stochK) || is_null($stochD))) {
            $metrics = $this->technicalAnalysisService->getMetrics($symbol);
            if ($metrics) {
                $stochK = $metrics['stoch_k'];
                $stochD = $metrics['stoch_d'];
                $divergence = $metrics['divergence'];
            }
        }

        Trade::create([
            'symbol' => $symbol,
            'type' => $request->type,
            'price' => $price,
            'amount' => $amount,
            'total' => $total,
            'trade_time' => $request->trade_time,
            'stoch_rsi_k' => $stochK,
            'stoch_rsi_d' => $stochD,
            'divergence' => $divergence,
            'notes' => $request->notes,
        ]);

        return redirect()->route('trade.index')->with('success', 'Transaksi perdagangan berhasil ditambahkan.');
    }

    /**
     * API to fetch current stats for a symbol to pre-fill the form.
     */
    public function getLiveStats($symbol)
    {
        $symbol = strtoupper(trim($symbol));
        
        // Fetch all prices to check valid pairs
        $allPrices = $this->tokocryptoService->getAllPrices();
        
        // Auto-resolve base asset (e.g. HBAR) to full pair if it's not a pair itself
        if (!isset($allPrices[$symbol])) {
            if (isset($allPrices[$symbol . 'USDT'])) {
                $symbol = $symbol . 'USDT';
            } elseif (isset($allPrices[$symbol . 'IDR'])) {
                $symbol = $symbol . 'IDR';
            } elseif (isset($allPrices[$symbol . 'BIDR'])) {
                $symbol = $symbol . 'BIDR';
            }
        }

        $metrics = $this->technicalAnalysisService->getMetrics($symbol);
        
        if (!$metrics) {
            // Try to get price only from the resolved symbol
            $price = $allPrices[$symbol] ?? 0.0;
            if ($price > 0) {
                return response()->json([
                    'success' => true,
                    'symbol' => $symbol,
                    'price' => $price,
                    'stoch_k' => null,
                    'stoch_d' => null,
                    'divergence' => 'None'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan data live untuk symbol tersebut'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'symbol' => $symbol,
            'price' => $metrics['price'],
            'stoch_k' => $metrics['stoch_k'],
            'stoch_d' => $metrics['stoch_d'],
            'divergence' => $metrics['divergence']
        ]);
    }

    /**
     * Update the specified trade.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'symbol' => 'required|string|max:20',
            'type' => 'required|in:BUY,SELL',
            'price' => 'required|numeric|min:0.00000001',
            'amount' => 'required|numeric|min:0.00000001',
            'trade_time' => 'required|date',
            'auto_metrics' => 'nullable|boolean',
            'stoch_rsi_k' => 'nullable|numeric|min:0|max:100',
            'stoch_rsi_d' => 'nullable|numeric|min:0|max:100',
            'divergence' => 'required|string|in:None,Bullish,Bearish',
            'notes' => 'nullable|string|max:1000',
        ]);

        $trade = Trade::findOrFail($id);

        $symbol = strtoupper(str_replace(' ', '', trim($request->symbol)));
        $price = (float)$request->price;
        $amount = (float)$request->amount;
        $total = $price * $amount;

        $stochK = $request->filled('stoch_rsi_k') ? (float)$request->stoch_rsi_k : null;
        $stochD = $request->filled('stoch_rsi_d') ? (float)$request->stoch_rsi_d : null;
        $divergence = $request->divergence;

        // If the user selected "auto_metrics" and they were empty, we fetch them automatically
        if ($request->boolean('auto_metrics') && (is_null($stochK) || is_null($stochD))) {
            $metrics = $this->technicalAnalysisService->getMetrics($symbol);
            if ($metrics) {
                $stochK = $metrics['stoch_k'];
                $stochD = $metrics['stoch_d'];
                $divergence = $metrics['divergence'];
            }
        }

        $trade->update([
            'symbol' => $symbol,
            'type' => $request->type,
            'price' => $price,
            'amount' => $amount,
            'total' => $total,
            'trade_time' => $request->trade_time,
            'stoch_rsi_k' => $stochK,
            'stoch_rsi_d' => $stochD,
            'divergence' => $divergence,
            'notes' => $request->notes,
        ]);

        return redirect()->route('trade.index')->with('success', 'Transaksi perdagangan berhasil diperbarui.');
    }

    /**
     * Remove the specified trade.
     */
    public function destroy($id)
    {
        $trade = Trade::findOrFail($id);
        $trade->delete();

        return redirect()->route('trade.index')->with('success', 'Transaksi perdagangan berhasil dihapus.');
    }
}
