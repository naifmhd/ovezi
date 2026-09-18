import { apiRequest } from '@/lib/api-client';
import type { PaginatedResponse, Placeholder } from '@/types/api';

type DataResponse<T> = { data: T };

export function fetchPlaceholders(token: string) {
  return apiRequest<PaginatedResponse<Placeholder>>('/placeholders', { token });
}

export async function createPlaceholder(
  token: string,
  input: { name: string; contactType: 'email' | 'phone'; contactValue: string },
) {
  const response = await apiRequest<DataResponse<Placeholder>>('/placeholders', {
    method: 'POST',
    token,
    body: {
      name: input.name,
      contact_type: input.contactType,
      contact_value: input.contactValue,
    },
  });

  return response.data;
}
