<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BulkPageSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_delete_selected_pages_and_audit_each_delete(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => Hash::make('A-long-test-password'), 'role' => 'admin', 'status' => 'active']);
        $ids = [];
        foreach (['First page', 'Second page'] as $index => $title) {
            $ids[] = DB::table('pages')->insertGetId(['title' => $title, 'slug' => 'page-'.$index, 'body' => 'Content', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($admin)->post('/admin/pages/bulk-delete', ['page_ids' => $ids])
            ->assertRedirect('/admin/pages')
            ->assertSessionHas('success', '2 page(s) deleted.');

        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_bulk_delete_rejects_an_empty_selection(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => Hash::make('A-long-test-password'), 'role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->post('/admin/pages/bulk-delete', ['page_ids' => []])->assertSessionHasErrors('page_ids');
    }
}
