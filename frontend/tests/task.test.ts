import assert from 'node:assert/strict';
import { test } from 'node:test';
import { taskFields, type Task, type TaskDraft } from '../src/task/task.ts';

const draft: TaskDraft = {
  title: 'Задача', description: '', status: 'open', assignee_id: null, due_at: '2026-11-11T10:30',
};
const existing: Task = {
  ...draft, id: 1, project_id: 1, version: 1,
  due_at: '2026-11-11T10:30:45Z', updated_at: '2026-11-10T12:00:00Z',
};

test('saving other fields preserves deadline seconds', () => {
  assert.equal(taskFields({ ...draft, title: 'Новое название' }, existing).due_at, existing.due_at);
});

test('changing the deadline sets it in UTC or clears it when empty', () => {
  assert.equal(taskFields({ ...draft, due_at: '2026-11-12T09:00' }, existing).due_at, '2026-11-12T09:00:00Z');
  assert.equal(taskFields({ ...draft, due_at: '' }, existing).due_at, null);
});
