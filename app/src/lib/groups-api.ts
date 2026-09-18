import { apiRequest, apiTextRequest } from '@/lib/api-client';
import type { Group, GroupBalances, GroupMember, PaginatedResponse } from '@/types/api';

type DataResponse<T> = { data: T };

export function fetchGroups(token: string, status: 'active' | 'archived' = 'active') {
  return apiRequest<PaginatedResponse<Group>>(`/groups?status=${status}&per_page=100`, { token });
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

export async function updateGroup(
  token: string,
  groupId: number,
  input: { name: string; reportingCurrencyCode: string },
) {
  const response = await apiRequest<DataResponse<Group>>(`/groups/${groupId}`, {
    method: 'PATCH',
    token,
    body: {
      name: input.name,
      reporting_currency_code: input.reportingCurrencyCode,
    },
  });
  return response.data;
}

export async function setGroupArchived(token: string, groupId: number, archived: boolean) {
  const response = await apiRequest<DataResponse<Group>>(`/groups/${groupId}/archive`, {
    method: archived ? 'PUT' : 'DELETE',
    token,
  });
  return response.data;
}

export async function addGroupPlaceholder(token: string, groupId: number, placeholderId: number) {
  const response = await apiRequest<DataResponse<GroupMember>>(`/groups/${groupId}/members`, {
    method: 'POST',
    token,
    body: { placeholder_id: placeholderId },
  });
  return response.data;
}

export async function removeGroupMember(token: string, groupId: number, memberId: number) {
  const response = await apiRequest<DataResponse<GroupMember>>(
    `/groups/${groupId}/members/${memberId}`,
    { method: 'DELETE', token },
  );
  return response.data;
}

export async function transferGroupOwnership(token: string, groupId: number, userId: number) {
  const response = await apiRequest<DataResponse<Group>>(`/groups/${groupId}/owner`, {
    method: 'PUT',
    token,
    body: { user_id: userId },
  });
  return response.data;
}

export function fetchGroupHistoryCsv(token: string, groupId: number) {
  return apiTextRequest(`/groups/${groupId}/export`, token);
}
