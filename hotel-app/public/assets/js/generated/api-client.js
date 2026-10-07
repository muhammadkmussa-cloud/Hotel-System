// Generated from api/openapi.json by scripts/generate-api-client.mjs. Do not edit by hand.

export const contractVersion = "1.0.0";

export const operations = [
  {
    "operationId": "getCollectionDisplay",
    "method": "GET",
    "path": "/collection",
    "tags": [
      "Collection"
    ],
    "summary": "Get redacted collection projection",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "pollEvents",
    "method": "GET",
    "path": "/events",
    "tags": [
      "Events"
    ],
    "summary": "Poll authorized change events",
    "requiresAuth": false,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
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
  },
  {
    "operationId": "cancelKioskOrder",
    "method": "POST",
    "path": "/kiosk/cancel",
    "tags": [
      "Kiosk"
    ],
    "summary": "Cancel the current unpaid kiosk order",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "getKioskCart",
    "method": "GET",
    "path": "/kiosk/cart",
    "tags": [
      "Kiosk"
    ],
    "summary": "Get authoritative kiosk cart quote",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "acceptKioskCartChanges",
    "method": "POST",
    "path": "/kiosk/cart/accept-changes",
    "tags": [
      "Kiosk"
    ],
    "summary": "Accept current kiosk quote changes",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "addKioskCartLine",
    "method": "POST",
    "path": "/kiosk/cart/lines",
    "tags": [
      "Kiosk"
    ],
    "summary": "Add a kiosk cart line",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "removeKioskCartLine",
    "method": "DELETE",
    "path": "/kiosk/cart/lines/{lineId}",
    "tags": [
      "Kiosk"
    ],
    "summary": "Remove a kiosk cart line",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "updateKioskCartLine",
    "method": "PATCH",
    "path": "/kiosk/cart/lines/{lineId}",
    "tags": [
      "Kiosk"
    ],
    "summary": "Update a kiosk cart line",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "getKioskMeal",
    "method": "GET",
    "path": "/kiosk/meals/{mealId}",
    "tags": [
      "Kiosk"
    ],
    "summary": "Get one published meal",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "getKioskMenu",
    "method": "GET",
    "path": "/kiosk/menu",
    "tags": [
      "Kiosk"
    ],
    "summary": "Get published kiosk menu",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "createKioskMpesaAttempt",
    "method": "POST",
    "path": "/kiosk/mpesa-attempts",
    "tags": [
      "Kiosk"
    ],
    "summary": "Start kiosk M-PESA",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "201"
    ]
  },
  {
    "operationId": "submitKioskOrder",
    "method": "POST",
    "path": "/kiosk/orders",
    "tags": [
      "Kiosk"
    ],
    "summary": "Submit kiosk order for review/payment",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "200",
      "201"
    ]
  },
  {
    "operationId": "selectKioskCashierPayment",
    "method": "POST",
    "path": "/kiosk/pay-at-cashier",
    "tags": [
      "Kiosk"
    ],
    "summary": "Select cashier payment",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "startKioskSession",
    "method": "POST",
    "path": "/kiosk/sessions",
    "tags": [
      "Kiosk"
    ],
    "summary": "Start a fresh kiosk order",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "201"
    ]
  },
  {
    "operationId": "getKioskStatus",
    "method": "GET",
    "path": "/kiosk/status",
    "tags": [
      "Kiosk"
    ],
    "summary": "Get current kiosk order status",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "listKitchenTasks",
    "method": "GET",
    "path": "/kitchen/tasks",
    "tags": [
      "Kitchen"
    ],
    "summary": "List authorized kitchen tasks",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "transitionKitchenTicket",
    "method": "POST",
    "path": "/kitchen/tickets/{ticketId}/transitions",
    "tags": [
      "Kitchen"
    ],
    "summary": "Transition a kitchen ticket",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "getTableBill",
    "method": "GET",
    "path": "/table/bill",
    "tags": [
      "Table"
    ],
    "summary": "Get this guest’s bill",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "getTableCart",
    "method": "GET",
    "path": "/table/cart",
    "tags": [
      "Table"
    ],
    "summary": "Get authoritative cart quote",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "acceptTableCartChanges",
    "method": "POST",
    "path": "/table/cart/accept-changes",
    "tags": [
      "Table"
    ],
    "summary": "Accept current quote changes",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "addTableCartLine",
    "method": "POST",
    "path": "/table/cart/lines",
    "tags": [
      "Table"
    ],
    "summary": "Add a cart line",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "removeTableCartLine",
    "method": "DELETE",
    "path": "/table/cart/lines/{lineId}",
    "tags": [
      "Table"
    ],
    "summary": "Remove a cart line",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "updateTableCartLine",
    "method": "PATCH",
    "path": "/table/cart/lines/{lineId}",
    "tags": [
      "Table"
    ],
    "summary": "Update a cart line",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "createTableCheckout",
    "method": "POST",
    "path": "/table/checkouts",
    "tags": [
      "Table"
    ],
    "summary": "Start this guest’s checkout",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "cancelTableCheckout",
    "method": "POST",
    "path": "/table/checkouts/{checkoutId}/cancel",
    "tags": [
      "Table"
    ],
    "summary": "Cancel this guest’s checkout",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "createTableMpesaAttempt",
    "method": "POST",
    "path": "/table/checkouts/{checkoutId}/mpesa-attempts",
    "tags": [
      "Table"
    ],
    "summary": "Start M-PESA for this checkout",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "201"
    ]
  },
  {
    "operationId": "getTableContext",
    "method": "GET",
    "path": "/table/context",
    "tags": [
      "Table"
    ],
    "summary": "Get bound table and guest context",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "getTableMeal",
    "method": "GET",
    "path": "/table/meals/{mealId}",
    "tags": [
      "Table"
    ],
    "summary": "Get one published meal",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "getTableMenu",
    "method": "GET",
    "path": "/table/menu",
    "tags": [
      "Table"
    ],
    "summary": "Get published menu",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "listTableOrders",
    "method": "GET",
    "path": "/table/orders",
    "tags": [
      "Table"
    ],
    "summary": "List this guest’s orders",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "submitTableOrder",
    "method": "POST",
    "path": "/table/orders",
    "tags": [
      "Table"
    ],
    "summary": "Submit an independent guest order",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "200",
      "201"
    ]
  },
  {
    "operationId": "getTablePaymentAttempt",
    "method": "GET",
    "path": "/table/payment-attempts/{attemptId}",
    "tags": [
      "Table"
    ],
    "summary": "Refresh this guest’s payment attempt",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "getTableReceipt",
    "method": "GET",
    "path": "/table/receipts/{checkoutId}",
    "tags": [
      "Table"
    ],
    "summary": "Get this guest’s receipt",
    "requiresAuth": true,
    "hasJsonBody": false,
    "successCodes": [
      "200"
    ]
  },
  {
    "operationId": "createTableServiceRequest",
    "method": "POST",
    "path": "/table/service-requests",
    "tags": [
      "Table"
    ],
    "summary": "Request waiter assistance",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "201"
    ]
  },
  {
    "operationId": "createTableShareProposal",
    "method": "POST",
    "path": "/table/share-proposals",
    "tags": [
      "Table"
    ],
    "summary": "Propose sharing a charge",
    "requiresAuth": true,
    "hasJsonBody": true,
    "successCodes": [
      "201"
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
