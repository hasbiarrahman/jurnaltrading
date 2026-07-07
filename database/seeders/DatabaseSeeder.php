<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Setting;
use App\Models\Watchlist;
use App\Models\Trade;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed default user
        User::updateOrCreate(
            ['email' => 'admin@pelagic.com'],
            [
                'name' => 'Pelagic Trader',
                'password' => Hash::make('admin123'),
                'role' => 'super_admin',
            ]
        );

        // 2. Seed default Tokocrypto settings
        Setting::updateOrCreate(
            ['key' => 'tokocrypto_api_key'],
            ['value' => 'd2700448B6f7B6d9fCe41C9BF38F2835ihrwK9uVmUCyhqoHkAbiuzxqxydyXu8h']
        );

        Setting::updateOrCreate(
            ['key' => 'tokocrypto_api_secret'],
            ['value' => '']
        );

        // 3. Seed default watchlist symbols
        $watchlists = [
            ['symbol' => 'BTCUSDT', 'notes' => 'Bitcoin Core Asset'],
            ['symbol' => 'ETHUSDT', 'notes' => 'Ethereum Core Asset'],
            ['symbol' => 'BNBUSDT', 'notes' => 'Binance native utility token'],
            ['symbol' => 'TKOUSDT', 'notes' => 'Tokocrypto native utility token'],
        ];

        foreach ($watchlists as $wl) {
            Watchlist::updateOrCreate(
                ['symbol' => $wl['symbol']],
                ['notes' => $wl['notes']]
            );
        }

        // 4. Seed initial mock trades
        $trades = [
            [
                'symbol' => 'BTCUSDT',
                'type' => 'BUY',
                'price' => 61250.00,
                'amount' => 0.05,
                'total' => 3062.50,
                'trade_time' => '2026-06-15 08:30:00',
                'stoch_rsi_k' => 15.20,
                'stoch_rsi_d' => 12.10,
                'divergence' => 'Bullish',
                'notes' => 'Bought at support. Daily Stochastic RSI was oversold with bullish divergence.'
            ],
            [
                'symbol' => 'ETHUSDT',
                'type' => 'BUY',
                'price' => 3120.00,
                'amount' => 0.8,
                'total' => 2496.00,
                'trade_time' => '2026-06-18 14:15:00',
                'stoch_rsi_k' => 45.50,
                'stoch_rsi_d' => 42.00,
                'divergence' => 'None',
                'notes' => 'Breakout trade.'
            ],
            [
                'symbol' => 'BTCUSDT',
                'type' => 'BUY',
                'price' => 62100.00,
                'amount' => 0.03,
                'total' => 1863.00,
                'trade_time' => '2026-06-20 09:00:00',
                'stoch_rsi_k' => 78.40,
                'stoch_rsi_d' => 72.30,
                'divergence' => 'None',
                'notes' => 'Averaging up near consolidation boundary.'
            ]
        ];

        foreach ($trades as $t) {
            Trade::create($t);
        }
    }
}
