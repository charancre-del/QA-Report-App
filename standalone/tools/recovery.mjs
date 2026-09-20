import { InspectionStore } from '../src/store.mjs';
import { backupBundle, restoreBundle } from '../src/recovery.mjs';
try {
  const [command, path, source, arg1, arg2, arg3] = process.argv.slice(2);
  if (command === 'backup' && arg3) {
    const store = new InspectionStore(path, source);
    try { console.log(JSON.stringify(await backupBundle(store, arg1, arg2, arg3))); } finally { store.close(); }
  } else if (command === 'restore' && arg2) console.log(JSON.stringify(restoreBundle(path, arg1, arg2, source)));
  else throw new Error('Usage: recovery.mjs backup DB SOURCE MEDIA_DIR SNAPSHOT NEW_BUNDLE_DIR | restore BUNDLE SOURCE NEW_RESTORE_DIR MANIFEST_SHA256');
} catch (error) { console.error(error.message); process.exitCode = 1; }
