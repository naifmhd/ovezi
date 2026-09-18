export type User = {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  default_currency_code: string;
  avatar_url: string | null;
  created_at: string;
};

export type AuthSession = {
  user: User;
  token: string;
};

export type ValidationErrors = Record<string, string[]>;

export type GroupMember = {
  id: number;
  group_id: number;
  role: 'owner' | 'member';
  user?: { id: number; name: string };
  placeholder?: { id: number; name: string };
  joined_at: string;
  left_at: string | null;
};

export type Group = {
  id: number;
  name: string;
  reporting_currency_code: string;
  created_by: number;
  active_member_count?: number;
  is_archived: boolean;
  archived_at: string | null;
  members?: GroupMember[];
  created_at: string;
  updated_at: string;
};

export type BalanceMember = {
  member_id: number;
  participant: {
    key: string;
    user_id: number | null;
    placeholder_id: number | null;
    name: string;
  };
  balance_minor: number;
};

export type GroupBalances = {
  group_id: number;
  currency_code: string;
  members: BalanceMember[];
  suggested_settlements: { from: string; to: string; amount_minor: number }[];
};

export type Activity = {
  id: number;
  group_id: number | null;
  actor: { id: number; name: string } | null;
  subject: { type: string; id: number };
  event: string;
  metadata: Record<string, unknown> | null;
  created_at: string;
};

export type PaginatedResponse<T> = {
  data: T[];
  links: Record<string, string | null>;
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};
