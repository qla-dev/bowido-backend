<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarNoteFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_list_calendar_note_with_notified_members(): void
    {
        $admin = $this->makeUser('admin');
        $member = $this->makeUser('operator', [
            'name' => 'Warehouse Planner',
            'email' => 'planner@example.com',
        ]);
        $customer = $this->makeUser('customer', [
            'name' => 'Customer Hidden',
            'email' => 'customer-hidden@example.com',
        ]);

        $createResponse = $this->actingAs($admin, 'api')->postJson('/api/calendar_notes', [
            'note_date' => '2026-07-14',
            'note_time' => '09:30',
            'title' => 'Billing follow-up',
            'note' => 'Call about overdue pallets.',
            'notified_user_ids' => [$member->id],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.note_date', '2026-07-14')
            ->assertJsonPath('data.note_time', '09:30')
            ->assertJsonPath('data.notified_user_ids.0', $member->id);

        $noteId = $createResponse->json('data.id');

        $this->assertDatabaseHas('calendar_notes', [
            'id' => $noteId,
            'created_by_user_id' => $admin->id,
            'title' => 'Billing follow-up',
        ]);
        $this->assertDatabaseHas('calendar_note_user', [
            'calendar_note_id' => $noteId,
            'user_id' => $member->id,
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/calendar_notes?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.0.id', $noteId);

        $candidatesResponse = $this->actingAs($admin, 'api')
            ->getJson('/api/calendar_notes/notify-candidates?search=planner');

        $candidatesResponse
            ->assertOk()
            ->assertJsonPath('data.0.id', $member->id);

        $candidateIds = collect($candidatesResponse->json('data'))->pluck('id')->all();

        $this->assertNotContains($customer->id, $candidateIds);
    }
}
