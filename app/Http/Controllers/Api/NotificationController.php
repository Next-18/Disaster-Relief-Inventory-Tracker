<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BeneficiaryNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->beneficiary_id, 403);

        $query = BeneficiaryNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest();

        return response()->json([
            'success' => true,
            'data' => (clone $query)->limit(30)->get(),
            'unread_count' => (clone $query)->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        abort_unless($request->user()->beneficiary_id, 403);

        $notification = BeneficiaryNotification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
