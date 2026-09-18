import { apiRequest } from '@/lib/api-client';
import type { Friendship } from '@/types/api';

type DataResponse<T> = { data: T };

export async function fetchFriends(token: string) {
  const response = await apiRequest<DataResponse<Friendship[]>>('/friends', { token });
  return response.data;
}

export async function requestFriend(token: string, email: string) {
  const response = await apiRequest<DataResponse<Friendship>>('/friends', {
    method: 'POST',
    token,
    body: { email },
  });
  return response.data;
}

export async function acceptFriend(token: string, friendshipId: number) {
  const response = await apiRequest<DataResponse<Friendship>>(`/friends/${friendshipId}/accept`, {
    method: 'POST',
    token,
  });
  return response.data;
}

export function removeFriend(token: string, friendshipId: number) {
  return apiRequest<void>(`/friends/${friendshipId}`, {
    method: 'DELETE',
    token,
  });
}
