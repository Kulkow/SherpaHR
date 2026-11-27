import { Injectable } from '@angular/core';
import type { AttentionItem } from '../attention/attention-item';
import type { Project } from '../project/project';
import type { Comment, EditableTask, Task, TaskDetail } from '../task/task';
import type { Context } from '../user/user';
import type { CreateComment, CreateTask, ResolvedComment, UpdateTask } from './contract/types.gen';

@Injectable({ providedIn: 'root' })
export class Api {
  userId = 1;

  context(): Promise<Context> { return this.request('/context'); }
  projects(): Promise<Project[]> { return this.request('/projects'); }
  tasks(projectId: number): Promise<Task[]> { return this.request(`/projects/${projectId}/tasks`); }
  task(taskId: number): Promise<TaskDetail> { return this.request(`/tasks/${taskId}`); }
  attention(): Promise<AttentionItem[]> { return this.request('/attention'); }

  createTask(projectId: number, fields: CreateTask): Promise<EditableTask> {
    return this.request(`/projects/${projectId}/tasks`, 'POST', fields);
  }

  updateTask(taskId: number, fields: UpdateTask): Promise<EditableTask> {
    return this.request(`/tasks/${taskId}`, 'PATCH', fields);
  }

  addComment(taskId: number, body: string, requiresAttention: boolean): Promise<Comment> {
    const input: CreateComment = { body, requires_attention: requiresAttention };
    return this.request(`/tasks/${taskId}/comments`, 'POST', input);
  }

  resolveComment(taskId: number, commentId: number): Promise<ResolvedComment> {
    return this.request(`/tasks/${taskId}/comments/${commentId}/resolve`, 'POST');
  }

  private async request<T>(path: string, method = 'GET', data?: unknown): Promise<T> {
    const response = await fetch(`/api${path}`, {
      method,
      headers: { 'Content-Type': 'application/json', 'X-Demo-User': String(this.userId) },
      body: data === undefined ? undefined : JSON.stringify(data),
    });
    const result: unknown = await response.json().catch(() => null);
    if (!response.ok) {
      const message = result !== null && typeof result === 'object' && 'error' in result && typeof result.error === 'string'
        ? result.error : `Ошибка ${response.status}`;
      throw new Error(message);
    }
    if (result === null || typeof result !== 'object' || !('data' in result)) {
      throw new Error('Сервер вернул ответ без данных.');
    }
    return result.data as T;
  }
}
