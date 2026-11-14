#!/usr/bin/env python3
"""離線 MySQL 搬遷：私有備份、空庫匯入、逐表核對與受控回退, 不載入 Laravel 環境。"""
import argparse
import contextlib
from decimal import Decimal
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import uuid


class TransferError(RuntimeError):
    """只包含可公開的操作錯誤, 不輸出連線憑證或SQL資料。"""


def encode(value):
    return json.dumps(value, ensure_ascii=True, sort_keys=True, separators=(',', ':')).encode()


def digest(path):
    h = hashlib.sha256()
    with Path(path).open('rb') as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b''):
            h.update(block)
    return h.hexdigest()


def canonical_ddl(ddl):
    # SHOW CREATE may add an explicit charset after dump/restore; COLLATE already fixes it.
    pattern = r'^(\s*`(?:``|[^`])+` (?:tinytext|text|mediumtext|longtext|char\(\d+\)|varchar\(\d+\))) CHARACTER SET ([A-Za-z0-9_]+) COLLATE ([A-Za-z0-9_]+)'
    return re.sub(pattern, lambda match: match[1]+' COLLATE '+match[3] if match[3].startswith(match[2]+'_') else match[0], ddl, flags=re.MULTILINE | re.IGNORECASE)


def canonical_json(text):
    """JSON數字保留十進位精度, 並區分物件、陣列、字串、數字與null。"""
    def typed(value):
        if value is None:
            return ['null']
        if isinstance(value, bool):
            return ['boolean', value]
        if isinstance(value, str):
            return ['string', value]
        if isinstance(value, Decimal):
            return ['number', str(value)]
        if isinstance(value, list):
            return ['array', [typed(item) for item in value]]
        return ['object', [[key, typed(value[key])] for key in sorted(value)]]
    return typed(json.loads(text, parse_int=Decimal, parse_float=Decimal))


def private(path):
    path = Path(path)
    if not path.is_file() or path.is_symlink() or path.stat().st_mode & 0o077:
        raise TransferError('Required private regular file must have mode 0600')
    return path


def save(path, value):
    temporary = path.with_suffix('.tmp')
    with temporary.open('w') as stream:
        json.dump(value, stream, sort_keys=True, indent=2)
        stream.flush()
        os.fsync(stream.fileno())
    temporary.replace(path)


class Endpoint:
    """只執行固定mysql工具, 憑證從私有option file讀取, 不傳入命令列。"""

    def __init__(self, config):
        self.database = config['database']
        if not re.fullmatch(r'[A-Za-z0-9_]+', self.database) or self.database.lower() in {'mysql', 'sys', 'performance_schema', 'information_schema'}:
            raise TransferError('Invalid or system database')
        self.defaults = config['defaultsFile']
        if not self.defaults.startswith('/'):
            raise TransferError('An absolute defaultsFile path is required')
        self.container = config.get('container')
        if self.container and not re.fullmatch(r'[A-Za-z0-9_.-]+', self.container):
            raise TransferError('Invalid client container')
        if not self.container:
            private(self.defaults)
        self.key = digest(private(config['appKeyFile']))

    def command(self, program, args=()):
        prefix = ['docker', 'exec', '-i', self.container] if self.container else []
        return prefix + [program, '--defaults-extra-file='+self.defaults] + (['--connect-timeout=10'] if program == 'mysql' else []) + list(args)

    def execute(self, sql, output=None):
        result = subprocess.run(self.command('mysql', ['--batch', '--raw', '--skip-column-names', '--binary-mode', '--default-character-set=utf8mb4', self.database]), input=("SET time_zone='+00:00';\n"+sql).encode(), stdout=output or subprocess.PIPE, stderr=subprocess.PIPE, timeout=300)
        if result.returncode:
            raise TransferError('MySQL operation failed; credentials and SQL output withheld')
        return result.stdout

    def scalar(self, sql):
        return self.execute(sql).decode().strip()

    def tables(self):
        rows = self.scalar('SELECT JSON_ARRAY(TABLE_NAME,TABLE_TYPE,ENGINE) FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')
        result = [json.loads(row) for row in rows.splitlines()]
        if any(kind != 'BASE TABLE' or engine != 'InnoDB' for _, kind, engine in result):
            raise TransferError('Only InnoDB base tables supported; review views/nontransactional tables separately')
        # Current TaskTracker has none; fail closed rather than silently lose executable schema objects.
        for table, column in [('TRIGGERS', 'TRIGGER_SCHEMA'), ('ROUTINES', 'ROUTINE_SCHEMA'), ('EVENTS', 'EVENT_SCHEMA')]:
            if self.scalar(f'SELECT COUNT(*) FROM information_schema.{table} WHERE {column}=DATABASE()') != '0':
                raise TransferError('Executable database objects require a separate migration review')
        return [name for name, _, _ in result]

    def identity(self):
        return hashlib.sha256(self.scalar('SELECT CONCAT(@@server_uuid, CHAR(0), DATABASE())').encode()).hexdigest()

    def inventory(self):
        """依主鍵逐列串流, JSON物件鍵正規化, NULL／二進位／陣列順序保留。"""
        result, columns_by_table, keys_by_table = {}, {}, {}
        metadata = self.scalar('SELECT JSON_ARRAY(TABLE_NAME,COLUMN_NAME,DATA_TYPE) FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION')
        for line in metadata.splitlines():
            table, column, kind = json.loads(line)
            columns_by_table.setdefault(table, []).append((column, kind))
        keys = self.scalar("SELECT JSON_ARRAY(TABLE_NAME,COLUMN_NAME) FROM information_schema.key_column_usage WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME='PRIMARY' ORDER BY TABLE_NAME,ORDINAL_POSITION")
        for line in keys.splitlines():
            table, column = json.loads(line)
            keys_by_table.setdefault(table, []).append(column)
        for table in self.tables():
            name = '`'+table.replace('`', '``')+'`'
            columns, pk = columns_by_table[table], keys_by_table.get(table, [])
            if any(kind in ['float', 'double', 'geometry', 'vector'] for _, kind in columns):
                raise TransferError('Floating-point or spatial/vector columns need a lossless verifier before migration')
            if not pk:
                raise TransferError('Every table needs an explicit primary key for stable verification')
            quoted = lambda column: '`'+column.replace('`', '``')+'`'
            fields = ','.join(f'IF({quoted(column)} IS NULL,NULL,HEX(CAST({quoted(column)} AS BINARY)))' for column, _ in columns)
            query = f'SELECT JSON_ARRAY({fields}) FROM {name} ORDER BY '+','.join(map(quoted, pk))
            command = self.command('mysql', ['--batch', '--raw', '--skip-column-names', '--quick', '--default-character-set=utf8mb4', self.database])
            # Spool one table to a private temporary file: bounded RAM and a real process timeout.
            with tempfile.TemporaryFile() as rows:
                result_code = subprocess.run(command, input=("SET time_zone='+00:00';\n"+query).encode(), stdout=rows, stderr=subprocess.DEVNULL, timeout=300).returncode
                if result_code:
                    raise TransferError('Streaming verification failed')
                rows.seek(0)
                h, count = hashlib.sha256(), 0
                for line in rows:
                    row = json.loads(line)
                    for index, (_, kind) in enumerate(columns):
                        if kind == 'json' and row[index] is not None:
                            row[index] = {'json': canonical_json(bytes.fromhex(row[index]).decode())}
                    h.update(encode(row)+b'\n'); count += 1
            ddl = self.scalar('SHOW CREATE TABLE '+name).split('\t', 1)[1]
            result[table] = {'rows': count, 'sha256': h.hexdigest(), 'ddlSha256': hashlib.sha256(canonical_ddl(ddl).encode()).hexdigest()}
        return result

    @contextlib.contextmanager
    def write_guard(self):
        """跨bundle／操作者以目標伺服器命名鎖序列化, 連線結束即釋放。"""
        name = 'tasktracker-transfer-'+hashlib.sha256(self.database.encode()).hexdigest()[:40]
        process = subprocess.Popen(self.command('mysql', ['--batch', '--raw', '--skip-column-names', '--unbuffered', self.database]), stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL)
        try:
            process.stdin.write(("SELECT GET_LOCK('"+name+"',0);\n").encode());process.stdin.flush()
            if process.stdout.readline().strip() != b'1':
                raise TransferError('Another migration holds the target lock')
            yield
        finally:
            process.stdin.close()
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                process.kill();process.wait()
            process.stdout.close()

    def dump(self, destination):
        with destination.open('wb') as out:
            result = subprocess.run(self.command('mysqldump', ['--single-transaction', '--quick', '--hex-blob', '--no-tablespaces', '--column-statistics=0', '--set-gtid-purged=OFF', '--skip-dump-date', self.database]), stdout=out, stderr=subprocess.PIPE, timeout=3600)
        if result.returncode or destination.stat().st_size == 0:
            raise TransferError('Backup failed; do not import an incomplete dump')

    def restore(self, source):
        with source.open('rb') as stream:
            result = subprocess.run(self.command('mysql', ['--binary-mode', '--default-character-set=utf8mb4', self.database]), stdin=stream, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, timeout=3600)
        if result.returncode:
            raise TransferError('Import failed; partial target must be rolled back before reuse')


def rollback(target, manifest, journal, path):
    if target.identity() != journal['target']:
        raise TransferError('Target identity changed; rollback refused')
    if journal['state'] == 'applied' and target.inventory() != manifest['tables']:
        raise TransferError('Target has changed since import; rollback refused to protect new writes')
    names = target.tables()
    if not set(names).issubset(manifest['tables']):
        raise TransferError('Unexpected target objects; automatic cleanup refused')
    if names:
        backup = path.parent/('target-before-rollback-'+str(uuid.uuid4())+'.sql')
        target.dump(backup)
        journal['rollbackBackup'] = {'file': backup.name, 'sha256': digest(backup)}
        save(path, journal)
        quoted = ','.join('`'+name.replace('`', '``')+'`' for name in names)
        target.execute('SET FOREIGN_KEY_CHECKS=0; DROP TABLE '+quoted+'; SET FOREIGN_KEY_CHECKS=1;')
    if target.tables():
        raise TransferError('Target rollback incomplete')
    journal['state'] = 'rolled-back'; save(path, journal)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=['snapshot', 'apply', 'verify', 'rollback'])
    parser.add_argument('--config', type=Path, required=True)
    parser.add_argument('--bundle', type=Path, required=True)
    parser.add_argument('--confirm-stopped', action='store_true', help='Operator confirms BOTH databases and DDL writers are stopped')
    parser.add_argument('--confirm-target', help='Required for target writes; exact target database name')
    args = parser.parse_args(argv)
    os.umask(0o077)
    if not args.confirm_stopped:
        raise TransferError('Confirm stopped application, workers, jobs and DDL writers first')
    config = json.loads(private(args.config).read_text())
    source, target = Endpoint(config['source']), Endpoint(config['target'])
    if source.key != target.key:
        raise TransferError('APP_KEY differs; encrypted data would become unreadable')
    if source.identity() == target.identity():
        raise TransferError('Source and target must be different databases')
    bundle = args.bundle.resolve()
    if args.operation == 'snapshot':
        bundle.mkdir(mode=0o700, parents=True, exist_ok=False)
    if not bundle.is_dir() or bundle.stat().st_mode & 0o077:
        raise TransferError('Private bundle directory must have mode 0700')
    lock = bundle / '.lock'
    descriptor = os.open(lock, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    os.close(descriptor)
    guard = contextlib.ExitStack()
    try:
        if args.operation in ['apply', 'rollback']:
            guard.enter_context(target.write_guard())
        manifest_path, dump, journal_path = bundle/'manifest.json', bundle/'source.sql', bundle/'target-journal.json'
        if args.operation == 'snapshot':
            if target.tables():
                raise TransferError('Target is not completely empty; preserve it and provision a fresh database')
            before = source.inventory()
            if not before:
                raise TransferError('Empty source is not a migration')
            source.dump(dump)
            if source.inventory() != before:
                raise TransferError('Source changed during backup; stop writers and take a new bundle')
            save(manifest_path, {'version': 1, 'source': source.identity(), 'target': target.identity(), 'appKeySha256': source.key, 'dumpSha256': digest(dump), 'tables': before, 'targetBefore': {}})
            print('PASS snapshot:', len(before), 'tables; target untouched')
            return
        manifest = json.loads(private(manifest_path).read_text())
        if manifest['source'] != source.identity() or manifest['target'] != target.identity() or manifest['appKeySha256'] != source.key or manifest['dumpSha256'] != digest(private(dump)):
            raise TransferError('Bundle, endpoints or APP_KEY changed')
        if args.operation == 'verify':
            if source.inventory() != manifest['tables'] or target.inventory() != manifest['tables']:
                raise TransferError('Source/target table counts, definitions or complete contents differ')
            print('PASS verify:', len(manifest['tables']), 'tables; counts, definitions and full content hashes match')
            return
        if args.confirm_target != target.database:
            raise TransferError('Exact --confirm-target database name is required for any target write')
        journal = json.loads(private(journal_path).read_text()) if journal_path.exists() else {'target': target.identity(), 'state': 'prepared'}
        if args.operation == 'rollback':
            if not journal_path.exists():
                raise TransferError('No target import journal; rollback refused')
            rollback(target, manifest, journal, journal_path)
            print('PASS rollback: target is empty; source untouched')
            return
        if source.inventory() != manifest['tables']:
            raise TransferError('Source changed after snapshot; create a new bundle')
        if journal['state'] == 'applied':
            if target.inventory() != manifest['tables']:
                raise TransferError('Previously applied target changed; no overwrite')
            print('PASS apply: already applied and verified; no writes')
            return
        if journal['state'] == 'importing':
            raise TransferError('Interrupted import; review/back up partial target and use explicit rollback before applying again')
        if target.tables():
            raise TransferError('Nonempty target refused; no overwrite')
        journal['state'] = 'importing'; save(journal_path, journal)
        try:
            target.restore(dump)
            imported = target.inventory()
            if imported != manifest['tables']:
                changed = [table+':'+','.join(field for field in ['rows','sha256','ddlSha256'] if imported.get(table, {}).get(field) != expected[field]) for table, expected in manifest['tables'].items() if imported.get(table) != expected]
                ddl_changes = {}
                for table in manifest['tables']:
                    if imported.get(table, {}).get('ddlSha256') != manifest['tables'][table]['ddlSha256']:
                        name = '`'+table.replace('`','``')+'`'
                        ddl_changes[table] = {'source': source.scalar('SHOW CREATE TABLE '+name), 'target': target.scalar('SHOW CREATE TABLE '+name)}
                save(bundle/'ddl-differences.json', ddl_changes)
                raise TransferError('Imported contents differ: '+', '.join(changed[:5]))
            journal['state'] = 'applied'; save(journal_path, journal)
        except BaseException:
            rollback(target, manifest, journal, journal_path)
            raise
        print('PASS apply:', len(manifest['tables']), 'tables; target verified; keep application stopped until cutover')
    finally:
        guard.close()
        lock.unlink()


if __name__ == '__main__':
    try:
        main()
    except (TransferError, OSError, ValueError, subprocess.SubprocessError, KeyError) as failure:
        # Raw client errors, SQL, paths and connection config are deliberately excluded.
        print('FAIL:', str(failure) if isinstance(failure, TransferError) else type(failure).__name__, file=sys.stderr)
        sys.exit(1)
