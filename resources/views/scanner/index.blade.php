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
        <div class="stat-desc">Stoch RSI < 7 & RSI < 40</div>
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
                    <th style="cursor: pointer; user-select: none;" onclick="changeSort('rsi')">RSI (1D) <span id="sort-icon-rsi" style="margin-left: 3px; font-size: 0.75rem;">↕</span></th>
                    <th style="cursor: pointer; user-select: none;" onclick="changeSort('volume_24h')">Volume 24h <span id="sort-icon-volume_24h" style="margin-left: 3px; font-size: 0.75rem;">↕</span></th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody id="scanner-all-table-body">
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                        Memuat data 500 altcoin...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    let scannerItems = [];
    let currentFilter = 'all';
    let searchQuery = '';
    let sortColumn = 'volume_24h';
    let sortDirection = 'desc'; // 'asc' or 'desc'
    
    let lastUpdatedTimestamp = null;
    let pollingInterval = null;

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
        fetch('/api/scanner/all?_t=' + new Date().getTime())
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
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                                Tidak ada data pemindaian. Silakan jalankan Scan Sekarang.
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(err => {
                console.error("Failed to load all scanner results", err);
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--color-danger); padding: 3rem 0; font-weight: 600;">
                            Gagal memuat data hasil scan.
                        </td>
                    </tr>
                `;
            });
    }

    function calculateStats() {
        const total = scannerItems.length;
        const oversold = scannerItems.filter(item => item.rsi < 40 && item.stochK < 7).length;
        const journal = scannerItems.filter(item => item.is_journal).length;
        
        statTotalScanned.textContent = total;
        statTotalOversold.textContent = oversold;
        statTotalJournal.textContent = journal;
        
        // Update tab buttons counts
        document.querySelector('[data-filter="all"]').textContent = `Semua (${total})`;
        document.querySelector('[data-filter="oversold"]').textContent = `Jenuh Jual (${oversold})`;
        document.querySelector('[data-filter="journal"]').textContent = `Aset Jurnal (${journal})`;
    }

    function renderTable() {
        // Filter items
        let filteredItems = scannerItems.filter(item => {
            const matchesSearch = item.symbol.toLowerCase().includes(searchQuery.toLowerCase());
            
            let matchesTab = true;
            if (currentFilter === 'oversold') {
                matchesTab = (item.rsi < 40 && item.stochK < 7);
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
                const statusBadge = isOversold 
                    ? `<span class="badge badge-success" style="font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 4px;">Oversold</span>`
                    : `<span class="badge" style="background: rgba(255,255,255,0.05); color: var(--text-muted); font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;">Neutral</span>`;
                
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
                            <a href="/trade?symbol=${item.symbol}" class="badge badge-success" style="text-decoration: none; display: inline-block; padding: 0.35rem 0.65rem;">Jurnal Trade</a>
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
            }
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
</script>
@endsection
