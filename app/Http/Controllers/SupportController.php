<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = SupportTicket::query()->with(['reporter:id,name,email,role', 'handler:id,name']);

        if (!$user->isAdmin()) {
            $query->where('reporter_id', $user->id);
        }

        return Inertia::render('Admin/Support', [
            'tickets' => $query->latest()->paginate(15)->withQueryString(),
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['staff', 'volunteer'], true), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:technical,system-bug,operational,other'],
            'priority' => ['required', 'in:low,normal,urgent'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $ticket = SupportTicket::create([
            ...$data,
            'reporter_id' => $request->user()->id,
            'reporter_name' => $request->user()->name,
            'reporter_role' => $request->user()->role,
            'status' => 'open',
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create',
            'module' => 'support',
            'description' => "Submitted support ticket #{$ticket->id}: {$ticket->title}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.support')->with('success', 'Your report was sent to the administrator.');
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,resolved'],
            'admin_response' => ['nullable', 'string', 'max:5000'],
        ]);

        $ticket->update([
            'status' => $data['status'],
            'admin_response' => filled($data['admin_response'] ?? null) ? trim($data['admin_response']) : null,
            'handled_by' => $request->user()->id,
            'resolved_at' => $data['status'] === 'resolved' ? ($ticket->resolved_at ?? now()) : null,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'update',
            'module' => 'support',
            'description' => "Updated support ticket #{$ticket->id} status to {$ticket->status}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.support')->with('success', 'Support ticket updated.');
    }
}
