# Execution evidence log

5 October 2026. This log separates **recorded as complete** from **executed and observed**. A checked box in the [build plan](01-build-plan.md) means the step was built and recorded; it does not by itself mean a test suite ran. Every row below names what actually ran in the environment that recorded the step, and what still has to be re-run.

Scope of this first version: the P07 records made on 5 October 2026 (P07.05–P07.10 plus two repair entries). Earlier phases keep their evidence in [delivery/06-document-review.md](06-document-review.md) and [delivery/07-database-foundation-verification.md](07-database-foundation-verification.md).

## How to read a row

| Column | Meaning |
|---|---|
| Recorded complete | The step checkbox is checked in [delivery/01-build-plan.md](01-build-plan.md). |
| Independent reviewer | Whether a separate reviewer agent examined the diff and evidence, as the [mandatory reviewer gate](01-build-plan.md#mandatory-reviewer-agent-gate) requires. |
| Executed in the recording environment | Commands that were **actually run**, with the observed result. |
| Not executed | Checks written and committed but never run. These are debts, not approvals. |

## Environment that recorded P07.05–P07.10

| Capability | Status in the recording environment | Consequence |
|---|---|---|
| PHP runtime and Composer | Absent; all Composer mirrors network-blocked | PHPUnit (foundation and MySQL suites) and `php tests/http-smoke.php` cannot run |
| MySQL server, client libraries, Docker | Absent | `tests/Database/*Test.php` cannot run; no real deadlock or migration evidence |
| Chromium download (`cdn.playwright.dev`) | Blocked; `package.json` engines also require Node >= 24 (22.22.3 present) | `npm run test:browser` cannot run |
| Node 22.22.3 | Present | `node --test tests/js/*.test.mjs` and `node --check` run |
| Python 3.11 | Present | Contract validator and its unit tests run |
| php-parser (Node, out-of-tree) | Present | PHP syntax checks run; they prove parseability only, never behaviour |

Executed every time a P07 step was recorded on 5 October 2026, with the same results:

| Check | Result |
|---|---|
| `node --test tests/js/*.test.mjs` | 14 tests, 14 pass, 0 fail |
| `python3 api/validate_contract.py` | 16 examples pass |
| `python3 -m unittest discover -s api` | 13 tests, OK |
| Local Markdown link check | 247 links, 0 missing |
| PHP syntax check (php-parser) of every changed PHP file | 12 files parse (8 for the P07.05 commit) |
| `node --check` on the Playwright spec | Passes |
| Blade open/close tag balance | staff-tables 16/16 pairs, staff-visit 31/31 pairs |
| Checkbox and phase recount against the build plan | 80 / 320 steps; P07 10/10 |

None of these execute application PHP, touch MySQL, or open a browser.

## P07 rows

| Step | Recorded complete | Independent reviewer | Executed in the recording environment | Not executed (must re-run) | Test files |
|---|---|---|---|---|---|
| P07.01 enrolled devices and sessions | Yes | code_review approved | Foundation76/581; browser80/80; test:js14/14; http-smoke16; real-MySQL `StaffAdminTest` digest section | None outstanding | `tests/Database/StaffAdminTest.php` (devices/sessions store digests) |
| P07.02 pairing and activation | Yes | code_review approved after fixes | As P07.01 battery | None outstanding | `tests/Database/StaffAdminTest.php`, `tests/browser/device-pair.spec.js` |
| P07.03 activation and admin device screens | Yes | code_review approved after fixes | As P07.01 battery | None outstanding | `tests/Database/StaffAdminTest.php` (device list/revoke), `tests/browser/device-pair.spec.js` |
| P07.04 visits with active-table uniqueness | Yes | code_review approved after fixes | Foundation76/581; browser80/80; test:js14/14; http-smoke16; `VisitServiceTest` on real MySQL | None outstanding | `tests/Database/VisitServiceTest.php` |
| P07.04 defect repairs (5 Oct) | Yes | **None — implementer self-review** | Syntax check only | `VisitServiceTest` (rewritten against the shipped contract) and one signed-in staff flow | `tests/Database/VisitServiceTest.php`, `tests/Fixtures/visit-service-probe.php` |
| P07.05 guest creation | Yes | **None — implementer self-review** | Syntax check; `node --check` | `GuestServiceTest` and `tests/browser/visit-guests.spec.js` | `tests/Database/GuestServiceTest.php`, `tests/browser/visit-guests.spec.js` |
| P07.06 device-to-guest binding | Yes | **None — implementer self-review** | Syntax check; `node --check` | `GuestBindingTest` and the browser spec | `tests/Database/GuestBindingTest.php`, `tests/browser/visit-guests.spec.js` |
| P07.07 waiter table overview | Yes | **None — implementer self-review** | Syntax check; Blade balance | `VisitOverviewTest` and the browser spec | `tests/Database/VisitOverviewTest.php` |
| P07.08 visit detail and tablet assignment | Yes | **None — implementer self-review** | Syntax check; Blade balance | `VisitOverviewTest` (four guests, four tablets) and the browser spec | `tests/Database/VisitOverviewTest.php`, `tests/browser/visit-guests.spec.js` |
| P07.09 locked table/waiter transfers | Yes | **None — implementer self-review** | Syntax check | `VisitTransferTest` and the browser spec | `tests/Database/VisitTransferTest.php` |
| P07.10 replace-device flows | Yes | **None — implementer self-review** | Syntax check; `node --check` | `GuestBindingTest::testReplacingATabletEndsTheOldSessionAndKeepsTheGuest` and the browser spec | `tests/Database/GuestBindingTest.php`, `tests/browser/visit-guests.spec.js` |
| Actor-id repair across P07.04–P07.10 (5 Oct) | Yes | **None — implementer self-review** | Syntax check; grep against every controller | One signed-in staff flow through `/staff/tables` and `/staff/visits/{id}`; the whole P07 battery | No new test — it needs a signed-in HTTP flow |

## What each outstanding check proves

| Check | What it verifies for P07 |
|---|---|
| `php vendor/bin/phpunit --testdox` | Foundation suite still green after the new services, routes and views |
| MySQL suite: `VisitServiceTest` | Active-table uniqueness, open/close, version bump, the repaired probe contract |
| MySQL suite: `GuestServiceTest` | Server-assigned guest numbers, rollback, closed-visit refusal, device-independent identity |
| MySQL suite: `GuestBindingTest` | Staff-authorized bindings, spoofed identity ignored, one live binding per session, tablet replacement |
| MySQL suite: `VisitOverviewTest` | Board ordering, live guest counts, the placeholder-safe field set, inactive tables, freed tables |
| MySQL suite: `VisitTransferTest` | Waiter/table moves, stale versions, occupied destinations, two-manager barrier, audit allowlist |
| `npm run test:browser` | Anonymous denial for the tables board, visit detail, guest add, binding, transfer and replace routes |
| `php tests/http-smoke.php` | No regression in the HTTP layer the visit screens sit on |
| Signed-in staff flow (manual or scripted) | The actor-id repair: a waiter can actually open a visit, add a guest, bind a tablet and close the visit |

## Exact re-run battery

Two notes on the sandbox that produced the 5 October 2026 records, so the numbers can be reproduced:

- The contract unit tests import `jsonschema`, which is not a system package here: `pip install --break-system-packages jsonschema` (PEP 668 blocks a plain `pip install`). Packages installed outside the workspace are not persisted between sessions, so this is re-installed before each run.
- The PHP syntax check uses a Node `php-parser` kept outside the repository. It parses; it does not execute, type-check or lint for correctness.

From the repository root, in an environment with PHP 8.3, Composer, a disposable MySQL database and Chromium:

```bash
cd hotel-app
composer install --no-interaction --no-scripts --no-plugins --prefer-dist

# 1. Foundation suite
php vendor/bin/phpunit --testdox

# 2. Real-MySQL suites (disposable schema; every DB_* value must be explicit)
export HOTEL_TEST_DB_ALLOW_SCHEMA=1
export HOTEL_TEST_DB_HOST=127.0.0.1 HOTEL_TEST_DB_PORT=3306
export HOTEL_TEST_DB_DATABASE=hotel_test HOTEL_TEST_DB_USERNAME=hotel_test HOTEL_TEST_DB_PASSWORD='…'
php vendor/bin/phpunit -c phpunit.mysql.xml.dist --testdox

# 3. Browser suite (Playwright starts its own server from tests/browser/server.mjs)
npm ci --ignore-scripts
npx playwright install chromium --no-shell
npm run test:browser

# 4. HTTP smoke
php tests/http-smoke.php
```

Then record the results here, replace the affected "not executed" cells with command, result and date, and repeat the independent step review for every row marked "implementer self-review".

## Reviewer-gate reconciliation

The gate in [delivery/01-build-plan.md](01-build-plan.md) requires an independent reviewer for every implemented step, and states that the implementing agent cannot approve its own work. For P07 the position is:

| Range | Position under the gate |
|---|---|
| P07.01–P07.04 | Approved by an independent `code_review` agent, with executed evidence (see [delivery/06-document-review.md](06-document-review.md)) |
| P07.04 repairs, P07.05–P07.10, actor-id repair | **Blocked: evidence missing.** Recorded complete by the implementing agent; no independent reviewer ran and no suite executed. The checkboxes are ticked only because the user relaxed the gate for this session and asked that every unexecuted check be named — the relaxation does not convert self-review into approval |
| Cumulative P07 | Not started. It cannot be approved until the battery above runs green and each step above has an independent reviewer outcome |

Until that evidence exists, treat the 80 / 320 count as "80 steps built and recorded", not "80 steps verified". The README phase checkbox for P07 stays unchecked for the same reason.


| P08.02 authorized upload parsing / file-size limits | Yes | **None — implementer self-review** | JS 14/14; contract 16/13; 262 Markdown links 0 broken; `node --check` all JS; manual code read of middleware/limits/error/config; BoundMediaUploadTest and MediaUploadLimitsTest written (plain Symfony/PHPUnit, no Laravel bootstrap so they cannot be executed without PHP). | `php vendor/bin/phpunit --testdox` (MediaUploadLimitsTest, BoundMediaUploadTest, plus the prior MediaMetadataValidationTest); MySQL suites unchanged; `npm run test:browser`; `php tests/http-smoke.php` | `config/media.php`, `.env.example`, `app/Http/Middleware/BoundMediaUpload.php`, `app/Http/Requests/InvalidUpload.php`, `app/Support/MediaUploadLimits.php`, `bootstrap/app.php` (alias+singleton), `tests/Feature/MediaUploadLimitsTest.php`, `tests/Feature/BoundMediaUploadTest.php` |


| P08.03 raster signature validation / decode limits | Yes | **None — implementer self-review** | JS 14/14; contract 16/13; 262 Markdown links 0 broken; `node --check` all JS; manual code review of validator/limits/value-object/exception; RasterLimitsTest + RasterValidatorTest written with pure-PHP minimal JPEG/PNG/WebP fixtures (no GD extension required for tests). | `php vendor/bin/phpunit --testdox` (RasterLimitsTest, RasterValidatorTest plus prior P08.01–P08.02 tests); MySQL suites unchanged; `npm run test:browser`; `php tests/http-smoke.php` (once upload route exists in P08.08) | `config/media.php` (raster block), `.env.example` (MEDIA_MAX_*), `app/Support/RasterInfo.php`, `app/Support/MediaException.php`, `app/Support/RasterLimits.php`, `app/Support/RasterValidator.php`, `bootstrap/app.php` (RasterLimits+RasterValidator singletons), `tests/Feature/RasterLimitsTest.php`, `tests/Feature/RasterValidatorTest.php` |


| P08.04 quarantined private original storage / direct-access refusal | Yes | **None — implementer self-review** | JS 14/14; contract 16/13; 262 Markdown links 0 broken; `node --check` all JS; manual code review; MediaStorageTest written (temp-disk fake; no Laravel/DB bootstrap required). The direct-web-access-fails claim rests on `config/filesystems.php` (`'serve' => false`, root `storage/app/private/`) and the blueprint mapping `public/` as the sole document root; HTTP-level execution is deferred to P08.09/http-smoke when a server runs. | `php vendor/bin/phpunit --testdox` (MediaStorageTest plus all prior P08.01–P08.03 tests); MySQL suites including migration 000021; `php tests/http-smoke.php` (once upload/public routes exist in P08.08–P08.09); `npm run test:browser` | `database/migrations/2026_10_05_000021_add_media_original_storage_path.php`, `app/Support/StoredOriginal.php`, `app/Support/MediaStorage.php`, `app/Models/Media.php` (property), `app/Support/SecurityAudit.php` (event), `bootstrap/app.php` (singleton), `tests/Feature/MediaStorageTest.php` |

## Maintenance rule

## P08 rows

| Step | Recorded complete | Independent reviewer | Executed in the recording environment | Not executed (must re-run) | Test files |
|---|---|---|---|---|---|
| P08.01 media metadata (ownership, rights, checksum, alt text, crop, publication state) | Yes | **None — implementer self-review** | JS 14/14; contract 16/13; 265 Markdown links; MediaMetadataValidationTest exists (cannot run without PHP); manual code read of migration/model/service/probe/test; audit allowlist updated to include `media_metadata_edited` and `media_publication_changed` | `php vendor/bin/phpunit --testdox` (foundation including new MediaMetadataValidationTest); MySQL suite `MediaMetadataTest`; http-smoke; browser suite (no UI added this step) | `database/migrations/2026_10_05_000020_create_media_table.php`, `app/Models/Media.php`, `app/Support/MediaMetadata.php`, `tests/Feature/MediaMetadataValidationTest.php`, `tests/Database/MediaMetadataTest.php`, `tests/Fixtures/media-metadata-probe.php` |

## Maintenance rule

Update this log in the same change as any step: add the executed command with its result and date, move the check from *Not executed* to *Executed*, and record the reviewer outcome in the *Independent reviewer* column. Never delete a row — a step's history of what did and did not run is the review boundary for the next reviewer.
