<?php

namespace App\Http\Middleware;

use App\Support\AdminNotifications;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()?->only('id', 'name', 'email', 'role'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => fn () => app(AdminNotifications::class)->for($request->user()),
            'routeUrls' => [
                'dashboard' => route('dashboard'),
                'beneficiaries' => route('admin.beneficiaries'),
                'beneficiaryStore' => route('admin.beneficiaries.store'),
                'beneficiaryUpdate' => route('admin.beneficiaries.update', ['id' => '__ID__']),
                'beneficiaryDelete' => route('admin.beneficiaries.delete', ['id' => '__ID__']),
                'beneficiariesBulkDelete' => route('admin.beneficiaries.bulk-delete'),
                'beneficiariesBulkStatus' => route('admin.beneficiaries.bulk-status'),
                'beneficiariesExport' => route('admin.beneficiaries.export'),
                'qrGenerate' => route('admin.qr-codes.generate', ['id' => '__ID__']),
                'inventory' => route('admin.inventory'),
                'inventoryStore' => route('admin.inventory.store'),
                'inventoryUpdate' => route('admin.inventory.update', ['id' => '__ID__']),
                'inventoryDelete' => route('admin.inventory.delete', ['id' => '__ID__']),
                'distribution' => route('admin.distribution'),
                'distributionStore' => route('admin.distribution.store'),
                'distributionUpdate' => route('admin.distribution.update', ['id' => '__ID__']),
                'distributionDelete' => route('admin.distribution.delete', ['id' => '__ID__']),
                'packages' => route('admin.packages'),
                'packageStore' => route('admin.packages.store'),
                'packageUpdate' => route('admin.packages.update', ['id' => '__ID__']),
                'packageDelete' => route('admin.packages.delete', ['id' => '__ID__']),
                'qrCodes' => route('admin.qr-codes'),
                'qrDownload' => route('admin.qr-codes.download', ['id' => '__ID__']),
                'lostQr' => route('admin.lost-qr'),
                'lostQrReport' => route('admin.lost-qr.report', ['id' => '__ID__']),
                'reports' => route('admin.reports'),
                'reportExport' => route('admin.reports.export'),
                'inventoryReportExport' => route('admin.reports.inventory-export'),
                'auditLogs' => route('admin.audit-logs'),
                'users' => route('admin.users'),
                'settings' => route('admin.settings'),
                'passwordUpdate' => route('admin.account.password'),
                'search' => route('admin.search'),
                'logout' => route('logout'),
                'login' => route('login'),
                'loginAttempt' => route('login.attempt'),
                'register' => route('register'),
                'registerAttempt' => route('register.attempt'),
                'logo' => asset('images/logo.png'),
                'qrBase' => asset('qr-codes'),
            ],
        ];
    }
}
