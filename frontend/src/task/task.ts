import type { Task, TaskFields } from '../project-desk/contract/types.gen';

export type { Comment, CommentAttention, EditableTask, Task, TaskDetail, TaskFields, TaskStatus }
  from '../project-desk/contract/types.gen';

export interface TaskDraft extends Omit<TaskFields, 'due_at'> {
  due_at: string;
}

export function taskFields(draft: TaskDraft, existing: Task | null): TaskFields {
  const originalMinute = existing?.due_at?.slice(0, 16) ?? '';
  const dueAt = draft.due_at === originalMinute
    ? existing?.due_at ?? null
    : draft.due_at ? `${draft.due_at}:00Z` : null;

  return { ...draft, due_at: dueAt };
}
