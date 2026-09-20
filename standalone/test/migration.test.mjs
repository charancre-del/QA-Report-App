import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { packSnapshot, validateSnapshot } from '../src/snapshot.mjs';
import { importSnapshot } from '../src/import.mjs';
import { payload, snapshot, source } from './fixtures.mjs';

test('preserves all source records, raw schemas, identifiers beyond Number precision and historical content', () => {
  const result = validateSnapshot(snapshot(), source);
  assert.deepEqual(result.payload, payload());
  assert.equal(result.indexes.reports.get('9007199254740993').closing_notes, '<script>unsafe()</script>');
  assert.deepEqual(result.counts, { schools: 2, reports: 2, responses: 1, photos: 1, ai_summaries: 1, templates: 1, workflow_comments: 1 });
});
const invalid = [
  ['wrong source', p => { p.source.instance = 'other'; }, /Source instance/],
  ['missing required table', p => { delete p.tables.photos; }, /Required table/],
  ['unknown table', p => { p.tables.unreviewed = p.tables.photos; }, /Unreviewed source/],
  ['mismatched total', p => { p.tables.reports.count = 3; }, /Control total/],
  ['duplicate ID', p => { p.tables.reports.rows[1].id = p.tables.reports.rows[0].id; }, /duplicate ID/],
  ['unsafe integer', p => { p.tables.reports.rows[0].id = 9007199254740993; }, /Invalid/],
  ['missing column', p => { delete p.tables.reports.rows[0].closing_notes; }, /Schema drift/],
  ['orphan school', p => { p.tables.reports.rows[0].school_id = '999'; }, /Orphan report school/],
  ['orphan response', p => { p.tables.responses.rows[0].report_id = '999'; }, /Orphan report child/],
  ['cross-school history', p => { p.tables.reports.rows[0].previous_report_id = '22'; }, /history reference/],
  ['unmapped user', p => { p.principals.pop(); }, /principal reference/],
  ['missing media inventory', p => { p.media = []; }, /Incomplete media/],
  ['wrong media reference', p => { p.media[0].source_ref = 'other'; }, /Media reference mismatch/],
  ['nontransactional export', p => { p.consistency = 'unknown'; }, /Consistent database/]
];
for (const [name, mutate, message] of invalid) test(`rejects ${name}`, () => {
  const p = payload(); mutate(p); assert.throws(() => validateSnapshot(packSnapshot(p), source), message);
});
test('tampering is rejected before import', () => {
  assert.throws(() => validateSnapshot(snapshot().replace('Synthetic North', 'Synthetic West'), source), /checksum mismatch/);
});
test('current 1.4.1 source requires and preserves snapshot and Monday mapping tables', () => {
  const p = payload(); p.source.plugin_db_version = '1.4.1';
  assert.throws(() => validateSnapshot(packSnapshot(p), source), /Required 1.4 source table missing/);
  const table = rows => ({ rows, count: rows.length, columns: Object.keys(rows[0]), schema: 'synthetic schema' });
  p.tables.report_snapshots = table([{ id: '1', report_id: '9007199254740993', version_number: '1', user_id: '0', snapshot_data: JSON.stringify({ report: { id: '9007199254740993' } }) }]);
  p.tables.monday_poi_syncs = table([{ id: '1', report_id: '9007199254740993', poi_key: 'synthetic-key' }]);
  const result = validateSnapshot(packSnapshot(p), source);
  assert.equal(result.counts.report_snapshots, 1); assert.equal(result.counts.monday_poi_syncs, 1);
  p.tables.report_snapshots.rows[0].snapshot_data = 'invalid';
  assert.throws(() => validateSnapshot(packSnapshot(p), source), /Malformed historical snapshot/);
});
test('imports persist, exact retry is idempotent, and an older snapshot remains available for rollback', t => {
  const directory = mkdtempSync(join(tmpdir(), 'cqa-migration-test-'));
  t.after(() => rmSync(directory, { recursive: true }));
  const input = join(directory, 'source.json');
  writeFileSync(input, snapshot());
  const first = importSnapshot(input, join(directory, 'private'), source);
  assert.equal(first.cutover_ready, false);
  assert.equal(first.replay, false);
  assert.equal(importSnapshot(input, join(directory, 'private'), source).replay, true);
  assert.equal(readFileSync(first.path, 'utf8'), snapshot());
  const next = payload(); next.tables.reports.rows[0].closing_notes = 'Changed synthetic source note';
  writeFileSync(input, packSnapshot(next));
  const second = importSnapshot(input, join(directory, 'private'), source);
  assert.notEqual(second.path, first.path);
  assert.equal(validateSnapshot(readFileSync(first.path, 'utf8'), source).indexes.reports.get('9007199254740993').closing_notes, '<script>unsafe()</script>');
});
