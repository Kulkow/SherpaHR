import type { AttentionReason } from '../project-desk/contract/types.gen';

export type { AttentionItem, AttentionReason } from '../project-desk/contract/types.gen';

export const reasonLabels: Record<AttentionReason, string> = {
  comment_attention: 'Комментарий требует внимания',
  blocked: 'Заблокирована',
  overdue: 'Просрочена',
  unassigned: 'Без исполнителя',
};
