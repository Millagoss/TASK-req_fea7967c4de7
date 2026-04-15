<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditableTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_model_writes_audit_log_with_action_created(): void
    {
        $role = Role::create(['name' => 'admin', 'description' => 'Administrator']);

        $log = AuditLog::where('resource_type', 'roles')
            ->where('resource_id', (string) $role->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('created', $log->action);
        $this->assertNotNull($log->after_hash);
        $this->assertNull($log->before_hash);
    }

    public function test_updating_a_model_writes_audit_log_with_before_and_after_hashes(): void
    {
        $role = Role::create(['name' => 'editor', 'description' => 'Editor role']);

        $role->update(['description' => 'Updated editor role']);

        $log = AuditLog::where('resource_type', 'roles')
            ->where('resource_id', (string) $role->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('updated', $log->action);
        $this->assertNotNull($log->before_hash);
        $this->assertNotNull($log->after_hash);
        $this->assertNotEquals($log->before_hash, $log->after_hash);
    }

    public function test_updating_a_model_metadata_includes_changed_field_names(): void
    {
        $role = Role::create(['name' => 'viewer', 'description' => 'Viewer role']);

        $role->update(['name' => 'viewer_v2']);

        $log = AuditLog::where('resource_type', 'roles')
            ->where('resource_id', (string) $role->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertIsArray($log->metadata);
        $this->assertArrayHasKey('changed', $log->metadata);
        $this->assertContains('name', $log->metadata['changed']);
    }

    public function test_deleting_a_model_writes_audit_log_with_action_deleted(): void
    {
        $role = Role::create(['name' => 'temp', 'description' => 'Temporary']);
        $roleId = $role->id;

        $role->delete();

        $log = AuditLog::where('resource_type', 'roles')
            ->where('resource_id', (string) $roleId)
            ->where('action', 'deleted')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('deleted', $log->action);
        $this->assertNotNull($log->before_hash);
        $this->assertNull($log->after_hash);
    }

    public function test_created_log_has_correct_resource_type_and_id(): void
    {
        $role = Role::create(['name' => 'manager', 'description' => 'Manager']);

        $log = AuditLog::where('action', 'created')
            ->where('resource_id', (string) $role->id)
            ->first();

        $this->assertEquals('roles', $log->resource_type);
        $this->assertEquals((string) $role->id, $log->resource_id);
    }

    public function test_metadata_is_null_for_created_action(): void
    {
        $role = Role::create(['name' => 'tester', 'description' => 'Tester']);

        $log = AuditLog::where('action', 'created')
            ->where('resource_id', (string) $role->id)
            ->first();

        $this->assertNull($log->metadata);
    }
}
