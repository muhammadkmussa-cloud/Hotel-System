// Hand-written Fetch wrapper around the generated contract (P03.10).
import { findOperation, buildPath, contractVersion } from '../generated/api-client.js';

export class ApiError extends Error {
  constructor({ status, code, message, fieldErrors = [], retryable = false, retryAfter = null, requestId = null }) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.fieldErrors = fieldErrors;
    this.retryable = retryable;
    this.retryAfter = retryAfter;
    this.requestId = requestId;
  }
}

function csrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta && typeof meta.content === 'string' ? meta.content : null;
}

function isMutation(method) {
  return method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS';
}

/**
 * Call a contract operation.
 * @param {string} operationId
 * @param {object} [options]
 */
export async function api(operationId, options = {}) {
  const op = findOperation(operationId);
  const {
    params = {},
    query = {},
    body,
    idempotencyKey,
    ifMatch,
    signal,
    baseUrl = '',
    fetchImpl = globalThis.fetch,
  } = options;

  const url = new URL(baseUrl + buildPath(op.path, params), globalThis.location?.origin ?? 'http://localhost');
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null) url.searchParams.set(key, String(value));
  }

  const headers = { Accept: 'application/json', 'X-Contract-Version': contractVersion };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (isMutation(op.method)) {
    const token = csrfToken();
    if (token) headers['X-CSRF-TOKEN'] = token;
  }
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
  if (ifMatch) headers['If-Match'] = ifMatch;

  let response;
  try {
    response = await fetchImpl(url.toString(), {
      method: op.method,
      headers,
      credentials: 'same-origin',
      body: body === undefined ? undefined : JSON.stringify(body),
      signal,
    });
  } catch (cause) {
    if (cause instanceof DOMException && cause.name === 'AbortError') throw cause;
    throw new ApiError({ status: 0, code: 'network_error', message: 'Request could not be completed.', retryable: true });
  }

  const requestId = response.headers.get('X-Request-Id');
  let payload = null;
  const text = await response.text();
  if (text !== '') {
    try {
      payload = JSON.parse(text);
    } catch {
      throw new ApiError({ status: response.status, code: 'invalid_json', message: 'Response was not valid JSON.', requestId });
    }
  }

  if (response.ok) {
    if (payload === null || typeof payload !== 'object' || !('data' in payload)) {
      throw new ApiError({ status: response.status, code: 'invalid_envelope', message: 'Success envelope missing data.', requestId });
    }
    return { data: payload.data, requestId: payload.requestId ?? requestId, status: response.status };
  }

  const error = payload && typeof payload === 'object' && payload.error && typeof payload.error === 'object' ? payload.error : {};
  const retryAfterHeader = response.headers.get('Retry-After');
  throw new ApiError({
    status: response.status,
    code: typeof error.code === 'string' ? error.code : 'unknown_error',
    message: typeof error.message === 'string' ? error.message : 'Request failed.',
    fieldErrors: Array.isArray(error.fieldErrors) ? error.fieldErrors : [],
    retryable: error.retryable === true,
    retryAfter: Number.isFinite(Number(retryAfterHeader)) && retryAfterHeader !== null && retryAfterHeader.trim() !== '' ? Number(retryAfterHeader) : (typeof error.retryAfter === 'number' ? error.retryAfter : null),
    requestId: payload && typeof payload.requestId === 'string' ? payload.requestId : requestId,
  });
}
