<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);

        $auditLogs = AuditLog::query()
            ->with('actor')
            ->where('clinic_id', $request->user()->clinic_id)
            ->when($request->filled('action'), function ($query) use ($request) {
                $query->where('action', $request->string('action')->toString());
            })
            ->when($request->filled('actor_user_id'), function ($query) use ($request) {
                $query->where('actor_user_id', $request->integer('actor_user_id'));
            })
            ->when($request->filled('auditable_type'), function ($query) use ($request) {
                $query->where('auditable_type', $request->string('auditable_type')->toString());
            })
            ->when($request->filled('auditable_id'), function ($query) use ($request) {
                $query->where('auditable_id', $request->integer('auditable_id'));
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->date('date_from'));
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->date('date_to'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return AuditLogResource::collection($auditLogs);
    }

    public function show(AuditLog $auditLog)
    {
        $this->authorize('view', $auditLog);

        return response()->json([
            'data' => new AuditLogResource($auditLog->load('actor')),
        ]);
    }
}
