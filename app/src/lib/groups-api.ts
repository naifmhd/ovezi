import { apiRequest } from '@/lib/api-client';
import type { Group, GroupBalances, PaginatedResponse } from '@/types/api';

type DataResponse<T> = { data: T };

export function fetchGroups(token: string) {
  return apiRequest<PaginatedResponse<Group>>('/groups?status=active&per_page=100', { token });
}

export async function fetchGroup(token: string, groupId: number) {
  const response = await apiRequest<DataResponse<Group>>(`/groups/${groupId}`, { token });
  return response.data;
}

export async function createGroup(
  token: string,
  input: { name: string; reportingCurrencyCode: string },
) {
  const response = await apiRequest<DataResponse<Group>>('/groups', {
    method: 'POST',
    token,
    body: {
      name: input.name,
      reporting_currency_code: input.reportingCurrencyCode,
    },
  });
  return response.data;
}

export async function fetchGroupBalances(token: string, groupId: number) {
  const response = await apiRequest<DataResponse<GroupBalances>>(`/groups/${groupId}/balances`, {
    token,
  });
  return response.data;
}
