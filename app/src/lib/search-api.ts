import { apiRequest } from '@/lib/api-client';
import type { SearchResults } from '@/types/api';

type DataResponse<T> = { data: T };

export async function searchOvezi(token: string, query: string, signal?: AbortSignal) {
  const response = await apiRequest<DataResponse<SearchResults>>(
    `/search?q=${encodeURIComponent(query)}`,
    { token, signal },
  );
  return response.data;
}
