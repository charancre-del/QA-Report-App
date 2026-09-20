import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';

export const FORMAT = 'chroma-qa-snapshot/v1';
export const REQUIRED = ['schools', 'reports', 'responses', 'photos', 'ai_summaries'];
export const OPTIONAL = ['templates', 'workflow_comments', 'report_snapshots', 'monday_poi_syncs'];
export const digest = value => createHash('sha256').update(value).digest('hex');
const id = value => typeof value === 'string' && /^[1-9][0-9]{0,19}$/.test(value);
const check = (condition, message) => { if (!condition) throw new Error(message); };
export function packSnapshot(payload) {
  const payload_json = JSON.stringify(payload);
  return JSON.stringify({ format: FORMAT, sha256: digest(payload_json), payload_json });
}

// Never coerce BIGINT primary keys through JavaScript Number or match people by name.
export function validateSnapshot(raw, expectedSource) {
  const envelope = JSON.parse(raw);
  check(envelope.format === FORMAT && typeof envelope.payload_json === 'string', 'Unsupported snapshot format');
  check(envelope.sha256 === digest(envelope.payload_json), 'Snapshot checksum mismatch');
  const p = JSON.parse(envelope.payload_json);
  check(expectedSource && p.source?.instance === expectedSource, 'Source instance mismatch');
  check(typeof p.source.url === 'string' && /^https?:\/\//.test(p.source.url), 'Source URL missing');
  check(Number.isFinite(Date.parse(p.exported_at)), 'Snapshot time missing');
  check(Date.parse(p.exported_at) <= Date.now() + 300000, 'Snapshot time is in the future');
  check(p.consistency === 'repeatable-read', 'Consistent database snapshot required');
  check(p.tables && typeof p.tables === 'object' && !Array.isArray(p.tables), 'Tables missing');
  const tables = Object.keys(p.tables);
  for (const name of REQUIRED) check(tables.includes(name), `Required table missing: ${name}`);
  if (/^1\.[4-9]\./.test(p.source.plugin_db_version ?? '')) {
    for (const name of ['report_snapshots','monday_poi_syncs']) check(tables.includes(name), `Required 1.4 source table missing: ${name}`);
  }
  for (const name of tables) check([...REQUIRED, ...OPTIONAL].includes(name), `Unreviewed source table: ${name}`);
  const indexes = {};
  const counts = {};
  for (const name of tables) {
    const table = p.tables[name];
    check(Array.isArray(table.rows) && table.count === table.rows.length, `Control total mismatch: ${name}`);
    check(Array.isArray(table.columns) && table.columns.includes('id'), `Schema missing: ${name}`);
    check(typeof table.schema === 'string' && table.schema.length > 0, `DDL missing: ${name}`);
    const index = new Map();
    for (const row of table.rows) {
      check(row && id(row.id) && !index.has(row.id), `Invalid or duplicate ID: ${name}`);
      check(Object.keys(row).length === table.columns.length && table.columns.every(c => Object.hasOwn(row, c)), `Schema drift: ${name}`);
      check(Object.values(row).every(v => v === null || typeof v === 'string'), `Lossy source value: ${name}`);
      index.set(row.id, row);
    }
    indexes[name] = index;
    counts[name] = index.size;
  }
  check(Array.isArray(p.principals), 'Principal inventory missing');
  const principals = new Set();
  for (const principal of p.principals) {
    check(id(principal.id) && !principals.has(principal.id), 'Invalid principal mapping');
    check(typeof principal.exists === 'boolean', 'Principal state missing');
    principals.add(principal.id);
  }
  const principalRef = value => check(id(value) && principals.has(value), 'Unreconciled principal reference');
  for (const row of indexes.reports.values()) {
    check(indexes.schools.has(row.school_id), 'Orphan report school');
    principalRef(row.user_id);
    if (row.previous_report_id && row.previous_report_id !== '0') {
      const previous = indexes.reports.get(row.previous_report_id);
      check(previous && previous.school_id === row.school_id && previous.id !== row.id, 'Invalid report history reference');
    }
  }
  const summaryReports = new Set();
  for (const name of ['responses', 'photos', 'ai_summaries', 'workflow_comments', 'report_snapshots', 'monday_poi_syncs']) {
    for (const row of indexes[name]?.values() ?? []) {
      check(indexes.reports.has(row.report_id), `Orphan report child: ${name}`);
      if (name === 'workflow_comments') principalRef(row.user_id);
      if (name === 'report_snapshots' && row.user_id !== '0') principalRef(row.user_id);
      if (name === 'ai_summaries') {
        check(!summaryReports.has(row.report_id), 'Duplicate report summary');
        summaryReports.add(row.report_id);
      }
    }
  }
  for (const [name, field] of [['report_snapshots','version_number'], ['monday_poi_syncs','poi_key']]) {
    const unique = new Set();
    for (const row of indexes[name]?.values() ?? []) {
      const key = `${row.report_id}:${row[field]}`;
      check(row[field] && !unique.has(key), `Duplicate source mapping: ${name}`);
      unique.add(key);
      if (name === 'report_snapshots') {
        let historical;
        try { historical = JSON.parse(row.snapshot_data); } catch { throw new Error('Malformed historical snapshot'); }
        check(historical && typeof historical === 'object' && historical.report, 'Historical report payload missing');
      }
    }
  }
  for (const row of indexes.templates?.values() ?? []) if (row.created_by && row.created_by !== '0') principalRef(row.created_by);
  check(Array.isArray(p.media), 'Media inventory missing');
  const mediaPhotos = new Set();
  for (const media of p.media) {
    const photo = indexes.photos.get(media.photo_id);
    check(photo && !mediaPhotos.has(media.photo_id) && photo.drive_file_id === media.source_ref, 'Media reference mismatch');
    check(['wordpress', 'drive', 'missing'].includes(media.kind), 'Unknown media storage');
    mediaPhotos.add(media.photo_id);
  }
  check(mediaPhotos.size === indexes.photos.size, 'Incomplete media inventory');
  return { payload: p, sha256: envelope.sha256, indexes, counts,
    limitations: ['Media bytes and access have not been migrated', 'Browser offline drafts are outside this snapshot',
      'Source grants are inventory only; fresh reviewed app grants are required', 'Full legacy authoring, AI, PDF, notification, Monday and version-restore parity remain unverified'] };
}

export function readSnapshot(path, source) { return validateSnapshot(readFileSync(path, 'utf8'), source); }
