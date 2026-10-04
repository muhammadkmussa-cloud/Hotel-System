// Generated from api/openapi.json by scripts/generate-api-client.mjs. Do not edit by hand.

export const contractVersion = "0.1.0";

export const operations = [
  {
    "operationId": "getLiveness",
    "method": "GET",
    "path": "/health/live",
    "tags": [
      "Health"
    ],
    "summary": "Process liveness",
    "requiresAuth": false,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "getReadiness",
    "method": "GET",
    "path": "/health/ready",
    "tags": [
      "Health"
    ],
    "summary": "Operations readiness",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  }
];

/**
 * Look up a contract operation by id.
 * @param {string} operationId
 */
export function findOperation(operationId) {
  const op = operations.find((candidate) => candidate.operationId === operationId);
  if (!op) throw new Error(`Unknown operation ${operationId}`);
  return op;
}

/**
 * Expand `{param}` placeholders in an OpenAPI path.
 * @param {string} path
 * @param {Record<string, string | number>} [params]
 */
export function buildPath(path, params = {}) {
  return path.replace(/\{([^}]+)\}/g, (match, name) => {
    if (!(name in params)) throw new Error(`Missing path parameter ${name}`);
    return encodeURIComponent(String(params[name]));
  });
}
