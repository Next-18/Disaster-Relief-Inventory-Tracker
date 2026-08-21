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
                <div class="brand"><span class="mark">DR</span><span>Disaster Relief<br>Inventory Tracker</span></div>
                <div class="brand-copy"><h1>Barangay operations portal.</h1><p>This portal is only for authorized Barangay Admin and Relief Staff. They can manage inventory, beneficiary records, and relief distribution.</p></div>
                <div class="secure">&#128274; Secure access for authorized personnel</div>
            </aside>
            <section class="form-panel">

                <h2>Admin / Staff Login</h2>
                <p class="intro">Use your barangay-issued account. Beneficiaries cannot log in here.</p>
                @if ($errors->any()) <div class="notice">{{ $errors->first() }}</div> @endif
                <form method="POST" action="{{ route('login.attempt') }}">
                    @csrf
                    <label for="email">Email address</label>
                    <input class="field" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="admin@barangay.gov.ph" required autofocus autocomplete="email">
                    <label for="password">Password</label>
                    <div class="password-wrap">
                        <input class="field" id="password" name="password" type="password" placeholder="Enter password" required autocomplete="current-password">
                        <button class="toggle" type="button" aria-label="Show password" onclick="const p=document.getElementById('password'); p.type=p.type==='password'?'text':'password'; this.textContent=p.type==='password'?'◉':'◌';">◉</button>
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
