@extends('layouts.app')

@section('title', 'Jurnal Trading')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Jurnal Trading</h1>
        <p>Log transaksi trading beserta data indikator Stochastic RSI dan Divergence pada saat transaksi</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success mb-4">
        {{ session('success') }}
    </div>
@endif

<div class="dashboard-row" style="grid-template-columns: 1fr 2.5fr; gap: 1.5rem;">
    <!-- Left Column: Add Trade Form -->
    <div class="glass-card" style="height: fit-content;">
        <div class="card-title" id="formCardTitle">Catat Transaksi Baru</div>
        
        <form action="{{ route('trade.store') }}" method="POST" id="addTradeForm">
            @csrf
            <div id="method_container"></div>
            
            <div class="form-group">
                <label for="trade_symbol" class="form-label">Simbol Aset (e.g. BTCUSDT)</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" name="symbol" id="trade_symbol" class="form-control" placeholder="BTCUSDT" style="text-transform: uppercase;" required>
                    <button type="button" id="btnFetchStats" class="btn btn-secondary" style="padding: 0.6rem; font-size: 0.75rem; white-space: nowrap;" title="Ambil data live dari pasar">
                        Auto-Fill
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="trade_type" class="form-label">Tipe Transaksi</label>
                <select name="type" id="trade_type" class="form-control" required>
                    <option value="BUY">BUY</option>
                    <option value="SELL">SELL</option>
                </select>
            </div>

            <div class="form-grid-2col" style="margin-bottom: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="trade_price" class="form-label">Harga Aset (USDT)</label>
                    <input type="number" name="price" id="trade_price" step="any" class="form-control" placeholder="0.00" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="trade_amount" class="form-label">Jumlah Aset</label>
                    <input type="number" name="amount" id="trade_amount" step="any" class="form-control" placeholder="0.00" required>
                </div>
            </div>

            <div class="form-group">
                <label for="trade_time" class="form-label">Waktu Transaksi</label>
                <input type="datetime-local" name="trade_time" id="trade_time" class="form-control" required>
            </div>

            <div style="border-top: 1px solid var(--border-light); margin: 1.5rem 0; padding-top: 1rem;">
                <span class="form-label" style="font-weight: 700; color: var(--color-primary);">Log Indikator Teknikal (1D)</span>
                
                <div class="form-grid-2col" style="margin-bottom: 1rem; margin-top: 0.5rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="stoch_rsi_k" class="form-label">Stoch RSI %K</label>
                        <input type="number" name="stoch_rsi_k" id="stoch_rsi_k" step="any" class="form-control" placeholder="0.00">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="stoch_rsi_d" class="form-label">Stoch RSI %D</label>
                        <input type="number" name="stoch_rsi_d" id="stoch_rsi_d" step="any" class="form-control" placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label for="divergence" class="form-label">Divergence Status</label>
                    <select name="divergence" id="divergence" class="form-control">
                        <option value="None">None (Tidak ada)</option>
                        <option value="Bullish">Bullish Divergence</option>
                        <option value="Bearish">Bearish Divergence</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="notes" class="form-label">Catatan Tambahan</label>
                <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Alasan entri, target TP/SL, dll..."></textarea>
            </div>

            <!-- hidden field to signal backend we want auto metrics if inputs are left blank -->
            <input type="hidden" name="auto_metrics" value="1">

            <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                <button type="submit" id="btnSubmitTrade" class="btn btn-primary" style="width: 100%;">
                    Simpan Transaksi
                </button>
                <button type="button" id="btnCancelEdit" class="btn btn-secondary" style="width: 100%; display: none;">
                    Batal Edit
                </button>
            </div>
        </form>
    </div>

    <!-- Right Column: Filter and Trades List Table -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem; min-width: 0;">
        <!-- Filters panel -->
        <div class="glass-card" style="padding: 1.25rem;">
            <form action="{{ route('trade.index') }}" method="GET" class="filter-grid">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_symbol" class="form-label" style="margin-bottom: 0.25rem;">Cari Simbol</label>
                    <input type="text" name="symbol" id="filter_symbol" class="form-control" placeholder="Cari BTC, ETH, dll..." style="padding: 0.6rem; text-transform: uppercase;" value="{{ request('symbol') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_type" class="form-label" style="margin-bottom: 0.25rem;">Tipe</label>
                    <select name="type" id="filter_type" class="form-control" style="padding: 0.6rem;">
                        <option value="">Semua</option>
                        <option value="BUY" {{ request('type') === 'BUY' ? 'selected' : '' }}>BUY</option>
                        <option value="SELL" {{ request('type') === 'SELL' ? 'selected' : '' }}>SELL</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_divergence" class="form-label" style="margin-bottom: 0.25rem;">Divergence</label>
                    <select name="divergence" id="filter_divergence" class="form-control" style="padding: 0.6rem;">
                        <option value="">Semua</option>
                        <option value="None" {{ request('divergence') === 'None' ? 'selected' : '' }}>None</option>
                        <option value="Bullish" {{ request('divergence') === 'Bullish' ? 'selected' : '' }}>Bullish</option>
                        <option value="Bearish" {{ request('divergence') === 'Bearish' ? 'selected' : '' }}>Bearish</option>
                    </select>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex-grow: 1; padding: 0.65rem;">
                        Filter
                    </button>
                    @if(request()->anyFilled(['symbol', 'type', 'divergence']))
                        <a href="{{ route('trade.index') }}" class="btn btn-secondary" style="padding: 0.65rem;" title="Reset filter">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="glass-card">
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
                            <th>Stoch RSI (1D)</th>
                            <th>Divergent</th>
                            <th>Catatan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trades as $trade)
                            <tr>
                                <td style="font-size: 0.8rem; white-space: nowrap;">
                                    {{ date('d/m/Y H:i', strtotime($trade->trade_time)) }}
                                </td>
                                <td style="font-weight: 700;">{{ $trade->symbol }}</td>
                                <td>
                                    <span class="badge {{ $trade->type === 'BUY' ? 'badge-success' : 'badge-danger' }}">
                                        {{ $trade->type }}
                                    </span>
                                </td>
                                 <td style="font-family: monospace;">{{ $trade->formatted_price }}</td>
                                 <td style="font-family: monospace;">{{ number_format($trade->amount, 4) }}</td>
                                 <td style="font-family: monospace;">{{ $trade->formatted_total }}</td>
                                <td>
                                    @if(!is_null($trade->stoch_rsi_k))
                                        <span style="font-family: monospace; font-size: 0.85rem;">
                                            K: {{ round($trade->stoch_rsi_k) }} / D: {{ round($trade->stoch_rsi_d) }}
                                        </span>
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
                                <td style="font-size: 0.8rem; color: var(--text-muted); max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $trade->notes }}">
                                    {{ $trade->notes ?? '-' }}
                                </td>
                                <td style="white-space: nowrap;">
                                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                                        <!-- Edit button -->
                                        <button type="button" class="btn-edit-trade" 
                                            data-id="{{ $trade->id }}"
                                            data-symbol="{{ $trade->symbol }}"
                                            data-type="{{ $trade->type }}"
                                            data-price="{{ $trade->price }}"
                                            data-amount="{{ $trade->amount }}"
                                            data-time="{{ date('Y-m-d\TH:i', strtotime($trade->trade_time)) }}"
                                            data-stoch-k="{{ !is_null($trade->stoch_rsi_k) ? round($trade->stoch_rsi_k, 2) : '' }}"
                                            data-stoch-d="{{ !is_null($trade->stoch_rsi_d) ? round($trade->stoch_rsi_d, 2) : '' }}"
                                            data-divergence="{{ $trade->divergence }}"
                                            data-notes="{{ $trade->notes }}"
                                            style="background: none; border: none; color: var(--color-primary); cursor: pointer; display: flex; align-items: center; padding: 0;" title="Edit transaksi">
                                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <!-- Delete form -->
                                        <form action="{{ route('trade.destroy', $trade->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus catatan trade ini?')" style="margin: 0; display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; display: flex; align-items: center; padding: 0;" title="Hapus transaksi">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 4rem 0;">
                                    Tidak ada transaksi perdagangan yang terdaftar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Custom dark mode pagination wrapper -->
            @if($trades->hasPages())
                <div class="pagination-container" style="border-top: 1px solid var(--border-light); padding-top: 1.25rem;">
                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                        Menampilkan {{ $trades->firstItem() }} - {{ $trades->lastItem() }} dari {{ $trades->total() }} transaksi
                    </div>
                    <div>
                        {{ $trades->links('pagination::simple-bootstrap-4') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Set default time to now
        const timeInput = document.getElementById("trade_time");
        if (timeInput && !timeInput.value) {
            const now = new Date();
            // timezone offset format yyyy-MM-ddThh:mm
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            timeInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        // Live stats fetcher
        const btnFetch = document.getElementById("btnFetchStats");
        const symbolInput = document.getElementById("trade_symbol");
        const priceInput = document.getElementById("trade_price");
        const kInput = document.getElementById("stoch_rsi_k");
        const dInput = document.getElementById("stoch_rsi_d");
        const divSelect = document.getElementById("divergence");

        if (btnFetch) {
            btnFetch.addEventListener("click", function() {
                const symbol = symbolInput.value.trim().toUpperCase();
                
                if (!symbol) {
                    alert("Silakan masukkan simbol aset terlebih dahulu (misal: BTCUSDT)");
                    return;
                }

                btnFetch.disabled = true;
                btnFetch.textContent = "Loading...";

                fetch(`/api/trade-live-stats/${symbol}`)
                    .then(response => {
                        if (!response.ok) throw new Error("Gagal mengambil data pasar.");
                        return response.json();
                    })
                    .then(data => {
                        btnFetch.disabled = false;
                        btnFetch.textContent = "Auto-Fill";

                        if (data.success) {
                            symbolInput.value = data.symbol;
                            priceInput.value = data.price;
                            
                            if (data.stoch_k !== null) {
                                kInput.value = data.stoch_k.toFixed(2);
                                dInput.value = data.stoch_d.toFixed(2);
                            } else {
                                kInput.value = "";
                                dInput.value = "";
                            }

                            if (data.divergence) {
                                divSelect.value = data.divergence;
                            } else {
                                divSelect.value = "None";
                            }
                        }
                    })
                    .catch(err => {
                        btnFetch.disabled = false;
                        btnFetch.textContent = "Auto-Fill";
                        alert("Gagal mengambil data live. Pastikan simbol valid dan internet aktif.");
                        console.error(err);
                    });
            });
        }

        // Edit and Cancel Mode Handlers
        const form = document.getElementById("addTradeForm");
        const formTitle = document.getElementById("formCardTitle");
        const btnSubmit = document.getElementById("btnSubmitTrade");
        const btnCancel = document.getElementById("btnCancelEdit");
        const methodContainer = document.getElementById("method_container");
        const defaultAction = "{{ route('trade.store') }}";

        document.querySelectorAll(".btn-edit-trade").forEach(button => {
            button.addEventListener("click", function() {
                const id = this.getAttribute("data-id");
                const symbol = this.getAttribute("data-symbol");
                const type = this.getAttribute("data-type");
                const price = this.getAttribute("data-price");
                const amount = this.getAttribute("data-amount");
                const time = this.getAttribute("data-time");
                const stochK = this.getAttribute("data-stoch-k");
                const stochD = this.getAttribute("data-stoch-d");
                const divergence = this.getAttribute("data-divergence");
                const notes = this.getAttribute("data-notes");

                // Switch form to edit mode
                formTitle.textContent = "Edit Transaksi #" + id;
                form.action = `/trade/${id}`;
                methodContainer.innerHTML = '<input type="hidden" name="_method" value="PUT">';

                // Pre-fill inputs
                symbolInput.value = symbol;
                document.getElementById("trade_type").value = type;
                priceInput.value = price;
                document.getElementById("trade_amount").value = amount;
                timeInput.value = time;
                kInput.value = stochK;
                dInput.value = stochD;
                divSelect.value = divergence;
                document.getElementById("notes").value = notes;

                // Show cancel button
                btnSubmit.textContent = "Simpan Perubahan";
                btnCancel.style.display = "block";

                // Scroll to form smoothly
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });

        btnCancel.addEventListener("click", function() {
            // Reset form
            formTitle.textContent = "Catat Transaksi Baru";
            form.action = defaultAction;
            methodContainer.innerHTML = '';
            form.reset();

            // Re-set default time to now
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            timeInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;

            // Hide cancel button
            btnSubmit.textContent = "Simpan Transaksi";
            btnCancel.style.display = "none";
        });
    });
</script>
<style>
    /* Format default bootstrap simple pagination buttons for dark mode compatibility */
    .pagination .page-item .page-link {
        background-color: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-light);
        color: var(--text-main);
        padding: 0.5rem 1rem;
        border-radius: 8px;
        transition: var(--transition-smooth);
    }
    .pagination .page-item .page-link:hover {
        background-color: var(--color-primary);
        border-color: var(--color-primary);
        color: var(--text-inverse);
    }
    .pagination .page-item.disabled .page-link {
        background-color: transparent;
        border-color: var(--border-light);
        color: var(--text-muted);
        cursor: not-allowed;
    }
</style>
@endsection
