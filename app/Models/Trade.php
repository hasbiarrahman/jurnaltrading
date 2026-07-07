<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    protected $fillable = [
        'symbol',
        'type',
        'price',
        'amount',
        'total',
        'trade_time',
        'stoch_rsi_k',
        'stoch_rsi_d',
        'divergence',
        'notes'
    ];

    /**
     * Get the quote asset for the trade (e.g., USDT, BIDR).
     */
    public function getQuoteAssetAttribute()
    {
        $symbol = strtoupper($this->symbol);
        if (strpos($symbol, '_') !== false) {
            $parts = explode('_', $symbol);
            return end($parts);
        }
        
        if (str_ends_with($symbol, 'BIDR')) {
            return 'BIDR';
        }
        if (str_ends_with($symbol, 'USDT')) {
            return 'USDT';
        }
        if (str_ends_with($symbol, 'USDC')) {
            return 'USDC';
        }
        if (str_ends_with($symbol, 'BUSD')) {
            return 'BUSD';
        }
        if (str_ends_with($symbol, 'IDRT')) {
            return 'IDRT';
        }
        
        if (strlen($symbol) > 4) {
            $last4 = substr($symbol, -4);
            if (in_array($last4, ['USDT', 'BIDR', 'USDC', 'BUSD', 'IDRT'])) {
                return $last4;
            }
        }
        
        return substr($symbol, -3);
    }

    /**
     * Get formatted price with correct currency symbol (Rp or $).
     */
    public function getFormattedPriceAttribute()
    {
        $quote = $this->quote_asset;
        if (in_array($quote, ['BIDR', 'IDR', 'IDRT'])) {
            return 'Rp ' . number_format($this->price, 0, ',', '.');
        }
        return '$' . number_format($this->price, 4, '.', ',');
    }

    /**
     * Get formatted total with correct currency symbol (Rp or $).
     */
    public function getFormattedTotalAttribute()
    {
        $quote = $this->quote_asset;
        if (in_array($quote, ['BIDR', 'IDR', 'IDRT'])) {
            return 'Rp ' . number_format($this->total, 0, ',', '.');
        }
        return '$' . number_format($this->total, 2, '.', ',');
    }
}
