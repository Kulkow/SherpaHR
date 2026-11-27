import { DatePipe } from '@angular/common';
import { Component, input, output } from '@angular/core';
import { reasonLabels, type AttentionItem } from './attention-item';

@Component({
  selector: 'app-attention-overview',
  imports: [DatePipe],
  templateUrl: './attention-overview.html',
})
export class AttentionOverviewComponent {
  readonly items = input.required<AttentionItem[]>();
  readonly loading = input.required<boolean>();
  readonly failed = input.required<boolean>();
  readonly openTask = output<AttentionItem>();
  readonly retry = output<void>();
  readonly labels = reasonLabels;
}
