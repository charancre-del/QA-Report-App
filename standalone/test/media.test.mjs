import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import sharp from 'sharp';
import { storeImage, readImage } from '../src/media.mjs';
import { migrateDatabase, InspectionStore } from '../src/store.mjs';
import { renderReportPdf } from '../src/pdf.mjs';
import { backupBundle, restoreBundle } from '../src/recovery.mjs';
import { snapshot, source } from './fixtures.mjs';
import { validateSnapshot } from '../src/snapshot.mjs';

test('private images are normalized, metadata-free, scope checked, PDF-rendered and restored with the database', async t => {
  const dir = mkdtempSync(join(tmpdir(), 'cqa-media-test-')); t.after(() => rmSync(dir, { recursive: true }));
  const bytes = await sharp({ create: { width: 20, height: 20, channels: 3, background: '#3d8f80' } }).png().toBuffer();
  const mediaDirectory = join(dir, 'media'), media = await storeImage(bytes, mediaDirectory);
  const normalized = readImage(media, mediaDirectory); const metadata = await sharp(normalized).metadata();
  assert.equal(metadata.format, 'jpeg'); assert.equal(metadata.exif, undefined);
  await assert.rejects(() => storeImage(Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"></svg>'), mediaDirectory), /unsupported_image/);
  await assert.rejects(() => storeImage(Buffer.from('not an image'), mediaDirectory), /invalid_image/);
  const database = join(dir, 'app.sqlite'); migrateDatabase(database, source); const store = new InspectionStore(database, source);
  const access = { subject: 'synthetic-actor', allowed: id => id === '11', grant: { source_user_id: '101', capabilities: ['cqa_create_reports','cqa_edit_own_reports','cqa_view_all_reports'] } };
  const definition = { version: 'synthetic', reviewed_by: 'synthetic', sections: [{ key: 'test', items: [{ key: 'one', label: 'Synthetic observation' }] }] };
  try {
    const report = store.create(access, 'synthetic-create-01', { school_id: '11', inspection_date: '2026-01-04', report_type: 'tier1' }, validateSnapshot(snapshot(), source), definition);
    const photo = store.addPhoto(access, 'synthetic-photo-01', report.id, { ...media, caption: 'Synthetic photo evidence', revision: 1 });
    assert.equal(store.photo(report.id, photo.photo_id, access).sha256, media.sha256);
    assert.throws(() => store.photo(report.id, photo.photo_id, { ...access, allowed: () => false }), /not_found/);
    const pdf = await renderReportPdf(store.detail(report.id, access), { name: 'Synthetic North' }, {
      mediaDirectory, pdfFontPath: process.env.CQA_TEST_PDF_FONT ?? (process.platform === 'win32' ? 'C:/Windows/Fonts/arial.ttf' : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')
    }, store, access);
    assert.ok(pdf.subarray(0,5).toString() === '%PDF-'); assert.ok(pdf.length > 5000);
    assert.ok(pdf.toString('latin1').includes('/Subtype /Image')); assert.ok(pdf.toString('latin1').includes('/FontFile2'));
    if (process.env.CQA_TEST_PDF_OUTPUT) writeFileSync(process.env.CQA_TEST_PDF_OUTPUT, pdf);
    const snapshotPath = join(dir, 'source.json'); writeFileSync(snapshotPath, snapshot());
    const bundle = join(dir, 'bundle'); const manifest = await backupBundle(store, mediaDirectory, snapshotPath, bundle);
    const restored = join(dir, 'restored'); assert.equal(restoreBundle(bundle, restored, manifest.manifest_sha256, source).photo_objects, 1);
    const restoredStore = new InspectionStore(join(restored, 'app.sqlite'), source);
    try { assert.deepEqual(readImage(restoredStore.photo(report.id, photo.photo_id, access), join(restored, 'media')), normalized); } finally { restoredStore.close(); }
    store.removePhoto(access, 'synthetic-remove-01', report.id, { photo_id: photo.photo_id, revision: 2 });
    assert.throws(() => store.photo(report.id, photo.photo_id, access), /not_found/);
    assert.equal(store.db.prepare('SELECT count(*) AS n FROM photos').get().n, 1);
    writeFileSync(join(mediaDirectory, `${media.sha256}.jpg`), 'corrupt');
    assert.throws(() => readImage(media, mediaDirectory), /media_integrity_error/);
  } finally { store.close(); }
});
