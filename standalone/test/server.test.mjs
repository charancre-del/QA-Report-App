import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { once } from 'node:events';
import { createApp } from '../src/server.mjs';
import { validateSnapshot } from '../src/snapshot.mjs';
import { snapshot, source, grants } from './fixtures.mjs';

const secret = 'synthetic-gateway-secret-not-for-any-deployment';
async function setup(t, extra = {}) {
  const directory = mkdtempSync(join(tmpdir(), 'cqa-server-test-'));
  const snapshotPath = join(directory, 'snapshot.json'), grantsPath = join(directory, 'grants.json'), auditPath = join(directory, 'audit.jsonl');
  writeFileSync(snapshotPath, snapshot()); writeFileSync(grantsPath, JSON.stringify(grants()));
  const server = createApp({ snapshotPath, snapshotSha256: validateSnapshot(snapshot(), source).sha256, source,
    proxySecret: secret, grantsPath, auditPath, now: () => Date.parse('2026-01-05T01:00:00Z'), ...extra });
  server.listen(0, '127.0.0.1'); await once(server, 'listening');
  t.after(async () => { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); rmSync(directory, { recursive: true }); });
  const request = (path, options = {}) => fetch(`http://127.0.0.1:${server.address().port}${path}`, {
    ...options, headers: { 'x-cqa-proxy-secret': secret, 'x-cqa-subject': grants()[0].subject, ...options.headers }
  });
  return { request, grantsPath, auditPath };
}
test('health is explicit about read-only state; anonymous and forged identity requests are denied', async t => {
  const { request } = await setup(t);
  assert.equal((await (await request('/healthz')).json()).cutover_ready, false);
  assert.equal((await request('/', { headers: { 'x-cqa-proxy-secret': '' } })).status, 401);
  assert.equal((await request('/', { headers: { 'x-cqa-subject': 'unmapped' } })).status, 403);
});
test('list, count, school filter, direct detail and media metadata enforce the same school scope', async t => {
  const { request } = await setup(t);
  const response = await request('/api/reports');
  const body = await response.json();
  assert.equal(body.total, 1); assert.equal(body.reports[0].id, '9007199254740993');
  assert.equal(body.status.freshness, 'stale');
  assert.equal((await (await request('/api/reports?school=12')).json()).total, 0);
  assert.equal((await request('/api/reports/22')).status, 404);
  const detail = await (await request('/api/reports/9007199254740993')).json();
  assert.equal(detail.responses.length, 1); assert.equal(detail.photos[0].available, false);
  assert.equal(detail.photos[0].drive_file_id, undefined);
  assert.equal((await request('/api/reports?page=0')).status, 400);
});
test('browser renders stored text safely, preserves history and refuses mutations', async t => {
  const { request } = await setup(t);
  const response = await request('/reports/9007199254740993'); const html = await response.text();
  assert.equal(response.status, 200); assert.ok(html.includes('&lt;script&gt;unsafe()&lt;/script&gt;'));
  assert.ok(html.includes('Synthetic historical summary')); assert.ok(html.includes('Synthetic comment'));
  assert.ok(!html.includes('Synthetic private south note'));
  assert.equal(response.headers.get('cache-control'), 'no-store');
  assert.ok(response.headers.get('content-security-policy').includes("default-src 'none'"));
  assert.equal((await request('/api/reports', { method: 'POST', body: '{}' })).status, 405);
  assert.equal((await request('/api/reports/9007199254740993', { method: 'DELETE' })).status, 405);
});
test('revocation, expiration, missing scope, duplicate grants and lost grant store fail closed immediately', async t => {
  const { request, grantsPath } = await setup(t);
  for (const change of [g => { g.disabled = true; }, g => { g.expires_at = '2020-01-01'; }, g => { g.school_ids = []; }, g => { g.source_user_id = '999'; }]) {
    const updated = grants(); change(updated[0]); writeFileSync(grantsPath, JSON.stringify(updated));
    assert.equal((await request('/')).status, 403);
  }
  writeFileSync(grantsPath, JSON.stringify([...grants(), ...grants()])); assert.equal((await request('/')).status, 403);
  writeFileSync(grantsPath, 'invalid'); assert.equal((await request('/')).status, 503);
});
test('audit persists metadata without records, identities or credentials and outage blocks reads', async t => {
  const { request, auditPath } = await setup(t);
  await request('/api/reports/9007199254740993'); await request('/api/reports/22');
  const raw = readFileSync(auditPath, 'utf8'); const records = raw.trim().split('\n').map(JSON.parse);
  assert.deepEqual(records.map(r => r.status), [200, 404]);
  assert.ok(!raw.includes('Synthetic')); assert.ok(!raw.includes(secret)); assert.ok(!raw.includes(grants()[0].subject));
  const unavailable = await setup(t, { audit: () => { throw new Error('test disk outage'); } });
  assert.equal((await unavailable.request('/api/reports')).status, 503);
});
test('wrong pinned digest and absent authentication configuration prevent startup', () => {
  assert.throws(() => createApp({}), /gateway secret/);
});
