# Security, privacy, and audit design

Product design baseline, not a legal certification. Production privacy/retention configuration must be reviewed for the hotel before launch.

## Main threats and controls

| Threat | Required control |
|---|---|
| Customer switches to another guest/table | Server-owned binding, object authorization, revocation |
| Fake 'payment successful' browser request | No such guest operation; server/provider evidence only |
| Replayed callbacks or repeated taps | Unique references, idempotent ledger application, durable inbox |
| Waiter hides or misclassifies collections | Individual identity, append-only ledger, custody tracking, cashier acknowledgment |
| Menu editor uploads executable content | Raster decode/re-encode, size limits, isolated storage |
| Credentials in frontend/logs/backups | Server-only secrets, redaction, encryption and restricted access |
| Public display exposes private information | Redacted dedicated collection projection |
| Lost tablet retains customer details | Managed device lock, scoped session expiry, remote revocation, local clearing |
| Local-network attacker | HTTPS, trusted device enrolment, network separation, limited service ports |
| Fraudulent printer/network target | Allowlisted printer addresses; no customer-controlled URLs |

## Information collection

Guest number is sufficient for table ordering; name is optional there. Kiosk needs a collection name; phone is collected only for the chosen payment route. Do not require account signup, date of birth, identity documents, or marketing consent to order. Do not use payment phone numbers for marketing by default.

Allergy notes are sensitive operational information. Restrict to relevant preparation and service staff, minimise free text, avoid general analytics logs, and apply an approved retention policy. Names/phones must not appear on the collection screen; an optional first name requires a deliberate hotel policy and must avoid disclosing payment details.

## Secrets and integrations

Keep separate test/production credentials outside source and browser bundles. Use private DirectAdmin-account configuration outside all document roots, host-compatible access restrictions, least privilege, rotation, and restore procedures. Never store PAN, CVV, PIN, or magnetic-stripe data; card terminal remains external. Verify webhook mechanisms per provider and validate amounts/references, not just message text.

Public callback handlers expose only provider intake routes and cannot bypass staff or print-bridge authorization. Restrict outbound provider hosts. Use separate installation-specific print-bridge credentials and replay protection. Never store secrets in DirectAdmin private_html, which may serve HTTPS content. Backups containing secrets require separate protection and controlled restoration.

## Sessions and requests

Secure HttpOnly same-site cookies, CSRF protection, bounded login attempts, hardened password hashing, server-side input schemas, output escaping, content security policy, and safe file handling. Rate-limit payment prompts to prevent repeated requests to a customer's phone. Staff role changes revoke existing privileges immediately.

## Audit policy

Capture login/security events, visit assignment, menu publication, price/removal changes, orders, bill allocations, payment confirmation source, refunds, voids, cash handovers, exports, fiscal retries, and configuration changes. Each includes actor, device, UTC time, target, request ID, and reason when required.

Do not put full secrets or unnecessary health/phone details in the audit stream. Access to detailed evidence is separately logged. Posted financial records are corrected with new entries; routine users cannot delete history. Retention periods, access/export requests, and financial-record obligations must be configured with the hotel's advisers; no arbitrary number of years is asserted here.
