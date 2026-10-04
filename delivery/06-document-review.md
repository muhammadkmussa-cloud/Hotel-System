# Documentation review record

Review date: 4 October 2026. Scope: the Markdown project and its consistency with the conversation. This review does not certify implemented software, live payment processing, hardware compatibility, or regulatory compliance.

## Conversation review

Checked the latest explicit decisions rather than treating the entire conversation as a flat requirement list. Preserved the one-hotel installation correction, independent guest submissions, cash reinstatement, external card terminal/cashier confirmation, additional orders, reusable ingredient library, customer photos/circles, receipts, and Desktop folder delivery. Marked research recommendations as defaults, with unresolved integration and hotel policies separately recorded.

## Cross-document corrections made during review

- Added an explicit kiosk session and kiosk-owned bill to avoid a guest-only data model.
- Distinguished proposed charges from posted sales so abandoned unpaid kiosk orders cannot inflate revenue.
- Defined late paid/unfulfillable kiosk money as an unapplied exception, rather than automatically creating a sale or preparation work.
- Added API operations for kiosk reset, staff guest reopening, recipe approval, and approved external refund completion.
- Made recipe approvals version-specific and invalidated by relevant ingredient changes.
- Preserved provider amount-precision uncertainty without silently rounding shared bills.
- Kept cash custody separate from payment settlement and fiscal state separate from both.
- Distinguished source-file blueprints from actual application files.

## Original version 1.0 documentation checks

Historical version 1.0 validation on 4 October 2026 (counts below predate version 1.1):

| Check | Result |
|---|---|
| Markdown files | 44 non-empty documents |
| Local document links | 95 checked; no missing targets |
| Fenced JSON examples | 10 parsed successfully |
| Code fences and UTF-8 | Balanced/readable; no replacement characters found |
| Requirements mapped into backlog | All 22 requirement IDs present |
| Confirmed decision sequence | C01–C18 present |
| Unfinished TODO/TBD/FIXME markers | None found |
| Main palette contrast | Five text/button pairs tested; all exceed 4.5:1 |

Contrast ratios: primary text on canvas 14.62:1; secondary 6.53:1; white on action green 10.15:1; warning 6.08:1; danger 6.56:1. Decorative accent and all future component combinations still need rendered UI review. This is not a whole-interface accessibility certification.

These checks reduce drafting errors but do not prove an error-free implementation. Checks of file counts and links are repeated after the Desktop copy; application tests remain unrun.

## Deliberate remaining limitations

No application code, live UI, production photographs, OpenAPI executable schema, database migrations, provider accounts, hardware purchases, or deployments are included. PHP, MySQL, and supporting dependency versions will be pinned at implementation; merchant capabilities, tax configuration, exact printer compatibility, translations, retention, and operational targets require validation at the listed gates. All future software/hardware acceptance remains untested.

## Version 1.1 release notes — 4 October 2026

- Recorded confirmed PHP, HTML/CSS/JavaScript, MySQL, documentation-only scope, and the final DirectAdmin hosting correction as C19–C23.
- Added the technology fact file and DirectAdmin account layout, linked from README, the index, and navigation guide.
- Retired D14's previous framework/database choices and D05's on-site hub. Updated frontend/backend blueprints to planned PHP/HTML/CSS/JavaScript paths and MySQL-compatible constraints.
- Specified private application folders outside both public_html and private_html, native JavaScript modules, hosted callbacks, short polling, bounded cron batches, and a hotel-side print bridge.
- Aligned R19, API event/bridge contracts, test scenarios, quality targets, installation/recovery procedures, integration notes, and host decision gate O13 with internet-dependent hosting.
- Kept PHP libraries, framework-free structure, hosting tier, tool versions, providers, hardware, and achievable recovery targets visibly proposed or awaiting validation. No implementation, deployment, package installation, or external account change was performed.

## Version 1.1 documentation validation

Checks completed on 4 October 2026 after the version 1.1 changes:

| Check | Result |
|---|---|
| Markdown documents | 46 non-empty, readable files |
| Local document links | 117 checked; no missing targets |
| Fenced JSON examples | 10 parsed successfully |
| Code fences and text encoding | Balanced; no replacement characters |
| Requirement/backlog traceability | R01–R22 retained |
| Confirmed decisions | C01–C23 present |
| Changed/added files | 33; all Markdown only |
| Diff whitespace check | Passed |

Reviewed active stack, deployment, API, recovery, and printing descriptions for contradictions; old stack/hosting names remain only where explicitly retired or contrasted for implementation compatibility. Application tests remain unrun because no application exists.
