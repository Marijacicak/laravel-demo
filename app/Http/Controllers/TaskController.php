<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = auth()->user()->tasks()
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Tasks/Index', ['tasks' => $tasks]);
    }

    public function store(StoreTaskRequest $request)
    {
        auth()->user()->tasks()->create($request->validated());

        return redirect()->route('tasks.index');
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return redirect()->route('tasks.index');
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()->route('tasks.index');
    }

    public function bulkDelete()
    {
        $ids = request()->validate(['ids' => 'required|array|min:1'])['ids'];

        auth()->user()->tasks()->whereIn('id', $ids)->delete();

        return redirect()->route('tasks.index');
    }

    public function bulkComplete()
    {
        $ids = request()->validate(['ids' => 'required|array|min:1'])['ids'];

        auth()->user()->tasks()->whereIn('id', $ids)->update(['completed' => true]);

        return redirect()->route('tasks.index');
    }
}
