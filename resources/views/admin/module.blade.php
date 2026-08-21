@extends('layouts.admin')

@section('pageTitle', $title.' | Relief Tracker')
@section('title', $title)
@section('subtitle')
    ADMIN PORTAL / {{ strtoupper($title) }}
@endsection

@section('content')
    <section class="module-empty">
        <span>{{ $icon }}</span>
        <h2>{{ $title }}</h2>
        <p>{{ $description }}</p>
        <button class="primary-action">Coming soon</button>
    </section>
@endsection
