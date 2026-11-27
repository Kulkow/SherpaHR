import { Component, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Api } from './project-desk/api';
import type { AttentionItem } from './attention/attention-item';
import { AttentionOverviewComponent } from './attention/attention-overview';
import { TaskCardComponent } from './task/task-card';
import type { Project } from './project/project';
import { taskFields, type Task, type TaskDetail, type TaskDraft, type TaskStatus } from './task/task';
import type { Context } from './user/user';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, FormsModule, AttentionOverviewComponent, TaskCardComponent],
  templateUrl: './app.html',
})
export class AppComponent {
  api = inject(Api);
  context = signal<Context | null>(null);
  projects = signal<Project[]>([]);
  project = signal<Project | null>(null);
  tasks = signal<Task[]>([]);
  overview = signal(false);
  attentionItems = signal<AttentionItem[]>([]);
  canSeeOverview = computed(() => this.projects().some(p => !p.archived && p.owner_id === this.context()?.user.id));
  detailProject = computed(() => {
    const id = this.detail()?.project_id;
    return id === undefined ? this.project() : this.projects().find(project => project.id === id) ?? null;
  });
  canResolveAttention = computed(() => this.detailProject()?.owner_id === this.context()?.user.id && this.detailProject()?.archived === false);
  detail = signal<TaskDetail | null>(null);
  loading = signal(false);
  saving = signal(false);
  error = signal('');
  notice = signal('');
  status = '';
  search = '';
  comment = '';
  requiresAttention = false;
  creating = signal(false);
  draft = this.emptyDraft();
  private generation = 0;
  private detailGeneration = 0;
  readonly statuses: { value: TaskStatus; label: string }[] = [
    { value: 'open', label: 'Открыта' }, { value: 'in_progress', label: 'В работе' },
    { value: 'blocked', label: 'Заблокирована' }, { value: 'done', label: 'Готово' },
  ];

  constructor() { void this.load(); }

  async load(): Promise<void> {
    const generation = ++this.generation;
    this.loading.set(true);
    this.error.set('');
    this.closeDetail();
    try {
      const [context, projects] = await Promise.all([
        this.api.context(), this.api.projects(),
      ]);
      if (generation !== this.generation) return;
      this.context.set(context);
      this.projects.set(projects);
      const project = projects.find(p => p.id === this.project()?.id) ?? projects[0];
      if (this.canSeeOverview()) await this.selectOverview();
      else if (project) await this.selectProject(project);
    } catch (error) {
      if (generation === this.generation) this.fail(error);
    } finally {
      if (generation === this.generation) this.loading.set(false);
    }
  }

  switchUser(value: string): void {
    this.api.userId = Number(value);
    this.project.set(null);
    this.projects.set([]);
    this.tasks.set([]);
    this.overview.set(false);
    this.attentionItems.set([]);
    this.context.set(null);
    this.notice.set('');
    void this.load();
  }

  async selectProject(project: Project): Promise<void> {
    const generation = ++this.generation;
    this.loading.set(true);
    this.error.set('');
    this.notice.set('');
    this.project.set(project);
    this.overview.set(false);
    this.tasks.set([]);
    this.status = '';
    this.search = '';
    this.closeDetail();
    try {
      const tasks = await this.api.tasks(project.id);
      if (generation === this.generation) this.tasks.set(tasks);
    } catch (error) {
      if (generation === this.generation) this.fail(error);
    } finally {
      if (generation === this.generation) this.loading.set(false);
    }
  }

  async selectOverview(): Promise<void> {
    this.project.set(null);
    this.overview.set(true);
    this.notice.set('');
    this.closeDetail();
    await this.refreshOverview();
  }

  private async refreshOverview(): Promise<void> {
    const generation = ++this.generation;
    this.loading.set(true);
    this.error.set('');
    try {
      const items = await this.api.attention();
      if (generation === this.generation) this.attentionItems.set(items);
    } catch (error) {
      if (generation === this.generation) this.fail(error);
    } finally {
      if (generation === this.generation) this.loading.set(false);
    }
  }

  editableStatuses(): { value: TaskStatus; label: string }[] {
    const task = this.detail();
    return task === null ? [] : this.statuses.filter(
      entry => entry.value === task.status || task.allowed_statuses.includes(entry.value),
    );
  }

  filteredTasks(): Task[] {
    return this.tasks().filter(task => (!this.status || task.status === this.status)
      && task.title.toLocaleLowerCase('ru').includes(this.search.toLocaleLowerCase('ru')));
  }

  async openTask(task: Pick<Task, 'id'>): Promise<void> {
    const generation = ++this.detailGeneration;
    this.error.set('');
    this.creating.set(false);
    this.detail.set(null);
    try {
      const detail = await this.api.task(task.id);
      if (generation !== this.detailGeneration) return;
      this.detail.set(detail);
      this.comment = '';
      this.requiresAttention = false;
      this.draft = {
        title: detail.title, description: detail.description, status: detail.status,
        assignee_id: detail.assignee_id,
        due_at: detail.due_at ? detail.due_at.slice(0, 16) : '',
      };
    } catch (error) {
      if (generation === this.detailGeneration) this.fail(error);
    }
  }

  closeDetail(): void {
    ++this.detailGeneration;
    this.detail.set(null);
    this.creating.set(false);
    this.comment = '';
    this.requiresAttention = false;
  }

  createTask(): void {
    this.closeDetail();
    this.draft = this.emptyDraft();
    this.creating.set(true);
  }

  async saveTask(): Promise<void> {
    const project = this.detailProject();
    if (!project || this.saving()) return;
    const userId = this.api.userId;
    const generation = this.detailGeneration;
    const existing = this.detail();
    this.saving.set(true);
    this.error.set('');
    try {
      const fields = taskFields(this.draft, existing);
      const task = existing
        ? await this.api.updateTask(existing.id, { ...fields, version: existing.version })
        : await this.api.createTask(project.id, { ...fields, status: 'open' });
      if (userId !== this.api.userId) return;
      if (!existing) this.projects.update(list => list.map(p => p.id === project.id ? { ...p, task_count: p.task_count + 1 } : p));
      if (this.project()?.id === project.id) {
        this.tasks.update(list => list.some(item => item.id === task.id)
          ? list.map(item => item.id === task.id ? task : item)
          : [...list, task]);
      }
      if (this.overview()) void this.refreshOverview();
      if (generation === this.detailGeneration) {
        // Saving fields must not erase a comment the user has not sent yet.
        this.detail.set({ ...task, comments: existing?.comments ?? [] });
        this.creating.set(false);
        this.draft = {
          title: task.title, description: task.description, status: task.status,
          assignee_id: task.assignee_id, due_at: task.due_at?.slice(0, 16) ?? '',
        };
        this.notice.set('Задача сохранена');
      }
    } catch (error) { if (generation === this.detailGeneration) this.fail(error); }
    finally { this.saving.set(false); }
  }

  async addComment(): Promise<void> {
    const task = this.detail();
    if (!task || this.saving()) return;
    const generation = this.detailGeneration;
    this.saving.set(true);
    this.error.set('');
    try {
      const comment = await this.api.addComment(task.id, this.comment, this.requiresAttention);
      if (this.overview()) void this.refreshOverview();
      if (generation !== this.detailGeneration) return;
      // Refresh only the discussion: fields may contain unsaved user edits.
      this.detail.update(current => current ? { ...current, comments: [...current.comments, comment] } : current);
      this.comment = '';
      this.requiresAttention = false;
      this.notice.set('Комментарий добавлен');
    } catch (error) { if (generation === this.detailGeneration) this.fail(error); }
    finally { this.saving.set(false); }
  }

  async resolveComment(commentId: number): Promise<void> {
    const task = this.detail();
    if (!task || this.saving()) return;
    const generation = this.detailGeneration;
    this.saving.set(true);
    this.error.set('');
    try {
      const { attention } = await this.api.resolveComment(task.id, commentId);
      if (this.overview()) void this.refreshOverview();
      if (generation !== this.detailGeneration) return;
      this.detail.update(current => current ? {
        ...current,
        comments: current.comments.map(comment => comment.id === commentId ? { ...comment, attention } : comment),
      } : current);
      this.notice.set('Отметка обработана');
    } catch (error) { if (generation === this.detailGeneration) this.fail(error); }
    finally { this.saving.set(false); }
  }

  statusLabel(status: string): string { return this.statuses.find(s => s.value === status)?.label ?? status; }
  userName(id: number | null): string { return this.context()?.users.find(u => u.id === id)?.name ?? 'Не назначен'; }
  private fail(error: unknown): void { this.error.set(error instanceof Error ? error.message : 'Не удалось выполнить запрос'); }
  private emptyDraft(): TaskDraft {
    return { title: '', description: '', status: 'open', assignee_id: null, due_at: '' };
  }
}
