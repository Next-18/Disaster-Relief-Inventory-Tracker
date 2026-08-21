@extends('layouts.admin')

@section('pageTitle', 'Dashboard | Relief Tracker')
@section('title', 'Dashboard')
@section('subtitle', 'ADMIN PORTAL / OVERVIEW')

@section('content')
    <section class="welcome">
        <div>
            <h2>Good evening, {{ explode(' ', auth()->user()->name)[0] }} 👋</h2>
            <p>Here’s the latest relief operation overview for <strong>Barangay San Juan.</strong></p>
        </div>
        <button class="primary-action">+ Record Distribution</button>
    </section>

    <section class="metrics">
        <article class="metric-card blue">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6M17 11a3 3 0 1 0-1.3-5.7M17 14c2.8 0 5 2.3 5 5"/></svg></span>
            <div><p>Total Beneficiaries</p><strong>1,284</strong><small>+18 registered this month</small></div>
        </article>
        <article class="metric-card green">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="M4 10h16v10H4zM3 6h18v4H3zM12 6v14M9 3h6l-3 3-3-3Z"/></svg></span>
            <div><p>Available Relief Packs</p><strong>865</strong><small>Across 4 active packages</small></div>
        </article>
        <article class="metric-card orange">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="M4 11h16v8H4zM7 11V7h10v4M8 15h8"/></svg></span>
            <div><p>Distributed This Month</p><strong>419</strong><small>32% of registered families</small></div>
        </article>
        <article class="metric-card red">
            <span class="metric-icon"><svg viewBox="0 0 24 24"><path d="M12 3 2.8 20h18.4L12 3ZM12 9v5M12 17h.01"/></svg></span>
            <div><p>Stock Alerts</p><strong>3</strong><small>Items need attention</small></div>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="panel distribution" id="distribution">
            <div class="panel-heading">
                <div>
                    <h3>Recent Distribution Records</h3>
                    <p>Latest completed relief releases</p>
                </div>
                <a href="#distribution">View all →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>BENEFICIARY</th><th>RELIEF PACKAGE</th><th>DATE RELEASED</th><th>STATUS</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><i class="avatar a1">MR</i><b>Maria Reyes</b><small>BEN-1024</small></td><td>Family Food Pack</td><td>Aug 20, 2026</td><td><em class="status success">Released</em></td></tr>
                        <tr><td><i class="avatar a2">JD</i><b>Juan Dela Cruz</b><small>BEN-0981</small></td><td>Hygiene Kit</td><td>Aug 20, 2026</td><td><em class="status success">Released</em></td></tr>
                        <tr><td><i class="avatar a3">AS</i><b>Angela Santos</b><small>BEN-1153</small></td><td>Family Food Pack</td><td>Aug 19, 2026</td><td><em class="status success">Released</em></td></tr>
                        <tr><td><i class="avatar a4">RP</i><b>Ramon Perez</b><small>BEN-1047</small></td><td>Water &amp; Essentials</td><td>Aug 19, 2026</td><td><em class="status pending">Pending</em></td></tr>
                    </tbody>
                </table>
            </div>
        </article>

        <aside class="side-panels">
            <article class="panel stock" id="inventory">
                <div class="panel-heading"><div><h3>Stock Alerts</h3><p>Items below minimum stock</p></div><b class="alert-count">3</b></div>
                <div class="stock-item">🍚<div><b>Rice (5kg)</b><small>12 bags remaining</small></div><em>Low</em></div>
                <div class="stock-item">🥫<div><b>Canned Goods</b><small>24 units remaining</small></div><em>Low</em></div>
                <div class="stock-item">💧<div><b>Bottled Water</b><small>8 cases remaining</small></div><em>Critical</em></div>
                <a class="stock-link" href="#inventory">Manage inventory →</a>
            </article>
            <article class="panel quick">
                <h3>Quick Actions</h3>
                <a href="#beneficiaries">♟ <span>Add beneficiary<small>Create a beneficiary account</small></span>›</a>
                <a href="#qr-codes">▦ <span>Scan QR code<small>Verify a relief recipient</small></span>›</a>
                <a href="#reports">📊 <span>View reports<small>Distribution and stock reports</small></span>›</a>
            </article>
        </aside>
    </section>
@endsection
