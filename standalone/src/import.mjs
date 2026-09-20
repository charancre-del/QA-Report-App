import { mkdirSync, readFileSync, writeFileSync, openSync, fsyncSync, closeSync, linkSync, unlinkSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { resolve, join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { validateSnapshot } from './snapshot.mjs';

// Immutable content-addressed imports. No pointer switch and no database writes.
export function importSnapshot(input, directory, source) {
  const raw = readFileSync(input, 'utf8');
  const snapshot = validateSnapshot(raw, source);
  mkdirSync(directory, { recursive: true, mode: 0o700 });
  const path = join(resolve(directory), `${snapshot.sha256}.snapshot.json`);
  const temporary = join(resolve(directory), `.import-${randomUUID()}`);
  let replay = false;
  const fd = openSync(temporary, 'wx', 0o600);
  try { writeFileSync(fd, raw); fsyncSync(fd); } finally { closeSync(fd); }
  try {
    // Atomic publish without replacing an existing snapshot (including during concurrent imports).
    linkSync(temporary, path);
  } catch (error) {
    if (error.code !== 'EEXIST') throw error;
    if (readFileSync(path, 'utf8') !== raw) throw new Error('Existing immutable snapshot differs');
    replay = true;
  } finally { unlinkSync(temporary); }
  return { path, sha256: snapshot.sha256, source, counts: snapshot.counts, replay,
    mode: 'read-only-review', cutover_ready: false, limitations: snapshot.limitations };
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  try {
    const [input, directory, source] = process.argv.slice(2);
    if (!input || !directory || !source) throw new Error('Usage: npm run import -- SNAPSHOT_FILE PRIVATE_DIRECTORY EXPECTED_SOURCE_INSTANCE');
    console.log(JSON.stringify(importSnapshot(input, directory, source), null, 2));
  } catch (error) { console.error(error.message); process.exitCode = 1; }
}
