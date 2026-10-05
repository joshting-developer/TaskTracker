import { DatabaseSync } from 'node:sqlite';

export function createSqliteStore(filename) {
  const db = new DatabaseSync(filename);
  db.exec(`PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 3000;
    CREATE TABLE IF NOT EXISTS records (
      kind TEXT NOT NULL, id TEXT NOT NULL, payload TEXT NOT NULL,
      PRIMARY KEY (kind, id)
    );`);
  const get = db.prepare('SELECT payload FROM records WHERE kind = ? AND id = ?');
  const list = db.prepare('SELECT payload FROM records WHERE kind = ? ORDER BY rowid');
  const put = db.prepare('INSERT INTO records(kind,id,payload) VALUES (?,?,?) ON CONFLICT(kind,id) DO UPDATE SET payload=excluded.payload');
  return {
    transaction(work) {
      db.exec('BEGIN IMMEDIATE');
      try {
        const result = work({
          get: (kind, id) => { const row = get.get(kind, id); return row ? JSON.parse(row.payload) : null; },
          list: (kind) => list.all(kind).map((row) => JSON.parse(row.payload)),
          put: (kind, id, value) => put.run(kind, id, JSON.stringify(value)),
        });
        db.exec('COMMIT');
        return result;
      } catch (error) {
        db.exec('ROLLBACK');
        throw error;
      }
    },
    close: () => db.close(),
  };
}
