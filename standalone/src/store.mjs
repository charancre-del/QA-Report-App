import { DatabaseSync, backup } from 'node:sqlite';
import { randomUUID } from 'node:crypto';
import { readFileSync, existsSync, chmodSync, copyFileSync, constants } from 'node:fs';
import { digest } from './snapshot.mjs';

export const problem = (status, code) => { const e = new Error(code); e.status = status; throw e; };
const requireValue = (value, code = 'invalid_input') => { if (!value) problem(422, code); };
const cap = (access, name) => Array.isArray(access.grant.capabilities) && access.grant.capabilities.includes(name);
export const canCreate = a => cap(a, 'cqa_create_reports');
export const canEdit = (a, r) => a.allowed(r.school_id) && (cap(a, 'cqa_edit_all_reports') ||
  (a.subject === r.author_subject && cap(a, 'cqa_edit_own_reports')));
export const canReview = (a, r) => a.allowed(r.school_id) && cap(a, 'cqa_approve_reports') && a.subject !== r.author_subject;
export const canView = (a, r) => a.allowed(r.school_id) && (cap(a, 'cqa_view_all_reports') ||
  (a.subject === r.author_subject && (cap(a, 'cqa_create_reports') || cap(a, 'cqa_view_own_reports'))) ||
  (a.grant.role === 'cqa_program_manager' && cap(a, 'cqa_view_own_reports')));

export function migrateDatabase(path, source) {
  if (!source) throw new Error('Explicit source instance required');
  const db = new DatabaseSync(path); chmodSync(path, 0o600);
  const sql = readFileSync(new URL('../migrations/001_native_inspections.sql', import.meta.url), 'utf8');
  try {
    db.exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL; PRAGMA synchronous=FULL; BEGIN IMMEDIATE;');
    const version = db.prepare('PRAGMA user_version').get().user_version;
    if (version === 0) {
      db.exec(sql);
      const insert = db.prepare('INSERT INTO app_metadata(key,value) VALUES (?,?)');
      insert.run('source_instance', source); insert.run('migration_001_sha256', digest(sql));
    } else if (version !== 1 || db.prepare("SELECT value FROM app_metadata WHERE key='source_instance'").get()?.value !== source ||
      db.prepare("SELECT value FROM app_metadata WHERE key='migration_001_sha256'").get()?.value !== digest(sql)) throw new Error('Schema or source identity mismatch');
    db.exec('COMMIT');
  } catch (error) { db.exec('ROLLBACK'); throw error; } finally { db.close(); }
}

export class InspectionStore {
  constructor(path, source) {
    if (!existsSync(path)) throw new Error('Run the explicit database migration first');
    this.db = new DatabaseSync(path, { timeout: 5000 });
    this.source = source;
    this.db.exec('PRAGMA foreign_keys=ON; PRAGMA synchronous=FULL;');
    if (this.db.prepare('PRAGMA user_version').get().user_version !== 1 ||
      this.db.prepare("SELECT value FROM app_metadata WHERE key='source_instance'").get()?.value !== source) {
      this.db.close(); throw new Error('Database schema/source mismatch');
    }
  }
  close() { this.db.close(); }
  health() { this.db.prepare('SELECT 1').get(); return { schema_version: 1, writes: 'explicitly_gated', adapters: { media: 'native_private_storage_requires_configuration', pdf: 'native_renderer_requires_font', ai: 'unavailable', notifications: 'blocked_adapter', monday: 'unavailable', legacy_version_restore: 'unavailable' } }; }
  list(access) {
    return this.db.prepare('SELECT * FROM reports WHERE source_instance=? ORDER BY inspection_date DESC, created_at DESC').all(this.source)
      .filter(r => canView(access, r)).map(({ checklist_json, author_subject, ...r }) => r);
  }
  record(id, access) {
    const r = this.db.prepare('SELECT * FROM reports WHERE id=? AND source_instance=?').get(id, this.source);
    if (!r || !canView(access, r)) problem(404, 'not_found');
    return r;
  }
  detail(id, access) {
    const r = this.record(id, access);
    const { author_subject, checklist_json, ...report } = r;
    return { report, checklist: JSON.parse(checklist_json),
      responses: this.db.prepare('SELECT section_key,item_key,rating,notes,evidence_type FROM responses WHERE report_id=? ORDER BY section_key,item_key').all(id),
      photos: this.db.prepare('SELECT id,caption,bytes,mime,created_at FROM photos WHERE report_id=? AND deleted_at IS NULL ORDER BY created_at,id').all(id),
      workflow: this.db.prepare('SELECT action,from_status,to_status,comment,created_at FROM workflow WHERE report_id=? ORDER BY created_at,id').all(id),
      permissions: { edit: canEdit(access, r), review: canReview(access, r) },
      integrations: this.health().adapters };
  }
  command(access, key, input, execute) {
    requireValue(typeof key === 'string' && /^[a-zA-Z0-9_-]{16,100}$/.test(key), 'idempotency_key_required');
    const fingerprint = digest(JSON.stringify(input));
    this.db.exec('BEGIN IMMEDIATE');
    try {
      const prior = this.db.prepare('SELECT * FROM commands WHERE actor_subject=? AND idempotency_key=?').get(access.subject, key);
      if (prior) {
        if (prior.fingerprint !== fingerprint) problem(409, 'idempotency_conflict');
        this.record(prior.report_id, access); // Current authorization applies even to replay.
        this.db.exec('COMMIT'); return { ...JSON.parse(prior.result_json), replay: true };
      }
      const result = execute();
      this.db.prepare('INSERT INTO commands VALUES (?,?,?,?,?,?)').run(access.subject, key, fingerprint, result.id, JSON.stringify(result), new Date().toISOString());
      this.db.exec('COMMIT'); return { ...result, replay: false };
    } catch (error) { this.db.exec('ROLLBACK'); throw error; }
  }
  audit(access, reportId, action, revision) {
    this.db.prepare('INSERT INTO audit VALUES (?,?,?,?,?,?)').run(randomUUID(), access.subject, reportId, action, revision, new Date().toISOString());
  }
  create(access, key, input, snapshot, definition) {
    if (!canCreate(access) || !access.allowed(input.school_id) || !snapshot.indexes.schools.has(input.school_id)) problem(403, 'access_denied');
    requireValue(['tier1','tier1_tier2','new_acquisition'].includes(input.report_type), 'invalid_report_type');
    requireValue(typeof input.inspection_date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(input.inspection_date) &&
      Number.isFinite(Date.parse(input.inspection_date)) && new Date(input.inspection_date).toISOString().slice(0,10) === input.inspection_date, 'invalid_inspection_date');
    requireValue(definition?.reviewed_by && definition?.version && Array.isArray(definition.sections) && definition.sections.length > 0, 'reviewed_checklist_required');
    const keys = new Set();
    for (const section of definition.sections) {
      requireValue(/^[a-zA-Z0-9_-]+$/.test(section.key) && section.items?.length > 0, 'invalid_checklist');
      for (const item of section.items) {
        const k = `${section.key}.${item.key}`;
        requireValue(/^[a-zA-Z0-9_-]+$/.test(item.key) && typeof item.label === 'string' && !keys.has(k), 'invalid_checklist'); keys.add(k);
      }
    }
    return this.command(access, key, { action: 'create', ...input, checklist_sha256: digest(JSON.stringify(definition)) }, () => {
      const id = randomUUID(), at = new Date().toISOString(), checklist = JSON.stringify(definition);
      this.db.prepare(`INSERT INTO reports(id,source_instance,school_id,source_snapshot_sha256,author_subject,author_source_user_id,
        report_type,inspection_date,status,revision,checklist_json,checklist_sha256,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)`)
        .run(id, this.source, input.school_id, snapshot.sha256, access.subject, access.grant.source_user_id, input.report_type, input.inspection_date, 'draft', 1, checklist, digest(checklist), at, at);
      this.audit(access, id, 'created', 1); return { id, revision: 1, status: 'draft' };
    });
  }
  save(access, key, id, input) {
    const initial = this.record(id, access); if (!canEdit(access, initial)) problem(403, 'access_denied');
    return this.command(access, key, { action: 'save', id, ...input }, () => {
      const r = this.record(id, access);
      if (r.revision !== input.revision) problem(409, 'stale_revision');
      if (!['draft','needs_revision'].includes(r.status)) problem(409, 'report_locked');
      requireValue(Array.isArray(input.responses) && input.responses.length <= 2000, 'invalid_responses');
      requireValue(typeof input.closing_notes === 'string' && input.closing_notes.length <= 20000, 'invalid_notes');
      requireValue(['pending','exceeds','meets','needs_improvement'].includes(input.overall_rating), 'invalid_overall_rating');
      const allowed = new Set(JSON.parse(r.checklist_json).sections.flatMap(s => s.items.map(i => `${s.key}.${i.key}`))), seen = new Set();
      for (const response of input.responses) {
        const k = `${response.section_key}.${response.item_key}`;
        requireValue(allowed.has(k) && !seen.has(k), 'invalid_checklist_item'); seen.add(k);
        requireValue(['yes','sometimes','no','na'].includes(response.rating), 'invalid_rating');
        requireValue(typeof response.notes === 'string' && response.notes.length <= 10000, 'invalid_notes');
        requireValue(['observation','documentation','interview'].includes(response.evidence_type), 'invalid_evidence_type');
      }
      this.db.prepare('DELETE FROM responses WHERE report_id=?').run(id);
      const insert = this.db.prepare('INSERT INTO responses VALUES (?,?,?,?,?,?)');
      for (const response of input.responses) insert.run(id, response.section_key, response.item_key, response.rating, response.notes, response.evidence_type);
      this.db.prepare('UPDATE reports SET closing_notes=?,overall_rating=?,revision=revision+1,updated_at=? WHERE id=?')
        .run(input.closing_notes, input.overall_rating, new Date().toISOString(), id);
      this.audit(access, id, 'saved', r.revision + 1); return { id, revision: r.revision + 1, status: r.status };
    });
  }
  photo(id, photoId, access) {
    this.record(id, access);
    const photo = this.db.prepare('SELECT * FROM photos WHERE id=? AND report_id=? AND deleted_at IS NULL').get(photoId, id);
    if (!photo) problem(404, 'not_found');
    return photo;
  }
  addPhoto(access, key, id, input) {
    const initial = this.record(id, access); if (!canEdit(access, initial)) problem(403, 'access_denied');
    return this.command(access, key, { action: 'photo', id, ...input }, () => {
      const r = this.record(id, access);
      if (r.revision !== input.revision) problem(409, 'stale_revision');
      if (!['draft','needs_revision'].includes(r.status)) problem(409, 'report_locked');
      requireValue(/^[a-f0-9]{64}$/.test(input.sha256) && Number.isSafeInteger(input.bytes) && input.bytes > 0 && input.bytes <= 10 * 1024 * 1024, 'invalid_photo');
      requireValue(typeof input.caption === 'string' && input.caption.length <= 2000, 'invalid_caption');
      const photoId = randomUUID(), at = new Date().toISOString();
      this.db.prepare('INSERT INTO photos VALUES (?,?,?,?,?,?,?,NULL)').run(photoId, id, input.sha256, input.bytes, 'image/jpeg', input.caption, at);
      this.db.prepare('UPDATE reports SET revision=revision+1,updated_at=? WHERE id=?').run(at, id);
      this.audit(access, id, 'photo_added', r.revision + 1); return { id, photo_id: photoId, revision: r.revision + 1, status: r.status };
    });
  }
  removePhoto(access, key, id, input) {
    const initial = this.record(id, access); if (!canEdit(access, initial)) problem(403, 'access_denied');
    return this.command(access, key, { action: 'photo_remove', id, ...input }, () => {
      const r = this.record(id, access);
      if (r.revision !== input.revision) problem(409, 'stale_revision');
      if (!['draft','needs_revision'].includes(r.status)) problem(409, 'report_locked');
      this.photo(id, input.photo_id, access);
      const at = new Date().toISOString();
      this.db.prepare('UPDATE photos SET deleted_at=? WHERE id=?').run(at, input.photo_id);
      this.db.prepare('UPDATE reports SET revision=revision+1,updated_at=? WHERE id=?').run(at, id);
      this.audit(access, id, 'photo_removed', r.revision + 1); return { id, revision: r.revision + 1, status: r.status };
    });
  }
  transition(access, key, id, input) {
    this.record(id, access);
    return this.command(access, key, { action: 'transition', id, ...input }, () => {
      const r = this.record(id, access);
      if (r.revision !== input.revision) problem(409, 'stale_revision');
      const target = { submit: 'submitted', start_review: 'under_review', approve: 'approved', request_revision: 'needs_revision' }[input.action];
      requireValue(target, 'invalid_action');
      if (input.action === 'submit' ? !canEdit(access, r) : !canReview(access, r)) problem(403, 'access_denied');
      const valid = input.action === 'submit' ? ['draft','needs_revision'] : input.action === 'start_review' ? ['submitted'] : ['under_review'];
      if (!valid.includes(r.status)) problem(409, 'invalid_transition');
      requireValue(typeof input.comment === 'string' && input.comment.length <= 10000, 'invalid_comment');
      if (input.action === 'request_revision') requireValue(input.comment.trim().length > 0, 'revision_reason_required');
      if (input.action === 'submit') {
        const count = JSON.parse(r.checklist_json).sections.reduce((n,s) => n + s.items.length, 0);
        requireValue(this.db.prepare('SELECT count(*) AS total FROM responses WHERE report_id=?').get(id).total === count && r.overall_rating !== 'pending', 'incomplete_inspection');
      }
      const at = new Date().toISOString();
      this.db.prepare('UPDATE reports SET status=?,revision=revision+1,updated_at=? WHERE id=?').run(target, at, id);
      this.db.prepare('INSERT INTO workflow VALUES (?,?,?,?,?,?,?,?)').run(randomUUID(), id, access.subject, input.action, r.status, target, input.comment, at);
      this.audit(access, id, input.action, r.revision + 1);
      // An event is durable, but cannot dispatch email/AI without a separately verified adapter.
      this.db.prepare('INSERT INTO outbox VALUES (?,?,?,?,?,?)').run(randomUUID(), id, `report.${target}`, r.revision + 1, 'blocked_adapter', at);
      return { id, revision: r.revision + 1, status: target, notification: 'blocked_adapter' };
    });
  }
  async backup(path) {
    if (existsSync(path)) throw new Error('Backup destination must be new');
    await backup(this.db, path); chmodSync(path, 0o600);
    return { sha256: digest(readFileSync(path)), source: this.source, schema: 1 };
  }
}

export function restoreDatabase(backupPath, destination, expectedSha256, source) {
  if (digest(readFileSync(backupPath)) !== expectedSha256) throw new Error('Backup checksum mismatch');
  const check = new DatabaseSync(backupPath, { readOnly: true });
  try {
    if (check.prepare('PRAGMA integrity_check').get().integrity_check !== 'ok' || check.prepare('PRAGMA foreign_key_check').all().length ||
      check.prepare("SELECT value FROM app_metadata WHERE key='source_instance'").get()?.value !== source) throw new Error('Backup reconciliation failed');
  } finally { check.close(); }
  copyFileSync(backupPath, destination, constants.COPYFILE_EXCL); chmodSync(destination, 0o600);
  return { restored: true, writes_enabled: false };
}
