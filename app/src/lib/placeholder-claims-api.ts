import { apiRequest } from '@/lib/api-client';
import type { PlaceholderClaim } from '@/types/api';

type DataResponse<T> = { data: T };

export async function fetchPlaceholderClaims(token: string) {
  const response = await apiRequest<DataResponse<PlaceholderClaim[]>>('/placeholder-claims', { token });
  return response.data;
}

export async function claimPlaceholder(token: string, placeholderId: number) {
  const response = await apiRequest<DataResponse<PlaceholderClaim>>(
    `/placeholder-claims/${placeholderId}`,
    { method: 'POST', token },
  );
  return response.data;
}
