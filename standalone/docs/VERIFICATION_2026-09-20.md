# Standalone QA verification

Scope: owning QA repository, new `standalone/` runtime and CI only. No source plugin files, WordPress tables, remote files, DNS, credentials, bank data or production services changed.

## Executed evidence

| Command/check | Outcome | Limits |
| --- | --- | --- |
| `npm test` in `standalone/` | 30 tests passed, exit 0 | Synthetic fixtures, real HTTP server and real SQLite files; not live WordPress/OIDC |
| `npm audit --audit-level=high` | Zero advisories, exit 0 | Registry advisory snapshot, not a code security audit |
| `wsl --exec php -l tools/export-wordpress.php` | Syntax valid, exit 0 | Live MySQL/WordPress snapshot export not executed |
| `wsl --exec sh -n deploy/backup.sh` | Exit 0 | `age`, service installation and encrypted off-host restore unverified |
| `node deploy/verify-release.mjs deploy/release-evidence.example.json` | Exit 1, 16 explicit blocked gates | Intended fail-closed result |
| Existing plugin Jest baseline | 13 tests passed, exit 0 | Older owning-repository plugin, not current 1.4.1 lineage |
| Existing plugin PHPUnit baseline | 32 tests, 94 assertions, exit 0 | Older owning-repository plugin; WordPress functions mocked |

PHPUnit ran on WSL PHP 8.3.6 with temporary extensions extracted without root from `php8.3-xml`, `php8.3-mbstring`, and `libonig5`, bundle `/tmp/cqa-php-Um8HU6`. Reproduce with `wsl --exec sh standalone/tools/test-plugin-wsl.sh /mnt/c/Users/chara/Documents/ChatGPT/ChromaOS-worktrees/QA-Report-App-p00/chroma-qa-reports`. The script records its fresh temporary bundle path; system PHP was not changed.

The standalone test workflow covers snapshot tampering, unknown/missing tables, control totals, large source IDs, schema drift, orphan relationships, history, media references, atomic/idempotent imports, separate school scopes, grant revocation, HTML escaping, CSRF/Origin checks, native create/save/upload/submit/review/approve, locked reports, stale revisions, retry collisions, transactional audit failure, blocked notifications, scoped photo/PDF downloads, unsupported images, stripped image metadata, corruption detection, embedded-font PDFs, database/media backup and isolated restore including idempotency records. The HTTP PDF is generated from the native database and its private media.

## Rendered evidence

Playwright CLI synthetic browser rehearsal completed create → save → submit → start review → approve, reading persisted state at each step. Synthetic report ID: `79020c33-d6e4-4d92-9e8e-ef149964dfad`; final state `approved`, revision 5. Snapshot/screenshot artifacts are ignored under `standalone/output/playwright/`. This rehearsal initially caught a real browser form `Origin: null` caused by `Referrer-Policy: no-referrer`; changing it to `same-origin` kept external referrers suppressed and restored strict same-origin form submission. The screenshot was captured before the subsequent photo/PDF additions; those additions have HTTP and rendered-PDF coverage, not a claim of later browser UAT.

The PDF fixture `standalone/output/pdf/synthetic-inspection.pdf` was rendered with WSL `pdftoppm` to two PNGs and both pages visually inspected. Text extraction confirmed the report, draft label, checklist item, source-independent report ID, page numbers and photo caption. The synthetic photo is deliberately a solid colored fixture. No production images were used. The PDF skill guided render-and-inspect verification; the Playwright skill guided actual form verification.

## Not verified / release blockers

Actual current source export and row totals, Drive/local historical media bytes, offline browser draft drain, encrypted source backup restore, real OIDC authentication and immutable subject mapping, real current grants, 1.4.1 React/dynamic classroom/version/Monday parity, operator UAT, production hosting/runtime, alert routing, encrypted native backup restore, and designated release approval remain open. GitHub CI is supplied but is not represented as passing until a remote run completes. All source claims are separately distinguished in `README.md`.
