@extends('layouts.app')

@section('title', 'Dashboard Overview')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Dashboard Overview</h1>
        <p>Ringkasan performa akun trading Tokocrypto Jurnal Trading Anda</p>
    </div>
    <div class="header-actions">
        <span class="badge {{ $portfolio['is_live'] ? 'badge-success' : 'badge-danger' }}">
            {{ $portfolio['is_live'] ? 'Koneksi API Tokocrypto: LIVE' : 'Mode Fallback: Database Trade Logs' }}
        </span>
    </div>
</div>

<!-- Stats Summary Grid -->
<div class="stats-grid">
    <!-- Stat 1: Total Valuation -->
    <div class="glass-card">
        <div class="card-title">
            <span>Estimasi Nilai Portofolio</span>
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="stat-value text-success">${{ number_format($portfolio['total_usdt'], 2) }}</div>
        <div class="stat-desc">≈ Rp {{ number_format($portfolio['total_idr'], 0, ',', '.') }}</div>
    </div>

    <!-- Stat 2: Active Watchlist -->
    <div class="glass-card">
        <div class="card-title">
            <span>Aset dalam Watchlist</span>
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        </div>
        <div class="stat-value" style="color: var(--color-secondary);">{{ $watchlistCount }}</div>
        <div class="stat-desc">Koin sedang dipantau di market</div>
    </div>

    <!-- Stat 3: Total Trades -->
    <div class="glass-card">
        <div class="card-title">
            <span>Total Catatan Perdagangan</span>
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
        </div>
        <div class="stat-value" style="color: var(--color-primary);">{{ $tradeCount }}</div>
        <div class="stat-desc">Transaksi terdaftar di database</div>
    </div>
</div>

<div class="dashboard-row">
    <!-- Allocation Chart -->
    <div class="glass-card">
        <div class="card-title">Alokasi Aset Portofolio</div>
        @if(count($chartValues) > 0)
            <div style="height: 300px; width: 100%; display: flex; justify-content: center; align-items: center;">
                <canvas id="allocationChart" style="max-height: 100%; max-width: 100%;"></canvas>
            </div>
        @else
            <div style="height: 300px; display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                Belum ada data alokasi aset. Silakan tambahkan riwayat perdagangan terlebih dahulu.
            </div>
        @endif
    </div>

    <!-- Watchlist Highlights -->
    <div class="glass-card">
        <div class="card-title">Highlights Watchlist</div>
        <div class="table-responsive">
            <table class="custom-table" style="font-size: 0.85rem;">
                <thead>
                    <tr>
                        <th>Simbol</th>
                        <th>Harga</th>
                        <th>Stoch RSI (1D)</th>
                        <th>Divergence</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($watchlistData as $item)
                        <tr class="watchlist-highlight-row" data-symbol="{{ $item->symbol }}">
                            <td style="font-weight: 700;">{{ $item->symbol }}</td>
                            <td>
                                <div class="skeleton skeleton-text" id="dash-price-loader-{{ $item->symbol }}" style="width: 70px; height: 1.1rem; margin-bottom: 0;"></div>
                                <span id="dash-price-{{ $item->symbol }}" style="font-family: monospace; display: none;">-</span>
                            </td>
                            <td>
                                <div class="skeleton skeleton-text" id="dash-stoch-loader-{{ $item->symbol }}" style="width: 80px; height: 1.1rem; margin-bottom: 0;"></div>
                                <span id="dash-stoch-{{ $item->symbol }}" style="font-family: monospace; display: none;">-</span>
                            </td>
                            <td>
                                <div class="skeleton skeleton-badge" id="dash-div-loader-{{ $item->symbol }}" style="width: 60px; height: 1.1rem;"></div>
                                <div id="dash-div-{{ $item->symbol }}" style="display: none;">
                                    <span class="badge badge-neutral">NONE</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
                                Watchlist kosong.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Activity Log -->
<div class="glass-card mb-6">
    <div class="card-title">Riwayat Perdagangan Terakhir</div>
    <div class="table-responsive">
        <table class="custom-table table-wide">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Simbol</th>
                    <th>Tipe</th>
                    <th>Harga</th>
                    <th>Jumlah</th>
                    <th>Total</th>
                    <th>Stoch RSI (K/D)</th>
                    <th>Divergence</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentTrades as $trade)
                    <tr>
                        <td>{{ date('d/m/Y H:i', strtotime($trade->trade_time)) }}</td>
                        <td style="font-weight: 700;">{{ $trade->symbol }}</td>
                        <td>
                            <span class="badge {{ $trade->type === 'BUY' ? 'badge-success' : 'badge-danger' }}">
                                {{ $trade->type }}
                            </span>
                        </td>
                        <td style="font-family: monospace;">${{ number_format($trade->price, 4) }}</td>
                        <td style="font-family: monospace;">{{ number_format($trade->amount, 4) }}</td>
                        <td style="font-family: monospace;">${{ number_format($trade->total, 2) }}</td>
                        <td>
                            @if(!is_null($trade->stoch_rsi_k))
                                <span style="font-family: monospace;">K: {{ $trade->stoch_rsi_k }} / D: {{ $trade->stoch_rsi_d }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($trade->divergence === 'Bullish')
                                <span class="badge badge-bullish">BULLISH</span>
                            @elseif($trade->divergence === 'Bearish')
                                <span class="badge badge-bearish">BEARISH</span>
                            @else
                                <span class="badge badge-neutral">NONE</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                            Belum ada riwayat transaksi.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Chart.js allocation code (only if canvas exists)
        const chartCanvas = document.getElementById('allocationChart');
        if (chartCanvas) {
            const ctx = chartCanvas.getContext('2d');
            const allocationChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: {!! json_encode($chartLabels) !!},
                    datasets: [{
                        data: {!! json_encode($chartValues) !!},
                        backgroundColor: [
                            '#9d4edd',
                            '#00f2fe',
                            '#00e676',
                            '#ff9f00',
                            '#ff1744',
                            '#3a0ca3',
                            '#4361ee'
                        ],
                        borderWidth: 1,
                        borderColor: 'rgba(255, 255, 255, 0.05)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: window.innerWidth < 768 ? 'bottom' : 'right',
                            labels: {
                                color: '#9ca3af',
                                font: {
                                    family: 'Outfit',
                                    size: 12
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        }
        
        // 2. Watchlist Highlights AJAX
        const highlightRows = document.querySelectorAll(".watchlist-highlight-row");
        highlightRows.forEach(row => {
            const symbol = row.getAttribute("data-symbol");
            fetch(`/api/watchlist-metrics/${symbol}`)
                .then(response => {
                    if (!response.ok) throw new Error("Fetch error");
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.metrics) {
                        const m = data.metrics;
                        
                        // Price
                        const priceLoader = document.getElementById(`dash-price-loader-${symbol}`);
                        const priceEl = document.getElementById(`dash-price-${symbol}`);
                        if (priceLoader && priceEl) {
                            priceLoader.style.display = 'none';
                            priceEl.textContent = formatPrice(m.price, symbol);
                            priceEl.style.display = 'block';
                        }

                        // Stoch RSI
                        const stochLoader = document.getElementById(`dash-stoch-loader-${symbol}`);
                        const stochEl = document.getElementById(`dash-stoch-${symbol}`);
                        if (stochLoader && stochEl) {
                            stochLoader.style.display = 'none';
                            stochEl.textContent = `K: ${Math.round(m.stoch_k)} / D: ${Math.round(m.stoch_d)}`;
                            stochEl.style.display = 'block';
                        }

                        // Divergence
                        const divLoader = document.getElementById(`dash-div-loader-${symbol}`);
                        const divEl = document.getElementById(`dash-div-${symbol}`);
                        if (divLoader && divEl) {
                            divLoader.style.display = 'none';
                            let badgeHtml = '<span class="badge badge-neutral">NONE</span>';
                            if (m.divergence === 'Bullish') {
                                badgeHtml = '<span class="badge badge-bullish">BULLISH</span>';
                            } else if (m.divergence === 'Bearish') {
                                badgeHtml = '<span class="badge badge-bearish">BEARISH</span>';
                            }
                            divEl.innerHTML = badgeHtml;
                            divEl.style.display = 'block';
                        }
                    }
                })
                .catch(err => {
                    console.error("Failed to load metrics for " + symbol, err);
                    setErrorState(symbol);
                });
        });

        function formatPrice(price, symbol) {
            if (symbol.endsWith('BIDR') || symbol.endsWith('IDRT')) {
                return 'Rp ' + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(price);
            }
            return '$' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(price);
        }

        function setErrorState(symbol) {
            const priceLoader = document.getElementById(`dash-price-loader-${symbol}`);
            if (priceLoader) priceLoader.style.display = 'none';
            const priceEl = document.getElementById(`dash-price-${symbol}`);
            if (priceEl) {
                priceEl.textContent = 'Eror';
                priceEl.style.color = 'var(--color-danger)';
                priceEl.style.display = 'block';
            }

            const stochLoader = document.getElementById(`dash-stoch-loader-${symbol}`);
            if (stochLoader) stochLoader.style.display = 'none';
            const stochEl = document.getElementById(`dash-stoch-${symbol}`);
            if (stochEl) {
                stochEl.textContent = 'Error';
                stochEl.style.display = 'block';
            }

            const divLoader = document.getElementById(`dash-div-loader-${symbol}`);
            if (divLoader) divLoader.style.display = 'none';
            const divEl = document.getElementById(`dash-div-${symbol}`);
            if (divEl) {
                divEl.innerHTML = '<span class="badge badge-danger">ERR</span>';
                divEl.style.display = 'block';
            }
        }
    });
</script>
@endsection
