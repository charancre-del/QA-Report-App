import http from 'node:http';
import { createHmac, timingSafeEqual, randomUUID } from 'node:crypto';
import { readFileSync, openSync, writeSync, fsyncSync, closeSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { readSnapshot } from './snapshot.mjs';
import { nativeRoute } from './native-routes.mjs';
import { InspectionStore } from './store.mjs';

const fail = (status, code) => { const error = new Error(code); error.status = status; throw error; };
const equal = (a, b) => typeof a === 'string' && Buffer.byteLength(a) === Buffer.byteLength(b) && timingSafeEqual(Buffer.from(a), Buffer.from(b));
const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const page = (title, content) => `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${escape(title)} · Chroma QA</title><link rel="stylesheet" href="/app.css"></head><body><header><a href="/work">Chroma QA</a><span>Quality assurance</span></header><main>${content}</main></body></html>`;
const css = 'body{margin:0;background:#f5f7fa;color:#172c3b;font:17px/1.6 system-ui,sans-serif}header{background:#153d4a;color:white;padding:1rem 3vw;display:flex;justify-content:space-between}header a{color:white;font-weight:700}main{max-width:1080px;margin:2rem auto;padding:0 1rem}a{color:#00677b}h1{line-height:1.2}.notice{background:#fff4d6;border-left:4px solid #b77900;padding:1rem}article,table{background:white;width:100%;border-collapse:collapse;margin:1rem 0}th,td{padding:.7rem;text-align:left;border-bottom:1px solid #d7e0e5;vertical-align:top}p,td{overflow-wrap:anywhere;white-space:pre-wrap}.muted{color:#536773}article{box-sizing:border-box;padding:1rem}input{padding:.5rem}button{padding:.5rem 1rem}small{display:block}@media(max-width:600px){table{font-size:14px}th,td{padding:.4rem}}';

function authorize(req, config, snapshot, now) {
  if (!equal(req.headers['x-cqa-proxy-secret'], config.proxySecret)) fail(401, 'authentication_required');
  const subject = req.headers['x-cqa-subject'];
  if (typeof subject !== 'string' || subject.length > 255 || !subject) fail(401, 'authentication_required');
  let grants;
  try { grants = JSON.parse(readFileSync(config.grantsPath, 'utf8')); } catch { fail(503, 'access_configuration_unavailable'); }
  if (!Array.isArray(grants)) fail(503, 'access_configuration_unavailable');
  const matches = grants.filter(g => g.subject === subject && g.source_instance === snapshot.payload.source.instance);
  if (matches.length !== 1) fail(403, 'access_denied');
  const grant = matches[0];
  if (grant.disabled !== false || !['archive:read','qa:operate'].includes(grant.action) || !grant.reviewed_by ||
      !Number.isFinite(Date.parse(grant.expires_at)) || Date.parse(grant.expires_at) <= now ||
      !(grant.native_identity === true && grant.source_user_id === null || snapshot.payload.principals.some(p => p.id === grant.source_user_id && p.exists)) ||
      !(grant.all_schools === true || (Array.isArray(grant.school_ids) && grant.school_ids.length > 0 &&
          grant.school_ids.every(s => typeof s === 'string' && snapshot.indexes.schools.has(s))))) fail(403, 'access_denied');
  if (grant.action === 'qa:operate' && !['cqa_super_admin','cqa_qa_officer','cqa_regional_director','cqa_program_manager'].includes(grant.role)) fail(403, 'access_denied');
  if (grant.role === 'cqa_regional_director' && (!(typeof grant.region === 'string' && grant.region.trim()) || grant.all_schools === true)) fail(403, 'access_denied');
  if (grant.role === 'cqa_program_manager' && (grant.all_schools === true || grant.school_ids.length !== 1)) fail(403, 'access_denied');
  return { subject, grant, allowed: school => {
    const inScope = grant.all_schools === true || grant.school_ids.includes(school);
    return inScope && (grant.role !== 'cqa_regional_director' || snapshot.indexes.schools.get(school)?.region === grant.region);
  } };
}

function durableAudit(path, event) {
  const fd = openSync(path, 'a', 0o600);
  try { writeSync(fd, JSON.stringify(event) + '\n'); fsyncSync(fd); } finally { closeSync(fd); }
}

export function createApp(config) {
  if (!config.proxySecret || config.proxySecret.startsWith('REPLACE_') || Buffer.byteLength(config.proxySecret) < 32) throw new Error('A gateway secret of at least 32 bytes is required');
  if (!config.auditPath || !config.grantsPath) throw new Error('Protected audit and grant files are required');
  const snapshot = readSnapshot(config.snapshotPath, config.source);
  if (snapshot.sha256 !== config.snapshotSha256) throw new Error('Pinned snapshot digest mismatch');
  const maxAge = config.maxAgeHours ?? 24;
  if (!Number.isFinite(maxAge) || maxAge <= 0) throw new Error('Invalid freshness threshold');
  const audit = config.audit ?? (event => durableAudit(config.auditPath, event));
  const now = config.now ?? Date.now;
  return http.createServer(async (req, res) => {
    const requestId = randomUUID();
    let code = 200, body, type = 'application/json; charset=utf-8', action = 'unknown', subject = '', responseHeaders = {};
    try {
      const url = new URL(req.url, 'http://localhost');
      if (url.pathname === '/healthz' && req.method === 'GET') {
        res.writeHead(200, { 'Cache-Control': 'no-store', 'Content-Type': type });
        res.end(JSON.stringify({ status: 'ok', mode: config.store ? 'standalone-pilot' : 'read-only-review', cutover_ready: false })); return;
      }
      const access = authorize(req, config, snapshot, now());
      subject = access.subject;
      action = 'read';
      if (config.store && (url.pathname === '/readyz' || url.pathname.startsWith('/work') || url.pathname.startsWith('/api/work/'))) {
        const result = await nativeRoute(req, url, access, snapshot, { ...config, now }, { escape, page });
        body = result.body; code = result.status ?? 200; type = result.type ?? type; responseHeaders = result.headers ?? {};
      }
      else if (req.method !== 'GET') fail(405, 'read_only');
      else if (url.pathname === '/app.css') { body = css; type = 'text/css; charset=utf-8'; }
      else {
        const stale = now() - Date.parse(snapshot.payload.exported_at) > maxAge * 3600000;
        const status = { mode: 'read-only-review', cutover_ready: false, freshness: stale ? 'stale' : 'snapshot',
          exported_at: snapshot.payload.exported_at, sha256: snapshot.sha256, limitations: snapshot.limitations };
        const notice = `<div class="notice"><strong>${stale ? 'Stale archive' : 'Historical snapshot'} — read only</strong><br>Captured ${escape(status.exported_at)}. New inspections and changes remain in WordPress. Photo files, offline drafts, PDF export and approvals are not available here.</div>`;
        const match = /^\/(?:api\/)?reports\/([1-9][0-9]{0,19})$/.exec(url.pathname);
        if (match) {
          const report = snapshot.indexes.reports.get(match[1]);
          if (!report || !access.allowed(report.school_id)) fail(404, 'not_found');
          const children = name => [...(snapshot.indexes[name]?.values() ?? [])].filter(r => r.report_id === report.id);
          const detail = { report, school: snapshot.indexes.schools.get(report.school_id), responses: children('responses'),
            photos: children('photos').filter(p => !p.deleted_at).map(p => ({ id: p.id, section_key: p.section_key, caption: p.caption, available: false })),
            summaries: children('ai_summaries'), workflow: children('workflow_comments'),
            versions: children('report_snapshots').map(v => ({ id: v.id, version_number: v.version_number, snapshot_type: v.snapshot_type, change_summary: v.change_summary, created_at: v.created_at, restore_available: false })), status };
          if (url.pathname.startsWith('/api/')) body = detail;
          else {
            type = 'text/html; charset=utf-8';
            body = page(`Report ${report.id}`, `${notice}<p><a href="/">← Reports</a></p><h1>${escape(detail.school.name)}</h1><p>${escape(report.inspection_date)} · ${escape(report.report_type)} · ${escape(report.status)} · ${escape(report.overall_rating)}</p><p>Source report #${escape(report.id)} · Source author #${escape(report.user_id)}</p><article><h2>Closing notes</h2><p>${escape(report.closing_notes)}</p></article><h2>Inspection checklist</h2><table><thead><tr><th>Section / item</th><th>Rating</th><th>Notes</th></tr></thead><tbody>${detail.responses.map(r => `<tr><td>${escape(r.section_key)}<br>${escape(r.item_key)}</td><td>${escape(r.rating)}</td><td>${escape(r.notes)}</td></tr>`).join('')}</tbody></table><h2>Photos</h2>${detail.photos.map(p => `<article><p>${escape(p.section_key)} — ${escape(p.caption)}</p><small>Photo #${escape(p.id)}: file migration and access verification pending.</small></article>`).join('') || '<p>No photo records.</p>'}<h2>Stored AI summary</h2>${detail.summaries.map(s => `<article><p>${escape(s.executive_summary)}</p><small>Historical generated text; requires human review.</small></article>`).join('') || '<p>No summary records.</p>'}<h2>Workflow history</h2>${detail.workflow.map(w => `<article><p>${escape(w.created_at)} · ${escape(w.action)} · Source user #${escape(w.user_id)}</p><p>${escape(w.comment)}</p></article>`).join('') || '<p>No recorded workflow comments.</p>'}`);
          }
        } else if (['/', '/api/reports'].includes(url.pathname)) {
          const pageNumber = Number(url.searchParams.get('page') || '1');
          if (!Number.isSafeInteger(pageNumber) || pageNumber < 1) fail(400, 'invalid_page');
          const schoolId = url.searchParams.get('school');
          const reports = [...snapshot.indexes.reports.values()].filter(r => access.allowed(r.school_id) && (!schoolId || r.school_id === schoolId))
            .sort((a, b) => String(b.inspection_date).localeCompare(String(a.inspection_date)) || (BigInt(a.id) < BigInt(b.id) ? 1 : -1));
          const rows = reports.slice((pageNumber - 1) * 50, pageNumber * 50);
          if (url.pathname.startsWith('/api/')) body = { reports: rows, total: reports.length, page: pageNumber, page_size: 50, status };
          else {
            type = 'text/html; charset=utf-8';
            const href = n => `/?page=${n}${schoolId ? '&amp;school=' + encodeURIComponent(schoolId) : ''}`;
            body = page('Reports', `${notice}<h1>Inspection reports</h1><p class="muted">${reports.length} reports in your approved school scope</p><table><thead><tr><th>School</th><th>Visit</th><th>Type</th><th>Status</th></tr></thead><tbody>${rows.map(r => `<tr><td><a href="/reports/${r.id}">${escape(snapshot.indexes.schools.get(r.school_id).name)}</a></td><td>${escape(r.inspection_date)}</td><td>${escape(r.report_type)}</td><td>${escape(r.status)}</td></tr>`).join('')}</tbody></table><nav aria-label="Report pages">${pageNumber > 1 ? `<a href="${href(pageNumber - 1)}">Previous</a> ` : ''} Page ${pageNumber} ${pageNumber * 50 < reports.length ? `<a href="${href(pageNumber + 1)}">Next</a>` : ''}</nav>`);
          }
        } else fail(404, 'not_found');
      }
    } catch (error) {
      code = error.status ?? 503; const reason = error.status ? error.message : 'service_unavailable';
      body = { error: reason, request_id: requestId };
      if (req.url?.startsWith('/work') && req.headers.accept?.includes('text/html')) {
        const explanations = { stale_revision: 'This inspection changed since you opened it. Return to the report, review the latest version, and reapply your changes.',
          incomplete_inspection: 'Answer every checklist item and select an overall rating before submitting.',
          writes_disabled: 'Changes are temporarily disabled. Your submitted changes were not saved.',
          access_denied: 'Your account does not have permission for this action.',
          report_locked: 'This inspection is in review or approved and cannot be edited.',
          invalid_image: 'The selected file could not be read as a valid image.', image_file_required: 'Select a JPEG or PNG photo to upload.' };
        type = 'text/html; charset=utf-8';
        body = page('Action unavailable', `<h1>Action unavailable</h1><p>${escape(explanations[reason] ?? 'The action could not be completed. Return to the inspection and check its current state before retrying.')}</p><p><a href="/work">Return to inspections</a></p><small>Reference ${requestId}</small>`);
      }
    }
    try {
      audit({ at: new Date(now()).toISOString(), request_id: requestId, action, status: code,
        principal_hash: subject ? createHmac('sha256', config.proxySecret).update(subject).digest('hex') : null, snapshot: snapshot.sha256 });
    } catch { code = 503; body = { error: 'audit_unavailable', request_id: requestId }; type = 'application/json; charset=utf-8'; }
    res.writeHead(code, { ...responseHeaders, 'Content-Type': type, 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff',
      'Referrer-Policy': 'same-origin', 'Content-Security-Policy': "default-src 'none'; style-src 'self'; img-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'" });
    res.end(typeof body === 'string' || Buffer.isBuffer(body) ? body : JSON.stringify(body));
  });
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  try {
    const store = process.env.CQA_DATABASE_PATH ? new InspectionStore(process.env.CQA_DATABASE_PATH, process.env.CQA_SOURCE_INSTANCE) : undefined;
    const server = createApp({ snapshotPath: process.env.CQA_SNAPSHOT_PATH, source: process.env.CQA_SOURCE_INSTANCE,
      snapshotSha256: process.env.CQA_SNAPSHOT_SHA256, proxySecret: process.env.CQA_PROXY_SECRET,
      grantsPath: process.env.CQA_GRANTS_PATH, auditPath: process.env.CQA_AUDIT_PATH, store,
      writesEnabled: process.env.CQA_WRITES_ENABLED === 'true', publicOrigin: process.env.CQA_PUBLIC_ORIGIN,
      checklistPath: process.env.CQA_CHECKLIST_PATH, checklistSha256: process.env.CQA_CHECKLIST_SHA256,
      mediaDirectory: process.env.CQA_MEDIA_DIRECTORY, pdfFontPath: process.env.CQA_PDF_FONT_PATH });
    server.listen(Number(process.env.PORT || 4310), '127.0.0.1', () => console.log('QA archive listening on loopback; writes and cutover disabled'));
  } catch { console.error('QA archive startup failed: verify the protected snapshot, digest, source, grants and audit configuration'); process.exitCode = 1; }
}
