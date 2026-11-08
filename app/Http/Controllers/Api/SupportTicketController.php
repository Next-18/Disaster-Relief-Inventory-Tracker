<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'user' && $user->beneficiary_id, 403);

        $tickets = SupportTicket::query()
            ->where('reporter_id', $user->id)
            ->latest()
            ->limit(50)
            ->get(['id', 'title', 'type', 'priority', 'description', 'status', 'admin_response', 'resolved_at', 'created_at', 'updated_at']);

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'user' && $user->beneficiary_id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:technical,system-bug,operational,other'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $ticket = SupportTicket::create([
            ...$data,
            'priority' => 'normal',
            'reporter_id' => $user->id,
            'reporter_name' => $user->name,
            'reporter_role' => $user->role,
            'status' => 'open',
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'create',
            'module' => 'support',
            'description' => "Beneficiary submitted support request #{$ticket->id}: {$ticket->title}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your support request was sent to the Barangay Administrator.',
            'data' => $ticket->only(['id', 'title', 'type', 'priority', 'description', 'status', 'admin_response', 'resolved_at', 'created_at', 'updated_at']),
        ], 201);
    }
}
