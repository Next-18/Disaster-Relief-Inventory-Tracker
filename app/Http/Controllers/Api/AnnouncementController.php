<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $announcements = Announcement::query()
            ->with('creator:id,name')
            ->where('is_published', true)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Announcement $announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'author' => $announcement->creator?->name,
                'published_at' => $announcement->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $announcements,
            'count' => $announcements->count(),
        ]);
    }
}
