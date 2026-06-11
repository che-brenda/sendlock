<?php

namespace App\Helpers;

use App\Models\AuditLog;

class AuditLogger
{
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $description = null
    ): void {

        AuditLog::create([

            'organization_id' =>
                auth()->user()?->organization_id,

            'user_id' =>
                auth()->id(),

            'action' =>
                $action,

            'entity_type' =>
                $entityType,

            'entity_id' =>
                $entityId,

            'description' =>
                $description,

            'ip_address' =>
                request()->ip(),

        ]);
    }
}