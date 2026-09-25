import { financialRequest } from '@/lib/financial-request';

export type CreateSettlementInput = {
  from_user_id?: number;
  from_placeholder_id?: number;
  to_user_id?: number;
  to_placeholder_id?: number;
  amount_minor: number;
  currency_code: string;
  reporting_currency_code?: string;
  method?: string;
  note?: string;
  occurred_at: string;
};

export function createSettlement(token: string, groupId: number, input: CreateSettlementInput) {
  return financialRequest(`/groups/${groupId}/settlements`, token, input);
}

export function createDirectSettlement(token: string, input: CreateSettlementInput) {
  return financialRequest('/settlements', token, input);
}
