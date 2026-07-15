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
            
            $top150 = array_slice($pairs, 0, 150);
            
            // Ensure all journal assets are included in the scan list
            $scanSymbols = [];
            foreach ($top150 as $p) {
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
                usleep(300000); // 300ms sleep between chunks to avoid KuCoin rate limit
            }
            
            $matches = [];
            foreach ($scanList as $pair) {
                $symbol = $pair['symbol'];
                $res = $responses[$symbol] ?? null;
                if ($res && $res->successful()) {
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
                            $meetsFilter = ($lastRsi < 40 && $lastK < 7);
                            
                            if ($meetsFilter || $isJournal) {
                                $matches[] = [
                                    'symbol' => str_replace('-', '', $symbol),
                                    'rsi' => round($lastRsi, 2),
                                    'stochK' => round($lastK, 2),
                                    'price' => end($closes),
                                    'volume_24h' => $pair['volume'],
                                    'is_journal' => $isJournal
                                ];
                            }
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
            
            $this->info("Scan completed. " . count($matches) . " matches written to {$resultsPath}");
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
}
