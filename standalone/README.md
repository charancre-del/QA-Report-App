# Independent Chroma QA application

This is a separate Node web runtime, HTML UI, API, scoped authorization layer, SQLite database and private evidence store. It does not bootstrap WordPress, call WordPress during inspection work, share WordPress cookies, write WordPress tables, or depend on WordPress being online. WordPress is an import source and a retained rollback/history system. The eventual approved cutover retires WordPress from the QA workflow.

**Status: implemented standalone inspection pilot; not approved for live cutover.** Source/data/role reconciliation, live identity gateway configuration, full 1.4.1 feature parity and human UAT remain open. The release gate intentionally returns `cutover_ready: false` without those proofs. Do not deploy the older `chroma-qa-reports/` tree as the current production artifact.

## Source lineage and exact environment

- Original owning-repository baseline: `b2629497ea87d4ff29336db7eab8f7b1be5ccd5b`, authorization remediation over the old 1.0.1 plugin, PR #1.
- Current source candidate: `Chroma ELA Website/Wptstchroma`, commit `162a79b5d76c540b4eed7e15c062a017f7b5447a`, `plugins/QA-Report-App/chroma-qa-reports`, version **1.4.1**. Its schema, snapshot model and Monday contracts were inspected for this migration. That source is substantially newer than the owning repository baseline. Its separate security port is not part of this standalone change.
- Verified by the coordinating Chroma ELA task on 2026-09-19/20: staging `https://x3yyntt5tp-staging.wpdns.site`, WordPress 7.1, QA plugin 1.4.1 asset URLs, `/qa-reports/login/`, protected `cqa/v1` routes returning 401 anonymously. Production and staging route/manifest responses differ.
- SSH identity: `x3yyadl@131.153.236.189`; document root `/home/x3yyadl/public_html/x3yyntt5tp-staging.wpdns.site`; database `x3yyadl_staging`; prefix `wp_`. Surviving local SSH keys were rejected; no authenticated wp-admin session is available. Live row counts, artifact digest, role inventory and current backups are **BLOCKED_EXTERNAL**.
- These facts identify the migration source, not a Node hosting target. Dedicated app hosting, hostname, TLS, OIDC client and release approval still need actual configuration. Nothing here changes DNS or the WordPress vhost.

## Implemented workflow

An inspector can create a school-scoped inspection, save a pinned and reviewed checklist with notes/evidence, upload private JPEG/PNG photos, submit it, and receive a revision request. A different authorized reviewer can start review and approve it. Approved/in-review records cannot be edited. PDF export includes checklist answers, notes, report identity, draft status and photos, with an embedded configured font. Photos are normalized to JPEG, stripped of metadata, bounded in size/pixels, and served only through current report authorization. Removal is a soft delete; retained objects survive backup.

Each write commits data, audit, idempotency outcome and any notification event in one SQLite transaction. A stale revision is 409. The same retry key returns the recorded outcome; a changed payload with that key is 409. Audit failure rolls the transaction back. The notification outbox is deliberately `blocked_adapter`; no emails, AI requests or Monday writes are dispatched. A transport timeout can be retried with the original idempotency key and reconciled without a duplicate write.

The archive independently reads immutable, checksum-pinned imports. IDs remain strings, including values beyond JavaScript integer precision. It preserves all columns, schema DDL, report history, soft-deleted photo records, stored AI summaries, templates, comments, Monday references and snapshot JSON. Unknown tables, orphan relations, missing principals, invalid histories, incomplete media inventories and mismatched totals fail import. Source grants are inventory, never automatic login grants. Historical source photos are marked unavailable until their actual files and access controls are migrated.

## Local verification and synthetic browser rehearsal

Requirements: Node 22.18+ (Node 24 LTS deployment profile), a local SQLite-capable runtime, and a readable TrueType font. Tests on this Windows host used Node 25.6.0; `node:sqlite` is experimental in that installed runtime. Runtime support/capacity remains a production gate. Exact dependencies are locked.

```sh
cd standalone
npm ci
npm test
npm audit --audit-level=high
node tools/synthetic-demo.mjs
```

The last command binds only `127.0.0.1:4311` with fixed synthetic fixtures in a new temporary directory. `/work` opens the inspector UI; `/demo-as-reviewer` selects the synthetic reviewer. This demo gateway is never a production authentication mechanism. It does not read deployment credentials or real records. Stop it with Ctrl+C. Test artifacts belong in ignored `output/playwright/` and `output/pdf/`.

## Private import and initialization

First obtain a current encrypted database/files backup and prove a restore in isolation. Freeze only QA writes/cleanup at the source for the final export; drain the old clients' IndexedDB `cqa-offline` drafts and pending photos. Preserve source media and versioned photo references. Server SQL cannot recover browser-only drafts.

Run `tools/export-wordpress.php` using WP-CLI with `--skip-plugins --skip-themes` and these separately supplied environment values:

```text
CQA_SOURCE_INSTANCE=wpstchroma
CQA_EXPECTED_SITE_URL=https://x3yyntt5tp-staging.wpdns.site
CQA_EXPECTED_DATABASE_NAME=x3yyadl_staging
CQA_EXPORT_PATH=<new absolute private file outside the WordPress root>
wp --skip-plugins --skip-themes eval-file <absolute path to export-wordpress.php>
```

The exporter verifies exact URL/database identity and InnoDB tables, reads under a repeatable-read transaction, and creates a new mode-0600 file. It exports only QA tables and restricted QA principal inventory. It excludes principal login names/emails, passwords, authentication tokens and secret-bearing options. Report content and school data can still contain personal or confidential information, so the whole export is sensitive. Must-use plugins still load under WP-CLI: inspect them and run against a restored clone first. Never paste the exported content into chat, CI, Git or a public web directory.

For 1.4.1, required QA tables are `schools`, `reports`, `responses`, `photos`, `ai_summaries`, `report_snapshots`, `monday_poi_syncs`. Optional existing `templates` and `workflow_comments` are preserved. Query failures, nontransactional stores or schema differences are blockers; do not delete rows to make reconciliation pass.

```sh
npm run import -- PRIVATE_SOURCE_FILE PRIVATE_IMPORT_DIRECTORY wpstchroma
node tools/database.mjs migrate PRIVATE_APP_SQLITE wpstchroma
```

Import is atomic, immutable and replay-safe. It does not switch the server's active snapshot. Pin the printed payload SHA and source instance in the protected app configuration. QA school IDs remain source crosswalks; independent school administration/UI and reviewed dynamic classroom checklist equivalence are still parity work. Native records use UUIDs and their own database; imported originals are never overwritten.

## Identity and app access

The web process listens only on loopback. A trusted OIDC gateway authenticates each request, strips supplied identity headers and sends `X-CQA-Subject` (verified issuer + immutable subject) plus `X-CQA-Proxy-Secret` (minimum 32 random bytes, independently controlled). Nginx and gateway templates require real TLS/issuer/client validation and negative tests before use. An email/name is never the identity key. Do not expose port 4310 or trust a client-provided subject. The app grants file is read on every request so revocation is immediate.

A protected grants file is an array of reviewed entries with `subject`, `source_instance`, `source_user_id`, `action` (`qa:operate` or `archive:read`), `role`, explicit `capabilities`, `school_ids`, `all_schools`, `disabled:false`, `reviewed_by` and `expires_at`. A new standalone identity can use `native_identity:true` and `source_user_id:null`; it does not need a WordPress account. No source identity is matched by name or email.

Roles are `cqa_super_admin`, `cqa_qa_officer`, `cqa_regional_director`, `cqa_program_manager`. Regional grants require an exact `region` plus explicit school IDs; program managers require exactly one school and no global scope. Relevant explicit capabilities are `cqa_view_all_reports`, `cqa_view_own_reports`, `cqa_create_reports`, `cqa_edit_own_reports`, `cqa_edit_all_reports`, `cqa_approve_reports`, `cqa_export_reports`. Approval uses the current 1.4.1 capability and requires a different reviewer. Do not auto-copy broad legacy administrator grants. Writes also require the exact configured Origin and per-principal CSRF token.

## Runtime, operations and recovery

`deploy/app.env.example`, `chroma-qa.service`, `nginx.conf.example`, backup service/timer and `backup.sh` are reviewable deployment assets. Install to a new release path, use a dedicated `chroma-qa` service identity, `npm ci --omit=dev`, and private data/log directories. Do not share the WordPress database or uploads directory. Configure a reviewed checklist file with `types.<report_type> = {version, reviewed_by, sections:[{key,name,items:[{key,label}]}]}` and its exact file SHA. Existing regulatory checklist text must be reviewed/versioned before use; this repository does not certify its rules. Leave `CQA_WRITES_ENABLED=false` until the scoped pilot is approved.

`GET /healthz` is liveness only. Authenticated `GET /readyz` reports the database, historical-source freshness, adapter states and unpassed cutover gates. Alert on 5xx, service restart loops, disk headroom, failed backups, expired grants, unknown notification outcomes, and blocked outbox growth. Log contents contain audit metadata rather than inspection notes. Human alert routing and load limits remain deployment validation work.

```sh
node tools/recovery.mjs backup APP_DB wpstchroma PRIVATE_MEDIA_DIR SOURCE_SNAPSHOT NEW_BUNDLE_DIR
node tools/recovery.mjs restore BUNDLE_DIR wpstchroma NEW_ISOLATED_RESTORE_DIR MANIFEST_SHA256
```

Bundles pin the SQLite backup, historical snapshot and every referenced image (including soft-deleted ones). Restore checks integrity, foreign keys, source identity, hashes and photo control totals, and refuses existing destinations. Current grants/secrets are intentionally not restored, so a backup cannot revive revoked access. Verify restored data through the app before changing the active path. The scheduled wrapper encrypts with `age`; actual decryption/key recovery, remote backup placement, retention and monitoring must be rehearsed. Plaintext working bundles require controlled cleanup after encrypted restore proof. Never restore over valid new inspection work.

Before traffic cutover, run `node deploy/verify-release.mjs PRIVATE_REVIEWED_EVIDENCE_FILE`. The example intentionally fails with 16 blocked gates. This evidence checker does not confer approval or establish that referenced evidence is true.

Rollback before native writes: remove the new routing and continue the unchanged WordPress QA UI. After native writes: disable native writes, preserve and back up the new journal/media/outbox, reconcile all native records, and use a reviewed forward/export recovery plan. Switching traffic back alone would strand native work. WordPress source retirement requires complete accepted migration/parity; never uninstall/delete QA tables or media as part of this pilot. No source freeze bridge has been installed or verified live.

## Remaining parity and live gates

The current 1.4.1 React wizard, dynamic classroom/enrollment field behavior, templates, comparisons/analytics, geo/photo annotation, original PDF equivalence, offline sync/recovery, legacy version restoration, school administration and Monday actions need explicit parity or approved exclusion. Native photo/PDF functionality exists, but migration of all historical originals/marked-up variants and Drive access does not. AI stays optional and unavailable until credentials, privacy policy, provider boundary and human review are verified. Notifications are durable blocked work pending an approved transport and delivery proof.

The live source export, current counts/roles, backups, credential rotation assessment, real OIDC session/subject behavior, staging installation, monitoring, encrypted restore and operator UAT are blocked or unverified. Current local tests do not close these gates. Restore scoped SSH access or authenticate the staging WordPress admin before taking the next live export/backup step.
