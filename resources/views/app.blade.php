<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title inertia>ReliefTrack | Disaster Relief Inventory Tracker</title>
    <meta name="description" content="ReliefTrack helps local teams manage emergency inventory, relief packages, beneficiary records, and distributions.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-icon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @vite('resources/js/app.js')
    <x-inertia::head />
</head>
<body>
    <x-inertia::app />
</body>
</html>
