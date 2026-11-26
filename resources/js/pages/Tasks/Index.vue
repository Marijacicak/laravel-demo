<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

interface Task {
    id: number;
    title: string;
    description: string | null;
    completed: boolean;
}

const props = defineProps<{
    tasks: Task[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tasks', href: '/tasks' },
];

const form = reactive({
    title: '',
    description: '',
});

const editForm = reactive({
    title: '',
    description: '',
});

const errors = reactive({ title: '' });
const editingId = ref<number | null>(null);
const isCreating = ref(false);
const isDeleting = ref<number | null>(null);
const isDeletingSelected = ref(false);
const isCompletingSelected = ref(false);
const selectedIds = ref<Set<number>>(new Set());

const allSelected = ref(false);

const toggleAllSelected = () => {
    allSelected.value = !allSelected.value;
    if (allSelected.value) {
        selectedIds.value = new Set(props.tasks.map(t => t.id));
    } else {
        selectedIds.value.clear();
    }
};

const createTask = () => {
    errors.title = '';
    if (!form.title.trim()) {
        errors.title = 'Title is required';
        return;
    }
    isCreating.value = true;
    router.post(route('tasks.store'), form, {
        onSuccess: () => {
            form.title = '';
            form.description = '';
            isCreating.value = false;
            selectedIds.value.clear();
            allSelected.value = false;
        },
        onError: (errs: Record<string, string>) => {
            errors.title = errs.title || '';
            isCreating.value = false;
        },
    });
};

const toggleSelection = (taskId: number) => {
    if (selectedIds.value.has(taskId)) {
        selectedIds.value.delete(taskId);
    } else {
        selectedIds.value.add(taskId);
    }
    // Update allSelected state
    allSelected.value = selectedIds.value.size === props.tasks.length && props.tasks.length > 0;
};

const startEdit = (task: Task) => {
    editingId.value = task.id;
    editForm.title = task.title;
    editForm.description = task.description || '';
};

const saveEdit = (task: Task) => {
    if (!editForm.title.trim()) {
        editingId.value = null;
        return;
    }
    router.put(route('tasks.update', task.id), {
        title: editForm.title,
        description: editForm.description,
        completed: task.completed,
    });
    editingId.value = null;
};

const toggleCompletion = (task: Task) => {
    router.put(route('tasks.update', task.id), {
        title: task.title,
        description: task.description,
        completed: !task.completed,
    });
};

const deleteTask = (task: Task) => {
    if (!confirm('Are you sure you want to delete this task?')) return;
    isDeleting.value = task.id;
    router.delete(route('tasks.destroy', task.id), {
        onFinish: () => {
            isDeleting.value = null;
            selectedIds.value.delete(task.id);
        },
    });
};

const deleteSelected = () => {
    if (selectedIds.value.size === 0) return;
    if (!confirm(`Delete ${selectedIds.value.size} task(s)?`)) return;

    isDeletingSelected.value = true;
    router.post(route('tasks.bulk-delete'),
        { ids: Array.from(selectedIds.value) },
        {
            onFinish: () => {
                isDeletingSelected.value = false;
                selectedIds.value.clear();
                allSelected.value = false;
            },
        }
    );
};

const completeSelected = () => {
    if (selectedIds.value.size === 0) return;

    isCompletingSelected.value = true;
    router.post(route('tasks.bulk-complete'),
        { ids: Array.from(selectedIds.value) },
        {
            onFinish: () => {
                isCompletingSelected.value = false;
                selectedIds.value.clear();
                allSelected.value = false;
            },
        }
    );
};
</script>

<template>
    <Head title="Tasks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Create task form -->
            <div class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-neutral-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">Add New Task</h2>
                <form @submit.prevent="createTask" class="space-y-4">
                    <div>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="Task title"
                            class="block w-full rounded-lg border border-gray-300 px-4 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-gray-100"
                        />
                        <span v-if="errors.title" class="mt-1 text-sm text-red-600">{{ errors.title }}</span>
                    </div>
                    <div>
                        <textarea
                            v-model="form.description"
                            placeholder="Description (optional)"
                            class="block w-full rounded-lg border border-gray-300 px-4 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-gray-100"
                            rows="2"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="isCreating"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ isCreating ? 'Creating...' : 'Add Task' }}
                    </button>
                </form>
            </div>

            <!-- Tasks list -->
            <div class="rounded-xl border border-sidebar-border/70 bg-white p-6 dark:border-sidebar-border dark:bg-neutral-900">
                <!-- Header with select all and bulk action buttons -->
                <div class="mb-4 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-3">
                        <input
                            v-if="tasks.length"
                            type="checkbox"
                            :checked="allSelected"
                            @change="toggleAllSelected"
                            class="h-5 w-5 rounded text-blue-600"
                            title="Select all tasks"
                        />
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            My Tasks
                            <span class="ml-2 text-sm font-normal text-gray-500">({{ tasks.length }})</span>
                        </h2>
                    </div>

                    <!-- Bulk action buttons -->
                    <div v-if="selectedIds.size > 0" class="flex gap-2">
                        <button
                            @click="completeSelected"
                            :disabled="isCompletingSelected"
                            class="rounded-lg bg-green-600 px-3 py-1 text-sm text-white hover:bg-green-700 disabled:opacity-50"
                        >
                            {{ isCompletingSelected ? 'Completing...' : `Complete Selected (${selectedIds.size})` }}
                        </button>
                        <button
                            @click="deleteSelected"
                            :disabled="isDeletingSelected"
                            class="rounded-lg bg-red-600 px-3 py-1 text-sm text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            {{ isDeletingSelected ? 'Deleting...' : `Delete Selected (${selectedIds.size})` }}
                        </button>
                    </div>
                </div>

                <!-- Tasks list -->
                <div v-if="tasks.length" class="space-y-2">
                    <div
                        v-for="task in tasks"
                        :key="task.id"
                        :class="[
                            'flex items-start gap-4 rounded-lg border p-4 transition-colors',
                            task.completed
                                ? 'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950'
                                : 'border-gray-200 hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-800',
                        ]"
                    >
                        <!-- Selection checkbox -->
                        <input
                            type="checkbox"
                            :checked="selectedIds.has(task.id)"
                            @change="toggleSelection(task.id)"
                            class="mt-1 h-5 w-5 rounded text-blue-600"
                        />

                        <!-- Content -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <input
                                    v-if="editingId === task.id"
                                    v-model="editForm.title"
                                    type="text"
                                    @blur="saveEdit(task)"
                                    @keyup.enter="saveEdit(task)"
                                    @keyup.escape="editingId = null"
                                    class="flex-1 rounded border border-gray-300 px-2 py-1 dark:border-neutral-700 dark:bg-neutral-800 dark:text-gray-100"
                                    autofocus
                                />
                                <p
                                    v-else
                                    @click="startEdit(task)"
                                    :class="[
                                        'flex-1 cursor-pointer font-medium hover:underline dark:text-gray-100',
                                        task.completed
                                            ? 'text-gray-700 dark:text-gray-300'
                                            : 'text-gray-900 dark:text-gray-100',
                                    ]"
                                >
                                    {{ task.title }}
                                </p>
                            </div>
                            <p
                                v-if="task.description && editingId !== task.id"
                                :class="[
                                    'mt-1 text-sm',
                                    task.completed
                                        ? 'text-gray-600 dark:text-gray-400'
                                        : 'text-gray-600 dark:text-gray-400',
                                ]"
                            >
                                {{ task.description }}
                            </p>
                            <textarea
                                v-else-if="editingId === task.id"
                                v-model="editForm.description"
                                @blur="saveEdit(task)"
                                @keyup.escape="editingId = null"
                                class="mt-2 w-full rounded border border-gray-300 px-2 py-1 dark:border-neutral-700 dark:bg-neutral-800 dark:text-gray-100"
                                rows="2"
                            />
                        </div>

                        <!-- Complete/Undo button -->
                        <button
                            @click="toggleCompletion(task)"
                            :class="[
                                'flex-shrink-0 text-sm font-medium hover:underline',
                                task.completed
                                    ? 'text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300'
                                    : 'text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300',
                            ]"
                        >
                            {{ task.completed ? 'Undo' : 'Complete' }}
                        </button>

                        <!-- Delete button -->
                        <button
                            @click="deleteTask(task)"
                            :disabled="isDeleting === task.id"
                            :class="[
                                'flex-shrink-0 text-sm font-medium hover:underline disabled:opacity-50',
                                'text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300',
                            ]"
                        >
                            {{ isDeleting === task.id ? '...' : 'Delete' }}
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="py-12 text-center">
                    <p class="text-lg text-gray-500 dark:text-gray-400">No tasks yet. Create one to get started!</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
