@extends('layouts.app')

@section('title', 'Realisasi Profit & Loss')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Realisasi Profit & Loss (PNL)</h1>
        <p>Catatan realisasi keuntungan dan kerugian dari penjualan aset non-USDT dan non-IDR</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid mb-6">
    <div class="glass-card">
        <div class="card-title" style="color: var(--text-muted);">Total Realisasi Profit</div>
        <div class="stat-value text-success" style="margin-top: 0.5rem; font-size: 1.8rem;">
            +${{ number_format($total_profit_usdt, 2) }}
        </div>
        <div class="stat-desc text-success">
            ≈ +Rp {{ number_format($total_profit_idr, 0, ',', '.') }}
        </div>
    </div>
    <div class="glass-card">
        <div class="card-title" style="color: var(--text-muted);">Total Realisasi Loss</div>
        <div class="stat-value text-danger" style="margin-top: 0.5rem; font-size: 1.8rem;">
            -${{ number_format($total_loss_usdt, 2) }}
        </div>
        <div class="stat-desc text-danger">
            ≈ -Rp {{ number_format($total_loss_idr, 0, ',', '.') }}
        </div>
    </div>
    <div class="glass-card">
        <div class="card-title" style="color: var(--text-muted);">Net Realized PNL</div>
        <div class="stat-value {{ $net_pnl_usdt >= 0 ? 'text-success' : 'text-danger' }}" style="margin-top: 0.5rem; font-size: 1.8rem;">
            {{ $net_pnl_usdt >= 0 ? '+' : '' }}${{ number_format($net_pnl_usdt, 2) }}
        </div>
        <div class="stat-desc {{ $net_pnl_usdt >= 0 ? 'text-success' : 'text-danger' }}">
            ≈ {{ $net_pnl_usdt >= 0 ? '+' : '' }}Rp {{ number_format($net_pnl_idr, 0, ',', '.') }}
        </div>
    </div>
    <div class="glass-card">
        <div class="card-title" style="color: var(--text-muted);">Floating Loss (Aset Minus)</div>
        <div class="stat-value text-danger" style="margin-top: 0.5rem; font-size: 1.8rem;">
            -${{ number_format($unrealized_loss_usdt, 2) }}
        </div>
        <div class="stat-desc text-danger">
            ≈ -Rp {{ number_format($unrealized_loss_idr, 0, ',', '.') }}
        </div>
    </div>
</div>

<!-- Date Filter Form -->
<div class="glass-card mb-6" style="padding: 1.25rem;">
    <form action="{{ route('portfolio.pnl') }}" method="GET">
        <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0; flex-grow: 1; min-width: 200px;">
                <label for="start_date" class="form-label" style="margin-bottom: 0.25rem;">Tanggal Mulai</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $start_date }}" style="padding: 0.55rem;">
            </div>
            <div class="form-group" style="margin-bottom: 0; flex-grow: 1; min-width: 200px;">
                <label for="end_date" class="form-label" style="margin-bottom: 0.25rem;">Tanggal Akhir</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $end_date }}" style="padding: 0.55rem;">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem; font-size: 0.88rem; font-weight: 600; cursor: pointer; border-radius: 6px; border: none; background: var(--color-primary); color: white;">
                    Filter
                </button>
                @if($start_date || $end_date)
                    <a href="{{ route('portfolio.pnl') }}" class="btn btn-secondary" style="padding: 0.6rem 1.5rem; font-size: 0.88rem; font-weight: 600; text-decoration: none; display: inline-block; border-radius: 6px; background: rgba(255, 255, 255, 0.05); color: var(--text-main); border: 1px solid var(--border-light);">
                        Reset
                    </a>
                @endif
            </div>
        </div>
    </form>
</div>

<!-- Grouped Summary by Asset -->
<div class="glass-card mb-6">
    <div class="card-header" style="margin-bottom: 1rem;">
        <div class="card-title">Ringkasan Profit & Loss Per Aset</div>
    </div>
    <div class="table-responsive">
        <table class="custom-table" style="font-size: 0.88rem;">
            <thead>
                <tr>
                    <th>Nama Aset</th>
                    <th style="text-align: right;">Total Koin Terjual</th>
                    <th style="text-align: right;">Total PNL (USD)</th>
                    <th style="text-align: right;">Total PNL (Rupiah)</th>
                    <th style="text-align: center;">Persentase PNL</th>
                </tr>
            </thead>
            <tbody>
                @forelse($asset_summaries as $summary)
                    <tr>
                        <td style="font-weight: 700; color: white;">{{ $summary['asset'] }}</td>
                        <td style="text-align: right; font-family: monospace;">{{ number_format($summary['total_sold'], 4) }}</td>
                        <td style="text-align: right; font-family: monospace; font-weight: 700;" class="{{ $summary['pnl_usdt'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $summary['pnl_usdt'] >= 0 ? '+' : '' }}${{ number_format($summary['pnl_usdt'], 2) }}
                        </td>
                        <td style="text-align: right; font-family: monospace; font-weight: 700;" class="{{ $summary['pnl_usdt'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $summary['pnl_usdt'] >= 0 ? '+' : '' }}Rp {{ number_format($summary['pnl_idr'], 0, ',', '.') }}
                        </td>
                        <td style="text-align: center;">
                            <span class="badge {{ $summary['pnl_percent'] >= 0 ? 'badge-success' : 'badge-danger' }}" style="font-family: monospace; font-weight: 700;">
                                {{ $summary['pnl_percent'] >= 0 ? '+' : '' }}{{ number_format($summary['pnl_percent'], 2) }}%
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
                            Tidak ada data ringkasan aset untuk periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Detailed PNL Transaction Logs -->
<div class="glass-card">
    <div class="card-header" style="margin-bottom: 1rem;">
        <div class="card-title">Detail Transaksi Penjualan (Realisasi PNL)</div>
    </div>
    <div class="table-responsive">
        <table class="custom-table" style="font-size: 0.88rem;">
            <thead>
                <tr>
                    <th>Tanggal Penjualan</th>
                    <th>Aset</th>
                    <th style="text-align: right;">Jumlah</th>
                    <th style="text-align: right;">Harga Rata-rata Beli (Avg Buy)</th>
                    <th style="text-align: right;">Harga Jual</th>
                    <th style="text-align: right;">Realisasi Profit / Loss</th>
                    <th style="text-align: center;">PNL (%)</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pnl_records as $record)
                    <tr>
                        <td style="font-size: 0.82rem; white-space: nowrap;">
                            {{ date('d/m/Y H:i', strtotime($record['trade_time'])) }}
                        </td>
                        <td style="font-weight: 700; color: white;">{{ $record['asset'] }}</td>
                        <td style="text-align: right; font-family: monospace;">{{ number_format($record['amount'], 4) }}</td>
                        <td style="text-align: right; font-family: monospace; color: var(--text-muted);">
                            ${{ number_format($record['avg_buy_price_usdt'], 4) }}
                        </td>
                        <td style="text-align: right; font-family: monospace; color: white;">
                            ${{ number_format($record['sell_price_usdt'], 4) }}
                        </td>
                        <td style="text-align: right; font-family: monospace; font-weight: 700;" class="{{ $record['pnl_usdt'] >= 0 ? 'text-success' : 'text-danger' }}">
                            <div>{{ $record['pnl_usdt'] >= 0 ? '+' : '' }}${{ number_format($record['pnl_usdt'], 2) }}</div>
                            <div style="font-size: 0.75rem; font-weight: normal; opacity: 0.85;">
                                {{ $record['pnl_usdt'] >= 0 ? '+' : '' }}Rp {{ number_format($record['pnl_usdt'] * $usdt_idr_rate, 0, ',', '.') }}
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge {{ $record['pnl_percent'] >= 0 ? 'badge-success' : 'badge-danger' }}" style="font-family: monospace; font-weight: 700;">
                                {{ $record['pnl_percent'] >= 0 ? '+' : '' }}{{ number_format($record['pnl_percent'], 2) }}%
                            </span>
                        </td>
                        <td style="font-size: 0.82rem; color: var(--text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $record['notes'] }}">
                            {{ $record['notes'] ?? '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                            Belum ada riwayat transaksi penjualan (SELL) terdaftar di database.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
