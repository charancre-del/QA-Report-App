import { InspectionStore, migrateDatabase, restoreDatabase } from '../src/store.mjs';
try {
  const [command, path, source, destination, hash] = process.argv.slice(2);
  if (!path || !source) throw new Error('Usage: node tools/database.mjs migrate|backup|restore DATABASE SOURCE [NEW_DESTINATION] [BACKUP_SHA256]');
  if (command === 'migrate') { migrateDatabase(path, source); console.log(JSON.stringify({ schema: 1, source, migrated: true })); }
  else if (command === 'backup' && destination) {
    const store = new InspectionStore(path, source);
    try { console.log(JSON.stringify(await store.backup(destination))); } finally { store.close(); }
  } else if (command === 'restore' && destination && hash) console.log(JSON.stringify(restoreDatabase(path, destination, hash, source)));
  else throw new Error('Unknown or incomplete command');
} catch (error) { console.error(error.message); process.exitCode = 1; }
