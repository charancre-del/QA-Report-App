import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { once } from 'node:events';
import { randomUUID } from 'node:crypto';
import sharp from 'sharp';
import { migrateDatabase, InspectionStore, restoreDatabase } from '../src/store.mjs';
import { createApp } from '../src/server.mjs';
import { validateSnapshot, digest } from '../src/snapshot.mjs';
import { snapshot, source, grants } from './fixtures.mjs';

const checklist = { version: 'synthetic-v1', reviewed_by: 'synthetic-reviewer', sections: [{ key: 'synthetic', name: 'Synthetic checklist',
  items: [{ key: 'observation', label: 'Synthetic observation' }, { key: 'documentation', label: 'Synthetic documentation' }] }] };
const responseInput = revision => ({ revision, responses: checklist.sections[0].items.map(item => ({ section_key: 'synthetic', item_key: item.key,
  rating: 'yes', notes: 'Synthetic inspection evidence', evidence_type: 'observation' })), closing_notes: 'Synthetic closing notes', overall_rating: 'meets' });
const operator = () => ({ subject: 'test-issuer|opaque-101', allowed: id => id === '11', grant: { source_user_id: '101', role: 'cqa_qa_officer',
  capabilities: ['cqa_create_reports','cqa_edit_own_reports','cqa_view_all_reports'] } });
const reviewer = () => ({ subject: 'test-issuer|opaque-102', allowed: id => id === '11', grant: { source_user_id: '102', role: 'cqa_regional_director',
  capabilities: ['cqa_view_all_reports','cqa_approve_reports'] } });
function setupStore(t) {
  const dir = mkdtempSync(join(tmpdir(), 'cqa-native-test-')), path = join(dir, 'app.sqlite');
  migrateDatabase(path, source); migrateDatabase(path, source);
  const store = new InspectionStore(path, source);
  t.after(() => { try { store.close(); } catch {} rmSync(dir, { recursive: true }); });
  return { dir, path, store, snap: validateSnapshot(snapshot(), source) };
}
const make = (store, snap, access = operator(), key = randomUUID()) => store.create(access, key, { school_id: '11', report_type: 'tier1', inspection_date: '2026-01-04' }, snap, checklist);

test('native workflow persists checklist, revisions, separate approval, atomic audit and blocked notifications', t => {
  const { store, snap } = setupStore(t); const a = operator(), r = reviewer();
  const created = make(store, snap);
  assert.equal(created.revision, 1);
  assert.throws(() => store.transition(a, randomUUID(), created.id, { revision: 1, action: 'submit', comment: '' }), /incomplete_inspection/);
  store.save(a, randomUUID(), created.id, responseInput(1));
  store.transition(a, randomUUID(), created.id, { revision: 2, action: 'submit', comment: '' });
  assert.throws(() => store.save(a, randomUUID(), created.id, responseInput(3)), /report_locked/);
  assert.throws(() => store.transition(a, randomUUID(), created.id, { revision: 3, action: 'start_review', comment: '' }), /access_denied/);
  store.transition(r, randomUUID(), created.id, { revision: 3, action: 'start_review', comment: 'Synthetic reviewed' });
  const approved = store.transition(r, randomUUID(), created.id, { revision: 4, action: 'approve', comment: 'Synthetic approved' });
  assert.equal(approved.status, 'approved'); assert.equal(approved.notification, 'blocked_adapter');
  assert.equal(store.detail(created.id, a).responses.length, 2);
  assert.equal(store.detail(created.id, a).workflow.length, 3);
  assert.equal(store.db.prepare('SELECT count(*) AS n FROM audit').get().n, 5);
  assert.equal(store.db.prepare("SELECT count(*) AS n FROM outbox WHERE state='blocked_adapter'").get().n, 3);
});
test('safe retries, stale edits and changed idempotency bodies cannot duplicate or overwrite reports', t => {
  const { store, snap } = setupStore(t), key = randomUUID(); const first = make(store, snap, operator(), key);
  assert.equal(make(store, snap, operator(), key).replay, true);
  assert.equal(store.list(operator()).length, 1);
  assert.throws(() => store.create(operator(), key, { school_id: '11', report_type: 'tier1', inspection_date: '2026-01-05' }, snap, checklist), /idempotency_conflict/);
  const saveKey = randomUUID(); store.save(operator(), saveKey, first.id, responseInput(1));
  assert.equal(store.save(operator(), saveKey, first.id, responseInput(1)).replay, true);
  assert.throws(() => store.save(operator(), randomUUID(), first.id, responseInput(1)), /stale_revision/);
  assert.equal(store.detail(first.id, operator()).report.revision, 2);
});
test('out-of-scope users, viewers and self-approval are denied; audit failure rolls back the transaction', t => {
  const { store, snap } = setupStore(t), created = make(store, snap);
  const stranger = { ...operator(), allowed: () => false };
  assert.equal(store.list(stranger).length, 0);
  assert.throws(() => store.detail(created.id, stranger), /not_found/);
  assert.throws(() => make(store, snap, stranger), /access_denied/);
  const viewOnly = { ...operator(), grant: { role: 'cqa_program_manager', capabilities: ['cqa_view_own_reports'] } };
  assert.throws(() => store.save(viewOnly, randomUUID(), created.id, responseInput(1)), /access_denied/);
  store.db.exec("CREATE TRIGGER test_audit_outage BEFORE INSERT ON audit BEGIN SELECT RAISE(ABORT,'synthetic audit outage'); END");
  assert.throws(() => store.save(operator(), randomUUID(), created.id, responseInput(1)), /synthetic audit outage/);
  assert.equal(store.detail(created.id, operator()).report.revision, 1);
  assert.equal(store.detail(created.id, operator()).responses.length, 0);
});
test('backup restores into an isolated new database with source identity, controls and idempotency intact', async t => {
  const { store, snap, dir } = setupStore(t), key = randomUUID(); const report = make(store, snap, operator(), key);
  store.save(operator(), randomUUID(), report.id, responseInput(1));
  const backupPath = join(dir, 'backup.sqlite'), restoredPath = join(dir, 'restored.sqlite');
  const manifest = await store.backup(backupPath);
  assert.throws(() => restoreDatabase(backupPath, restoredPath, 'wrong', source), /checksum mismatch/);
  assert.throws(() => restoreDatabase(backupPath, restoredPath, manifest.sha256, 'wrong-source'), /reconciliation failed/);
  restoreDatabase(backupPath, restoredPath, manifest.sha256, source);
  const restored = new InspectionStore(restoredPath, source);
  try {
    assert.equal(restored.detail(report.id, operator()).report.revision, 2);
    assert.equal(restored.detail(report.id, operator()).responses.length, 2);
    assert.equal(make(restored, snap, operator(), key).replay, true);
    assert.throws(() => restoreDatabase(backupPath, restoredPath, manifest.sha256, source), /EEXIST/);
  } finally { restored.close(); }
});

test('HTTP create/save/submit/review/approve survives database readback and renders a real report', async t => {
  const { store, snap, dir } = setupStore(t);
  const files = { snapshotPath: join(dir, 'source.json'), grantsPath: join(dir, 'grants.json'), checklistPath: join(dir, 'checklists.json'), auditPath: join(dir, 'audit.jsonl') };
  const definitions = JSON.stringify({ types: { tier1: checklist } });
  writeFileSync(files.snapshotPath, snapshot()); writeFileSync(files.checklistPath, definitions);
  const allGrants = [operator(), reviewer()].map(a => ({ ...grants()[0], ...a.grant, capabilities: [...a.grant.capabilities, 'cqa_export_reports'], subject: a.subject, action: 'qa:operate', region: 'test-north' }));
  writeFileSync(files.grantsPath, JSON.stringify(allGrants));
  const secret = 'synthetic-gateway-secret-never-for-production', origin = 'https://synthetic-app.invalid';
  const server = createApp({ ...files, store, source, snapshotSha256: snap.sha256, proxySecret: secret, writesEnabled: true,
    checklistSha256: digest(definitions), publicOrigin: origin, now: () => Date.parse('2026-01-04T12:00:00Z'),
    mediaDirectory: join(dir, 'media'), pdfFontPath: process.env.CQA_TEST_PDF_FONT ?? (process.platform === 'win32' ? 'C:/Windows/Fonts/arial.ttf' : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf') });
  server.listen(0, '127.0.0.1'); await once(server, 'listening');
  t.after(async () => { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); });
  const request = (path, actor = operator(), options = {}) => fetch(`http://127.0.0.1:${server.address().port}${path}`, {
    ...options, headers: { 'x-cqa-proxy-secret': secret, 'x-cqa-subject': actor.subject, ...options.headers }, redirect: 'manual'
  });
  const newForm = await (await request('/work/new')).text();
  assert.ok(newForm.includes('Synthetic North')); assert.ok(!newForm.includes('Synthetic South'));
  const token = /name="csrf" value="([a-f0-9]+)"/.exec(newForm)[1];
  const post = async (path, body, actor = operator(), csrf = token) => request(path, actor, { method: 'POST', body: JSON.stringify(body), headers: {
    'content-type': 'application/json', origin, 'x-cqa-csrf': csrf, 'idempotency-key': randomUUID() } });
  assert.equal((await post('/api/work/reports', {}, operator(), 'wrong')).status, 403);
  const createdResponse = await post('/api/work/reports', { school_id: '11', report_type: 'tier1', inspection_date: '2026-01-04' });
  assert.equal(createdResponse.status, 200); const created = await createdResponse.json();
  const image = await sharp({ create: { width: 10, height: 10, channels: 3, background: '#3d8f80' } }).png().toBuffer();
  const form = new FormData(); form.set('file', new Blob([image], { type: 'image/png' }), 'synthetic.png');
  form.set('caption', 'Synthetic HTTP photo'); form.set('revision', '1'); form.set('csrf', token); form.set('idempotency_key', randomUUID());
  const uploaded = await request(`/api/work/reports/${created.id}/photos`, operator(), { method: 'POST', body: form, headers: { origin } });
  assert.equal(uploaded.status, 200); const photo = await uploaded.json();
  const download = await request(`/api/work/reports/${created.id}/photos/${photo.photo_id}`);
  assert.equal(download.status, 200); assert.equal(download.headers.get('content-type'), 'image/jpeg');
  assert.equal((await post(`/api/work/reports/${created.id}/save`, responseInput(2))).status, 200);
  assert.equal((await post(`/api/work/reports/${created.id}/transition`, { revision: 3, action: 'submit' })).status, 200);
  const reviewForm = await (await request(`/work/${created.id}`, reviewer())).text();
  const reviewerToken = /name="csrf" value="([a-f0-9]+)"/.exec(reviewForm)[1];
  assert.equal((await post(`/api/work/reports/${created.id}/transition`, { revision: 4, action: 'start_review' }, reviewer(), reviewerToken)).status, 200);
  assert.equal((await post(`/api/work/reports/${created.id}/transition`, { revision: 5, action: 'approve' }, reviewer(), reviewerToken)).status, 200);
  const detail = await (await request(`/api/work/reports/${created.id}`)).json(); assert.equal(detail.report.status, 'approved');
  const finalPage = await (await request(`/work/${created.id}`)).text(); assert.ok(finalPage.includes('Synthetic closing notes'));
  assert.ok(finalPage.includes('approved')); assert.ok(!finalPage.includes('name="rating.'));
  assert.equal(store.record(created.id, operator()).status, 'approved');
  assert.equal((await (await request('/readyz')).json()).cutover_ready, false);
  assert.equal((await post(`/api/work/reports/${created.id}/ai`, {})).status, 503);
  const pdf = await request(`/api/work/reports/${created.id}/pdf`); assert.equal(pdf.status, 200);
  assert.equal(pdf.headers.get('content-type'), 'application/pdf'); assert.equal(Buffer.from(await pdf.arrayBuffer()).subarray(0,5).toString(), '%PDF-');
  allGrants[0].school_ids = ['12']; writeFileSync(files.grantsPath, JSON.stringify(allGrants));
  assert.equal((await request(`/api/work/reports/${created.id}/photos/${photo.photo_id}`)).status, 404);
  assert.equal((await request(`/api/work/reports/${created.id}/pdf`)).status, 404);
});
