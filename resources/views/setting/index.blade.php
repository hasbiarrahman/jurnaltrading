@extends('layouts.app')

@section('title', 'API Settings')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>API Settings</h1>
        <p>Konfigurasi koneksi API Tokocrypto untuk sinkronisasi portofolio dan riwayat perdagangan</p>
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

<div class="dashboard-row" style="grid-template-columns: 1.5fr 1fr; gap: 1.5rem;">
    <!-- Left Column: Settings Form -->
    <div class="glass-card">
        <div class="card-title">Kredensial API Tokocrypto</div>
        
        <form action="{{ route('setting.update') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label for="tokocrypto_api_key" class="form-label">Tokocrypto API Key</label>
                <input type="text" name="tokocrypto_api_key" id="tokocrypto_api_key" class="form-control" placeholder="Masukkan API Key Anda" value="{{ old('tokocrypto_api_key', $apiKey) }}">
                <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                    *API Key saat ini diinisialisasi berdasarkan data dari Jurnal Trading.
                </small>
            </div>

            <div class="form-group">
                <label for="tokocrypto_api_secret" class="form-label">Tokocrypto API Secret Key</label>
                <input type="password" name="tokocrypto_api_secret" id="tokocrypto_api_secret" class="form-control" placeholder="Masukkan API Secret Key" value="{{ old('tokocrypto_api_secret', $apiSecret) }}">
                <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                    *Secret Key disimpan terenkripsi di database lokal Anda.
                </small>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 1rem; padding: 0.8rem 2rem;">
                Simpan Konfigurasi
            </button>
        </form>
    </div>

    <!-- Right Column: API Guide -->
    <div class="glass-card" style="height: fit-content;">
        <div class="card-title" style="color: var(--color-secondary);">Panduan Keamanan API</div>
        
        <div style="font-size: 0.9rem; color: var(--text-main); display: flex; flex-direction: column; gap: 1rem;">
            <p>Untuk mengaktifkan sinkronisasi saldo otomatis dan data trading langsung dari akun Tokocrypto Anda, silakan ikuti petunjuk berikut:</p>
            
            <ol style="margin-left: 1.25rem; display: flex; flex-direction: column; gap: 0.5rem; color: var(--text-muted);">
                <li>Buka akun Tokocrypto Anda lalu pilih menu <strong>Manajemen API</strong>.</li>
                <li>Buat API Key baru dengan nama identifikasi yang mudah diingat (misal: <em>Jurnal Trading</em>).</li>
                <li>Pada pengaturan izin API, pilih <strong>Enable Reading</strong> (Wajib) untuk membaca portofolio.</li>
                <li><strong style="color: var(--color-danger);">PENTING:</strong> <strong>Jangan aktifkan</strong> izin <em>Enable Withdrawals</em> untuk keamanan dana Anda.</li>
                <li>Salin <strong>API Key</strong> dan <strong>API Secret</strong> ke form di sebelah kiri lalu klik simpan.</li>
            </ol>

            <div style="background: rgba(147, 51, 234, 0.1); border: 1px solid rgba(147, 51, 234, 0.2); padding: 1rem; border-radius: 10px; font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                <strong style="color: var(--color-primary); display: block; margin-bottom: 0.25rem;">Informasi Fallback</strong>
                Jika API Secret dikosongkan, sistem tetap berfungsi normal dengan memperhitungkan saldo portofolio dan average buy price secara kalkulatif dari riwayat transaksi yang Anda catat pada menu <strong>Jurnal Transaksi</strong>.
            </div>
        </div>
    </div>
</div>

<div class="glass-card" style="margin-top: 1.5rem;">
    <div class="card-title" style="color: var(--color-primary);">Sinkronisasi Database (Browser-Bridge)</div>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
        Sinkronisasikan seluruh perubahan data (jurnal transaksi, watchlist koin, user, dan setelan API) yang Anda lakukan di lingkungan **Local Development** langsung ke server **Produksi** ini menggunakan browser Anda sebagai jembatan penyeberang data.
    </p>

    <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); padding: 1.5rem; border-radius: 12px;">
        <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; margin-bottom: 1.5rem;">
            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                <label for="local_url" class="form-label" style="margin-bottom: 0;">URL Dashboard Lokal</label>
                <input type="text" id="local_url" class="form-control" value="http://localhost:8080" placeholder="http://localhost:8080" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.1); width: 280px; font-family: monospace;">
            </div>
            <button type="button" id="btnCheckSync" class="btn btn-secondary" style="padding: 0.8rem 1.5rem; margin-top: 1.25rem;">
                Cek Perubahan Lokal
            </button>
        </div>

        <div id="syncStatus" style="background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.1); padding: 1rem; border-radius: 8px; font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
            Masukkan URL local server Anda (misal: <code>http://localhost:8080</code>) dan klik <strong>Cek Perubahan Lokal</strong> untuk memindai antrean perubahan database yang tertunda.
        </div>

        <div style="margin-top: 1.5rem;">
            <button type="button" id="btnRunSync" class="btn btn-primary" style="display: none; padding: 0.8rem 2rem; font-size: 0.95rem; font-weight: 600;">
                Sinkron DB Sekarang
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener("DOMContentLoaded", function() {
    const btnCheck = document.getElementById("btnCheckSync");
    const btnRun = document.getElementById("btnRunSync");
    const localUrlInput = document.getElementById("local_url");
    const statusDiv = document.getElementById("syncStatus");
    
    let pendingQueries = [];

    // Helper to format timestamps
    function formatTime(unixTimestamp) {
        const date = new Date(unixTimestamp * 1000);
        return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    if (btnCheck) {
        btnCheck.addEventListener("click", function() {
            let localUrl = localUrlInput.value.trim();
            if (!localUrl) {
                alert("Silakan masukkan URL local dashboard Anda terlebih dahulu.");
                return;
            }
            if (localUrl.endsWith("/")) {
                localUrl = localUrl.slice(0, -1);
            }

            btnCheck.disabled = true;
            btnCheck.textContent = "Memindai...";
            statusDiv.innerHTML = `<span style="color: var(--color-secondary);">Menghubungkan ke ${localUrl}/api/database/pending-queries...</span>`;
            btnRun.style.display = "none";

            // Call local API (CORS enabled)
            fetch(`${localUrl}/api/database/pending-queries`, {
                method: 'GET',
                mode: 'cors',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error("Gagal memuat log query. Pastikan server local Anda aktif dan URL sudah benar.");
                return response.json();
            })
            .then(data => {
                btnCheck.disabled = false;
                btnCheck.textContent = "Cek Perubahan Lokal";
                
                pendingQueries = data;
                
                if (pendingQueries.length === 0) {
                    statusDiv.innerHTML = '<span style="color: #10b981; font-weight: 600;">✔ Sinkron! Database produksi Anda sudah sama dengan data local. Tidak ada perubahan yang tertunda.</span>';
                    btnRun.style.display = "none";
                } else {
                    let preview = '<ul style="margin: 0.5rem 0 0 1.25rem; padding: 0; color: var(--text-muted); max-height: 150px; overflow-y: auto; font-family: monospace; font-size: 0.75rem;">';
                    pendingQueries.slice(0, 5).forEach(q => {
                        // Truncate query for UI display
                        let displaySql = q.sql.length > 80 ? q.sql.substring(0, 80) + '...' : q.sql;
                        preview += `<li style="margin-bottom: 0.25rem;">[${formatTime(q.timestamp)}] ${displaySql}</li>`;
                    });
                    if (pendingQueries.length > 5) {
                        preview += `<li>...dan ${pendingQueries.length - 5} query lainnya.</li>`;
                    }
                    preview += '</ul>';

                    statusDiv.innerHTML = `
                        <div style="color: var(--color-secondary); font-weight: 600; margin-bottom: 0.5rem;">
                            📢 Terdeteksi ${pendingQueries.length} perubahan database baru di local:
                        </div>
                        ${preview}
                        <div style="color: #f87171; font-size: 0.75rem; margin-top: 0.75rem; font-weight: 500;">
                            *Peringatan: Klik tombol di bawah akan mengeksekusi semua perubahan ini secara berurutan ke database produksi.
                        </div>
                    `;
                    btnRun.style.display = "inline-block";
                    btnRun.textContent = `Sinkron ${pendingQueries.length} Perubahan ke Produksi`;
                }
            })
            .catch(err => {
                btnCheck.disabled = false;
                btnCheck.textContent = "Cek Perubahan Lokal";
                statusDiv.innerHTML = `<span style="color: #ef4444; font-weight: 600;">✖ Hubungan Gagal:</span><br><span style="font-size: 0.8rem; color: var(--text-muted);">${err.message}</span><br><small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Periksa apakah XAMPP/server lokal menyala di port tersebut dan fitur CORS diizinkan.</small>`;
                btnRun.style.display = "none";
                console.error(err);
            });
        });
    }

    if (btnRun) {
        btnRun.addEventListener("click", function() {
            if (pendingQueries.length === 0) return;
            
            let localUrl = localUrlInput.value.trim();
            if (localUrl.endsWith("/")) {
                localUrl = localUrl.slice(0, -1);
            }

            if (!confirm(`Apakah Anda yakin ingin menyinkronkan dan menerapkan ${pendingQueries.length} perubahan lokal ke database produksi ini?`)) {
                return;
            }

            btnRun.disabled = true;
            btnRun.textContent = "Menerapkan...";
            statusDiv.innerHTML = `<span style="color: var(--color-secondary);">Mengeksekusi ${pendingQueries.length} query di server produksi...</span>`;

            // 1. Send queries to production server to execute
            fetch("{{ route('api.database.apply-queries') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    queries: pendingQueries
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || "Gagal menerapkan perubahan ke server produksi.");
                    });
                }
                return response.json();
            })
            .then(prodData => {
                if (prodData.success) {
                    statusDiv.innerHTML = `<span style="color: #10b981;">✔ Sukses diterapkan di produksi. Membersihkan antrean log di local...</span>`;
                    
                    // 2. Clear pending log in local environment
                    return fetch(`${localUrl}/api/database/clear-pending-queries`, {
                        method: 'POST',
                        mode: 'cors',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                } else {
                    throw new Error(prodData.message || "Gagal menerapkan perubahan.");
                }
            })
            .then(localRes => {
                if (localRes && !localRes.ok) {
                    throw new Error("Perubahan sukses masuk produksi, namun gagal membersihkan antrean log lokal. Silakan bersihkan log manual.");
                }
                return localRes ? localRes.json() : null;
            })
            .then(data => {
                btnRun.disabled = false;
                btnRun.style.display = "none";
                alert("Sinkronisasi database berhasil! Seluruh perubahan di local telah sukses diterapkan di produksi.");
                statusDiv.innerHTML = '<span style="color: #10b981; font-weight: 600;">✔ Sinkronisasi berhasil diselesaikan!</span>';
                pendingQueries = [];
                
                // Refresh dashboard to display latest synchronized states
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            })
            .catch(err => {
                btnRun.disabled = false;
                btnRun.textContent = `Sinkron ${pendingQueries.length} Perubahan ke Produksi`;
                statusDiv.innerHTML = `<span style="color: #ef4444; font-weight: 600;">✖ Kegagalan Sinkronisasi:</span><br><span style="font-size: 0.8rem; color: var(--text-muted);">${err.message}</span>`;
                console.error(err);
            });
        });
    }
});
</script>
@endsection
