<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->otherUser = User::factory()->create(['email_verified_at' => now()]);
    }

    // Index tests
    public function test_index_returns_successful_response_for_authenticated_user()
    {
        $response = $this->actingAs($this->user)->get(route('tasks.index'));

        $response->assertStatus(200);
    }

    public function test_index_redirects_unauthenticated_users()
    {
        $response = $this->get(route('tasks.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_only_shows_users_own_tasks()
    {
        Task::factory()->create(['user_id' => $this->user->id, 'title' => 'My Task']);
        Task::factory()->create(['user_id' => $this->otherUser->id, 'title' => 'Other User Task']);

        $tasks = $this->user->tasks()->get();
        $this->assertEquals(1, $tasks->count());
        $this->assertEquals('My Task', $tasks[0]->title);
    }

    public function test_index_orders_by_created_at_desc()
    {
        $older = Task::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Older Task',
            'created_at' => now()->subHour(),
        ]);
        $newer = Task::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Newer Task',
            'created_at' => now(),
        ]);

        $tasks = $this->user->tasks()
            ->orderBy('created_at', 'desc')
            ->get();
        $this->assertEquals('Newer Task', $tasks[0]->title);
        $this->assertEquals('Older Task', $tasks[1]->title);
    }

    // Store tests
    public function test_store_creates_task_for_authenticated_user()
    {
        $response = $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => 'New Task',
            'description' => 'Task description',
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('tasks', [
            'user_id' => $this->user->id,
            'title' => 'New Task',
            'description' => 'Task description',
        ]);
    }

    public function test_store_requires_title()
    {
        $response = $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => '',
            'description' => 'Task description',
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseMissing('tasks', ['description' => 'Task description']);
    }

    public function test_store_allows_null_description()
    {
        $response = $this->actingAs($this->user)->post(route('tasks.store'), [
            'title' => 'New Task',
            'description' => null,
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('tasks', [
            'user_id' => $this->user->id,
            'title' => 'New Task',
            'description' => null,
        ]);
    }

    public function test_store_redirects_unauthenticated_users()
    {
        $response = $this->post(route('tasks.store'), [
            'title' => 'New Task',
        ]);

        $response->assertRedirect(route('login'));
    }

    // Update tests
    public function test_update_modifies_own_task()
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->put(route('tasks.update', $task), [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'completed' => true,
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'completed' => true,
        ]);
    }

    public function test_update_prevents_access_to_others_tasks()
    {
        $otherTask = Task::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)->put(route('tasks.update', $otherTask), [
            'title' => 'Hacked Title',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', [
            'id' => $otherTask->id,
            'title' => $otherTask->title,
        ]);
    }

    public function test_update_requires_title()
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->put(route('tasks.update', $task), [
            'title' => '',
            'completed' => false,
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_update_allows_toggling_completed()
    {
        $task = Task::factory()->create([
            'user_id' => $this->user->id,
            'completed' => false,
        ]);

        $this->actingAs($this->user)->put(route('tasks.update', $task), [
            'title' => $task->title,
            'completed' => true,
        ]);

        $this->assertTrue($task->fresh()->completed);
    }

    public function test_update_redirects_unauthenticated_users()
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->put(route('tasks.update', $task), [
            'title' => 'Updated',
        ]);

        $response->assertRedirect(route('login'));
    }

    // Destroy tests
    public function test_destroy_deletes_own_task()
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete(route('tasks.destroy', $task));

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_destroy_prevents_deletion_of_others_tasks()
    {
        $otherTask = Task::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)->delete(route('tasks.destroy', $otherTask));

        $response->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $otherTask->id]);
    }

    public function test_destroy_redirects_unauthenticated_users()
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->delete(route('tasks.destroy', $task));

        $response->assertRedirect(route('login'));
    }

    // Bulk delete tests
    public function test_bulk_delete_deletes_multiple_own_tasks()
    {
        $task1 = Task::factory()->create(['user_id' => $this->user->id]);
        $task2 = Task::factory()->create(['user_id' => $this->user->id]);
        $task3 = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->post(route('tasks.bulk-delete'), [
            'ids' => [$task1->id, $task2->id],
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseMissing('tasks', ['id' => $task1->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task2->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task3->id]);
    }

    public function test_bulk_delete_requires_ids()
    {
        $response = $this->actingAs($this->user)->post(route('tasks.bulk-delete'), [
            'ids' => [],
        ]);

        $response->assertSessionHasErrors('ids');
    }

    public function test_bulk_delete_prevents_deletion_of_others_tasks()
    {
        $userTask = Task::factory()->create(['user_id' => $this->user->id]);
        $otherTask = Task::factory()->create(['user_id' => $this->otherUser->id]);

        $this->actingAs($this->user)->post(route('tasks.bulk-delete'), [
            'ids' => [$userTask->id, $otherTask->id],
        ]);

        // Only user's task should be deleted (scoped by user()->tasks())
        $this->assertDatabaseMissing('tasks', ['id' => $userTask->id]);
        $this->assertDatabaseHas('tasks', ['id' => $otherTask->id]);
    }

    public function test_bulk_delete_redirects_unauthenticated_users()
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->post(route('tasks.bulk-delete'), [
            'ids' => [$task->id],
        ]);

        $response->assertRedirect(route('login'));
    }

    // Bulk complete tests
    public function test_bulk_complete_completes_multiple_own_tasks()
    {
        $task1 = Task::factory()->create(['user_id' => $this->user->id, 'completed' => false]);
        $task2 = Task::factory()->create(['user_id' => $this->user->id, 'completed' => false]);
        $task3 = Task::factory()->create(['user_id' => $this->user->id, 'completed' => false]);

        $response = $this->actingAs($this->user)->post(route('tasks.bulk-complete'), [
            'ids' => [$task1->id, $task2->id],
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertTrue($task1->fresh()->completed);
        $this->assertTrue($task2->fresh()->completed);
        $this->assertFalse($task3->fresh()->completed);
    }

    public function test_bulk_complete_requires_ids()
    {
        $response = $this->actingAs($this->user)->post(route('tasks.bulk-complete'), [
            'ids' => [],
        ]);

        $response->assertSessionHasErrors('ids');
    }

    public function test_bulk_complete_only_affects_own_tasks()
    {
        $userTask = Task::factory()->create(['user_id' => $this->user->id, 'completed' => false]);
        $otherTask = Task::factory()->create(['user_id' => $this->otherUser->id, 'completed' => false]);

        $this->actingAs($this->user)->post(route('tasks.bulk-complete'), [
            'ids' => [$userTask->id, $otherTask->id],
        ]);

        $this->assertTrue($userTask->fresh()->completed);
        $this->assertFalse($otherTask->fresh()->completed);
    }

    public function test_bulk_complete_redirects_unauthenticated_users()
    {
        $task = Task::factory()->create(['user_id' => $this->user->id]);

        $response = $this->post(route('tasks.bulk-complete'), [
            'ids' => [$task->id],
        ]);

        $response->assertRedirect(route('login'));
    }
}
