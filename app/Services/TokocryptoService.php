<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Trade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TokocryptoService
{
    protected $baseUrl = 'https://www.tokocrypto.site';

    /**
     * Retrieve the API Key from database or .env fallback.
     */
    public function getApiKey(): string
    {
        $setting = Setting::where('key', 'tokocrypto_api_key')->first();
        if ($setting && !empty($setting->value)) {
            return $setting->value;
        }
        return config('services.tokocrypto.api_key', env('TOKOCRYPTO_API_KEY', ''));
    }

    /**
     * Retrieve the API Secret from database.
     */
    public function getApiSecret(): string
    {
        $setting = Setting::where('key', 'tokocrypto_api_secret')->first();
        if ($setting && !empty($setting->value)) {
            return $setting->value;
        }
        return env('TOKOCRYPTO_API_SECRET', '');
    }

    /**
     * Check if API credentials are fully configured.
     */
    public function hasCredentials(): bool
    {
        return !empty($this->getApiKey()) && !empty($this->getApiSecret());
    }

    /**
     * Fetch current market prices for symbols.
     * Caches all prices for 10 seconds to avoid rate limiting.
     */
    public function getAllPrices(): array
    {
        return Cache::remember('tokocrypto_market_prices', 10, function () {
            try {
                // Try Tokocrypto (site) public API first as it works in Indonesia
                $response = Http::timeout(3)->get($this->baseUrl . '/api/v3/ticker/price');
                if (!$response->successful()) {
                    // Fallback to Binance
                    $response = Http::timeout(3)->get('https://api.binance.com/api/v3/ticker/price');
                }

                if ($response->successful()) {
                    $data = $response->json();
                    $prices = [];
                    foreach ($data as $item) {
                        $prices[$item['symbol']] = (float)$item['price'];
                    }
                    return $prices;
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to fetch market prices: " . $e->getMessage());
            }

            return [];
        });
    }

    /**
     * Fetch price of a single symbol.
     */
    public function getPrice(string $symbol): float
    {
        $prices = $this->getAllPrices();
        $symbol = strtoupper(trim($symbol));
        return $prices[$symbol] ?? 0.0;
    }

    /**
     * Fetch user spot account balances.
     * Returns an array of assets with balances and current valuations.
     */
    public function getPortfolio(): array
    {
        $apiKey = $this->getApiKey();
        $secretKey = $this->getApiSecret();

        $balances = [];
        $fetchedFromApi = false;

        // If credentials exist, try to call Tokocrypto API
        if ($this->hasCredentials()) {
            try {
                $params = [
                    'timestamp' => round(microtime(true) * 1000),
                    'recvWindow' => 60000,
                ];

                $queryString = http_build_query($params);
                $signature = hash_hmac('sha256', $queryString, $secretKey);
                
                $response = Http::timeout(4)
                    ->withHeaders([
                        'X-MBX-APIKEY' => $apiKey,
                        'X-TCDX-APIKEY' => $apiKey, // Support older endpoints as backup
                    ])
                    ->get($this->baseUrl . '/api/v3/account?' . $queryString . '&signature=' . $signature);

                if ($response->successful()) {
                    $accountData = $response->json();
                    if (isset($accountData['balances'])) {
                        foreach ($accountData['balances'] as $b) {
                            $free = (float)$b['free'];
                            $locked = (float)$b['locked'];
                            $total = $free + $locked;

                            if ($total > 0.00001) {
                                $balances[$b['asset']] = [
                                    'asset' => $b['asset'],
                                    'free' => $free,
                                    'locked' => $locked,
                                    'total' => $total,
                                    'source' => 'API'
                                ];
                            }
                        }
                        $fetchedFromApi = true;
                    }
                } else {
                    \Log::warning("Tokocrypto API Account returned error: " . $response->body());
                }
            } catch (\Exception $e) {
                \Log::error("Failed to fetch Tokocrypto API Portfolio: " . $e->getMessage());
            }
        }

        // Fallback: If not fetched from API, construct balance from trade logs in database
        if (!$fetchedFromApi) {
            $trades = Trade::orderBy('trade_time', 'asc')->get();
            $holdings = [];

            foreach ($trades as $trade) {
                $symbol = $trade->symbol;
                
                // Identify the main crypto asset (e.g. BTC from BTCUSDT)
                $asset = $symbol;
                foreach (['USDT', 'BIDR', 'IDRT', 'BUSD', 'USDC', 'BTC', 'ETH', 'BNB', 'IDR'] as $q) {
                    if (str_ends_with($symbol, $q) && strlen($symbol) > strlen($q)) {
                        $asset = substr($symbol, 0, -strlen($q));
                        break;
                    }
                }

                if (!isset($holdings[$asset])) {
                    $holdings[$asset] = 0.0;
                }

                if (strtoupper($trade->type) === 'BUY') {
                    $holdings[$asset] += (float)$trade->amount;
                } else {
                    $holdings[$asset] -= (float)$trade->amount;
                }
            }

            foreach ($holdings as $asset => $amount) {
                if ($amount > 0.00001) {
                    $balances[$asset] = [
                        'asset' => $asset,
                        'free' => $amount,
                        'locked' => 0.0,
                        'total' => $amount,
                        'source' => 'Database Logs (No API Secret)'
                    ];
                }
            }
        }

        // Add valuations (convert to USDT, etc.)
        $prices = $this->getAllPrices();
        $portfolioData = [];
        $totalValuationUsdt = 0.0;

        foreach ($balances as $asset => $data) {
            $symbol = $asset . 'USDT';
            
            // If asset is USDT itself
            if ($asset === 'USDT') {
                $price = 1.0;
            } else {
                // Check if price is in prices list
                $price = $prices[$symbol] ?? 0.0;
                
                // Fallback: if we only have BIDR or IDRT pairs (common on Tokocrypto)
                if ($price === 0.0) {
                    $bidrPrice = $prices[$asset . 'BIDR'] ?? 0.0;
                    $usdtBidr = $prices['USDTIDR'] ?? ($prices['USDTBIDR'] ?? ($prices['USDTIDRT'] ?? 15000.0));
                    if ($bidrPrice > 0 && $usdtBidr > 0) {
                        $price = $bidrPrice / $usdtBidr;
                    }
                }
            }

            $valueUsdt = $data['total'] * $price;
            $totalValuationUsdt += $valueUsdt;

            $portfolioData[] = array_merge($data, [
                'price' => $price,
                'value_usdt' => $valueUsdt,
            ]);
        }

        // Sort by valuation descending
        usort($portfolioData, function ($a, $b) {
            return $b['value_usdt'] <=> $a['value_usdt'];
        });

        // Add IDR estimation (assume 1 USDT = 16,400 IDR or live rate if available)
        $usdtIdr = $prices['USDTIDR'] ?? ($prices['USDTBIDR'] ?? ($prices['USDTIDRT'] ?? 16400.0));
        if ($usdtIdr < 1000) {
            $usdtIdr = 16400.0; // fallback safety
        }

        return [
            'assets' => $portfolioData,
            'total_usdt' => $totalValuationUsdt,
            'total_idr' => $totalValuationUsdt * $usdtIdr,
            'usdt_idr_rate' => $usdtIdr,
            'is_live' => $fetchedFromApi
        ];
    }
}
