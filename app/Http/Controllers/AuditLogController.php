<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        if (auth()->user()->hasRole('Super Admin')) {

            $logs = AuditLog::with([
                'user',
                'organization'
            ])
            ->latest()
            ->paginate(20);

        } else {

            $logs = AuditLog::with([
                'user',
                'organization'
            ])
            ->where(
                'organization_id',
                auth()->user()->organization_id
            )
            ->latest()
            ->paginate(20);

        }

        return view(
            'audit-logs.index',
            compact('logs')
        );
    }
}
