import { mkdirSync, copyFileSync, writeFileSync, readFileSync, chmodSync, constants } from 'node:fs';
import { DatabaseSync } from 'node:sqlite';
import { join } from 'node:path';
import { digest, readSnapshot } from './snapshot.mjs';
import { readImage } from './media.mjs';
import { restoreDatabase } from './store.mjs';

export async function backupBundle(store, mediaDirectory, snapshotPath, destination) {
  // A partial bundle has no completed manifest and is never restorable.
  mkdirSync(destination, { mode: 0o700 }); mkdirSync(join(destination, 'media'), { mode: 0o700 });
  const database = await store.backup(join(destination, 'app.sqlite'));
  const db = new DatabaseSync(join(destination, 'app.sqlite'), { readOnly: true });
  let photos;
  try { photos = db.prepare('SELECT DISTINCT sha256,bytes FROM photos').all(); } finally { db.close(); }
  for (const photo of photos) writeFileSync(join(destination, 'media', `${photo.sha256}.jpg`), readImage(photo, mediaDirectory), { flag: 'wx', mode: 0o600, flush: true });
  const snapshot = readSnapshot(snapshotPath, store.source);
  copyFileSync(snapshotPath, join(destination, 'source.snapshot.json'), constants.COPYFILE_EXCL);
  chmodSync(join(destination, 'source.snapshot.json'), 0o600);
  const manifest = { format: 'chroma-qa-backup/v1', source: store.source, database_sha256: database.sha256,
    snapshot_file_sha256: digest(readFileSync(snapshotPath)), source_payload_sha256: snapshot.sha256,
    photos, created_at: new Date().toISOString(), contains_sensitive_evidence: true };
  const raw = JSON.stringify(manifest, null, 2);
  writeFileSync(join(destination, 'manifest.json'), raw, { flag: 'wx', mode: 0o600, flush: true });
  return { manifest_sha256: digest(raw), source: store.source, photo_objects: photos.length };
}
export function restoreBundle(bundle, destination, expectedManifestSha256, source) {
  const raw = readFileSync(join(bundle, 'manifest.json'));
  if (digest(raw) !== expectedManifestSha256) throw new Error('Backup manifest checksum mismatch');
  const manifest = JSON.parse(raw);
  if (manifest.format !== 'chroma-qa-backup/v1' || manifest.source !== source || !Array.isArray(manifest.photos)) throw new Error('Backup source mismatch');
  if (digest(readFileSync(join(bundle, 'source.snapshot.json'))) !== manifest.snapshot_file_sha256) throw new Error('Source snapshot checksum mismatch');
  readSnapshot(join(bundle, 'source.snapshot.json'), source);
  for (const photo of manifest.photos) readImage(photo, join(bundle, 'media'));
  mkdirSync(destination, { mode: 0o700 }); mkdirSync(join(destination, 'media'), { mode: 0o700 });
  restoreDatabase(join(bundle, 'app.sqlite'), join(destination, 'app.sqlite'), manifest.database_sha256, source);
  const db = new DatabaseSync(join(destination, 'app.sqlite'), { readOnly: true });
  try {
    const references = db.prepare('SELECT DISTINCT sha256,bytes FROM photos').all();
    if (references.length !== manifest.photos.length || references.some(r => !manifest.photos.some(p => p.sha256 === r.sha256 && p.bytes === r.bytes))) throw new Error('Photo reconciliation failed');
  } finally { db.close(); }
  for (const photo of manifest.photos) {
    const target = join(destination, 'media', `${photo.sha256}.jpg`);
    copyFileSync(join(bundle, 'media', `${photo.sha256}.jpg`), target, constants.COPYFILE_EXCL); chmodSync(target, 0o600);
  }
  copyFileSync(join(bundle, 'source.snapshot.json'), join(destination, 'source.snapshot.json'), constants.COPYFILE_EXCL);
  chmodSync(join(destination, 'source.snapshot.json'), 0o600);
  return { restored: true, photo_objects: manifest.photos.length, writes_enabled: false, grants_restored: false };
}
