export type User = {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  default_currency_code: string;
  avatar_url: string | null;
  has_password: boolean;
  connected_providers: ('google' | 'apple')[];
  created_at: string;
};

export type AuthSession = {
  user: User;
  token: string;
};

export type ValidationErrors = Record<string, string[]>;

export type Currency = {
  code: string;
  name: string;
  symbol: string | null;
  minor_unit_factor: number;
};

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

export type Placeholder = {
  id: number;
  name: string;
  contact_type: 'email' | 'phone';
  is_claimed: boolean;
  claimed_at: string | null;
  created_at: string;
};

export type PlaceholderClaim = {
  id: number;
  name: string;
  contact_type: 'email';
  created_by: { id: number; name: string } | null;
  groups: { id: number; name: string | null }[];
  expense_count: number;
  group_count: number;
  is_claimed: boolean;
  claimed_at: string | null;
  created_at: string;
};

export type Friendship = {
  id: number;
  friend: { id: number; name: string; email: string };
  status: 'pending' | 'accepted';
  direction: 'incoming' | 'outgoing';
  accepted_at: string | null;
  created_at: string;
};

export type SearchResults = {
  groups: Group[];
  expenses: Expense[];
  friends: { id: number; name: string; email: string }[];
};

export type GroupInvite = {
  id: number;
  group_id: number;
  invited_by: number;
  invited_email: string | null;
  expires_at: string;
  is_expired: boolean;
  is_revoked: boolean;
  accepted_by: number | null;
  accepted_at: string | null;
  created_at: string;
};

export type GroupCurrencyRate = {
  id: number;
  group_id: number;
  base_currency_code: string;
  quote_currency_code: string;
  rate: string;
  created_by: number;
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

export type ExpenseType = 'group' | 'direct' | 'personal';
export type SplitType = 'equal' | 'exact' | 'percentage' | 'shares';

export type ExpenseSplit = {
  id: number;
  user_id: number | null;
  placeholder_id: number | null;
  claimed_user_id: number | null;
  name: string | null;
  amount_owed_minor: number;
  reporting_amount_owed_minor: number;
  split_type: SplitType;
  split_value: string | null;
};

export type Expense = {
  id: number;
  expense_type: ExpenseType;
  group_id: number | null;
  payer: {
    user_id: number | null;
    placeholder_id: number | null;
    claimed_user_id: number | null;
    name: string | null;
  };
  amount_minor: number;
  currency_code: string;
  reporting_amount_minor: number;
  reporting_currency_code: string;
  exchange_rate: string | null;
  exchange_rate_source: string;
  exchange_rate_effective_date: string | null;
  description: string;
  category: string | null;
  occurred_at: string;
  created_by: number;
  splits: ExpenseSplit[];
  created_at: string;
  updated_at: string;
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
