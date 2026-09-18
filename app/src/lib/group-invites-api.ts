import { apiRequest } from '@/lib/api-client';
import type { GroupInvite, GroupMember, PaginatedResponse } from '@/types/api';

type DataResponse<T> = { data: T };

export function fetchGroupInvites(token: string, groupId: number) {
  return apiRequest<PaginatedResponse<GroupInvite>>(`/groups/${groupId}/invites`, { token });
}

export function createGroupInvite(token: string, groupId: number, invitedEmail?: string) {
  return apiRequest<DataResponse<GroupInvite> & { meta: { token: string } }>(
    `/groups/${groupId}/invites`,
    {
      method: 'POST',
      token,
      body: invitedEmail ? { invited_email: invitedEmail } : {},
    },
  );
}

export async function revokeGroupInvite(token: string, groupId: number, inviteId: number) {
  const response = await apiRequest<DataResponse<GroupInvite>>(
    `/groups/${groupId}/invites/${inviteId}`,
    { method: 'DELETE', token },
  );
  return response.data;
}

export async function acceptGroupInvite(token: string, inviteToken: string) {
  const response = await apiRequest<DataResponse<GroupMember>>('/group-invites/accept', {
    method: 'POST',
    token,
    body: { token: inviteToken },
  });
  return response.data;
}
