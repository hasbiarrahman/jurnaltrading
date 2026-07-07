@extends('layouts.app')

@section('title', 'Portofolio Detail')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Portofolio Detail</h1>
        <p>Pantau saldo kepemilikan aset, harga rata-rata beli (average price), serta keuntungan/kerugian belum terealisasi</p>
    </div>
    <div class="header-actions">
        <span class="badge {{ $is_live ? 'badge-success' : 'badge-danger' }}">
            {{ $is_live ? 'Koneksi API Tokocrypto: LIVE' : 'Mode Fallback: Database Trade Logs' }}
        </span>
    </div>
</div>

<!-- Portfolio Performance Boxes -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
    <!-- Card 1: Valuation -->
    <div class="glass-card">
        <div class="card-title">
            <span>Nilai Portofolio Saat Ini</span>
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="stat-value text-success">${{ number_format($total_valuation_usdt, 2) }}</div>
        <div class="stat-desc">≈ Rp {{ number_format($total_valuation_idr, 0, ',', '.') }}</div>
    </div>

    <!-- Card 2: Cost Basis -->
    <div class="glass-card">
        <div class="card-title">
            <span>Modal Investasi (Cost Basis)</span>
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
        </div>
        <div class="stat-value" style="color: var(--text-main);">${{ number_format($total_cost_usdt, 2) }}</div>
        <div class="stat-desc">≈ Rp {{ number_format($total_cost_idr, 0, ',', '.') }}</div>
    </div>

    <!-- Card 3: Unrealized Profit/Loss -->
    <div class="glass-card">
        <div class="card-title">
            <span>PNL Belum Terealisasi (Unrealized PNL)</span>
            @if($total_pnl_usdt >= 0)
                <span class="badge badge-success">PROFIT</span>
            @else
                <span class="badge badge-danger">LOSS</span>
            @endif
        </div>
        <div class="stat-value {{ $total_pnl_usdt >= 0 ? 'text-success' : 'text-danger' }}">
            {{ $total_pnl_usdt >= 0 ? '+' : '' }}${{ number_format($total_pnl_usdt, 2) }}
            <span style="font-size: 1.1rem; font-weight: 600; margin-left: 0.25rem;">
                ({{ $total_pnl_usdt >= 0 ? '+' : '' }}{{ number_format($total_pnl_percent, 2) }}%)
            </span>
        </div>
        <div class="stat-desc {{ $total_pnl_usdt >= 0 ? 'text-success' : 'text-danger' }}">
            {{ $total_pnl_usdt >= 0 ? '+' : '' }}≈ Rp {{ number_format($total_pnl_idr, 0, ',', '.') }}
        </div>
    </div>
</div>

<!-- Detailed Asset Holdings Table -->
<div class="glass-card mb-6">
    <div class="card-title">Rincian Kepemilikan Aset</div>
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Aset</th>
                    <th>Jumlah Saldo</th>
                    <th>Harga Rata-rata Beli</th>
                    <th>Harga Pasar</th>
                    <th>Modal (Cost Basis)</th>
                    <th>Nilai Saat Ini</th>
                    <th>PNL Belum Terealisasi</th>
                    <th>PNL (%)</th>
                    <th>Sumber</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assets as $asset)
                    <tr>
                        <td style="font-weight: 800; font-size: 1.1rem; color: var(--color-secondary);">
                            {{ $asset['asset'] }}
                        </td>
                        <td style="font-family: monospace; font-weight: 600;">
                            {{ number_format($asset['total'], 6) }}
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400; margin-top: 0.15rem;">
                                Tersedia: {{ number_format($asset['free'], 4) }} | Order: {{ number_format($asset['locked'], 4) }}
                            </div>
                        </td>
                        <td style="font-family: monospace;">
                            ${{ number_format($asset['avg_buy_price_usdt'], 4) }}
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                                Rp {{ number_format($asset['avg_buy_price_idr'], 0, ',', '.') }}
                            </div>
                        </td>
                        <td style="font-family: monospace; font-weight: 500;">
                            ${{ number_format($asset['current_price_usdt'], 4) }}
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                                Rp {{ number_format($asset['current_price_idr'], 0, ',', '.') }}
                            </div>
                        </td>
                        <td style="font-family: monospace;">
                            ${{ number_format($asset['cost_usdt'], 2) }}
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                                Rp {{ number_format($asset['cost_idr'], 0, ',', '.') }}
                            </div>
                        </td>
                        <td style="font-family: monospace; font-weight: 600;">
                            ${{ number_format($asset['value_usdt'], 2) }}
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                                Rp {{ number_format($asset['value_idr'], 0, ',', '.') }}
                            </div>
                        </td>
                        <td style="font-family: monospace; font-weight: 600;" class="{{ $asset['pnl_usdt'] >= 0 ? 'text-success' : 'text-danger' }}">
                            @if($asset['pnl_usdt'] >= 0)
                                +${{ number_format($asset['pnl_usdt'], 2) }}
                                <div style="font-size: 0.75rem; margin-top: 0.15rem;">
                                    +Rp {{ number_format($asset['pnl_idr'], 0, ',', '.') }}
                                </div>
                            @else
                                -${{ number_format(abs($asset['pnl_usdt']), 2) }}
                                <div style="font-size: 0.75rem; margin-top: 0.15rem;">
                                    -Rp {{ number_format(abs($asset['pnl_idr']), 0, ',', '.') }}
                                </div>
                            @endif
                        </td>
                        <td style="font-family: monospace; font-weight: 700;" class="{{ $asset['pnl_percent'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $asset['pnl_percent'] >= 0 ? '+' : '' }}{{ number_format($asset['pnl_percent'], 2) }}%
                        </td>
                        <td style="font-size: 0.75rem; color: var(--text-muted); font-style: italic;">
                            {{ $asset['source'] }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 4rem 0;">
                            Tidak ada aset dalam portofolio Anda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
