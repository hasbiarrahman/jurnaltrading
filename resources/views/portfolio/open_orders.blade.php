@extends('layouts.app')

@section('title', 'Antrean Pesanan Tokocrypto')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Antrean Pesanan (Open Orders)</h1>
        <p>Daftar pesanan perdagangan spot yang sedang mengantre (belum terealisasi) di Tokocrypto</p>
    </div>
</div>

@if(!$has_credentials)
    <div class="glass-card" style="text-align: center; padding: 4rem 2rem;">
        <div style="font-size: 4rem; margin-bottom: 1.5rem; color: #f59e0b;">⚠️</div>
        <h2 style="color: white; margin-bottom: 1rem;">API Key Tokocrypto Belum Terpasang</h2>
        <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 2rem auto; line-height: 1.6;">
            Untuk melihat antrean pesanan langsung dari akun Tokocrypto Anda, Anda harus mengonfigurasi API Key Anda terlebih dahulu di halaman pengaturan.
        </p>
        <a href="{{ route('setting.index') }}" class="btn btn-primary" style="padding: 0.75rem 2rem; font-weight: 600; text-decoration: none; border-radius: 6px; display: inline-block;">
            Buka Pengaturan API
        </a>
    </div>
@else
    <div class="glass-card">
        <div class="card-header" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div class="card-title">Daftar Order Aktif di Tokocrypto</div>
            <span class="badge" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; font-weight: 600; padding: 0.3rem 0.6rem; border-radius: 6px;">
                Tersambung Ke API Live
            </span>
        </div>

        <div class="table-responsive">
            <table class="custom-table table-wide" style="font-size: 0.88rem;">
                <thead>
                    <tr>
                        <th>Waktu Order</th>
                        <th>Simbol</th>
                        <th style="text-align: center;">Sisi</th>
                        <th style="text-align: center;">Tipe</th>
                        <th style="text-align: right;">Harga Order</th>
                        <th style="text-align: right;">Jumlah Aset</th>
                        <th style="text-align: right;">Total Nilai (USDT)</th>
                        <th style="text-align: right;">Harga Pasar</th>
                        <th style="text-align: center;">Jarak Ke Target (%)</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($open_orders as $order)
                        @php
                            $symbol = $order['symbol'];
                            // Parse quote to format pricing correctly
                            $quote = 'USDT';
                            foreach (['USDT', 'BIDR', 'IDRT', 'BUSD', 'USDC', 'BTC', 'ETH', 'BNB', 'IDR'] as $q) {
                                if (str_ends_with($symbol, $q) && strlen($symbol) > strlen($q)) {
                                    $quote = $q;
                                    break;
                                }
                            }
                            
                            $isRupiah = in_array($quote, ['BIDR', 'IDR', 'IDRT']);
                            $orderPriceFormatted = $isRupiah 
                                ? 'Rp ' . number_format($order['price'], 0, ',', '.') 
                                : '$' . number_format($order['price'], 4, '.', ',');
                            $marketPriceFormatted = $isRupiah 
                                ? 'Rp ' . number_format($order['current_price'], 0, ',', '.') 
                                : '$' . number_format($order['current_price'], 4, '.', ',');
                        @endphp
                        <tr>
                            <td style="font-size: 0.82rem; white-space: nowrap; color: var(--text-muted);">
                                {{ date('d/m/Y H:i:s', $order['time'] / 1000) }}
                            </td>
                            <td style="font-weight: 700; color: white;">
                                {{ $symbol }}
                            </td>
                            <td style="text-align: center;">
                                @if($order['side'] === 'BUY')
                                    <span class="badge badge-success" style="padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700;">BUY</span>
                                @else
                                    <span class="badge badge-danger" style="padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700;">SELL</span>
                                @endif
                            </td>
                            <td style="text-align: center; color: var(--text-muted);">
                                {{ $order['type'] }}
                            </td>
                            <td style="text-align: right; font-family: monospace; font-weight: 600; color: white;">
                                {{ $orderPriceFormatted }}
                            </td>
                            <td style="text-align: right; font-family: monospace;">
                                {{ number_format($order['origQty'], 4) }}
                            </td>
                            <td style="text-align: right; font-family: monospace; font-weight: 600;">
                                <div>${{ number_format($order['total_usdt'], 2) }}</div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: normal;">
                                    ≈ Rp {{ number_format($order['total_usdt'] * $usdt_idr_rate, 0, ',', '.') }}
                                </div>
                            </td>
                            <td style="text-align: right; font-family: monospace; color: var(--text-muted);">
                                {{ $order['current_price'] > 0 ? $marketPriceFormatted : '-' }}
                            </td>
                            <td style="text-align: center;">
                                @if(is_null($order['distance_percent']))
                                    <span style="color: var(--text-muted);">-</span>
                                @else
                                    @php
                                        $dist = $order['distance_percent'];
                                        $class = $dist >= 0 ? 'text-success' : 'text-danger';
                                        $sign = $dist >= 0 ? '+' : '';
                                    @endphp
                                    <span class="{{ $class }}" style="font-family: monospace; font-weight: 700;">
                                        {{ $sign }}{{ number_format($dist, 2) }}%
                                    </span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                <span class="badge" style="background: rgba(255,255,255,0.05); color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                    {{ $order['status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 4rem 0;">
                                Tidak ada antrean pesanan (Open Orders) aktif di akun Tokocrypto Anda saat ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
