import { parseDecimalToInteger } from '@/lib/format';
import type { ExpenseDraftParticipant as ParticipantDraft } from '@/stores/expense-draft-store';
import type { SplitType } from '@/types/api';

export function splitAllocationPreview(
  amountMinor: number | null,
  splitType: SplitType,
  participants: ParticipantDraft[],
  payerKey: string,
  fractionDigits: number,
) {
  if (!amountMinor || amountMinor < 1 || participants.length === 0) return new Map<string, number>();
  if (!participants.some((participant) => participant.key === payerKey)) payerKey = participants[0].key;

  if (splitType === 'equal') {
    const share = Math.floor(amountMinor / participants.length);
    const allocations = new Map(participants.map((participant) => [participant.key, share]));
    allocations.set(payerKey, share + (amountMinor % participants.length));
    return allocations;
  }

  const values = participants.map((participant) => {
    if (splitType === 'exact') return parseDecimalToInteger(participant.value, fractionDigits);
    if (splitType === 'percentage') return parseDecimalToInteger(participant.value, 2);

    const shares = Number(participant.value);
    return Number.isSafeInteger(shares) && shares > 0 ? shares : null;
  });
  if (values.some((value) => value === null)) return new Map<string, number>();

  const integerValues = values as number[];
  if (splitType !== 'exact' && integerValues.some((value) => value <= 0)) {
    return new Map<string, number>();
  }
  const total = integerValues.reduce((sum, value) => sum + value, 0);
  if (!Number.isSafeInteger(total)) return new Map<string, number>();
  if (splitType === 'exact') {
    if (total !== amountMinor) return new Map<string, number>();
    return new Map(participants.map((participant, index) => [participant.key, integerValues[index]]));
  }
  if (total < 1 || (splitType === 'percentage' && total !== 10_000)) {
    return new Map<string, number>();
  }

  let allocated = 0;
  const allocations = new Map(participants.map((participant, index) => {
    const value = Number((BigInt(amountMinor) * BigInt(integerValues[index])) / BigInt(total));
    allocated += value;
    return [participant.key, value] as const;
  }));
  allocations.set(payerKey, (allocations.get(payerKey) ?? 0) + amountMinor - allocated);

  return allocations;
}

export function remainingAllocation(total: number | null, values: string[], digits: number): number | null {
  if (total === null) return null;
  const parsed = values.map((value) => value.trim() === '' ? 0 : parseDecimalToInteger(value, digits));
  if (parsed.some((value) => value === null)) return null;
  const sum = (parsed as number[]).reduce((sum, value) => sum + value, 0);
  return Number.isSafeInteger(sum) ? total - sum : null;
}

export function distributeUnassigned(total: number, values: string[], digits: number): number[] | null {
  const remaining = remainingAllocation(total, values, digits);
  const blanks = values.filter((value) => !value.trim()).length;
  if (remaining === null || remaining < 0 || blanks === 0) return null;
  let extra = remaining % blanks;
  return values.map((value) => value.trim() ? parseDecimalToInteger(value, digits)! : Math.floor(remaining / blanks) + (extra-- > 0 ? 1 : 0));
}
