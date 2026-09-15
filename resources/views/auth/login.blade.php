<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — CKPN System</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 900px;
            min-height: 520px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 32px rgba(0,0,0,0.10);
            overflow: hidden;
        }

        /* ---- Left Panel ---- */
        .panel-left {
            flex: 1;
            background: linear-gradient(145deg, #4f46e5 0%, #6366f1 60%, #818cf8 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
            gap: 1.5rem;
        }

        .panel-left .brand-icon {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.18);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .panel-left .brand-icon svg {
            width: 36px;
            height: 36px;
            stroke: #fff;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .panel-left h1 {
            color: #fff;
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            text-align: center;
        }

        .panel-left p {
            color: rgba(255,255,255,0.75);
            font-size: 0.9rem;
            text-align: center;
            line-height: 1.6;
            max-width: 240px;
        }

        .panel-left .badge {
            margin-top: 1rem;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 999px;
            color: rgba(255,255,255,0.9);
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.35rem 0.85rem;
            letter-spacing: 0.04em;
        }

        /* ---- Right Panel (Form) ---- */
        .panel-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 2.75rem;
        }

        .panel-right h2 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }

        .panel-right .subtitle {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 2rem;
        }

        /* Alert error */
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
        }

        .alert-error svg {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            stroke: #ef4444;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            margin-top: 2px;
        }

        .alert-error ul {
            list-style: none;
            padding: 0;
        }

        .alert-error ul li {
            font-size: 0.8125rem;
            color: #dc2626;
            line-height: 1.5;
        }

        /* Form fields */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.45rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper svg.icon-left {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            stroke: #9ca3af;
            fill: none;
            stroke-width: 1.75;
            stroke-linecap: round;
            stroke-linejoin: round;
            pointer-events: none;
        }

        .input-wrapper input {
            width: 100%;
            height: 42px;
            padding: 0 1rem 0 2.5rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #1e293b;
            background: #fff;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            font-family: 'Inter', sans-serif;
        }

        .input-wrapper input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }

        .input-wrapper input.has-error {
            border-color: #f87171;
        }

        .input-wrapper input.has-error:focus {
            box-shadow: 0 0 0 3px rgba(239,68,68,0.12);
        }

        /* Password toggle */
        .btn-toggle-pass {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            display: flex;
            align-items: center;
        }

        .btn-toggle-pass svg {
            width: 16px;
            height: 16px;
            stroke: #9ca3af;
            fill: none;
            stroke-width: 1.75;
            stroke-linecap: round;
            stroke-linejoin: round;
            transition: stroke 0.15s;
        }

        .btn-toggle-pass:hover svg { stroke: #6366f1; }

        .field-error {
            font-size: 0.75rem;
            color: #dc2626;
            margin-top: 0.35rem;
        }

        /* Remember me */
        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            cursor: pointer;
        }

        .remember-me input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: #6366f1;
            cursor: pointer;
        }

        .remember-me span {
            font-size: 0.8125rem;
            color: #4b5563;
        }

        /* Submit button */
        .btn-submit {
            width: 100%;
            height: 42px;
            background: #6366f1;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
        }

        .btn-submit:hover {
            background: #4f46e5;
            box-shadow: 0 4px 12px rgba(99,102,241,0.35);
        }

        .btn-submit:active { transform: scale(0.99); }

        .btn-submit svg {
            width: 16px;
            height: 16px;
            stroke: #fff;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* Footer note */
        .form-footer {
            margin-top: 1.75rem;
            text-align: center;
            font-size: 0.75rem;
            color: #94a3b8;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .login-wrapper { flex-direction: column; min-height: auto; }
            .panel-left { padding: 2rem 1.5rem; min-height: 180px; gap: 1rem; }
            .panel-left h1 { font-size: 1.2rem; }
            .panel-left p { display: none; }
            .panel-right { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">

        {{-- Left brand panel --}}
        <div class="panel-left">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M3 3h18v18H3z" stroke-width="0" fill="rgba(255,255,255,0.1)" rx="4"/>
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
            </div>
            <h1>CKPN System</h1>
            <p>Sistem Perhitungan Cadangan Kerugian Penurunan Nilai berbasis PSAK 414</p>
            <span class="badge">Bank Syariah &bull; PSAK 414</span>
        </div>

        {{-- Right form panel --}}
        <div class="panel-right">
            <h2>Selamat datang</h2>
            <p class="subtitle">Masuk untuk mengakses dashboard CKPN</p>

            {{-- Validation errors --}}
            @if ($errors->any())
                <div class="alert-error">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                {{-- Email --}}
                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <div class="input-wrapper">
                        <svg class="icon-left" viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="nama@instansi.co.id"
                            autocomplete="email"
                            autofocus
                            class="{{ $errors->has('email') ? 'has-error' : '' }}"
                        >
                    </div>
                    @error('email')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="form-group">
                    <label for="password">Kata Sandi</label>
                    <div class="input-wrapper">
                        <svg class="icon-left" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            class="{{ $errors->has('password') ? 'has-error' : '' }}"
                        >
                        <button type="button" class="btn-toggle-pass" onclick="togglePassword()" aria-label="Tampilkan kata sandi">
                            <svg id="eye-icon" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="form-row">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        <span>Ingat saya</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn-submit">
                    <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Masuk ke Dashboard
                </button>
            </form>

            <p class="form-footer">&copy; {{ date('Y') }} CKPN System &mdash; Internal Use Only</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
            }
        }
    </script>
</body>
</html>
