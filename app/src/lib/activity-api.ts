import { apiRequest } from '@/lib/api-client';
import type { Activity, PaginatedResponse } from '@/types/api';

export type ActivitySource = 'all' | 'groups' | 'lending' | 'personal';

export function fetchActivity(token: string, groupId?: number, perPage = 20, page = 1, source: ActivitySource = 'all', signal?: AbortSignal, filters: { q?: string; from?: string; to?: string } = {}) {
  const path = groupId ? `/groups/${groupId}/activity` : '/activity';
  const params = new URLSearchParams({ per_page: String(perPage), page: String(page), source });
  if (filters.q) params.set('q', filters.q);
  if (filters.from) params.set('after', activityDateBoundary(filters.from));
  if (filters.to) params.set('before', activityDateBoundary(filters.to, true));
  return apiRequest<PaginatedResponse<Activity>>(`${path}?${params}`, { token, signal });
}

// Convert calendar days in the viewer's timezone, including DST, to UTC bounds.
export function activityDateBoundary(day: string, nextDay = false) {
  const [year, month, date] = day.split('-').map(Number);
  return new Date(year, month - 1, date + (nextDay ? 1 : 0)).toISOString();
}
