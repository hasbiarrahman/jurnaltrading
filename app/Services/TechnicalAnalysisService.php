<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TechnicalAnalysisService
{
    /**
     * Calculate Daily Stochastic RSI and detect Divergence for a given crypto symbol.
     *
     * @param string $symbol e.g. BTCUSDT, ETHUSDT
     * @return array|null
     */
    public function getMetrics(string $symbol)
    {
        try {
            // Clean up the symbol format (ensure uppercase)
            $symbol = strtoupper(trim($symbol));
            
            // Tokocrypto Site API (MBX engine) is fully open and works in Indonesia
            $response = Http::timeout(3)->get("https://www.tokocrypto.site/api/v3/klines", [
                'symbol' => $symbol,
                'interval' => '1d',
                'limit' => 100
            ]);

            if (!$response->successful()) {
                // Fallback to Binance (in case of geo-location difference)
                $response = Http::timeout(3)->get("https://api.binance.com/api/v3/klines", [
                    'symbol' => $symbol,
                    'interval' => '1d',
                    'limit' => 100
                ]);
            }

            if (!$response->successful()) {
                return null;
            }

            $klines = $response->json();
            if (count($klines) < 30) {
                return null;
            }

            $closes = [];
            $highs = [];
            $lows = [];
            $timestamps = [];

            foreach ($klines as $k) {
                $timestamps[] = $k[0];
                $closes[] = (float)$k[4];
                $highs[] = (float)$k[2];
                $lows[] = (float)$k[3];
            }

            // 1. Calculate RSI (14 period)
            $rsi = $this->calculateRSI($closes, 14);
            if (count($rsi) < 14) {
                return null;
            }

            // 2. Calculate Stochastic RSI (14 period)
            $stochRsi = [];
            $rsiKeys = array_keys($rsi);
            
            // We need 14 RSI values to compute the first StochRSI
            for ($i = 13; $i < count($rsiKeys); $i++) {
                $subRsi = [];
                for ($j = $i - 13; $j <= $i; $j++) {
                    $subRsi[] = $rsi[$rsiKeys[$j]];
                }
                
                $minRsi = min($subRsi);
                $maxRsi = max($subRsi);
                $currentRsi = $rsi[$rsiKeys[$i]];

                if ($maxRsi == $minRsi) {
                    $stochValue = 50.0;
                } else {
                    $stochValue = (($currentRsi - $minRsi) / ($maxRsi - $minRsi)) * 100;
                }
                
                $stochRsi[$rsiKeys[$i]] = $stochValue;
            }

            // 3. Calculate %K (3-period SMA of StochRSI)
            $kLine = [];
            $stochKeys = array_keys($stochRsi);
            for ($i = 2; $i < count($stochKeys); $i++) {
                $sum = 0;
                for ($j = 0; $j < 3; $j++) {
                    $sum += $stochRsi[$stochKeys[$i - $j]];
                }
                $kLine[$stochKeys[$i]] = $sum / 3;
            }

            // 4. Calculate %D (3-period SMA of %K)
            $dLine = [];
            $kKeys = array_keys($kLine);
            for ($i = 2; $i < count($kKeys); $i++) {
                $sum = 0;
                for ($j = 0; $j < 3; $j++) {
                    $sum += $kLine[$kKeys[$i - $j]];
                }
                $dLine[$kKeys[$i]] = $sum / 3;
            }

            // Get latest values
            $latestKey = end($kKeys);
            $latestK = isset($kLine[$latestKey]) ? round($kLine[$latestKey], 2) : 50.00;
            $latestD = isset($dLine[$latestKey]) ? round($dLine[$latestKey], 2) : 50.00;
            $latestRsi = isset($rsi[$latestKey]) ? round($rsi[$latestKey], 2) : 50.00;

            // 5. Detect Divergence
            $divergence = $this->detectDivergence($closes, $rsi, $highs, $lows);

            return [
                'rsi' => $latestRsi,
                'stoch_k' => $latestK,
                'stoch_d' => $latestD,
                'divergence' => $divergence,
                'price' => end($closes),
                'history' => $this->prepareChartData($closes, $kLine, $dLine, $timestamps)
            ];

        } catch (\Exception $e) {
            \Log::error("Failed to calculate Technical Metrics for {$symbol}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate Wilder's Relative Strength Index (RSI).
     */
    private function calculateRSI(array $closes, int $period = 14): array
    {
        $rsi = [];
        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? -$change : 0;
        }

        if (count($closes) <= $period) {
            return [];
        }

        // First average gain/loss
        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        if ($avgLoss == 0) {
            $rsi[$period] = 100;
        } else {
            $rs = $avgGain / $avgLoss;
            $rsi[$period] = 100 - (100 / (1 + $rs));
        }

        // Smoothed averages
        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i]) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i]) / $period;

            if ($avgLoss == 0) {
                $rsi[$i + 1] = 100;
            } else {
                $rs = $avgGain / $avgLoss;
                $rsi[$i + 1] = 100 - (100 / (1 + $rs));
            }
        }

        return $rsi;
    }

    /**
     * Detect Bullish/Bearish Divergence between Price action and RSI.
     */
    private function detectDivergence(array $closes, array $rsi, array $highs, array $lows): string
    {
        $data = [];
        foreach ($rsi as $idx => $val) {
            $data[] = [
                'index' => $idx,
                'close' => $closes[$idx],
                'high' => $highs[$idx],
                'low' => $lows[$idx],
                'rsi' => $val
            ];
        }

        $len = count($data);
        if ($len < 12) {
            return 'None';
        }

        $localLows = [];
        $localHighs = [];

        // Find pivots in price/RSI (using a window of 2 candles on each side)
        for ($i = 2; $i < $len - 2; $i++) {
            // Local Low
            if ($data[$i]['low'] <= $data[$i-1]['low'] && 
                $data[$i]['low'] <= $data[$i-2]['low'] && 
                $data[$i]['low'] <= $data[$i+1]['low'] && 
                $data[$i]['low'] <= $data[$i+2]['low']) {
                $localLows[] = $data[$i];
            }

            // Local High
            if ($data[$i]['high'] >= $data[$i-1]['high'] && 
                $data[$i]['high'] >= $data[$i-2]['high'] && 
                $data[$i]['high'] >= $data[$i+1]['high'] && 
                $data[$i]['high'] >= $data[$i+2]['high']) {
                $localHighs[] = $data[$i];
            }
        }

        // Bullish Divergence check (Price lower-low, RSI higher-low)
        if (count($localLows) >= 2) {
            $lastLow = end($localLows);
            $prevLow = prev($localLows);

            // Trigger only if the divergence pivot is relatively recent (within 12 days)
            if (($len - $lastLow['index']) <= 12) {
                if ($lastLow['low'] < $prevLow['low'] && $lastLow['rsi'] > $prevLow['rsi']) {
                    return 'Bullish';
                }
            }
        }

        // Bearish Divergence check (Price higher-high, RSI lower-high)
        if (count($localHighs) >= 2) {
            $lastHigh = end($localHighs);
            $prevHigh = prev($localHighs);

            if (($len - $lastHigh['index']) <= 12) {
                if ($lastHigh['high'] > $prevHigh['high'] && $lastHigh['rsi'] < $prevHigh['rsi']) {
                    return 'Bearish';
                }
            }
        }

        return 'None';
    }

    /**
     * Prepare data for frontend charts.
     */
    private function prepareChartData(array $closes, array $kLine, array $dLine, array $timestamps): array
    {
        $chartData = [];
        $kKeys = array_keys($kLine);
        
        // Take the last 30 periods for cleaner dashboard display
        $lastKeys = array_slice($kKeys, -30);

        foreach ($lastKeys as $key) {
            $time = date('d M', $timestamps[$key] / 1000);
            $chartData[] = [
                'time' => $time,
                'close' => $closes[$key],
                'k' => round($kLine[$key], 2),
                'd' => isset($dLine[$key]) ? round($dLine[$key], 2) : round($kLine[$key], 2),
            ];
        }

        return $chartData;
    }

    /**
     * Calculate supports, resistances, and Risk-to-Reward ratio for swing trading analysis.
     *
     * @param string $symbol
     * @return array|null
     */
    public function calculateSwingSetup(string $symbol)
    {
        try {
            $symbol = strtoupper(trim($symbol));
            
            // Fetch daily klines (250 candles for stable EMA 50 & 200)
            $response = Http::timeout(4)->get("https://www.tokocrypto.site/api/v3/klines", [
                'symbol' => $symbol,
                'interval' => '1d',
                'limit' => 250
            ]);

            if (!$response->successful()) {
                $response = Http::timeout(4)->get("https://api.binance.com/api/v3/klines", [
                    'symbol' => $symbol,
                    'interval' => '1d',
                    'limit' => 250
                ]);
            }

            if (!$response->successful()) {
                return null;
            }

            $klines = $response->json();
            $len = count($klines);
            if ($len < 15) {
                return null;
            }

            $closes = [];
            $highs = [];
            $lows = [];
            
            foreach ($klines as $k) {
                $closes[] = (float)$k[4];
                $highs[] = (float)$k[2];
                $lows[] = (float)$k[3];
            }

            $currentPrice = end($closes);

            // 1. Calculate EMA 50 and EMA 200
            $ema50Arr = $this->calculateEMA($closes, 50);
            $ema200Arr = $this->calculateEMA($closes, 200);
            $ema50 = end($ema50Arr);
            $ema200 = end($ema200Arr);

            // Determine Trend Status
            $trend = 'Neutral';
            if ($currentPrice > $ema50 && $ema50 > $ema200) {
                $trend = 'Bullish Kuat (Strong Bullish) 🟢';
            } elseif ($currentPrice > $ema50 && $ema50 <= $ema200) {
                $trend = 'Pemulihan Bullish (Bullish Recovery) 🟡';
            } elseif ($currentPrice < $ema50 && $ema50 < $ema200) {
                $trend = 'Bearish Kuat (Strong Bearish) 🔴';
            } elseif ($currentPrice < $ema50 && $ema50 >= $ema200) {
                $trend = 'Koreksi Bearish (Bearish Correction) 🟡';
            }

            // 2. Fibonacci Retracement Levels (based on last 60 days range)
            $last60Highs = array_slice($highs, -60);
            $last60Lows = array_slice($lows, -60);
            $fibHigh = max($last60Highs);
            $fibLow = min($last60Lows);
            $fibDiff = $fibHigh - $fibLow;

            $fib05 = $fibHigh - 0.5 * $fibDiff;
            $fib0618 = $fibHigh - 0.618 * $fibDiff; // Golden Pocket

            // 3. Upgraded S&R Detection (15-candle window: 7 left, 7 right)
            $peaks = [];
            $troughs = [];

            for ($i = 7; $i < $len - 7; $i++) {
                // Peak (Resistance)
                $isPeak = true;
                for ($j = 1; $j <= 7; $j++) {
                    if ($highs[$i] < $highs[$i - $j] || $highs[$i] < $highs[$i + $j]) {
                        $isPeak = false;
                        break;
                    }
                }
                if ($isPeak) {
                    $peaks[] = $highs[$i];
                }
                
                // Trough (Support)
                $isTrough = true;
                for ($j = 1; $j <= 7; $j++) {
                    if ($lows[$i] > $lows[$i - $j] || $lows[$i] > $lows[$i + $j]) {
                        $isTrough = false;
                        break;
                    }
                }
                if ($isTrough) {
                    $troughs[] = $lows[$i];
                }
            }

            // Include absolute lowest/highest close prices as candidates
            $troughs[] = min($lows);
            $peaks[] = max($highs);

            // Add Fibonacci levels and EMA lines as S&R candidates
            $troughs[] = $fib05;
            $troughs[] = $fib0618;
            $peaks[] = $fib05;
            $peaks[] = $fib0618;

            if ($ema50 < $currentPrice) {
                $troughs[] = $ema50;
            } else {
                $peaks[] = $ema50;
            }

            if ($ema200 < $currentPrice) {
                $troughs[] = $ema200;
            } else {
                $peaks[] = $ema200;
            }

            // Filter supports (troughs below current price)
            $supports = array_filter($troughs, function($val) use ($currentPrice) {
                return $val < $currentPrice;
            });
            rsort($supports); // Closest support first

            // Filter resistances (peaks above current price)
            $resistances = array_filter($peaks, function($val) use ($currentPrice) {
                return $val > $currentPrice;
            });
            sort($resistances); // Closest resistance first

            // Clean duplicate support levels (within 1.5% margin)
            $cleanSupports = [];
            foreach ($supports as $s) {
                $tooClose = false;
                foreach ($cleanSupports as $cs) {
                    if (abs($s - $cs) / $cs < 0.015) {
                        $tooClose = true;
                        break;
                    }
                }
                if (!$tooClose) {
                    $cleanSupports[] = $s;
                }
            }

            // Clean duplicate resistance levels (within 1.5% margin)
            $cleanResistances = [];
            foreach ($resistances as $r) {
                $tooClose = false;
                foreach ($cleanResistances as $cr) {
                    if (abs($r - $cr) / $cr < 0.015) {
                        $tooClose = true;
                        break;
                    }
                }
                if (!$tooClose) {
                    $cleanResistances[] = $r;
                }
            }

            // Apply sensible fallbacks if we don't have enough levels
            $s1 = count($cleanSupports) > 0 ? $cleanSupports[0] : $currentPrice * 0.95;
            
            $r1 = count($cleanResistances) > 0 ? $cleanResistances[0] : $currentPrice * 1.05;
            $r2 = count($cleanResistances) > 1 ? $cleanResistances[1] : $r1 * 1.05;
            $r3 = count($cleanResistances) > 2 ? $cleanResistances[2] : $r2 * 1.05;

            // Calculations
            $risk = $currentPrice - $s1;
            $reward = $r2 - $currentPrice; // Use R2 (Major Swing target) for Risk-to-Reward calculation
            $ratio = $risk > 0 ? ($reward / $risk) : 0;

            $pctRisk = ($risk / $currentPrice) * 100;
            $pctReward = ($reward / $currentPrice) * 100;

            // 4. Fetch Coinalyze Short Liquidation History if API key exists
            $coinalyzeKey = \App\Models\Setting::where('key', 'coinalyze_api_key')->value('value');
            $shortLiq3d = null;
            $shortLiq7d = null;
            
            if (!empty($coinalyzeKey)) {
                try {
                    $coinalyzeSymbol = $symbol; // e.g. STRKUSDT
                    if (!str_contains($coinalyzeSymbol, '_PERP')) {
                        $coinalyzeSymbol = $coinalyzeSymbol . '_PERP.A';
                    }

                    $liqResponse = Http::timeout(4)->get("https://api.coinalyze.net/v1/liquidation-history", [
                        'symbols' => $coinalyzeSymbol,
                        'interval' => 'daily',
                        'from' => now()->subDays(9)->timestamp,
                        'to' => now()->timestamp,
                        'api_key' => $coinalyzeKey
                    ]);

                    // Fallback to symbol without _PERP.A if empty or error
                    if (!$liqResponse->successful() || empty($liqResponse->json())) {
                        $liqResponse = Http::timeout(4)->get("https://api.coinalyze.net/v1/liquidation-history", [
                            'symbols' => $symbol,
                            'interval' => 'daily',
                            'from' => now()->subDays(9)->timestamp,
                            'to' => now()->timestamp,
                            'api_key' => $coinalyzeKey
                        ]);
                    }

                    if ($liqResponse->successful()) {
                        $liqData = $liqResponse->json();
                        if (!empty($liqData) && is_array($liqData)) {
                            $symbolData = $liqData[0] ?? null;
                            $history = $symbolData['history'] ?? [];
                            if (is_array($history) && count($history) > 0) {
                                $history3d = array_slice($history, -3);
                                $history7d = array_slice($history, -7);

                                $sum3d = 0.0;
                                foreach ($history3d as $h) {
                                    $sum3d += (float)($h['s'] ?? 0.0);
                                }

                                $sum7d = 0.0;
                                foreach ($history7d as $h) {
                                    $sum7d += (float)($h['s'] ?? 0.0);
                                }

                                $shortLiq3d = $sum3d;
                                $shortLiq7d = $sum7d;
                            }
                        }
                    }
                } catch (\Exception $ex) {
                    \Log::warning("Coinalyze API request failed: " . $ex->getMessage());
                }
            }

            // Determine if price is close to Fibonacci Golden Pocket (within 1.5%)
            $nearGoldenPocket = (abs($currentPrice - $fib0618) / $fib0618) <= 0.015;

            // Generate liquidation insight text if available
            $liqAdvice = "";
            if ($shortLiq3d !== null && $shortLiq3d > 0) {
                $liqAdvice = " Likuidasi short futures 3 hari terakhir mencapai " . $this->formatLiquidation($shortLiq3d) . " (7 hari: " . $this->formatLiquidation($shortLiq7d) . "). Tekanan likuidasi short ini memberi dorongan beli tambahan di pasar.";
            }

            // Score and advice
            if ($ratio >= 2.0 && $pctRisk <= 8.5) {
                $score = 'Sangat Bagus (Excellent)';
                $scoreClass = 'text-success';
                
                $advice = "Setup ideal! Rasio keuntungan sangat besar dengan risiko penempatan stop-loss yang ketat. Tren saat ini: {$trend}." . $liqAdvice;
                if ($nearGoldenPocket) {
                    $advice .= " Harga berada di dekat Golden Pocket Fibonacci 0.618, area support historis yang sangat kuat! Direkomendasikan untuk entri buy.";
                } else {
                    $advice .= " Direkomendasikan untuk cicil beli (buy on weakness) mendekati support.";
                }
            } elseif ($ratio >= 1.0) {
                $score = 'Moderat (Moderate)';
                $scoreClass = 'text-warning';
                
                $advice = "Setup lumayan sehat. Tren saat ini: {$trend}." . $liqAdvice;
                if ($nearGoldenPocket) {
                    $advice .= " Harga tertahan di Golden Pocket Fibonacci 0.618. Bagus untuk spekulasi beli dengan target ketat.";
                } else {
                    $advice .= " Disarankan untuk menunggu harga terkoreksi sedikit lebih dekat ke support untuk memaksimalkan rasio risk-to-reward sebelum entri.";
                }
            } else {
                $score = 'Kurang Menarik (Poor)';
                $scoreClass = 'text-danger';
                
                $advice = "Rasio Risk-to-Reward kurang menguntungkan saat ini (potensi kerugian mendekati atau lebih besar dari keuntungan). Tren saat ini: {$trend}." . $liqAdvice . " Disarankan untuk mencari peluang di koin lain.";
            }

            return [
                'symbol' => $symbol,
                'current_price' => $currentPrice,
                's1' => $s1,
                'r1' => $r1,
                'r2' => $r2,
                'r3' => $r3,
                'risk' => $risk,
                'reward' => $reward,
                'pct_risk' => $pctRisk,
                'pct_reward' => $pctReward,
                'ratio' => $ratio,
                'score' => $score,
                'score_class' => $scoreClass,
                'advice' => $advice,
                'short_liq_3d' => $shortLiq3d,
                'short_liq_7d' => $shortLiq7d,
                'short_liq_3d_formatted' => $this->formatLiquidation($shortLiq3d),
                'short_liq_7d_formatted' => $this->formatLiquidation($shortLiq7d),
                'has_coinalyze_key' => !empty($coinalyzeKey),
            ];

        } catch (\Exception $e) {
            \Log::error("Failed swing analysis: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper to calculate Exponential Moving Average (EMA).
     */
    private function calculateEMA(array $data, int $period): array
    {
        $len = count($data);
        if ($len < $period) {
            return array_fill(0, $len, end($data));
        }

        $ema = [];
        // Start with Simple Moving Average (SMA)
        $sum = 0;
        for ($i = 0; $i < $period; $i++) {
            $sum += $data[$i];
        }
        $sma = $sum / $period;

        for ($i = 0; $i < $period - 1; $i++) {
            $ema[$i] = $data[$i];
        }
        $ema[$period - 1] = $sma;

        $multiplier = 2 / ($period + 1);
        for ($i = $period; $i < $len; $i++) {
            $ema[$i] = ($data[$i] - $ema[$i - 1]) * $multiplier + $ema[$i - 1];
        }

        return $ema;
    }

    /**
     * Helper to format liquidation values to human readable K/M suffixes.
     */
    private function formatLiquidation($value)
    {
        if ($value === null) return '-';
        if ($value >= 1000000) {
            return '$' . number_format($value / 1000000, 2) . 'M';
        }
        if ($value >= 1000) {
            return '$' . number_format($value / 1000, 2) . 'K';
        }
        return '$' . number_format($value, 2);
    }
}
