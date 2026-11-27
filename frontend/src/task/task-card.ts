import { DatePipe } from '@angular/common';
import { Component, input, model, output } from '@angular/core';
import { FormsModule } from '@angular/forms';
import type { Project } from '../project/project';
import type { TaskDetail, TaskDraft, TaskStatus } from './task';

@Component({
  selector: 'app-task-card',
  imports: [DatePipe, FormsModule],
  templateUrl: './task-card.html',
})
export class TaskCardComponent {
  readonly project = input.required<Project>();
  readonly detail = input<TaskDetail | null>(null);
  readonly draft = input.required<TaskDraft>();
  readonly statuses = input.required<{ value: TaskStatus; label: string }[]>();
  readonly saving = input.required<boolean>();
  readonly canResolve = input.required<boolean>();
  readonly comment = model('');
  readonly requiresAttention = model(false);
  readonly closeCard = output<void>();
  readonly save = output<void>();
  readonly sendComment = output<void>();
  readonly resolveComment = output<number>();
}
