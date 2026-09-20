// Loopback-only, synthetic browser rehearsal. Never reads production configuration or data.
import http from 'node:http';
import { mkdtempSync, writeFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { once } from 'node:events';
import { randomBytes } from 'node:crypto';
import { payload, source, grants } from '../test/fixtures.mjs';
import { packSnapshot, validateSnapshot, digest } from '../src/snapshot.mjs';
import { migrateDatabase, InspectionStore } from '../src/store.mjs';
import { createApp } from '../src/server.mjs';

const directory = mkdtempSync(join(tmpdir(), 'cqa-synthetic-demo-'));
const snapshotPath = join(directory, 'snapshot.json'), databasePath = join(directory, 'app.sqlite');
const grantsPath = join(directory, 'grants.json'), checklistPath = join(directory, 'checklist.json');
const data = payload(); data.exported_at = new Date().toISOString();
if (data.source.instance !== 'synthetic-wordpress' || data.source.url !== 'https://synthetic.invalid') throw new Error('Synthetic fixture required');
const raw = packSnapshot(data); writeFileSync(snapshotPath, raw);
const inspector = { ...grants()[0], action: 'qa:operate', role: 'cqa_qa_officer', capabilities: ['cqa_create_reports','cqa_edit_own_reports','cqa_view_all_reports','cqa_export_reports'] };
const reviewer = { ...grants()[0], subject: 'test-issuer|opaque-102', source_user_id: '102', action: 'qa:operate', role: 'cqa_regional_director', region: 'test-north', capabilities: ['cqa_view_all_reports','cqa_approve_reports','cqa_export_reports'] };
writeFileSync(grantsPath, JSON.stringify([inspector, reviewer]));
const checklist = { version: 'synthetic-demo-v1', reviewed_by: 'synthetic-fixture-only', sections: [{ key: 'synthetic', name: 'Synthetic observation checklist', items: [
  { key: 'observation', label: 'Synthetic environment observation' }, { key: 'documentation', label: 'Synthetic documentation review' }] }] };
const definitions = JSON.stringify({ types: { tier1: checklist, tier1_tier2: checklist, new_acquisition: checklist } }); writeFileSync(checklistPath, definitions);
migrateDatabase(databasePath, source); const store = new InspectionStore(databasePath, source);
const proxySecret = randomBytes(32).toString('hex'), port = 4311;
const pdfFontPath = ['C:/Windows/Fonts/arial.ttf','/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'].find(existsSync);
const app = createApp({ snapshotPath, snapshotSha256: validateSnapshot(raw, source).sha256, source, proxySecret, grantsPath,
  auditPath: join(directory, 'audit.jsonl'), store, writesEnabled: true, publicOrigin: `http://127.0.0.1:${port}`, checklistPath, checklistSha256: digest(definitions), mediaDirectory: join(directory, 'media'), pdfFontPath });
app.listen(0, '127.0.0.1'); await once(app, 'listening');
const gateway = http.createServer((req,res) => {
  if (['/demo-as-inspector','/demo-as-reviewer'].includes(req.url)) {
    const role = req.url.endsWith('reviewer') ? 'reviewer' : 'inspector';
    res.writeHead(303, { Location: '/work', 'Set-Cookie': `synthetic_role=${role}; HttpOnly; SameSite=Strict; Path=/`, 'Cache-Control': 'no-store' }); res.end(); return;
  }
  const actor = req.headers.cookie?.includes('synthetic_role=reviewer') ? reviewer : inspector;
  const upstream = http.request({ host: '127.0.0.1', port: app.address().port, path: req.url, method: req.method,
    headers: { ...req.headers, 'x-cqa-proxy-secret': proxySecret, 'x-cqa-subject': actor.subject } }, response => {
    res.writeHead(response.statusCode, response.headers); response.pipe(res);
  });
  upstream.on('error', () => { res.writeHead(503); res.end('Synthetic demo unavailable'); }); req.pipe(upstream);
});
gateway.listen(port, '127.0.0.1', () => console.log(`Synthetic browser rehearsal: http://127.0.0.1:${port}/work ; reviewer switch: /demo-as-reviewer ; private fixture directory: ${directory}`));
process.on('SIGINT', () => { gateway.closeAllConnections(); gateway.close(); app.closeAllConnections(); app.close(); store.close(); process.exit(0); });
