<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('pageTitle', 'Relief Tracker')</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    @stack('scripts')

    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Sign Out',
                text: 'Are you sure you want to sign out?',
                icon: 'question',
                iconColor: '#64748b',
                showCancelButton: true,
                confirmButtonText: 'Sign Out',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#64748b',
                cancelButtonColor: '#94a3b8',
                background: '#ffffff',
                color: '#1e293b',
                customClass: {
                    popup: 'modern-swal-popup',
                    title: 'modern-swal-title',
                    content: 'modern-swal-content',
                    confirmButton: 'modern-swal-confirm',
                    cancelButton: 'modern-swal-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route('logout') }}';
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = '{{ csrf_token() }}';
                    form.appendChild(csrfInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>
