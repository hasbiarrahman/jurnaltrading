<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;
use App\Models\TelegramRecipient;
use App\Models\TelegramPriceAlert;
use App\Services\TokocryptoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckPriceAlerts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telegram:check-price-alerts';

    /**
     * The console command description.
     */
    protected $description = 'Check portfolio asset prices and send Telegram alerts for movements >= 2%';

    protected $tokocryptoService;

    public function __construct(TokocryptoService $tokocryptoService)
    {
        parent::__construct();
        $this->tokocryptoService = $tokocryptoService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $enabled = Setting::where('key', 'telegram_alert_enabled')->value('value') ?? '1';
        if ($enabled !== '1') {
            $this->warn('Telegram price alerts are currently disabled in settings.');
            return 0;
        }

        $botToken = Setting::where('key', 'telegram_bot_token')->value('value');
        if (empty($botToken)) {
            $this->warn('Telegram Bot Token is not configured.');
            return 0;
        }

        $recipients = TelegramRecipient::where('is_active', true)->get();
        if ($recipients->isEmpty()) {
            $this->info('No active Telegram recipients registered.');
            return 0;
        }

        $threshold = (float)(Setting::where('key', 'telegram_alert_threshold')->value('value') ?? 2.0);
        $this->info("Starting price alert check. Threshold: {$threshold}%");

        // Fetch current portfolio balances and market prices
        $portfolio = $this->tokocryptoService->getPortfolio();
        $prices = $this->tokocryptoService->getAllPrices();
        $usdtIdr = $portfolio['usdt_idr_rate'] ?? 16000.0;

        $cashSymbols = ['USDT', 'BIDR', 'IDRT', 'BUSD', 'USDC', 'BTC', 'ETH', 'BNB', 'IDR'];

        foreach ($portfolio['assets'] as $assetData) {
            $asset = strtoupper($assetData['asset']);
            $totalBalance = (float)$assetData['total'];

            // Skip stable cash currencies
            if (in_array($asset, ['USDT', 'BIDR', 'IDRT', 'BUSD', 'USDC', 'IDR'])) {
                continue;
            }

            if ($totalBalance <= 0.0001) {
                continue;
            }

            // Resolve ticker symbol and current price in USDT
            $ticker = null;
            $currentPriceUsdt = 0.0;

            foreach (['USDT', 'BIDR', 'IDR', 'IDRT'] as $quote) {
                $possibleTicker = $asset . $quote;
                if (isset($prices[$possibleTicker])) {
                    $ticker = $possibleTicker;
                    $rawPrice = $prices[$possibleTicker];
                    
                    // Convert quote price back to USDT equivalents if needed for comparisons
                    if ($quote === 'USDT') {
                        $currentPriceUsdt = $rawPrice;
                    } else {
                        $currentPriceUsdt = $rawPrice / $usdtIdr;
                    }
                    break;
                }
            }

            // If we couldn't resolve a price ticker, skip
            if (!$ticker || $currentPriceUsdt <= 0) {
                $this->warn("Could not resolve market price for asset: {$asset}");
                continue;
            }

            // Fetch or create baseline price alert log
            $alertLog = TelegramPriceAlert::where('symbol', $ticker)->first();

            if (!$alertLog) {
                // Initialize baseline and skip first alert to avoid immediate spam
                TelegramPriceAlert::create([
                    'symbol' => $ticker,
                    'last_alert_price' => $currentPriceUsdt,
                    'last_alert_time' => now()
                ]);
                $this->info("Initialized baseline price for {$ticker} at $" . $currentPriceUsdt);
                continue;
            }

            $lastPrice = $alertLog->last_alert_price;
            $percentChange = (($currentPriceUsdt - $lastPrice) / $lastPrice) * 100.0;

            if (abs($percentChange) >= $threshold) {
                // Calculate average buy price for this asset from trades table
                $buyTrades = \App\Models\Trade::where('symbol', 'like', $asset . '%')
                    ->where('type', 'BUY')
                    ->get();

                $totalBuyAmount = 0.0;
                $totalBuyCostUsdt = 0.0;

                foreach ($buyTrades as $trade) {
                    $tradeSymbol = $trade->symbol;
                    $tradePrice = (float)$trade->price;
                    $tradeAmount = (float)$trade->amount;

                    // Detect quote currency of trade (e.g. BIDR, USDT, IDRT)
                    $tradeQuote = 'USDT';
                    foreach (['USDT', 'BIDR', 'IDRT', 'BUSD', 'IDR'] as $q) {
                        if (str_ends_with($tradeSymbol, $q) && strlen($tradeSymbol) > strlen($q)) {
                            $tradeQuote = $q;
                            break;
                        }
                    }

                    // Standardize trade price to USDT
                    $priceInUsdt = $tradePrice;
                    if ($tradeQuote === 'BIDR' || $tradeQuote === 'IDRT' || $tradeQuote === 'IDR') {
                        $rate = $prices['USDTIDR'] ?? ($prices['USDT' . $tradeQuote] ?? ($prices['USDTIDRT'] ?? 16400.0));
                        if ($rate > 100) {
                            $priceInUsdt = $tradePrice / $rate;
                        } else {
                            $priceInUsdt = $tradePrice / 16400.0;
                        }
                    }

                    $totalBuyAmount += $tradeAmount;
                    $totalBuyCostUsdt += ($priceInUsdt * $tradeAmount);
                }

                $avgBuyPriceUsdt = $totalBuyAmount > 0 ? ($totalBuyCostUsdt / $totalBuyAmount) : 0.0;

                // Fallback to current price if no buy trades recorded
                if ($avgBuyPriceUsdt === 0.0) {
                    $avgBuyPriceUsdt = $currentPriceUsdt;
                }

                // Calculate asset cost and unrealized PNL
                $assetCostUsdt = $totalBalance * $avgBuyPriceUsdt;
                $valuationUsdt = $totalBalance * $currentPriceUsdt;
                $unrealizedPnlUsdt = $valuationUsdt - $assetCostUsdt;
                $unrealizedPnlIdr = $unrealizedPnlUsdt * $usdtIdr;
                $pnlPercent = $avgBuyPriceUsdt > 0 ? (($currentPriceUsdt - $avgBuyPriceUsdt) / $avgBuyPriceUsdt) * 100.0 : 0.0;

                $direction = $percentChange > 0 ? 'PUMP 📈' : 'DUMP 📉';
                $sign = $percentChange > 0 ? '+' : '';
                
                // Formulate beautiful Markdown message
                $priceFormatted = $currentPriceUsdt >= 1.0 
                    ? '$' . number_format($currentPriceUsdt, 2) 
                    : '$' . number_format($currentPriceUsdt, 6);
                
                $lastPriceFormatted = $lastPrice >= 1.0 
                    ? '$' . number_format($lastPrice, 2) 
                    : '$' . number_format($lastPrice, 6);

                $avgBuyPriceFormatted = $avgBuyPriceUsdt >= 1.0 
                    ? '$' . number_format($avgBuyPriceUsdt, 2) 
                    : '$' . number_format($avgBuyPriceUsdt, 6);

                $pnlSign = $unrealizedPnlUsdt >= 0 ? '+' : '';
                $pnlColorEmoji = $unrealizedPnlUsdt >= 0 ? '🟢' : '🔴';
                
                $pnlPercentFormatted = number_format($pnlPercent, 2) . '%';
                $pnlValueFormatted = "Rp " . number_format($unrealizedPnlIdr, 0, ',', '.') . " (~$" . number_format($unrealizedPnlUsdt, 2) . ")";

                $valuationIdr = $valuationUsdt * $usdtIdr;

                $message = "🔔 *ALERT PERGERAKAN HARGA ({$direction})*\n\n"
                         . "Aset: *{$asset}* (Pair: `{$ticker}`)\n"
                         . "Harga Sebelumnya: *{$lastPriceFormatted}*\n"
                         . "Harga Sekarang: *{$priceFormatted}*\n"
                         . "Perubahan: *{$sign}" . number_format($percentChange, 2) . "%*\n\n"
                         . "📊 *Statistik Posisi Anda:*\n"
                         . "• Rata-rata Beli: *{$avgBuyPriceFormatted}*\n"
                         . "• Saldo Aset: *" . number_format($totalBalance, 4) . " {$asset}*\n"
                         . "• Estimasi Nilai: *Rp " . number_format($valuationIdr, 0, ',', '.') . "* (~$" . number_format($valuationUsdt, 2) . ")\n"
                         . "• PNL Belum Terealisasi: {$pnlColorEmoji} *{$pnlSign}{$pnlPercentFormatted}* ({$pnlSign}{$pnlValueFormatted})\n\n"
                         . "📱 _Silakan cek Jurnal Trading untuk rincian lengkap._";

                $this->info("Price Alert triggered for {$ticker}: {$sign}" . number_format($percentChange, 2) . "%");

                foreach ($recipients as $recipient) {
                    $this->sendTelegramMessage($botToken, $recipient->chat_id, $message);
                }

                // Update baseline to prevent repeat alerts
                $alertLog->update([
                    'last_alert_price' => $currentPriceUsdt,
                    'last_alert_time' => now()
                ]);
            }
        }

        $this->info('Price alert check finished.');
        return 0;
    }

    /**
     * Helper to send cURL request to Telegram API.
     */
    private function sendTelegramMessage($token, $chatId, $message)
    {
        try {
            $response = Http::timeout(6)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

            if (!$response->successful()) {
                Log::warning("Telegram sendMessage returned error for chat ID {$chatId}: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Failed to send Telegram message to {$chatId}: " . $e->getMessage());
        }
    }
}
