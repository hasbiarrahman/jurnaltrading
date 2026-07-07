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
}
