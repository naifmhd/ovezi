import type { ValidationErrors } from '@/types/api';

const fallbackApiUrl = 'http://127.0.0.1:8000/api/v1';

export const apiBaseUrl = (process.env.EXPO_PUBLIC_API_URL ?? fallbackApiUrl).replace(/\/$/, '');

type ApiErrorBody = {
  message?: string;
  errors?: ValidationErrors;
};

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly errors: ValidationErrors = {},
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

type ApiRequestOptions = Omit<RequestInit, 'body'> & {
  body?: unknown;
  token?: string | null;
};

async function throwApiError(response: Response): Promise<never> {
  let errorBody: ApiErrorBody = {};

  try {
    errorBody = (await response.json()) as ApiErrorBody;
  } catch {
    // Some server and proxy errors do not include JSON.
  }

  throw new ApiError(
    errorBody.message ?? 'Something went wrong. Please try again.',
    response.status,
    errorBody.errors,
  );
}

export async function apiRequest<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const { body, headers, token, ...requestOptions } = options;
  const response = await fetch(path.startsWith('http') ? path : `${apiBaseUrl}${path}`, {
    ...requestOptions,
    headers: {
      Accept: 'application/json',
      ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  if (!response.ok) {
    return throwApiError(response);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}

export async function apiMultipartRequest<T>(
  path: string,
  token: string,
  body: FormData,
  method = 'POST',
): Promise<T> {
  const response = await fetch(path.startsWith('http') ? path : `${apiBaseUrl}${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body,
  });

  if (!response.ok) {
    return throwApiError(response);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}

export async function apiTextRequest(
  path: string,
  token: string,
  accept = 'text/plain',
): Promise<string> {
  const response = await fetch(path.startsWith('http') ? path : `${apiBaseUrl}${path}`, {
    headers: {
      Accept: accept,
      Authorization: `Bearer ${token}`,
    },
  });

  if (!response.ok) {
    return throwApiError(response);
  }

  return response.text();
}

export function errorMessage(error: unknown) {
  if (error instanceof ApiError) {
    return Object.values(error.errors).flat()[0] ?? error.message;
  }

  if (error instanceof TypeError || (error instanceof Error && /network|fetch|offline/i.test(error.message))) {
    return 'Ovezi can’t reach the server. Check your connection and try again.';
  }

  return error instanceof Error ? error.message : 'Something went wrong. Please try again.';
}
