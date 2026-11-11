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
        abort_unless(in_array($user->role, ['admin', 'staff', 'volunteer'], true), 403);

        $query = SupportTicket::query()->with(['reporter:id,name,email,role', 'handler:id,name']);
        if ($user->role === 'admin') {
            $query->where(function ($tickets) use ($user) {
                $tickets->where('recipient_role', 'admin')
                    ->orWhere(fn ($outgoing) => $outgoing
                        ->where('recipient_role', 'super_admin')
                        ->where('reporter_id', $user->id));
            });
        } else {
            $query->where('recipient_role', 'admin')->where('reporter_id', $user->id);
        }

        $tickets = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Support', [
            'tickets' => $tickets,
            'isAdmin' => $user->role === 'admin',
            'isSuperAdmin' => false,
        ]);
    }

    public function superAdminIndex(Request $request)
    {
        abort_unless($request->user()->role === 'super_admin', 403);

        $tickets = SupportTicket::withoutGlobalScope('barangay')
            ->with(['reporter:id,name,email,role,barangay_id', 'reporter.barangay:id,name', 'handler:id,name'])
            ->where('recipient_role', 'super_admin')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Support', [
            'tickets' => $tickets,
            'isSuperAdmin' => true,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'staff', 'volunteer'], true), 403);

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
            'recipient_role' => $request->user()->role === 'admin' ? 'super_admin' : 'admin',
            'status' => 'open',
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create',
            'module' => 'support',
            'description' => "Sent a support request to ".($ticket->recipient_role === 'super_admin' ? 'Super Admin' : 'barangay Admin')." #{$ticket->id}: {$ticket->title}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('admin.support')->with('success', $ticket->recipient_role === 'super_admin'
            ? 'Your report was sent to Super Admin.'
            : 'Your report was sent to your barangay Admin.');
    }

    public function adminUpdate(Request $request, int $ticket)
    {
        abort_unless($request->user()->role === 'admin', 403);

        $ticket = SupportTicket::query()->where('recipient_role', 'admin')->findOrFail($ticket);

        return $this->saveResponse($request, $ticket, 'admin.support');
    }

    public function update(Request $request, int $ticket)
    {
        abort_unless($request->user()->role === 'super_admin', 403);

        $ticket = SupportTicket::withoutGlobalScope('barangay')
            ->where('recipient_role', 'super_admin')
            ->findOrFail($ticket);

        return $this->saveResponse($request, $ticket, 'super-admin.support');
    }

    private function saveResponse(Request $request, SupportTicket $ticket, string $redirectRoute)
    {
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
            'barangay_id' => $ticket->barangay_id,
            'action' => 'update',
            'module' => 'support',
            'description' => "Updated support ticket #{$ticket->id} status to {$ticket->status}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route($redirectRoute)->with('success', 'Support ticket updated.');
    }
}
