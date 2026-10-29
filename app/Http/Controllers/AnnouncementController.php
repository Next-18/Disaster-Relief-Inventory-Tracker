<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $announcements = Announcement::query()
            ->with('creator:id,name')
            ->latest()
            ->get();

        return Inertia::render('Admin/Announcements', [
            'announcements' => $announcements,
            'canDeleteAnnouncements' => $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request)
    {
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
        $announcement->delete();

        return redirect()->route('admin.announcements')->with('success', 'Announcement removed.');
    }
}
