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

<!-- Altcoin Scanner Section -->
<div class="glass-card mb-6">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card-title" style="margin-bottom: 0;">Altcoin Scanner (Stoch RSI < 7 & RSI < 40)</div>
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <span id="scan-info" style="font-size: 0.85rem; color: var(--text-muted); font-family: monospace; display: none;">
                Total dipindai: <span id="scan-total-scanned" style="color: var(--color-secondary); font-weight: 600;">0</span> | Ditemukan: <span id="scan-total-matches" style="color: var(--color-success); font-weight: 600;">0</span> koin |
            </span>
            <span id="scan-last-updated" style="font-size: 0.85rem; color: var(--text-muted); font-family: monospace;">Terakhir diupdate: -</span>
            <button id="btn-trigger-scan" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; border-radius: 6px; background-color: var(--color-primary); color: white; border: none; font-weight: 600;">
                <svg id="scan-spinner" class="animate-spin" style="display: none; width: 14px; height: 14px; color: white;" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity: 0.25; stroke: currentColor; stroke-width: 4;" cx="12" cy="12" r="10"></circle>
                    <path style="opacity: 0.75; fill: currentColor;" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span id="scan-btn-text">Scan Sekarang</span>
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="custom-table table-wide" style="font-size: 0.88rem;">
            <thead>
                <tr>
                    <th>Simbol</th>
                    <th>Harga Aktual</th>
                    <th>Stoch RSI %K</th>
                    <th>RSI (1D)</th>
                    <th>Volume 24h</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody id="scanner-table-body">
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem 0;">
                        Memuat data hasil scan...
                    </td>
                </tr>
            </tbody>
        </table>
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

        // 3. Altcoin Scanner Integration
        const tableBody = document.getElementById("scanner-table-body");
        const btnTrigger = document.getElementById("btn-trigger-scan");
        const scanSpinner = document.getElementById("scan-spinner");
        const scanBtnText = document.getElementById("scan-btn-text");
        const scanLastUpdated = document.getElementById("scan-last-updated");
        const scanInfo = document.getElementById("scan-info");
        const scanTotalScanned = document.getElementById("scan-total-scanned");
        const scanTotalMatches = document.getElementById("scan-total-matches");
        
        let lastUpdatedTimestamp = null;
        let pollingInterval = null;

        function loadScannerResults() {
            fetch('/api/scanner/results')
                .then(res => res.json())
                .then(data => {
                    // Update last updated timestamp
                    if (data.last_updated) {
                        const date = new Date(data.last_updated);
                        scanLastUpdated.textContent = 'Terakhir diupdate: ' + date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
                        
                        // Update scanned stats
                        if (data.scanned_count !== undefined) {
                            scanTotalScanned.textContent = data.scanned_count;
                            scanTotalMatches.textContent = data.matches_count;
                            scanInfo.style.display = 'inline-block';
                        }
                        
                        // Check if background scan finished
                        if (lastUpdatedTimestamp && data.last_updated !== lastUpdatedTimestamp) {
                            clearInterval(pollingInterval);
                            setScanningState(false);
                        }
                        lastUpdatedTimestamp = data.last_updated;
                    }

                    // Render matches
                    if (data.matches && data.matches.length > 0) {
                        let html = '';
                        data.matches.forEach(item => {
                            const kColor = item.stochK < 3 ? '#00e676' : 'var(--color-primary)';
                            const rsiColor = item.rsi < 30 ? 'badge-bullish' : 'badge-neutral';
                            
                            html += `
                                <tr>
                                    <td style="font-weight: 700; color: white;">${item.symbol}</td>
                                    <td style="font-family: monospace;">$${parseFloat(item.price).toFixed(4)}</td>
                                    <td>
                                        <span class="badge" style="background-color: ${kColor}; color: black; font-weight: 700; font-family: monospace;">K: ${item.stochK.toFixed(2)}</span>
                                    </td>
                                    <td>
                                        <span class="badge ${rsiColor}" style="font-family: monospace;">${item.rsi.toFixed(2)}</span>
                                    </td>
                                    <td style="font-family: monospace; color: var(--text-muted);">$${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(item.volume_24h)}</td>
                                    <td style="text-align: center;">
                                        <a href="/trade?symbol=${item.symbol}" class="badge badge-success" style="text-decoration: none; display: inline-block; padding: 0.35rem 0.65rem;">Jurnal Trade</a>
                                    </td>
                                </tr>
                            `;
                        });
                        tableBody.innerHTML = html;
                    } else {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                                    Tidak ada koin yang memenuhi kriteria (Stoch RSI < 7 & RSI < 40) saat ini.
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(err => {
                    console.error("Failed to load scanner results", err);
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--color-danger); padding: 2rem 0; font-weight: 600;">
                                Gagal memuat data hasil scan.
                            </td>
                        </tr>
                    `;
                });
        }

        function setScanningState(isScanning) {
            if (isScanning) {
                btnTrigger.disabled = true;
                scanSpinner.style.display = 'inline-block';
                scanBtnText.textContent = 'Memindai...';
                btnTrigger.style.opacity = '0.7';
            } else {
                btnTrigger.disabled = false;
                scanSpinner.style.display = 'none';
                scanBtnText.textContent = 'Scan Sekarang';
                btnTrigger.style.opacity = '1';
            }
        }

        btnTrigger.addEventListener("click", function() {
            setScanningState(true);
            
            // Get CSRF Token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            fetch('/api/scanner/trigger', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Start polling scanner results every 3 seconds
                    pollingInterval = setInterval(loadScannerResults, 3000);
                } else {
                    alert("Gagal memulai scan: " + data.message);
                    setScanningState(false);
                }
            })
            .catch(err => {
                console.error("Failed to trigger scan", err);
                alert("Gagal mengirim request scan.");
                setScanningState(false);
            });
        });

        // Load initial results
        loadScannerResults();

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
