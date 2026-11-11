<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Barangay;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $canManage = $request->user()->canManageBarangay();
        if ($request->user()->role === 'super_admin') {
            $activeBarangayId = $request->session()->get('active_barangay_id');
            $canManage = $canManage && $activeBarangayId && Barangay::query()
                ->whereKey($activeBarangayId)
                ->where('is_active', true)
                ->exists();
        }

        $announcements = Announcement::query()
            ->with('creator:id,name')
            ->latest()
            ->get();

        return Inertia::render('Admin/Announcements', [
            'announcements' => $announcements,
            'canDeleteAnnouncements' => $canManage,
            'canPublishAnnouncements' => $canManage,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canManageBarangay(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        Announcement::create($validated + [
            'is_published' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.announcements')->with('success', 'Announcement published to staff and the beneficiary mobile app.');
    }

    public function destroy(Announcement $announcement)
    {
        abort_unless(request()->user()?->canManageBarangay(), 403);

        $announcement->delete();

        return redirect()->route('admin.announcements')->with('success', 'Announcement removed.');
    }
}
