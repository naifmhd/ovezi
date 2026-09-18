import { apiRequest } from '@/lib/api-client';
import type { Currency } from '@/types/api';

type DataResponse<T> = { data: T };

export async function fetchCurrencies(token: string) {
  const response = await apiRequest<DataResponse<Currency[]>>('/currencies', { token });
  return response.data;
}
