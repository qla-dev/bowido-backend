<?php

namespace Tests\Feature;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Shared\Enums\AuditEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogSummaryFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_index_returns_database_totals_for_the_active_filter(): void
    {
        $admin = $this->makeUser('admin');

        AuditLog::factory()->create([
            'made_by_user_id' => $admin->id,
            'event_type' => AuditEventType::StatusChanged->value,
        ]);
        AuditLog::factory()->create([
            'made_by_user_id' => $admin->id,
            'event_type' => AuditEventType::StatusChanged->value,
        ]);
        AuditLog::factory()->create([
            'made_by_user_id' => $admin->id,
            'event_type' => AuditEventType::QrCodeChanged->value,
        ]);
        $this->actingAs($admin, 'api')
            ->getJson('/api/audit_logs')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.status_changes', 2)
            ->assertJsonPath('meta.qr_version_changes', 1);

        $this->actingAs($admin, 'api')
            ->getJson('/api/audit_logs?event_type='.AuditEventType::QrCodeChanged->value)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.status_changes', 0)
            ->assertJsonPath('meta.qr_version_changes', 1);
    }
}
