@extends('layouts.app')

@section('title', 'Scanner Altcoin')

@section('content')
<style>
    .btn-tab {
        transition: all 0.2s ease;
    }
    .btn-tab:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        color: white !important;
    }
    .btn-tab.active {
        background: var(--color-primary) !important;
        color: white !important;
    }
</style>

<div class="page-header">
    <div class="page-title">
        <h1>Scanner Altcoin (500 Koin)</h1>
        <p>Daftar lengkap 500 altcoin volume teratas di KuCoin beserta indikator teknikal</p>
    </div>
    <div class="header-actions" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <span id="scan-last-updated" style="font-size: 0.85rem; color: var(--text-muted); font-family: monospace;">Terakhir diupdate: -</span>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label for="timeframe-select" style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Timeframe:</label>
            <select id="timeframe-select" style="padding: 0.5rem 1.75rem 0.5rem 0.75rem; font-size: 0.85rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.25); color: white; outline: none; cursor: pointer; font-weight: 600; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;utf8,<svg fill=&quot;white&quot; height=&quot;24&quot; viewBox=&quot;0 0 24 24&quot; width=&quot;24&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;><path d=&quot;M7 10l5 5 5-5z&quot;/></svg>'); background-repeat: no-repeat; background-position: right 6px center; background-size: 18px;">
                <option value="1day" style="background: #0f1026;">1 Hari (1D)</option>
                <option value="4hour" style="background: #0f1026;">4 Jam (4H)</option>
            </select>
        </div>
        <button id="btn-trigger-scan" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; border-radius: 6px; background-color: var(--color-primary); color: white; border: none; font-weight: 600;">
            <svg id="scan-spinner" class="animate-spin" style="display: none; width: 14px; height: 14px; color: white;" fill="none" viewBox="0 0 24 24">
                <circle style="opacity: 0.25; stroke: currentColor; stroke-width: 4;" cx="12" cy="12" r="10"></circle>
                <path style="opacity: 0.75; fill: currentColor;" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span id="scan-btn-text">Scan Sekarang</span>
        </button>
    </div>
</div>

<!-- Stats Cards Grid -->
<div class="stats-grid mb-6">
    <div class="glass-card">
        <div class="card-title" style="font-size: 0.9rem; color: var(--text-muted);">Total Dipindai</div>
        <div class="stat-value" id="stat-total-scanned" style="color: white; font-size: 2rem; font-weight: 700; margin-top: 0.5rem;">0</div>
        <div class="stat-desc">Koin berpasangan USDT teratas</div>
    </div>
    <div class="glass-card">
        <div class="card-title" style="font-size: 0.9rem; color: var(--text-muted);">Jenuh Jual (Oversold)</div>
        <div class="stat-value" id="stat-total-oversold" style="color: #00e676; font-size: 2rem; font-weight: 700; margin-top: 0.5rem;">0</div>
        <div class="stat-desc" id="stat-oversold-desc">Stoch RSI < 7 & RSI < 40</div>
    </div>
    <div class="glass-card">
        <div class="card-title" style="font-size: 0.9rem; color: var(--text-muted);">Aset Jurnal</div>
        <div class="stat-value" id="stat-total-journal" style="color: #c084fc; font-size: 2rem; font-weight: 700; margin-top: 0.5rem;">0</div>
        <div class="stat-desc">Aset terdaftar di Jurnal Transaksi</div>
    </div>
</div>

<!-- Filter Controls & Search Panel -->
<div class="glass-card mb-6" style="padding: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <!-- Filter Tabs -->
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <button class="btn btn-tab active" data-filter="all" style="padding: 0.5rem 1.25rem; font-size: 0.85rem; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; background: var(--color-primary); color: white;">
                Semua (0)
            </button>
            <button class="btn btn-tab" data-filter="oversold" style="padding: 0.5rem 1.25rem; font-size: 0.85rem; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; background: rgba(255,255,255,0.05); color: var(--text-muted);">
                Jenuh Jual (0)
            </button>
            <button class="btn btn-tab" data-filter="double_bottom" style="padding: 0.5rem 1.25rem; font-size: 0.85rem; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; background: rgba(255,255,255,0.05); color: var(--text-muted);">
                Double Bottom (0)
            </button>
            <button class="btn btn-tab" data-filter="journal" style="padding: 0.5rem 1.25rem; font-size: 0.85rem; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; background: rgba(255,255,255,0.05); color: var(--text-muted);">
                Aset Jurnal (0)
            </button>
        </div>
        
        <!-- Search box -->
        <div style="position: relative; width: 100%; max-width: 320px;">
            <input type="text" id="search-input" placeholder="Cari nama koin... (contoh: HBAR, SOL)" style="width: 100%; padding: 0.6rem 1rem; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.25); color: white; outline: none; font-size: 0.88rem;">
        </div>
    </div>
</div>

<!-- Table View -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="custom-table table-wide" style="font-size: 0.88rem;">
            <thead>
                <tr>
                    <th style="cursor: pointer; user-select: none;" onclick="changeSort('symbol')">Simbol <span id="sort-icon-symbol" style="margin-left: 3px; font-size: 0.75rem;">↕</span></th>
                    <th style="cursor: pointer; user-select: none;" onclick="changeSort('price')">Harga Aktual <span id="sort-icon-price" style="margin-left: 3px; font-size: 0.75rem;">↕</span></th>
                    <th style="cursor: pointer; user-select: none;" onclick="changeSort('stochK')">Stoch RSI %K <span id="sort-icon-stochK" style="margin-left: 3px; font-size: 0.75rem;">↕</span></th>
                    <th style="cursor: pointer; user-select: none;" onclick="changeSort('rsi')"><span id="rsi-header-text">RSI (1D)</span> <span id="sort-icon-rsi" style="margin-left: 3px; font-size: 0.75rem;">↕</span></th>
                    <th style="cursor: pointer; user-select: none;" onclick="changeSort('volume_24h')">Volume 24h <span id="sort-icon-volume_24h" style="margin-left: 3px; font-size: 0.75rem;">↕</span></th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody id="scanner-all-table-body">
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                        Memuat data 500 altcoin...
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Analisis Swing -->
<div id="modal-analisa" style="display: none; position: fixed; inset: 0; z-index: 9999; backdrop-filter: blur(12px); background: rgba(0,0,0,0.6); align-items: flex-start; justify-content: center; padding: 2rem 1rem; overflow-y: auto; transition: all 0.3s ease;">
    <div class="glass-card" style="width: 100%; max-width: 520px; border: 1px solid rgba(255,255,255,0.1); background: rgba(13, 14, 38, 0.85); box-shadow: 0 24px 64px rgba(0,0,0,0.8); position: relative; animation: modalSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); padding: 1.5rem;">
        <!-- Close Button -->
        <button onclick="closeAnalysisModal()" style="position: absolute; top: 1.25rem; right: 1.25rem; background: none; border: none; color: var(--text-muted); cursor: pointer; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='var(--text-muted)'">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <!-- Modal Header -->
        <div style="margin-bottom: 1.5rem;">
            <h2 id="analisa-symbol" style="font-size: 1.5rem; font-weight: 800; color: white; margin-bottom: 0.25rem; display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 0;">
                --
            </h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Hasil Analisis Kalkulasi Swing Trade (Struktur Harian)</p>
        </div>

        <!-- Loading State -->
        <div id="analisa-loading" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem 0;">
            <svg class="animate-spin" style="width: 32px; height: 32px; color: var(--color-primary); margin-bottom: 1rem;" fill="none" viewBox="0 0 24 24">
                <circle style="opacity: 0.25; stroke: currentColor; stroke-width: 4;" cx="12" cy="12" r="10"></circle>
                <path style="opacity: 0.75; fill: currentColor;" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Mengekstrak klines & menghitung Pivot...</span>
        </div>

        <!-- Content State -->
        <div id="analisa-content" style="display: none;">
            <!-- Price and Score Row -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                <div>
                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block; text-transform: uppercase; font-weight: 600;">Harga Aktual</span>
                    <span id="analisa-price" style="font-size: 1.35rem; font-weight: 800; font-family: monospace; color: white;">$0.0000</span>
                </div>
                <div>
                    <span style="font-size: 0.75rem; color: var(--text-muted); display: block; text-transform: uppercase; font-weight: 600;">Kualitas Setup</span>
                    <span id="analisa-score" style="font-size: 1.1rem; font-weight: 700; display: block; margin-top: 0.15rem;">--</span>
                </div>
            </div>

            <!-- Risk Reward Card -->
            <div style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.15); padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center;">
                <span style="font-size: 0.75rem; color: #93c5fd; display: block; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem;">Rasio Risk-to-Reward (R2 Target)</span>
                <div style="font-size: 2.25rem; font-weight: 900; color: white; font-family: monospace; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <span>1 :</span>
                    <span id="analisa-ratio" style="color: #60a5fa;">0.00</span>
                </div>
                <div style="display: flex; justify-content: center; gap: 1.5rem; margin-top: 0.75rem; font-size: 0.8rem; font-family: monospace; border-top: 1px solid rgba(59,130,246,0.15); padding-top: 0.75rem;">
                    <span style="color: #fca5a5;">Risiko (S1): -<span id="analisa-pct-risk">0.0</span>%</span>
                    <span style="color: #86efac;">Potensi (R2): +<span id="analisa-pct-reward">0.0</span>%</span>
                </div>
            </div>

            <!-- Levels List -->
            <div style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.85rem; font-weight: 700; color: white; text-transform: uppercase; margin-bottom: 0.75rem; letter-spacing: 0.5px; margin-top: 0;">Level Pivot Teknikal (1D)</h4>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: rgba(239, 68, 68, 0.05); border-left: 3px solid #ef4444; border-radius: 4px;">
                        <span style="font-size: 0.85rem; color: #fca5a5; font-weight: 600;">Resisten 3 (R3 - Max Target)</span>
                        <span id="analisa-r3" style="font-family: monospace; font-weight: 700; color: white;">$0.0000</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: rgba(59, 130, 246, 0.05); border-left: 3px solid #3b82f6; border-radius: 4px;">
                        <span style="font-size: 0.85rem; color: #93c5fd; font-weight: 600;">Resisten 2 (R2 - Swing Target)</span>
                        <span id="analisa-r2" style="font-family: monospace; font-weight: 700; color: white;">$0.0000</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: rgba(255, 255, 255, 0.02); border-left: 3px solid #94a3b8; border-radius: 4px;">
                        <span style="font-size: 0.85rem; color: #cbd5e1; font-weight: 600;">Resisten 1 (R1 - Minor Target)</span>
                        <span id="analisa-r1" style="font-family: monospace; font-weight: 700; color: white;">$0.0000</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: rgba(16, 185, 129, 0.05); border-left: 3px solid #10b981; border-radius: 4px;">
                        <span style="font-size: 0.85rem; color: #a7f3d0; font-weight: 600;">Support 1 (S1 - Batas Stop Loss)</span>
                        <span id="analisa-s1" style="font-family: monospace; font-weight: 700; color: white;">$0.0000</span>
                    </div>
                </div>
            </div>

            <!-- Liquidation Data Card -->
            <div id="analisa-liquidation-card" style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.85rem; font-weight: 700; color: white; text-transform: uppercase; margin-bottom: 0.75rem; letter-spacing: 0.5px; margin-top: 0;">Data Likuidasi Futures (Coinalyze)</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <!-- Long Liquidations -->
                    <div style="padding: 0.75rem; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.15); border-radius: 6px; text-align: center;">
                        <span style="font-size: 0.7rem; color: #a7f3d0; display: block; font-weight: 600; text-transform: uppercase; margin-bottom: 0.45rem;">Likuidasi Long</span>
                        <div style="font-size: 0.8rem; color: white; display: flex; flex-direction: column; gap: 0.35rem;">
                            <div style="width: 100%; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.15rem;">
                                <span style="color: var(--text-muted);">4 Jam:</span>
                                <strong id="analisa-liq-long-4h" style="font-family: monospace;">-</strong>
                            </div>
                            <div style="width: 100%; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.15rem;">
                                <span style="color: var(--text-muted);">24 Jam:</span>
                                <strong id="analisa-liq-long-24h" style="font-family: monospace;">-</strong>
                            </div>
                            <div style="width: 100%; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.15rem;">
                                <span style="color: var(--text-muted);">3 Hari:</span>
                                <strong id="analisa-liq-long-3d" style="font-family: monospace;">-</strong>
                            </div>
                            <div style="width: 100%; display: flex; justify-content: space-between;">
                                <span style="color: var(--text-muted);">7 Hari:</span>
                                <strong id="analisa-liq-long-7d" style="font-family: monospace;">-</strong>
                            </div>
                        </div>
                    </div>
                    <!-- Short Liquidations -->
                    <div style="padding: 0.75rem; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.15); border-radius: 6px; text-align: center;">
                        <span style="font-size: 0.7rem; color: #fca5a5; display: block; font-weight: 600; text-transform: uppercase; margin-bottom: 0.45rem;">Likuidasi Short</span>
                        <div style="font-size: 0.8rem; color: white; display: flex; flex-direction: column; gap: 0.35rem;">
                            <div style="width: 100%; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.15rem;">
                                <span style="color: var(--text-muted);">4 Jam:</span>
                                <strong id="analisa-liq-short-4h" style="font-family: monospace;">-</strong>
                            </div>
                            <div style="width: 100%; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.15rem;">
                                <span style="color: var(--text-muted);">24 Jam:</span>
                                <strong id="analisa-liq-short-24h" style="font-family: monospace;">-</strong>
                            </div>
                            <div style="width: 100%; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.15rem;">
                                <span style="color: var(--text-muted);">3 Hari:</span>
                                <strong id="analisa-liq-short-3d" style="font-family: monospace;">-</strong>
                            </div>
                            <div style="width: 100%; display: flex; justify-content: space-between;">
                                <span style="color: var(--text-muted);">7 Hari:</span>
                                <strong id="analisa-liq-short-7d" style="font-family: monospace;">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="analisa-liq-warning" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.4rem; font-style: italic; display: none;">
                    *Masukkan API Key Coinalyze di Pengaturan untuk mengaktifkan data ini.
                </div>
            </div>

            <!-- Futures Metrics Card (Volume & OI) -->
            <div id="analisa-futures-metrics-card" style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.85rem; font-weight: 700; color: white; text-transform: uppercase; margin-bottom: 0.75rem; letter-spacing: 0.5px; margin-top: 0;">Metrik Futures 24 Jam Terakhir</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <!-- Volume Futures -->
                    <div style="padding: 0.75rem; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; text-align: center;">
                        <span style="font-size: 0.7rem; color: var(--text-muted); display: block; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem;">Volume Futures</span>
                        <div id="analisa-fut-volume" style="font-size: 1.1rem; font-weight: 800; color: white; font-family: monospace;">-</div>
                        <div id="analisa-fut-volume-change" style="font-size: 0.75rem; font-weight: 600; margin-top: 0.15rem;">-</div>
                    </div>
                    <!-- Open Interest -->
                    <div style="padding: 0.75rem; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; text-align: center;">
                        <span style="font-size: 0.7rem; color: var(--text-muted); display: block; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem;">Open Interest (OI)</span>
                        <div id="analisa-fut-oi" style="font-size: 1.1rem; font-weight: 800; color: white; font-family: monospace;">-</div>
                        <div id="analisa-fut-oi-change" style="font-size: 0.75rem; font-weight: 600; margin-top: 0.15rem;">-</div>
                    </div>
                </div>
            </div>

            <!-- Recommendation Card -->
            <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px;">
                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; font-weight: 600; text-transform: uppercase; margin-bottom: 0.35rem;">Saran Tindakan:</span>
                <p id="analisa-advice" style="font-size: 0.85rem; color: #e2e8f0; line-height: 1.45; margin: 0;">--</p>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes modalSlideIn {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>

<script>
    let scannerItems = [];
    let currentFilter = 'all';
    let searchQuery = '';
    let sortColumn = 'volume_24h';
    let sortDirection = 'desc'; // 'asc' or 'desc'
    
    let lastUpdatedTimestamp = null;
    let pollingInterval = null;

    const timeframeSelect = document.getElementById("timeframe-select");
    let selectedTimeframe = timeframeSelect ? timeframeSelect.value : '1day';

    if (timeframeSelect) {
        timeframeSelect.addEventListener("change", function() {
            selectedTimeframe = this.value;
            clearInterval(pollingInterval);
            setScanningState(false);
            
            scannerItems = [];
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                        Memuat data hasil scan timeframe baru...
                    </td>
                </tr>
            `;
            
            const oversoldDesc = document.getElementById("stat-oversold-desc");
            const rsiHeader = document.getElementById("rsi-header-text");
            if (selectedTimeframe === '4hour') {
                if (oversoldDesc) oversoldDesc.textContent = "Stoch RSI <= 27 & RSI < 40";
                if (rsiHeader) rsiHeader.textContent = "RSI (4H)";
            } else {
                if (oversoldDesc) oversoldDesc.textContent = "Stoch RSI < 7 & RSI < 40";
                if (rsiHeader) rsiHeader.textContent = "RSI (1D)";
            }
            
            loadScannerAllResults();
        });
    }

    const tableBody = document.getElementById("scanner-all-table-body");
    const searchInput = document.getElementById("search-input");
    const statTotalScanned = document.getElementById("stat-total-scanned");
    const statTotalOversold = document.getElementById("stat-total-oversold");
    const statTotalJournal = document.getElementById("stat-total-journal");
    const scanLastUpdated = document.getElementById("scan-last-updated");
    
    const btnTrigger = document.getElementById("btn-trigger-scan");
    const scanSpinner = document.getElementById("scan-spinner");
    const scanBtnText = document.getElementById("scan-btn-text");

    function loadScannerAllResults() {
        fetch('/api/scanner/all?timeframe=' + selectedTimeframe + '&_t=' + new Date().getTime())
            .then(res => res.json())
            .then(data => {
                if (data.last_updated) {
                    const date = new Date(data.last_updated);
                    scanLastUpdated.textContent = 'Terakhir diupdate: ' + date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';
                    
                    if (lastUpdatedTimestamp && data.last_updated !== lastUpdatedTimestamp) {
                        clearInterval(pollingInterval);
                        setScanningState(false);
                    }
                    lastUpdatedTimestamp = data.last_updated;
                }

                if (data.items && data.items.length > 0) {
                    scannerItems = data.items;
                    calculateStats();
                    renderTable();
                } else {
                    if (scannerItems.length === 0) {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                                    Tidak ada data pemindaian. Silakan jalankan Scan Sekarang.
                                </td>
                            </tr>
                        `;
                    }
                }
            })
            .catch(err => {
                console.error("Failed to load all scanner results", err);
                if (scannerItems.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--color-danger); padding: 3rem 0; font-weight: 600;">
                                Gagal memuat data hasil scan.
                            </td>
                        </tr>
                    `;
                }
            });
    }

    function calculateStats() {
        const total = scannerItems.length;
        const stochKLimit = (selectedTimeframe === '4hour') ? 27.0 : 7.0;
        const oversold = scannerItems.filter(item => item.rsi < 40 && item.stochK <= stochKLimit).length;
        const doubleBottom = scannerItems.filter(item => item.is_double_bottom).length;
        const journal = scannerItems.filter(item => item.is_journal).length;
        
        statTotalScanned.textContent = total;
        statTotalOversold.textContent = oversold;
        statTotalJournal.textContent = journal;
        
        // Update tab buttons counts
        document.querySelector('[data-filter="all"]').textContent = `Semua (${total})`;
        document.querySelector('[data-filter="oversold"]').textContent = `Jenuh Jual (${oversold})`;
        document.querySelector('[data-filter="double_bottom"]').textContent = `Double Bottom (${doubleBottom})`;
        document.querySelector('[data-filter="journal"]').textContent = `Aset Jurnal (${journal})`;
    }

    function renderTable() {
        // Filter items
        let filteredItems = scannerItems.filter(item => {
            const matchesSearch = item.symbol.toLowerCase().includes(searchQuery.toLowerCase());
            
            let matchesTab = true;
            if (currentFilter === 'oversold') {
                const stochKLimit = (selectedTimeframe === '4hour') ? 27.0 : 7.0;
                matchesTab = (item.rsi < 40 && item.stochK <= stochKLimit);
            } else if (currentFilter === 'double_bottom') {
                matchesTab = item.is_double_bottom;
            } else if (currentFilter === 'journal') {
                matchesTab = item.is_journal;
            }
            
            return matchesSearch && matchesTab;
        });

        // Sort items
        filteredItems.sort((a, b) => {
            let valA = a[sortColumn];
            let valB = b[sortColumn];
            
            if (typeof valA === 'string') {
                valA = valA.toLowerCase();
                valB = valB.toLowerCase();
            }
            
            if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

        // Render HTML
        if (filteredItems.length > 0) {
            let html = '';
            filteredItems.forEach(item => {
                const kColor = item.stochK < 7 ? '#00e676' : 'rgba(255, 255, 255, 0.05)';
                const kTextColor = item.stochK < 7 ? 'black' : 'var(--text-muted)';
                const rsiClass = item.rsi < 40 ? 'badge-bullish' : 'badge-neutral';
                
                const isOversold = (item.rsi < 40 && item.stochK < 7);
                let badges = [];
                if (isOversold) {
                    badges.push(`<span class="badge badge-success" style="font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 4px;">Oversold</span>`);
                }
                if (item.is_double_bottom) {
                    badges.push(`<span class="badge" style="background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 4px;">Double Bottom</span>`);
                }
                if (badges.length === 0) {
                    badges.push(`<span class="badge" style="background: rgba(255,255,255,0.05); color: var(--text-muted); font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">Neutral</span>`);
                }
                const statusBadge = badges.join(' ');
                
                const journalBadge = item.is_journal ? `<span class="badge" style="background: rgba(147, 51, 234, 0.15); border: 1px solid rgba(147, 51, 234, 0.3); color: #c084fc; font-size: 0.7rem; margin-left: 0.5rem; padding: 0.15rem 0.35rem; border-radius: 4px; font-weight: 600;">Jurnal</span>` : '';
                
                html += `
                    <tr>
                        <td style="font-weight: 700; color: white; display: inline-flex; align-items: center; min-height: 38px;">
                            ${item.symbol} ${journalBadge}
                        </td>
                        <td style="font-family: monospace;">$${parseFloat(item.price).toFixed(4)}</td>
                        <td>
                            <span class="badge" style="background-color: ${kColor}; color: ${kTextColor}; font-weight: 700; font-family: monospace;">K: ${item.stochK.toFixed(2)}</span>
                        </td>
                        <td>
                            <span class="badge ${rsiClass}" style="font-family: monospace;">${item.rsi.toFixed(2)}</span>
                        </td>
                        <td style="font-family: monospace; color: var(--text-muted);">$${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(item.volume_24h)}</td>
                        <td style="text-align: center;">${statusBadge}</td>
                        <td style="text-align: center;">
                            ${['double_bottom', 'oversold', 'journal'].includes(currentFilter) ? `<button onclick="startAnalysis('${item.symbol}')" class="badge badge-primary" style="border: none; cursor: pointer; display: inline-block; padding: 0.35rem 0.65rem; font-family: inherit; font-weight: 600; line-height: normal; vertical-align: middle;">Mulai Analisa</button>` : '-'}
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                        Tidak ada koin yang cocok dengan filter / pencarian.
                    </td>
                </tr>
            `;
        }
        
        updateSortIcons();
    }

    function changeSort(column) {
        if (sortColumn === column) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = column;
            sortDirection = 'desc';
        }
        renderTable();
    }

    function updateSortIcons() {
        ['symbol', 'price', 'stochK', 'rsi', 'volume_24h'].forEach(col => {
            const iconSpan = document.getElementById(`sort-icon-${col}`);
            if (iconSpan) {
                if (sortColumn === col) {
                    iconSpan.innerHTML = sortDirection === 'asc' ? '▲' : '▼';
                    iconSpan.style.color = 'var(--color-primary)';
                } else {
                    iconSpan.innerHTML = '↕';
                    iconSpan.style.color = 'rgba(255,255,255,0.2)';
                }
            }
        });
    }

    // Set up filter buttons event listeners
    document.querySelectorAll('.btn-tab').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.btn-tab').forEach(b => {
                b.classList.remove('active');
                b.style.background = 'rgba(255,255,255,0.05)';
                b.style.color = 'var(--text-muted)';
            });
            
            this.classList.add('active');
            this.style.background = 'var(--color-primary)';
            this.style.color = 'white';
            
            currentFilter = this.getAttribute('data-filter');
            renderTable();
        });
    });

    // Set up search box listener
    searchInput.addEventListener('input', function() {
        searchQuery = this.value;
        renderTable();
    });

    // Scan trigger implementation
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
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        fetch('/api/scanner/trigger', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ timeframe: selectedTimeframe })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Poll results
                pollingInterval = setInterval(loadScannerAllResults, 3000);
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

    // Initial load
    loadScannerAllResults();

    // Modal Analysis Logic
    const modalAnalisa = document.getElementById('modal-analisa');
    const loadingState = document.getElementById('analisa-loading');
    const contentState = document.getElementById('analisa-content');
    
    window.startAnalysis = function(symbol) {
        // Show modal and loading state
        modalAnalisa.style.display = 'flex';
        loadingState.style.display = 'flex';
        contentState.style.display = 'none';
        document.body.style.overflow = 'hidden'; // Lock background scroll
        
        document.getElementById('analisa-symbol').textContent = symbol;
        
        // Fetch data
        fetch(`/api/scanner/analyse/${symbol}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const analysis = data.analysis;
                    
                    // Format values
                    document.getElementById('analisa-price').textContent = `$${parseFloat(analysis.current_price).toFixed(4)}`;
                    
                    const scoreEl = document.getElementById('analisa-score');
                    scoreEl.textContent = analysis.score;
                    scoreEl.className = analysis.score_class; // text-success, text-warning, text-danger
                    
                    // Apply inline styling class for color safety
                    if (analysis.score_class === 'text-success') {
                        scoreEl.style.color = '#00e676';
                    } else if (analysis.score_class === 'text-warning') {
                        scoreEl.style.color = '#ffb300';
                    } else {
                        scoreEl.style.color = '#ff1744';
                    }
                    
                    document.getElementById('analisa-ratio').textContent = parseFloat(analysis.ratio).toFixed(2);
                    document.getElementById('analisa-pct-risk').textContent = parseFloat(analysis.pct_risk).toFixed(1);
                    document.getElementById('analisa-pct-reward').textContent = parseFloat(analysis.pct_reward).toFixed(1);
                    
                    document.getElementById('analisa-s1').textContent = `$${parseFloat(analysis.s1).toFixed(4)}`;
                    document.getElementById('analisa-r1').textContent = `$${parseFloat(analysis.r1).toFixed(4)}`;
                    document.getElementById('analisa-r2').textContent = `$${parseFloat(analysis.r2).toFixed(4)}`;
                    document.getElementById('analisa-r3').textContent = `$${parseFloat(analysis.r3).toFixed(4)}`;
                    
                    document.getElementById('analisa-advice').textContent = analysis.advice;
                    
                    // Update Liquidation fields
                    const liqLong4h = document.getElementById('analisa-liq-long-4h');
                    const liqLong24h = document.getElementById('analisa-liq-long-24h');
                    const liqLong3d = document.getElementById('analisa-liq-long-3d');
                    const liqLong7d = document.getElementById('analisa-liq-long-7d');
                    const liqShort4h = document.getElementById('analisa-liq-short-4h');
                    const liqShort24h = document.getElementById('analisa-liq-short-24h');
                    const liqShort3d = document.getElementById('analisa-liq-short-3d');
                    const liqShort7d = document.getElementById('analisa-liq-short-7d');
                    const warningEl = document.getElementById('analisa-liq-warning');

                    if (analysis.has_coinalyze_key) {
                        liqLong4h.textContent = analysis.long_liq_4h_formatted;
                        liqLong24h.textContent = analysis.long_liq_24h_formatted;
                        liqLong3d.textContent = analysis.long_liq_3d_formatted;
                        liqLong7d.textContent = analysis.long_liq_7d_formatted;
                        liqShort4h.textContent = analysis.short_liq_4h_formatted;
                        liqShort24h.textContent = analysis.short_liq_24h_formatted;
                        liqShort3d.textContent = analysis.short_liq_3d_formatted;
                        liqShort7d.textContent = analysis.short_liq_7d_formatted;
                        warningEl.style.display = 'none';
                    } else {
                        liqLong4h.textContent = '-';
                        liqLong24h.textContent = '-';
                        liqLong3d.textContent = '-';
                        liqLong7d.textContent = '-';
                        liqShort4h.textContent = '-';
                        liqShort24h.textContent = '-';
                        liqShort3d.textContent = '-';
                        liqShort7d.textContent = '-';
                        warningEl.style.display = 'block';
                    }

                    // Update Futures Metrics (Volume and OI)
                    const futVolume = document.getElementById('analisa-fut-volume');
                    const futVolumeChange = document.getElementById('analisa-fut-volume-change');
                    const futOi = document.getElementById('analisa-fut-oi');
                    const futOiChange = document.getElementById('analisa-fut-oi-change');

                    if (analysis.has_coinalyze_key) {
                        futVolume.textContent = (analysis.volume_24h_formatted !== '$0.00' && analysis.volume_24h_formatted !== '-') ? analysis.volume_24h_formatted : '-';
                        
                        const volPct = parseFloat(analysis.volume_change_pct) || 0;
                        futVolumeChange.textContent = analysis.volume_change_pct_formatted || '-';
                        if (volPct > 0) {
                            futVolumeChange.style.color = '#00e676';
                        } else if (volPct < 0) {
                            futVolumeChange.style.color = '#ff1744';
                        } else {
                            futVolumeChange.style.color = 'var(--text-muted)';
                        }

                        // Open Interest
                        futOi.textContent = (analysis.oi_value_formatted !== '$0.00' && analysis.oi_value_formatted !== '-') ? analysis.oi_value_formatted : '-';
                        const oiPct = parseFloat(analysis.oi_change_pct) || 0;
                        futOiChange.textContent = analysis.oi_change_pct_formatted || '-';
                        if (oiPct > 0) {
                            futOiChange.style.color = '#00e676';
                        } else if (oiPct < 0) {
                            futOiChange.style.color = '#ff1744';
                        } else {
                            futOiChange.style.color = 'var(--text-muted)';
                        }
                    } else {
                        futVolume.textContent = '-';
                        futVolumeChange.textContent = '-';
                        futVolumeChange.style.color = 'var(--text-muted)';
                        futOi.textContent = '-';
                        futOiChange.textContent = '-';
                        futOiChange.style.color = 'var(--text-muted)';
                    }
                    
                    // Toggle visibility
                    loadingState.style.display = 'none';
                    contentState.style.display = 'block';
                } else {
                    alert(data.message || 'Gagal menghitung analisa.');
                    closeAnalysisModal();
                }
            })
            .catch(err => {
                console.error("Analysis failed", err);
                alert("Terjadi kesalahan saat memproses data analisis.");
                closeAnalysisModal();
            });
    }
    
    window.closeAnalysisModal = function() {
        modalAnalisa.style.display = 'none';
        document.body.style.overflow = ''; // Unlock background scroll
    }
    
    // Close modal when clicking outside the card
    modalAnalisa.addEventListener('click', function(e) {
        if (e.target === modalAnalisa) {
            closeAnalysisModal();
        }
    });
</script>
@endsection
