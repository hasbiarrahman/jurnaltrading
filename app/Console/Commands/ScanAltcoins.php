<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Client\Pool;

class ScanAltcoins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:altcoins';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the quantitative altcoin scanner in pure PHP';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting altcoin scan...');
        
        $memecoins = [
            "DOGE", "SHIB", "PEPE", "BONK", "WIF", "FLOKI", "MEME", "STAY", "RAVE", 
            "BOME", "BABYDOGE", "TURBO", "MYRO", "COQ", "MOG", "WEN", "SLERF", 
            "POPCAT", "BRETT", "MEW", "DEGEN", "SNEK", "COCOS", "LUNC", "USTC"
        ];
        $fiatStables = ['USDT', 'BIDR', 'IDRT', 'BUSD', 'USDC', 'IDR'];

        $this->info('Fetching trade symbols from database...');
        $journalAssets = [];
        try {
            $tradeSymbols = \App\Models\Trade::select('symbol')->distinct()->pluck('symbol')->toArray();
            foreach ($tradeSymbols as $sym) {
                $sym = strtoupper(trim($sym));
                $base = $sym;
                foreach (['USDT', 'BIDR', 'IDRT', 'BUSD', 'USDC', 'BTC', 'ETH', 'BNB', 'IDR'] as $q) {
                    if (str_ends_with($sym, $q) && strlen($sym) > strlen($q)) {
                        $base = substr($sym, 0, -strlen($q));
                        break;
                    }
                }
                if (!in_array($base, $fiatStables) && !in_array($base, $memecoins)) {
                    $journalAssets[] = $base;
                }
            }
            $journalAssets = array_unique($journalAssets);
        } catch (\Exception $e) {
            $this->warn('Could not read trade journal assets: ' . $e->getMessage());
        }

        $this->info('Fetching tickers from KuCoin...');
        try {
            $response = Http::timeout(10)->get('https://api.kucoin.com/api/v1/market/allTickers');
            if (!$response->successful()) {
                $this->error('Failed to fetch tickers from KuCoin.');
                return 1;
            }
            
            $json = $response->json();
            if (($json['code'] ?? '') !== '200000' || !isset($json['data']['ticker'])) {
                $this->error('Invalid ticker response format.');
                return 1;
            }
            
            $exclude = ["BTC-USDT", "USDC-USDT", "DAI-USDT", "USDT-DAI", "EUR-USDT", "GBP-USDT"];
            $pairs = [];
            foreach ($json['data']['ticker'] as $t) {
                $symbol = $t['symbol'];
                if (!str_ends_with($symbol, '-USDT') || in_array($symbol, $exclude)) {
                    continue;
                }
                $base = explode('-', $symbol)[0];
                if (in_array($base, $memecoins)) {
                    continue;
                }
                $pairs[] = [
                    'symbol' => $symbol,
                    'volume' => (float)$t['volValue']
                ];
            }
            
            // Sort by volume descending
            usort($pairs, function ($a, $b) {
                return $b['volume'] <=> $a['volume'];
            });
            
            $top500 = array_slice($pairs, 0, 500);
            
            // Ensure all journal assets are included in the scan list
            $scanSymbols = [];
            foreach ($top500 as $p) {
                $scanSymbols[$p['symbol']] = [
                    'symbol' => $p['symbol'],
                    'volume' => $p['volume'],
                    'is_journal' => false
                ];
            }

            foreach ($journalAssets as $asset) {
                $pairSymbol = $asset . '-USDT';
                if (isset($scanSymbols[$pairSymbol])) {
                    $scanSymbols[$pairSymbol]['is_journal'] = true;
                } else {
                    $tickerVolume = 0.0;
                    foreach ($json['data']['ticker'] as $t) {
                        if ($t['symbol'] === $pairSymbol) {
                            $tickerVolume = (float)$t['volValue'];
                            break;
                        }
                    }
                    $scanSymbols[$pairSymbol] = [
                        'symbol' => $pairSymbol,
                        'volume' => $tickerVolume,
                        'is_journal' => true
                    ];
                }
            }

            $scanList = array_values($scanSymbols);
            $this->info('Scanning ' . count($scanList) . ' altcoins...');
            
            $scanListChunks = array_chunk($scanList, 30);
            $responses = [];
            
            foreach ($scanListChunks as $chunk) {
                $chunkResponses = Http::pool(function (Pool $pool) use ($chunk) {
                    foreach ($chunk as $pair) {
                        $pool->as($pair['symbol'])->timeout(10)->get("https://api.kucoin.com/api/v1/market/candles?symbol={$pair['symbol']}&type=1day");
                    }
                });
                $responses = array_merge($responses, $chunkResponses);
                usleep(500000); // 500ms sleep between chunks to avoid KuCoin rate limit
            }
            
            $matches = [];
            $allScanned = [];
            foreach ($scanList as $pair) {
                $symbol = $pair['symbol'];
                $res = $responses[$symbol] ?? null;
                if ($res instanceof \Illuminate\Http\Client\Response && $res->successful()) {
                    $klines = $res->json();
                    if (($klines['code'] ?? '') === '200000' && isset($klines['data']) && count($klines['data']) >= 40) {
                        $closes = [];
                        foreach ($klines['data'] as $bar) {
                            $closes[] = (float)$bar[2];
                        }
                        $closes = array_reverse($closes);
                        
                        $rsiValues = $this->calculateRSI($closes, 14);
                        $kValues = $this->calculateStochRSI($rsiValues, 14, 3);
                        
                        $lastRsi = end($rsiValues);
                        $lastK = end($kValues);
                        
                        if (!is_null($lastRsi) && !is_null($lastK) && !is_nan($lastRsi) && !is_nan($lastK)) {
                            $isJournal = $pair['is_journal'];
                            $isDoubleBottom = $this->detectDoubleBottom($closes);
                            $meetsFilter = ($lastRsi < 40 && $lastK < 7) || $isDoubleBottom;
                            
                            $coinData = [
                                'symbol' => str_replace('-', '', $symbol),
                                'rsi' => round($lastRsi, 2),
                                'stochK' => round($lastK, 2),
                                'price' => end($closes),
                                'volume_24h' => $pair['volume'],
                                'is_journal' => $isJournal,
                                'is_double_bottom' => $isDoubleBottom
                            ];

                            if ($meetsFilter || $isJournal) {
                                $matches[] = $coinData;
                            }
                            
                            $allScanned[] = $coinData;
                        }
                    }
                }
            }
            
            $outputData = [
                'last_updated' => now()->toIso8601String(),
                'scanned_count' => count($scanList),
                'matches_count' => count($matches),
                'matches' => $matches
            ];
            
            $resultsPath = storage_path('app/altcoin_scan_results.json');
            File::ensureDirectoryExists(dirname($resultsPath));
            File::put($resultsPath, json_encode($outputData, JSON_PRETTY_PRINT));
            
            // Save all scanned tokens
            $allData = [
                'last_updated' => now()->toIso8601String(),
                'scanned_count' => count($scanList),
                'items' => $allScanned
            ];
            $allPath = storage_path('app/altcoin_scan_all.json');
            File::put($allPath, json_encode($allData, JSON_PRETTY_PRINT));
            
            $this->info("Scan completed. " . count($matches) . " matches / " . count($allScanned) . " total items written.");
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Error running scan: ' . $e->getMessage());
            return 1;
        }
    }

    private function calculateRSI(array $closes, int $period = 14): array
    {
        $rsi = array_fill(0, count($closes), null);
        if (count($closes) <= $period) {
            return $rsi;
        }
        
        $gains = [];
        $losses = [];
        for ($i = 1; $i < count($closes); $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $gains[] = $diff > 0 ? $diff : 0.0;
            $losses[] = $diff < 0 ? -$diff : 0.0;
        }
        
        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;
        
        $rsi[$period] = $avgLoss == 0.0 ? 100.0 : 100.0 - (100.0 / (1.0 + $avgGain / $avgLoss));
        
        for ($i = $period + 1; $i < count($closes); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i - 1]) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i - 1]) / $period;
            $rsi[$i] = $avgLoss == 0.0 ? 100.0 : 100.0 - (100.0 / (1.0 + $avgGain / $avgLoss));
        }
        return $rsi;
    }

    private function calculateStochRSI(array $rsiValues, int $period = 14, int $smoothK = 3): array
    {
        $cleanRsi = array_values(array_filter($rsiValues, function($v) { return !is_null($v); }));
        $stochRsi = [];
        for ($i = $period - 1; $i < count($cleanRsi); $i++) {
            $rsiWindow = array_slice($cleanRsi, $i - $period + 1, $period);
            $minRsi = min($rsiWindow);
            $maxRsi = max($rsiWindow);
            $value = ($maxRsi == $minRsi) ? 100.0 : (($cleanRsi[$i] - $minRsi) / ($maxRsi - $minRsi)) * 100.0;
            $stochRsi[] = $value;
        }
        
        $k = array_fill(0, count($stochRsi), null);
        for ($i = $smoothK - 1; $i < count($stochRsi); $i++) {
            $sum = array_sum(array_slice($stochRsi, $i - $smoothK + 1, $smoothK));
            $k[$i] = $sum / $smoothK;
        }
        return $k;
    }

    /**
     * Detect if the close prices form a Double Bottom chart pattern.
     */
    private function detectDoubleBottom(array $closes): bool
    {
        $n = count($closes);
        if ($n < 30) {
            return false;
        }

        // Analyze the last 40 daily candles
        $windowSize = min(40, $n);
        $data = array_slice($closes, -$windowSize);
        
        $lows = [];
        $highs = [];
        
        // Simple swing low/high detection with window size 3
        $w = 3;
        for ($i = $w; $i < count($data) - $w; $i++) {
            $val = $data[$i];
            
            // Check swing low
            $isLow = true;
            for ($j = -$w; $j <= $w; $j++) {
                if ($data[$i + $j] < $val) {
                    $isLow = false;
                    break;
                }
            }
            if ($isLow) {
                $lows[] = ['index' => $i, 'price' => $val];
            }
            
            // Check swing high
            $isHigh = true;
            for ($j = -$w; $j <= $w; $j++) {
                if ($data[$i + $j] > $val) {
                    $isHigh = false;
                    break;
                }
            }
            if ($isHigh) {
                $highs[] = ['index' => $i, 'price' => $val];
            }
        }

        // We need at least 2 lows and 1 high
        if (count($lows) < 2) {
            return false;
        }

        // Check pairs of lows to see if they form a double bottom
        for ($a = 0; $a < count($lows) - 1; $a++) {
            for ($b = $a + 1; $b < count($lows); $b++) {
                $low1 = $lows[$a];
                $low2 = $lows[$b];
                
                // Lows must be separated by some distance (e.g. at least 5 candles)
                $distance = $low2['index'] - $low1['index'];
                if ($distance < 5 || $distance > 30) {
                    continue;
                }
                
                // The two bottoms must be at a similar price level (within 4% tolerance)
                $priceDiff = abs($low1['price'] - $low2['price']) / min($low1['price'], $low2['price']);
                if ($priceDiff > 0.04) {
                    continue;
                }
                
                // Find the highest peak between low1 and low2
                $peakPrice = 0.0;
                $peakIndex = -1;
                for ($k = $low1['index'] + 1; $k < $low2['index']; $k++) {
                    if ($data[$k] > $peakPrice) {
                        $peakPrice = $data[$k];
                        $peakIndex = $k;
                    }
                }
                
                if ($peakIndex === -1) {
                    continue;
                }
                
                // The peak must be significantly higher than the bottoms (e.g. at least 3% higher)
                $avgBottom = ($low1['price'] + $low2['price']) / 2.0;
                $peakGain = ($peakPrice - $avgBottom) / $avgBottom;
                if ($peakGain < 0.03) {
                    continue;
                }
                
                // The current price should be above the second bottom, indicating it has bounced
                $currentPrice = end($data);
                if ($currentPrice < $low2['price']) {
                    continue;
                }
                
                // The current price should not have broken way past the neckline peak (max 15% above peak)
                if ($currentPrice > $peakPrice * 1.15) {
                    continue;
                }
                
                return true;
            }
        }
        
        return false;
    }
}
