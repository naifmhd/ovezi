import { apiRequest } from '@/lib/api-client';

export type CreateSettlementInput = {
  from_user_id?: number;
  from_placeholder_id?: number;
  to_user_id?: number;
  to_placeholder_id?: number;
  amount_minor: number;
  currency_code: string;
  method?: string;
  note?: string;
  occurred_at: string;
};

export function createSettlement(token: string, groupId: number, input: CreateSettlementInput) {
  return apiRequest(`/groups/${groupId}/settlements`, {
    method: 'POST',
    token,
    body: input,
  });
}
