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

const REQUEST_TIMEOUT_MS = 25000;

async function request<T>(url: string, options: RequestInit, read: (response: Response) => Promise<T>): Promise<T> {
  const resolved = url.startsWith('http') ? url : `${apiBaseUrl}${url}`;
  if (new URL(resolved).origin !== new URL(apiBaseUrl).origin) {
    throw new Error('This link does not belong to Ovezi.');
  }
  const controller = new AbortController();
  const upstream = options.signal;
  const cancel = () => controller.abort();
  if (upstream?.aborted) cancel();
  upstream?.addEventListener('abort', cancel, { once: true });
  let timedOut = false;
  const timer = setTimeout(() => { timedOut = true; controller.abort(); }, REQUEST_TIMEOUT_MS);
  try {
    const response = await fetch(resolved, { ...options, signal: controller.signal });
    if (!response.ok) return await throwApiError(response);
    if (response.status === 204) return undefined as T;
    return await read(response);
  } catch (error) {
    if (timedOut) throw new Error(options.headers && new Headers(options.headers).has('Idempotency-Key')
      ? 'The connection took too long. Your details are still here. Retry the same save to safely check its result.'
      : 'The connection took too long. Check your connection and try again.');
    throw error;
  } finally {
    clearTimeout(timer);
    upstream?.removeEventListener('abort', cancel);
  }
}

export async function apiRequest<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const { body, headers, token, ...requestOptions } = options;
  return request(path, {
    ...requestOptions,
    headers: { Accept: 'application/json', ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}), ...headers },
    body: body === undefined ? undefined : JSON.stringify(body),
  }, (response) => response.json() as Promise<T>);
}

export async function apiMultipartRequest<T>(path: string, token: string, body: FormData, method = 'POST'): Promise<T> {
  return request(path, { method, headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }, body },
    (response) => response.json() as Promise<T>);
}

export async function apiTextRequest(path: string, token: string, accept = 'text/plain'): Promise<string> {
  return request(path, { headers: { Accept: accept, Authorization: `Bearer ${token}` } }, (response) => response.text());
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
