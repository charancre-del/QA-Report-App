import { randomUUID, createHmac, timingSafeEqual } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { digest } from './snapshot.mjs';
import { canCreate, problem } from './store.mjs';
import { storeImage, readImage } from './media.mjs';
import { renderReportPdf } from './pdf.mjs';

const equal = (a,b) => typeof a === 'string' && Buffer.byteLength(a) === Buffer.byteLength(b) && timingSafeEqual(Buffer.from(a), Buffer.from(b));
async function readInput(req) {
  let bytes = 0; const chunks = [];
  for await (const chunk of req) {
    bytes += chunk.length;
    if (bytes > (req.headers['content-type']?.startsWith('multipart/form-data') ? 11 * 1024 * 1024 : 512 * 1024)) problem(413, 'request_too_large');
    chunks.push(chunk);
  }
  const buffer = Buffer.concat(chunks), text = buffer.toString('utf8');
  try {
    if (req.headers['content-type']?.startsWith('multipart/form-data')) {
      return Object.fromEntries(await new Response(buffer, { headers: { 'content-type': req.headers['content-type'] } }).formData());
    }
    if (req.headers['content-type']?.split(';')[0] === 'application/json') return JSON.parse(text);
    if (req.headers['content-type']?.split(';')[0] === 'application/x-www-form-urlencoded') return Object.fromEntries(new URLSearchParams(text));
  } catch { problem(400, 'invalid_body'); }
  problem(415, 'unsupported_content_type');
}

export async function nativeRoute(req, url, access, snapshot, config, ui) {
  const { store } = config, { escape: e, page } = ui;
  const api = url.pathname.startsWith('/api/');
  const token = createHmac('sha256', config.proxySecret).update(`csrf:${access.subject}`).digest('hex');
  const fields = revision => `<input type="hidden" name="csrf" value="${token}"><input type="hidden" name="idempotency_key" value="${randomUUID()}">${revision ? `<input type="hidden" name="revision" value="${revision}">` : ''}`;
  const notice = '<div class="notice"><strong>Standalone inspection pilot</strong><br>Historical WordPress records remain in the archive. AI, Monday sync and email delivery are not enabled. Notifications are retained as blocked work. This pilot has not passed cutover acceptance.</div>';
  const html = (title, content) => ({ type: 'text/html; charset=utf-8', body: page(title, `${notice}<p><a href="/work">Inspections</a> · <a href="/">WordPress archive</a></p>${content}`) });
  const writable = () => {
    if (config.writesEnabled !== true) problem(503, 'writes_disabled');
    // App grants are the live authorization authority. A historical import must not
    // require WordPress to remain online; its age is shown separately in the archive.
    if (access.grant.action !== 'qa:operate') problem(403, 'access_denied');
  };
  if (url.pathname === '/readyz' && req.method === 'GET') return { body: { status: 'limited', cutover_ready: false, source_instance: snapshot.payload.source.instance,
    source_freshness: config.now() - Date.parse(snapshot.payload.exported_at) > (config.maxAgeHours ?? 24) * 3600000 ? 'stale' : 'snapshot',
    writes_enabled: config.writesEnabled === true, database: store.health(), blockers: snapshot.limitations } };
  if (req.method === 'GET') {
    const photoMatch = /^\/api\/work\/reports\/([a-f0-9-]{36})\/photos\/([a-f0-9-]{36})$/.exec(url.pathname);
    if (photoMatch) return { type: 'image/jpeg', body: readImage(store.photo(photoMatch[1], photoMatch[2], access), config.mediaDirectory), headers: { 'Content-Disposition': 'inline; filename="qa-photo.jpg"' } };
    const pdfMatch = /^\/api\/work\/reports\/([a-f0-9-]{36})\/pdf$/.exec(url.pathname);
    if (pdfMatch) {
      const detail = store.detail(pdfMatch[1], access);
      if (!access.grant.capabilities?.includes('cqa_export_reports')) problem(403, 'access_denied');
      return { type: 'application/pdf', body: await renderReportPdf(detail, snapshot.indexes.schools.get(detail.report.school_id), config, store, access), headers: { 'Content-Disposition': 'attachment; filename="qa-inspection.pdf"' } };
    }
    if (['/work','/api/work/reports'].includes(url.pathname)) {
      const reports = store.list(access);
      if (api) return { body: { reports, total: reports.length } };
      return html('Inspections', `<h1>Inspections</h1>${canCreate(access) ? '<p><a href="/work/new">Create inspection</a></p>' : ''}<table><thead><tr><th>School</th><th>Visit</th><th>Status</th><th>Revision</th></tr></thead><tbody>${reports.map(r => `<tr><td><a href="/work/${r.id}">${e(snapshot.indexes.schools.get(r.school_id)?.name ?? r.school_id)}</a></td><td>${e(r.inspection_date)}</td><td>${e(r.status)}</td><td>${r.revision}</td></tr>`).join('')}</tbody></table>`);
    }
    if (url.pathname === '/work/new') {
      if (!canCreate(access)) problem(403, 'access_denied');
      return html('Create inspection', `<h1>Create inspection</h1><form method="post" action="/work/new">${fields()}<p><label>School <select name="school_id" required>${[...snapshot.indexes.schools.values()].filter(s => access.allowed(s.id)).map(s => `<option value="${s.id}">${e(s.name)}</option>`).join('')}</select></label></p><p><label>Visit date <input type="date" name="inspection_date" required></label></p><p><label>Report type <select name="report_type"><option value="tier1">Tier 1</option><option value="tier1_tier2">Tier 1 + Tier 2</option><option value="new_acquisition">New acquisition</option></select></label></p><button>Create draft</button></form>`);
    }
    const match = /^\/(?:api\/work\/reports|work)\/([a-f0-9-]{36})$/.exec(url.pathname);
    if (!match) problem(404, 'not_found');
    const detail = store.detail(match[1], access);
    if (api) return { body: detail };
    const { report: r, checklist, responses } = detail;
    const edit = detail.permissions.edit && ['draft','needs_revision'].includes(r.status);
    const byKey = new Map(responses.map(x => [`${x.section_key}.${x.item_key}`, x]));
    const sections = checklist.sections.map(section => `<article><h2>${e(section.name ?? section.key)}</h2>${section.items.map(item => {
      const k = `${section.key}.${item.key}`, response = byKey.get(k);
      return `<fieldset><legend>${e(item.label)}</legend>${edit ? `<label>Rating <select name="rating.${k}"><option value="">Not answered</option>${['yes','sometimes','no','na'].map(v => `<option value="${v}" ${response?.rating === v ? 'selected' : ''}>${v}</option>`).join('')}</select></label><p><label>Notes <textarea name="notes.${k}" rows="3" maxlength="10000">${e(response?.notes)}</textarea></label></p><label>Evidence <select name="evidence.${k}">${['observation','documentation','interview'].map(v => `<option value="${v}" ${response?.evidence_type === v ? 'selected' : ''}>${v}</option>`).join('')}</select></label>` : `<p>${e(response?.rating ?? 'Not answered')}</p><p>${e(response?.notes)}</p>`}</fieldset>`;
    }).join('')}</article>`).join('');
    const saveForm = edit ? `<form method="post" action="/work/${r.id}/save">${fields(r.revision)}${sections}<p><label>Overall rating <select name="overall_rating">${['pending','exceeds','meets','needs_improvement'].map(v => `<option value="${v}" ${r.overall_rating === v ? 'selected' : ''}>${v}</option>`).join('')}</select></label></p><p><label>Closing notes <textarea name="closing_notes" rows="5" maxlength="20000">${e(r.closing_notes)}</textarea></label></p><button>Save inspection</button></form>` : `${sections}<h2>Closing notes</h2><p>${e(r.closing_notes)}</p>`;
    const actions = edit ? ['submit'] : detail.permissions.review ? r.status === 'submitted' ? ['start_review'] : r.status === 'under_review' ? ['approve','request_revision'] : [] : [];
    const photos = `<h2>Photo evidence</h2>${detail.photos.map(photo => `<article><img src="/api/work/reports/${r.id}/photos/${photo.id}" alt="${e(photo.caption || 'Inspection photo')}" width="400"><p>${e(photo.caption)}</p>${edit ? `<form method="post" action="/work/${r.id}/remove-photo">${fields(r.revision)}<input type="hidden" name="photo_id" value="${photo.id}"><button>Remove photo from report</button></form>` : ''}</article>`).join('')}${edit ? `<form method="post" action="/work/${r.id}/photos" enctype="multipart/form-data">${fields(r.revision)}<p><label>JPEG or PNG photo <input type="file" name="file" accept="image/jpeg,image/png" required></label></p><p><label>Caption <input name="caption" maxlength="2000"></label></p><button>Upload photo</button></form>` : ''}`;
    return html('Inspection', `<h1>${e(snapshot.indexes.schools.get(r.school_id)?.name ?? r.school_id)}</h1><p>${e(r.inspection_date)} · ${e(r.status)} · Revision ${r.revision}</p>${access.grant.capabilities?.includes('cqa_export_reports') ? `<p><a href="/api/work/reports/${r.id}/pdf">Download PDF</a></p>` : ''}<small>Checklist ${e(checklist.version)} · Save changes before submitting or uploading photos.</small>${saveForm}${photos}${actions.map(action => `<form method="post" action="/work/${r.id}/transition">${fields(r.revision)}<input type="hidden" name="action" value="${action}"><p><label>Review comment <textarea name="comment" maxlength="10000" ${action === 'request_revision' ? 'required' : ''}></textarea></label></p><button>${e(action.replaceAll('_',' '))}</button></form>`).join('')}<h2>Workflow history</h2>${detail.workflow.map(w => `<article><p>${e(w.created_at)} · ${e(w.action)}</p><p>${e(w.comment)}</p></article>`).join('') || '<p>No transitions yet.</p>'}`);
  }
  if (!['POST','PUT'].includes(req.method)) problem(405, 'method_not_allowed');
  writable();
  if (!config.publicOrigin || req.headers.origin !== config.publicOrigin) problem(403, 'origin_denied');
  const input = await readInput(req);
  if (!input || typeof input !== 'object' || Array.isArray(input)) problem(400, 'invalid_body');
  if (!equal(req.headers['x-cqa-csrf'] ?? input.csrf, token)) problem(403, 'csrf_denied');
  const key = req.headers['idempotency-key'] ?? input.idempotency_key;
  let result;
  if (['/work/new','/api/work/reports'].includes(url.pathname) && req.method === 'POST') {
    let definitions;
    try {
      const raw = readFileSync(config.checklistPath, 'utf8');
      if (digest(raw) !== config.checklistSha256) problem(503, 'checklist_digest_mismatch');
      definitions = JSON.parse(raw);
    } catch (error) { if (error.status) throw error; problem(503, 'checklist_unavailable'); }
    result = store.create(access, key, { school_id: input.school_id, report_type: input.report_type, inspection_date: input.inspection_date }, snapshot, definitions.types?.[input.report_type]);
  } else {
    const match = /^\/(?:api\/work\/reports|work)\/([a-f0-9-]{36})\/(save|transition|pdf|ai|photos|remove-photo)$/.exec(url.pathname);
    if (!match) problem(404, 'not_found');
    store.record(match[1], access);
    if (['pdf','ai'].includes(match[2])) problem(503, 'adapter_unavailable');
    if (match[2] === 'photos') {
      const detail = store.detail(match[1], access);
      if (!detail.permissions.edit) problem(403, 'access_denied');
      if (!['draft','needs_revision'].includes(detail.report.status)) problem(409, 'report_locked');
      if (!input.file || typeof input.file.arrayBuffer !== 'function') problem(422, 'image_file_required');
      const media = await storeImage(Buffer.from(await input.file.arrayBuffer()), config.mediaDirectory);
      result = store.addPhoto(access, key, match[1], { revision: Number(input.revision), caption: input.caption ?? '', ...media });
    } else if (match[2] === 'remove-photo') result = store.removePhoto(access, key, match[1], { revision: Number(input.revision), photo_id: input.photo_id });
    else if (match[2] === 'save') {
      const responses = api ? input.responses : Object.keys(input).filter(k => k.startsWith('rating.') && input[k]).map(k => {
        const [section_key,item_key] = k.slice(7).split('.');
        return { section_key, item_key, rating: input[k], notes: input[`notes.${section_key}.${item_key}`] ?? '', evidence_type: input[`evidence.${section_key}.${item_key}`] ?? 'observation' };
      });
      result = store.save(access, key, match[1], { revision: Number(input.revision), responses, closing_notes: input.closing_notes, overall_rating: input.overall_rating });
    } else result = store.transition(access, key, match[1], { revision: Number(input.revision), action: input.action, comment: input.comment ?? '' });
  }
  if (api) return { body: result };
  return { status: 303, headers: { Location: `/work/${result.id}` }, body: '' };
}
