import { apiRequest } from '@/lib/api-client';
import type { GroupCurrencyRate } from '@/types/api';

type DataResponse<T> = { data: T };

export function fetchGroupRates(token: string, groupId: number) {
  return apiRequest<DataResponse<GroupCurrencyRate[]>>(`/groups/${groupId}/currency-rates`, {
    token,
  });
}

export async function saveGroupRate(
  token: string,
  groupId: number,
  baseCurrencyCode: string,
  rate: string,
) {
  const response = await apiRequest<DataResponse<GroupCurrencyRate>>(
    `/groups/${groupId}/currency-rates/${baseCurrencyCode}`,
    { method: 'PUT', token, body: { rate } },
  );
  return response.data;
}

export function deleteGroupRate(token: string, groupId: number, baseCurrencyCode: string) {
  return apiRequest<void>(`/groups/${groupId}/currency-rates/${baseCurrencyCode}`, {
    method: 'DELETE',
    token,
  });
}
