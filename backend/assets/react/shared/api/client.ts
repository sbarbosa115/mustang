import createClient, {type Middleware} from 'openapi-fetch';
import type {paths} from './schema';

/** A refusal from the API: `code` is the stable error code the UI translates. */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly code: string,
    message: string,
    readonly violations: Array<{
      field: string;
      code: string;
      message: string;
    }> = [],
    readonly detail: Record<string, unknown> = {},
  ) {
    super(message);
    this.name = 'ApiError';
  }

  /** The violation message for one field, for showing it next to that field. */
  fieldError(field: string): string | undefined {
    return this.violations.find((v) => v.field === field)?.message;
  }
}

export const UNAUTHENTICATED_EVENT = 'mustang:unauthenticated';

const onUnauthenticated: Middleware = {
  onResponse({response, request}) {
    if (response.status === 401 && !request.url.includes('/auth/login')) {
      window.dispatchEvent(new Event(UNAUTHENTICATED_EVENT));
    }
    return response;
  },
};

/** The typed API client. Same-origin cookie session; every path and body is typed from openapi.json. */
export const api = createClient<paths>({
  baseUrl: typeof window === 'undefined' ? 'http://localhost' : '',
  credentials: 'same-origin',
  headers: {Accept: 'application/json'},
});
api.use(onUnauthenticated);

interface ErrorBody {
  error?: string;
  message?: string;
  violations?: Array<{field: string; code: string; message: string}>;
  detail?: Record<string, unknown>;
}

/**
 * Returns the data of an openapi-fetch result, or throws an ApiError carrying the API's error code.
 * Use it in every entity API function so features handle one error type.
 */
export function unwrap<T>(result: {
  data?: T;
  error?: unknown;
  response: Response;
}): T {
  if (result.response.ok) {
    return result.data as T;
  }
  const body = (result.error ?? {}) as ErrorBody;
  throw new ApiError(
    result.response.status,
    body.error ?? `http_${result.response.status}`,
    body.message ?? result.response.statusText,
    body.violations ?? [],
    body.detail ?? {},
  );
}
