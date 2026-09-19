import { apiRequest } from '@/lib/api-client';
import type { OverallBalances } from '@/types/api';

type DataResponse<T> = { data: T };

export async function fetchOverallBalances(token: string) {
  const response = await apiRequest<DataResponse<OverallBalances>>('/balances', { token });
  return response.data;
}
