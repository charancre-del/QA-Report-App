CREATE TABLE app_metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL) STRICT;
CREATE TABLE reports (
 id TEXT PRIMARY KEY, source_instance TEXT NOT NULL, school_id TEXT NOT NULL,
 source_snapshot_sha256 TEXT NOT NULL, author_subject TEXT NOT NULL, author_source_user_id TEXT,
 report_type TEXT NOT NULL CHECK(report_type IN ('tier1','tier1_tier2','new_acquisition')),
 inspection_date TEXT NOT NULL, status TEXT NOT NULL CHECK(status IN ('draft','submitted','under_review','needs_revision','approved')),
 revision INTEGER NOT NULL CHECK(revision > 0), checklist_json TEXT NOT NULL, checklist_sha256 TEXT NOT NULL,
 closing_notes TEXT NOT NULL DEFAULT '', overall_rating TEXT NOT NULL DEFAULT 'pending'
 CHECK(overall_rating IN ('pending','exceeds','meets','needs_improvement')),
 created_at TEXT NOT NULL, updated_at TEXT NOT NULL
) STRICT;
CREATE INDEX reports_school ON reports(source_instance, school_id, inspection_date);
CREATE TABLE responses (
 report_id TEXT NOT NULL REFERENCES reports(id), section_key TEXT NOT NULL, item_key TEXT NOT NULL,
 rating TEXT NOT NULL CHECK(rating IN ('yes','sometimes','no','na')), notes TEXT NOT NULL,
 evidence_type TEXT NOT NULL CHECK(evidence_type IN ('observation','documentation','interview')),
 PRIMARY KEY(report_id, section_key, item_key)
) STRICT;
CREATE TABLE workflow (
 id TEXT PRIMARY KEY, report_id TEXT NOT NULL REFERENCES reports(id), actor_subject TEXT NOT NULL,
 action TEXT NOT NULL, from_status TEXT NOT NULL, to_status TEXT NOT NULL, comment TEXT NOT NULL, created_at TEXT NOT NULL
) STRICT;
CREATE TABLE photos (
 id TEXT PRIMARY KEY, report_id TEXT NOT NULL REFERENCES reports(id),
 sha256 TEXT NOT NULL, bytes INTEGER NOT NULL, mime TEXT NOT NULL CHECK(mime='image/jpeg'),
 caption TEXT NOT NULL, created_at TEXT NOT NULL, deleted_at TEXT
) STRICT;
CREATE TABLE audit (
 id TEXT PRIMARY KEY, actor_subject TEXT NOT NULL, report_id TEXT REFERENCES reports(id),
 action TEXT NOT NULL, revision INTEGER, created_at TEXT NOT NULL
) STRICT;
CREATE TABLE commands (
 actor_subject TEXT NOT NULL, idempotency_key TEXT NOT NULL, fingerprint TEXT NOT NULL,
 report_id TEXT NOT NULL REFERENCES reports(id), result_json TEXT NOT NULL, created_at TEXT NOT NULL,
 PRIMARY KEY(actor_subject, idempotency_key)
) STRICT;
CREATE TABLE outbox (
 id TEXT PRIMARY KEY, report_id TEXT NOT NULL REFERENCES reports(id), event_type TEXT NOT NULL,
 revision INTEGER NOT NULL, state TEXT NOT NULL CHECK(state IN ('blocked_adapter','ready','delivered','outcome_unknown','failed')),
 created_at TEXT NOT NULL, UNIQUE(report_id,event_type,revision)
) STRICT;
PRAGMA user_version = 1;
