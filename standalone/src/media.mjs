import sharp from 'sharp';
import { mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { digest } from './snapshot.mjs';
import { problem } from './store.mjs';

export async function storeImage(bytes, directory) {
  if (!directory) problem(503, 'media_unavailable');
  if (!Buffer.isBuffer(bytes) || !bytes.length || bytes.length > 10 * 1024 * 1024) problem(413, 'invalid_image_size');
  let normalized;
  try {
    const image = sharp(bytes, { limitInputPixels: 40000000, failOn: 'warning', animated: false });
    const metadata = await image.metadata();
    if (!['jpeg','png'].includes(metadata.format) || (metadata.pages ?? 1) > 1) problem(415, 'unsupported_image');
    normalized = await image.rotate().resize({ width: 2400, height: 2400, fit: 'inside', withoutEnlargement: true }).jpeg({ quality: 85 }).toBuffer();
  } catch (error) { if (error.status) throw error; problem(422, 'invalid_image'); }
  const sha256 = digest(normalized);
  mkdirSync(directory, { recursive: true, mode: 0o700 });
  const path = join(directory, `${sha256}.jpg`);
  try { writeFileSync(path, normalized, { flag: 'wx', mode: 0o600, flush: true }); }
  catch (error) { if (error.code !== 'EEXIST' || digest(readFileSync(path)) !== sha256) throw error; }
  return { sha256, bytes: normalized.length };
}
export function readImage(photo, directory) {
  if (!directory) problem(503, 'media_unavailable');
  if (!/^[a-f0-9]{64}$/.test(photo.sha256)) problem(503, 'media_integrity_error');
  let data;
  try { data = readFileSync(join(directory, `${photo.sha256}.jpg`)); } catch { problem(503, 'media_unavailable'); }
  if (digest(data) !== photo.sha256 || data.length !== photo.bytes) problem(503, 'media_integrity_error');
  return data;
}
