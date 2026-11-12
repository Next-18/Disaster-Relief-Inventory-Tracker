<?php

namespace App\Http\Middleware;

use App\Support\AdminNotifications;
use App\Models\Announcement;
use App\Models\Barangay;
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
            'activeBarangayId' => fn () => $request->session()->get('active_barangay_id'),
            'activeBarangayName' => fn () => $request->user()?->role === 'super_admin'
                ? Barangay::query()->whereKey($request->session()->get('active_barangay_id'))->value('name')
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'user_credentials' => fn () => $request->session()->get('user_credentials'),
            ],
            'notifications' => fn () => app(AdminNotifications::class)->for($request->user()),
            'staffAnnouncements' => fn () => in_array($request->user()?->role, ['staff', 'volunteer'], true)
                    ? Announcement::query()->where('is_published', true)->latest()->get(['id', 'title', 'body', 'created_at'])
                    : [],
            'routeUrls' => [
                'dashboard' => route('dashboard'),
                'barangays' => route('super-admin.barangays'),
                'announcements' => route('admin.announcements'),
                'announcementStore' => route('admin.announcements.store'),
                'announcementDelete' => route('admin.announcements.destroy', ['announcement' => '__ID__']),
                'beneficiaries' => route('admin.beneficiaries'),
                'beneficiaryStore' => route('admin.beneficiaries.store'),
                'beneficiaryUpdate' => route('admin.beneficiaries.update', ['id' => '__ID__']),
                'beneficiaryDelete' => route('admin.beneficiaries.delete', ['id' => '__ID__']),
                'beneficiariesBulkDelete' => route('admin.beneficiaries.bulk-delete'),
                'beneficiariesBulkStatus' => route('admin.beneficiaries.bulk-status'),
                'beneficiariesExport' => route('admin.beneficiaries.export'),
                'qrGenerate' => route('admin.qr-codes.generate', ['id' => '__ID__']),
                'inventory' => route('admin.inventory'),
                'deliveries' => route('admin.deliveries'),
                'deliveryStore' => route('admin.deliveries.store'),
                'support' => route('admin.support'),
                'supportSuperAdmin' => route('super-admin.support'),
                'supportStore' => route('admin.support.store'),
                'supportUpdateAdmin' => route('admin.support.update', ['ticket' => '__ID__']),
                'supportUpdate' => route('super-admin.support.update', ['ticket' => '__ID__']),
                'inventoryAdjustStock' => route('admin.inventory.adjust-stock', ['id' => '__ID__']),
                'inventoryStore' => route('admin.inventory.store'),
                'inventoryUpdate' => route('admin.inventory.update', ['id' => '__ID__']),
                'inventoryDelete' => route('admin.inventory.delete', ['id' => '__ID__']),
                'distribution' => route('admin.distribution'),
                'distributionStore' => route('admin.distribution.store'),
                'distributionReleasePending' => route('admin.distribution.release-pending'),
                'distributionUpdate' => route('admin.distribution.update', ['id' => '__ID__']),
                'distributionDelete' => route('admin.distribution.delete', ['id' => '__ID__']),
                'packages' => route('admin.packages'),
                'packageStore' => route('admin.packages.store'),
                'packageUpdate' => route('admin.packages.update', ['id' => '__ID__']),
                'packageDelete' => route('admin.packages.delete', ['id' => '__ID__']),
                'qrCodes' => route('admin.qr-codes'),
                'qrImage' => route('admin.qr-codes.image', ['id' => '__ID__']),
                'qrDownload' => route('admin.qr-codes.download', ['id' => '__ID__']),
                'lostQr' => route('admin.lost-qr'),
                'lostQrReport' => route('admin.lost-qr.report', ['id' => '__ID__']),
                'lostQrGenerate' => route('admin.lost-qr.generate', ['id' => '__ID__']),
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
                'mfaChallenge' => route('mfa.challenge'),
                'mfaVerify' => route('mfa.verify'),
                'mfaDisable' => route('super-admin.mfa.disable'),
                'register' => route('register'),
                'registerAttempt' => route('register.attempt'),
                'logo' => asset('images/relief-tracker-logo-transparent.png'),
                'logoIcon' => asset('images/relief-tracker-logo.jpg'),
            ],
        ];
    }
}
