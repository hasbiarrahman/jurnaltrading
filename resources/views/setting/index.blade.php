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
                    *API Key saat ini diinisialisasi berdasarkan data dari Pelagic Capital.
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
                <li>Buat API Key baru dengan nama identifikasi yang mudah diingat (misal: <em>Pelagic Dashboard</em>).</li>
                <li>Pada pengaturan izin API, pilih <strong>Enable Reading</strong> (Wajib) untuk membaca portofolio.</li>
                <li><strong style="color: var(--color-danger);">PENTING:</strong> <strong>Jangan aktifkan</strong> izin <em>Enable Withdrawals</em> untuk keamanan dana Anda.</li>
                <li>Salin <strong>API Key</strong> dan <strong>API Secret</strong> ke form di sebelah kiri lalu klik simpan.</li>
            </ol>

            <div style="background: rgba(147, 51, 234, 0.1); border: 1px solid rgba(147, 51, 234, 0.2); padding: 1rem; border-radius: 10px; font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                <strong style="color: var(--color-primary); display: block; margin-bottom: 0.25rem;">Informasi Fallback</strong>
                Jika API Secret dikosongkan, sistem tetap berfungsi normal dengan memperhitungkan saldo portofolio dan average buy price secara kalkulatif dari riwayat transaksi yang Anda catat pada menu <strong>Jurnal Trading</strong>.
            </div>
        </div>
    </div>
</div>
@endsection
