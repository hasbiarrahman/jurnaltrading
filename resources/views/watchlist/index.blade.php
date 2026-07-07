@extends('layouts.app')

@section('title', 'Watchlist')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Watchlist Aset Crypto</h1>
        <p>Pantau harga real-time, Stochastic RSI harian, dan sinyal Divergence secara langsung</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success mb-4">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger mb-4">
        {{ $errors->first() }}
    </div>
@endif

<div class="dashboard-row" style="grid-template-columns: 1fr 3fr; gap: 1.5rem;">
    <!-- Left Column: Add Symbol Form -->
    <div class="glass-card" style="height: fit-content;">
        <div class="card-title">Tambah Aset Baru</div>
        <form action="{{ route('watchlist.store') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label for="symbol" class="form-label">Simbol Aset (e.g. BTCUSDT)</label>
                <input type="text" name="symbol" id="symbol" class="form-control" placeholder="BTCUSDT" style="text-transform: uppercase;" required>
                <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                    *Gunakan simbol pasar USDT/BIDR (Tanpa spasi)
                </small>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Catatan Pemantauan</label>
                <textarea name="notes" id="notes" class="form-control" rows="4" placeholder="Misal: Beli jika Stochastic RSI oversold harian..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                Tambahkan
            </button>
        </form>
    </div>

    <!-- Right Column: Watchlist Grid -->
    <div>
        <div class="watchlist-grid">
            @forelse($watchlist as $item)
                <div class="glass-card watchlist-card" id="wl-card-{{ $item->symbol }}" data-symbol="{{ $item->symbol }}">
                    <div class="wl-header">
                        <div>
                            <span class="wl-symbol">{{ $item->symbol }}</span>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem;">
                                {{ $item->notes ?? 'Tanpa catatan' }}
                            </p>
                        </div>
                        <form action="{{ route('watchlist.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus {{ $item->symbol }} dari watchlist?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; display: flex; align-items: center;" title="Hapus dari watchlist">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>

                    <!-- Async Metrics Content -->
                    <div class="wl-price-container">
                        <!-- Skeleton Loaders -->
                        <div class="skeleton skeleton-price" id="wl-price-loader-{{ $item->symbol }}"></div>
                        <div class="wl-price" id="wl-price-{{ $item->symbol }}" style="display: none;">$0.00</div>
                    </div>

                    <div class="wl-meta">
                        <span>Stoch RSI 1D</span>
                        <div class="skeleton skeleton-text" id="wl-stoch-loader-{{ $item->symbol }}" style="width: 80px; height: 1.1rem; margin-bottom: 0;"></div>
                        <span id="wl-stoch-{{ $item->symbol }}" style="font-family: monospace; font-weight: 600; display: none;">-</span>
                    </div>

                    <div class="wl-meta" style="border-top: none; padding-top: 0;">
                        <span>Divergence</span>
                        <div class="skeleton skeleton-badge" id="wl-div-loader-{{ $item->symbol }}" style="width: 60px; height: 1.2rem;"></div>
                        <div id="wl-div-{{ $item->symbol }}" style="display: none;">
                            <span class="badge badge-neutral">NONE</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="glass-card" style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; color: var(--text-muted);">
                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-bottom: 1rem; opacity: 0.5; color: var(--color-primary);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <p>Watchlist Anda masih kosong. Tambahkan simbol koin di panel sebelah kiri untuk mulai memantau.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const cards = document.querySelectorAll(".watchlist-card");
        
        cards.forEach(card => {
            const symbol = card.getAttribute("data-symbol");
            fetchMetricsForSymbol(symbol);
        });

        function fetchMetricsForSymbol(symbol) {
            fetch(`/api/watchlist-metrics/${symbol}`)
                .then(response => {
                    if (!response.ok) throw new Error("Gagal mengambil data");
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.metrics) {
                        const m = data.metrics;
                        
                        // 1. Update Price
                        const priceEl = document.getElementById(`wl-price-${symbol}`);
                        const priceLoader = document.getElementById(`wl-price-loader-${symbol}`);
                        if (priceEl && priceLoader) {
                            priceLoader.style.display = 'none';
                            priceEl.textContent = formatPrice(m.price, symbol);
                            priceEl.style.display = 'block';
                        }

                        // 2. Update Stochastic RSI values
                        const stochEl = document.getElementById(`wl-stoch-${symbol}`);
                        const stochLoader = document.getElementById(`wl-stoch-loader-${symbol}`);
                        if (stochEl && stochLoader) {
                            stochLoader.style.display = 'none';
                            if (m.stoch_k !== null) {
                                stochEl.textContent = `K: ${Math.round(m.stoch_k)} / D: ${Math.round(m.stoch_d)}`;
                            } else {
                                stochEl.textContent = 'Tidak Tersedia';
                            }
                            stochEl.style.display = 'inline';
                        }

                        // 3. Update Divergence Badge
                        const divEl = document.getElementById(`wl-div-${symbol}`);
                        const divLoader = document.getElementById(`wl-div-loader-${symbol}`);
                        if (divEl && divLoader) {
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
                .catch(error => {
                    console.error(`Error loading metrics for ${symbol}:`, error);
                    // Set error state
                    setErrorState(symbol);
                });
        }

        function formatPrice(price, symbol) {
            // If the symbol is a BIDR or IDRT pair
            if (symbol.endsWith('BIDR') || symbol.endsWith('IDRT')) {
                return 'Rp ' + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(price);
            }
            // Default USDT format
            return '$' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 }).format(price);
        }

        function setErrorState(symbol) {
            const priceLoader = document.getElementById(`wl-price-loader-${symbol}`);
            const priceEl = document.getElementById(`wl-price-${symbol}`);
            if (priceLoader && priceEl) {
                priceLoader.style.display = 'none';
                priceEl.textContent = 'Koneksi Eror';
                priceEl.style.color = 'var(--color-danger)';
                priceEl.style.display = 'block';
            }

            const stochLoader = document.getElementById(`wl-stoch-loader-${symbol}`);
            const stochEl = document.getElementById(`wl-stoch-${symbol}`);
            if (stochLoader && stochEl) {
                stochLoader.style.display = 'none';
                stochEl.textContent = 'Error';
                stochEl.style.display = 'inline';
            }

            const divLoader = document.getElementById(`wl-div-loader-${symbol}`);
            const divEl = document.getElementById(`wl-div-${symbol}`);
            if (divLoader && divEl) {
                divLoader.style.display = 'none';
                divEl.innerHTML = '<span class="badge badge-danger">ERROR</span>';
                divEl.style.display = 'block';
            }
        }
    });
</script>
@endsection
