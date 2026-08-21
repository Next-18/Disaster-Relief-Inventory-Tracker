<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', 'Relief Tracker')</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    <div class="app-shell">
        @include('partials.admin-sidebar')
        <main class="content">
            @include('partials.topbar', ['subtitle' => $__env->yieldContent('subtitle'), 'title' => $__env->yieldContent('title')])

            @yield('content')
        </main>
    </div>

    {{-- place for page-specific modals or dialogs --}}
    @stack('modals')
</body>
</html>
