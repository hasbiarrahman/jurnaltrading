@extends('layouts.app')

@section('title', 'Daftar User')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Daftar User</h1>
        <p>Kelola kredensial pengguna yang memiliki hak akses untuk memantau dashboard ini</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success mb-4">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger mb-4">
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger mb-4">
        {{ $errors->first() }}
    </div>
@endif

<div class="dashboard-row" style="grid-template-columns: 1fr 2.5fr; gap: 1.5rem;">
    <!-- Left Column: Add / Edit User Form -->
    <div class="glass-card" style="height: fit-content;">
        <div id="formCardTitle" class="card-title">Daftarkan User Baru</div>
        
        <form action="{{ route('user.store') }}" method="POST" id="userForm">
            @csrf
            <div id="methodContainer"></div> <!-- For PUT method during edits -->
            
            <div class="form-group">
                <label for="user_name" class="form-label">Nama Lengkap</label>
                <input type="text" name="name" id="user_name" class="form-control" placeholder="Nama lengkap user" required>
            </div>

            <div class="form-group">
                <label for="user_email" class="form-label">Alamat Email</label>
                <input type="email" name="email" id="user_email" class="form-control" placeholder="user@example.com" required>
            </div>

            <div class="form-group">
                <label for="user_role" class="form-label">Hak Akses / Role</label>
                <select name="role" id="user_role" class="form-control" required>
                    <option value="investor">Investor (Hanya Dashboard & Portofolio)</option>
                    <option value="super_admin">Super Admin (Akses Penuh)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="user_password" class="form-label">Kata Sandi</label>
                <input type="password" name="password" id="user_password" class="form-control" placeholder="Minimal 6 karakter">
                <small id="passwordHelp" style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                    *Kata sandi wajib diisi untuk registrasi baru.
                </small>
            </div>

            <div class="form-group">
                <label for="user_password_confirmation" class="form-label">Konfirmasi Kata Sandi</label>
                <input type="password" name="password_confirmation" id="user_password_confirmation" class="form-control" placeholder="Ulangi kata sandi">
            </div>

            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <button type="submit" id="btnSubmitForm" class="btn btn-primary" style="flex-grow: 1;">
                    Daftarkan User
                </button>
                <button type="button" id="btnCancelEdit" class="btn btn-secondary" style="display: none;">
                    Batal
                </button>
            </div>
        </form>
    </div>

    <!-- Right Column: Users List -->
    <div class="glass-card">
        <div class="card-title">Pengguna Terdaftar</div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Tanggal Terdaftar</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td style="font-weight: 600;">
                                {{ $user->name }}
                                @if(Auth::id() === $user->id)
                                    <span class="badge badge-success" style="font-size: 0.65rem; margin-left: 0.5rem; padding: 0.1rem 0.4rem;">SAYA</span>
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="badge {{ $user->role === 'super_admin' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $user->role === 'super_admin' ? 'Super Admin' : 'Investor' }}
                                </span>
                            </td>
                            <td>{{ $user->created_at->format('d M Y, H:i') }}</td>
                            <td style="text-align: right; display: flex; gap: 0.75rem; justify-content: flex-end; align-items: center; height: 3.5rem;">
                                <!-- Edit trigger button -->
                                <button type="button" class="btn-edit-user" 
                                        data-id="{{ $user->id }}"
                                        data-name="{{ $user->name }}"
                                        data-email="{{ $user->email }}"
                                        data-role="{{ $user->role }}"
                                        style="background: none; border: none; color: var(--color-secondary); cursor: pointer; display: flex; align-items: center;" 
                                        title="Edit User">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>

                                <!-- Delete button (disabled for active user) -->
                                @if(Auth::id() !== $user->id)
                                    <form action="{{ route('user.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus user {{ $user->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; display: flex; align-items: center;" title="Hapus User">
                                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("userForm");
        const formCardTitle = document.getElementById("formCardTitle");
        const methodContainer = document.getElementById("methodContainer");
        const btnSubmit = document.getElementById("btnSubmitForm");
        const btnCancel = document.getElementById("btnCancelEdit");
        const passwordHelp = document.getElementById("passwordHelp");

        const nameInput = document.getElementById("user_name");
        const emailInput = document.getElementById("user_email");
        const passwordInput = document.getElementById("user_password");
        const passwordConfirmInput = document.getElementById("user_password_confirmation");

        const editButtons = document.querySelectorAll(".btn-edit-user");

        editButtons.forEach(btn => {
            btn.addEventListener("click", function() {
                const id = btn.getAttribute("data-id");
                const name = btn.getAttribute("data-name");
                const email = btn.getAttribute("data-email");
                const role = btn.getAttribute("data-role");

                // Shift form state to Edit
                formCardTitle.textContent = `Edit User: ${name}`;
                form.action = `/user/${id}`;
                methodContainer.innerHTML = '<input type="hidden" name="_method" value="PUT">';
                btnSubmit.textContent = "Simpan Perubahan";
                btnCancel.style.display = "block";
                
                // Set fields
                nameInput.value = name;
                emailInput.value = email;
                document.getElementById("user_role").value = role;
                passwordInput.value = ""; // blank password implies keep original
                passwordConfirmInput.value = "";
                
                passwordInput.required = false; // not required for edit
                passwordHelp.textContent = "*Biarkan kosong jika tidak ingin mengubah kata sandi.";
                
                // Scroll form into view for responsive screens
                nameInput.focus();
            });
        });

        btnCancel.addEventListener("click", function() {
            // Restore form state to Add
            formCardTitle.textContent = "Daftarkan User Baru";
            form.action = "{{ route('user.store') }}";
            methodContainer.innerHTML = '';
            btnSubmit.textContent = "Daftarkan User";
            btnCancel.style.display = "none";
            
            // Clear fields
            nameInput.value = "";
            emailInput.value = "";
            document.getElementById("user_role").value = "investor";
            passwordInput.value = "";
            passwordConfirmInput.value = "";
            
            passwordInput.required = true; // required for new signups
            passwordHelp.textContent = "*Kata sandi wajib diisi untuk registrasi baru.";
        });
    });
</script>
@endsection
