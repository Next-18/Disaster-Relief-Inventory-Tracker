<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin / Staff Login | ReliefTrack</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body>
    <main class="page">
        <section class="shell" aria-label="Disaster relief inventory tracker login">
            <aside class="brand-panel">
                <div class="brand"><img src="{{ asset('images/logo.png') }}" alt="Disaster Relief Inventory Tracker" class="logo-image"><span>Disaster Relief<br>Inventory Tracker</span></div>
                <div class="brand-copy"><h1>Barangay operations portal.</h1><p>This portal is only for authorized Barangay Admin and Relief Staff. They can manage inventory, beneficiary records, and relief distribution.</p></div>
                <div class="secure">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    Secure access for authorized personnel
                </div>
            </aside>
            <section class="form-panel">

                <h2>Admin / Staff Login</h2>
                <p class="intro">Use your barangay-issued account. Beneficiaries cannot log in here.</p>
                @if ($errors->any()) <div class="notice">{{ $errors->first() }}</div> @endif
                <form method="POST" action="{{ route('login.attempt') }}">
                    @csrf
                    <label for="email">Email address</label>
                    <input class="field" id="email" name="email" type="email" placeholder="admin@barangay.gov.ph" required autofocus autocomplete="off">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input class="field" id="password" name="password" type="password" placeholder="Enter password" required autocomplete="off">
                        <button class="toggle" type="button" aria-label="Show password" onclick="const p=document.getElementById('password'); p.type=p.type==='password'?'text':'password'; this.innerHTML=p.type==='password'?'<svg width=\'20\' height=\'20\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><path d=\'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\'></path><circle cx=\'12\' cy=\'12\' r=\'3\'></circle></svg>':'<svg width=\'20\' height=\'20\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><path d=\'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24\'></path><line x1=\'1\' y1=\'1\' x2=\'23\' y2=\'23\'></line></svg>';">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div class="below"><label class="remember"><input type="checkbox" name="remember"> Remember me</label><a href="mailto:admin@barangay.gov.ph?subject=Account%20access%20help">Need help?</a></div>
                    <button class="submit" type="submit">Sign in to Dashboard</button>
                </form>
                <p class="help">For account concerns, please contact your Barangay Administrator.<br>Only registered Admin and Staff accounts can access this system.</p>
            </section>
        </section>
    </main>
</body>
</html>
