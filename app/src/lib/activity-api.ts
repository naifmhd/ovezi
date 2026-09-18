import { apiRequest } from '@/lib/api-client';
import type { Activity, PaginatedResponse } from '@/types/api';

export function fetchActivity(token: string, groupId?: number, perPage = 20) {
  const path = groupId ? `/groups/${groupId}/activity` : '/activity';
  return apiRequest<PaginatedResponse<Activity>>(`${path}?per_page=${perPage}`, { token });
}
