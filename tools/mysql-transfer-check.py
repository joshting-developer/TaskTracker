#!/usr/bin/env python3
"""TT-341只在本次建立的兩套無公開埠MySQL演練, 所有資料為合成。"""
import importlib.util
import uuid
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('transfer', ROOT/'tools/mysql-transfer.py')
transfer = importlib.util.module_from_spec(spec); spec.loader.exec_module(transfer)


def main():
    os.umask(0o077)
    data_only = '--data-only' in sys.argv
    assert transfer.canonical_json('{"b":2,"a":1}') == transfer.canonical_json('{"a":1,"b":2}')
    assert transfer.canonical_json('0.12345678901234567890') != transfer.canonical_json('0.12345678901234567891')
    assert transfer.canonical_json('1.2') != transfer.canonical_json('"1.2"')
    assert transfer.canonical_json('null') != transfer.canonical_json('[null]')
    folder = Path(tempfile.mkdtemp(prefix='tt341-rehearsal-', dir=ROOT/'.local'))
    ddl="CREATE TABLE `probe` (\n  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'CHARACTER SET latin1 COLLATE latin1_bin'\n)"
    canonical=transfer.canonical_ddl(ddl)
    assert "text COLLATE utf8mb4_bin" in canonical and "COMMENT 'CHARACTER SET latin1 COLLATE latin1_bin'" in canonical
    assert transfer.canonical_ddl(ddl.replace('utf8mb4_bin','utf8mb4_0900_ai_ci')) != canonical
    password = secrets.token_hex(32)
    env = dict(os.environ, TT_TRANSFER_TEST_PASSWORD=password)
    compose = ['docker', 'compose', '-p', 'tasktracker-codex-transfer-test', '-f', str(ROOT/'docker-compose.transfer-test.yml')]
    config = {}
    evidence = []

    def run(args, **kwargs):
        result = subprocess.run(args, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=240, **kwargs)
        if result.returncode:
            raise RuntimeError('Isolated rehearsal command failed (output withheld)')
        return result.stdout

    try:
        run(compose+['up', '-d', '--wait'])
        key = folder/'app-key'; key.write_text(secrets.token_hex(32))
        for service, db in [('source', 'tasktracker_transfer_source'), ('target', 'tasktracker_transfer_target')]:
            container = run(compose+['ps', '-q', service]).decode().strip()
            cnf = folder/(service+'.cnf'); cnf.write_text('[client]\nhost=127.0.0.1\nuser=root\npassword='+password+'\n')
            run(['docker', 'cp', str(cnf), container+':/tmp/tt341-client.cnf'])
            config[service] = {'database': db, 'container': container, 'defaultsFile': '/tmp/tt341-client.cnf', 'appKeyFile': str(key)}
        path = folder/'config.json'; path.write_text(json.dumps(config))
        source, target = transfer.Endpoint(config['source']), transfer.Endpoint(config['target'])
        assert source.identity() != target.identity()
        # Real TaskTracker migrations, indexes and foreign keys, copied from the owned isolated test backend only.
        # Production schema comes from the isolated backend's migration result, never the live database.
        assert run(['docker','inspect','--format','{{index .Config.Labels "com.docker.compose.project"}}','tasktracker-codex-test-mysql-1']).decode().strip() == 'tasktracker-codex-test'
        schema = run(['docker', 'exec', 'tasktracker-codex-test-mysql-1', 'sh', '-c', 'MYSQL_PWD="$(cat /run/tasktracker-app/password)" mysqldump --no-data --no-tablespaces --set-gtid-purged=OFF --column-statistics=0 -h127.0.0.1 -utasktracker_test tasktracker_test'+(' users workspaces workspace_members' if data_only else '')])
        schema_path = folder/'schema.sql'; schema_path.write_bytes(schema); source.restore(schema_path)
        # Add independent edge cases without copying a single live account or test account from another suite.
        source.execute("CREATE TABLE transfer_parents(id BIGINT PRIMARY KEY, note TEXT, payload JSON, opaque LONGBLOB, score DECIMAL(10,3)) ENGINE=InnoDB; CREATE TABLE transfer_children(id BIGINT PRIMARY KEY, parent_id BIGINT NOT NULL, FOREIGN KEY(parent_id) REFERENCES transfer_parents(id)) ENGINE=InnoDB; INSERT INTO transfer_parents VALUES(1,'繁體中文\\nquote\\\' and slash\\\\',JSON_OBJECT('nested',JSON_OBJECT('z',2,'a',1),'array',JSON_ARRAY(3,2,1)),UNHEX('000AFF80'),12.125),(2,NULL,JSON_OBJECT('empty',JSON_OBJECT(),'null',NULL,'precise',CAST('0.12345678901234567890' AS JSON)),NULL,-0.001),(3,NULL,CAST('null' AS JSON),NULL,NULL),(4,NULL,NULL,NULL,0); INSERT INTO transfer_children VALUES(10,1);")
        user_id, workspace_id = str(uuid.uuid4()), str(uuid.uuid4())
        password_hash = run(['docker', 'exec', 'tasktracker-codex-test-backend-1', 'php', '-r', "echo password_hash('synthetic-transfer-only', PASSWORD_BCRYPT);"]).decode()
        source.execute(f"INSERT INTO users(id,name,email,password,email_verification_required) VALUES('{user_id}','搬遷合成帳號','transfer@example.test','{password_hash}',0); INSERT INTO workspaces(public_id,name) VALUES('{workspace_id}','搬遷合成空間'); INSERT INTO workspace_members(user_id,workspace_id,role) SELECT '{user_id}',id,'owner' FROM workspaces WHERE public_id='{workspace_id}';")
        baseline = source.inventory()
        print('Synthetic source ready:',len(baseline),'tables',flush=True)
        bundle = folder/'bundle'

        def cli(operation, *, success=True, cfg=path, target_name='tasktracker_transfer_target'):
            args = [sys.executable, str(ROOT/'tools/mysql-transfer.py'), operation, '--config', str(cfg), '--bundle', str(bundle), '--confirm-stopped', '--confirm-target', target_name]
            result = subprocess.run(args, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=300)
            if success and result.returncode and (bundle/'ddl-differences.json').exists():
                differences=json.loads((bundle/'ddl-differences.json').read_text());print('SYNTHETIC DDL:',differences.get('transfer_parents'),flush=True)
            assert (result.returncode == 0) == success, (operation, result.stdout.decode(), result.stderr.decode())
            assert password.encode() not in result.stdout+result.stderr
            evidence.append({'operation': operation, 'expectedSuccess': success, 'exitCode': result.returncode})
            print(operation, 'expected-success' if success else 'expected-rejection', 'PASS', flush=True)
            return result

        cli('snapshot')
        assert target.tables() == [] and source.inventory() == baseline
        target.execute('CREATE TABLE protected_fixture(id INT PRIMARY KEY) ENGINE=InnoDB; INSERT INTO protected_fixture VALUES(9);')
        cli('apply', success=False)
        assert target.scalar('SELECT id FROM protected_fixture') == '9'
        target.execute('DROP TABLE protected_fixture')
        source.execute('INSERT INTO transfer_children VALUES(11,2)')
        cli('apply', success=False); assert target.tables() == []
        source.execute('DELETE FROM transfer_children WHERE id=11')
        dump = bundle/'source.sql'; original_dump = dump.read_bytes()
        dump.write_bytes(original_dump+b'\n-- synthetic corruption\n');cli('apply',success=False);assert target.tables()==[]
        dump.write_bytes(original_dump)
        # A syntactically valid but deliberately incomplete dump imports some tables, then fails.
        dump.write_bytes(original_dump+b'\nTHIS_IS_A_SYNTHETIC_IMPORT_FAILURE;\n')
        manifest_path = bundle/'manifest.json'; manifest = json.loads(manifest_path.read_text()); manifest['dumpSha256'] = transfer.digest(dump); transfer.save(manifest_path, manifest)
        cli('apply', success=False)
        assert target.tables() == [] and source.inventory() == baseline
        assert json.loads((bundle/'target-journal.json').read_text())['state'] == 'rolled-back'
        dump.write_bytes(original_dump); manifest['dumpSha256'] = transfer.digest(dump); transfer.save(manifest_path, manifest)
        with target.write_guard():
            cli('apply', success=False)
        cli('apply'); cli('verify'); cli('apply')
        assert target.inventory() == baseline
        copied_hash = target.scalar("SELECT password FROM users WHERE email='transfer@example.test'")
        assert copied_hash == password_hash
        assert run(['docker', 'exec', '-i', 'tasktracker-codex-test-backend-1', 'php', '-r', "exit(password_verify('synthetic-transfer-only',stream_get_contents(STDIN))?0:1);"],input=copied_hash.encode()) == b''
        target.execute("UPDATE transfer_parents SET payload=JSON_SET(payload,'$.precise',CAST('0.12345678901234567891' AS DECIMAL(30,20))) WHERE id=2");cli('verify',success=False)
        target.execute("UPDATE transfer_parents SET payload=JSON_SET(payload,'$.precise',CAST('0.12345678901234567890' AS JSON)) WHERE id=2")
        target.execute('UPDATE transfer_parents SET score=score+0.001 WHERE id=1');cli('verify',success=False)
        target.execute('UPDATE transfer_parents SET score=12.125 WHERE id=1')
        target.execute('UPDATE transfer_parents SET payload=NULL WHERE id=3');cli('verify',success=False)
        target.execute("UPDATE transfer_parents SET payload=CAST('null' AS JSON) WHERE id=3")
        target.execute("UPDATE transfer_parents SET note='new target writes' WHERE id=1")
        cli('rollback', success=False); cli('apply', success=False)
        assert target.scalar('SELECT note FROM transfer_parents WHERE id=1') == 'new target writes'
        # Only the two synthetic databases are touched; restore the exact synthetic original for rollback verification.
        # Rebuild the target from the saved complete snapshot after explicitly cleaning this test-only database.
        names=','.join('`'+name+'`' for name in target.tables());target.execute('SET FOREIGN_KEY_CHECKS=0;DROP TABLE '+names+';SET FOREIGN_KEY_CHECKS=1;')
        target.restore(dump)
        cli('rollback'); assert target.tables() == []; cli('rollback'); cli('apply'); cli('verify')
        wrong_key = folder/'wrong-key'; wrong_key.write_text('synthetic-different-key')
        wrong = json.loads(path.read_text()); wrong['target']['appKeyFile'] = str(wrong_key)
        wrong_path=folder/'wrong.json';wrong_path.write_text(json.dumps(wrong));cli('verify',cfg=wrong_path,success=False)
        cli('verify', target_name='not-the-target')  # verify is read-only; confirmation matters only for writes.
        cli('apply', target_name='not-the-target', success=False)
        same=json.loads(path.read_text());same['target']=same['source'];same_path=folder/'same.json';same_path.write_text(json.dumps(same));cli('verify',cfg=same_path,success=False)
        assert source.inventory()==baseline
        journal=json.loads((bundle/'target-journal.json').read_text());journal['state']='importing';transfer.save(bundle/'target-journal.json',journal)
        target.execute("UPDATE transfer_parents SET note='interrupted target progress' WHERE id=1")
        cli('apply',success=False);assert target.scalar('SELECT note FROM transfer_parents WHERE id=1') == 'interrupted target progress'
        cli('rollback');assert target.tables()==[]
        backup=json.loads((bundle/'target-journal.json').read_text())['rollbackBackup'];assert b'interrupted target progress' in (bundle/backup['file']).read_bytes()
        cli('apply');cli('verify')
        if data_only:
            cli('rollback')
            source.execute("UPDATE transfer_parents SET payload=JSON_SET(payload,'$.precise',CAST('0.12345678901234567890' AS DECIMAL(30,20))) WHERE id=2")
            bundle=folder/'precision-bundle'
            cli('snapshot');cli('apply',success=False);assert target.tables()==[]
            source.execute("UPDATE transfer_parents SET payload=JSON_SET(payload,'$.precise',CAST('0.12345678901234567890' AS JSON)) WHERE id=2")
            assert source.inventory()==baseline
        output = ROOT/('artifacts/tt341/rehearsal-data.json' if data_only else 'artifacts/tt341/rehearsal.json');output.parent.mkdir(parents=True,exist_ok=True)
        output.write_text(json.dumps({'passed':True,'twoSeparateMySqlServers':True,'tables':len(baseline),'rows':sum(v['rows'] for v in baseline.values()),'syntheticOnly':True,'checks':evidence},indent=2)+'\n')
        print('PASS: two isolated MySQL servers;',len(baseline),'tables; complete hashes, import failure rollback, retry, idempotency, changed-target protection, key and endpoint guards')
    finally:
        run(compose+['down', '--volumes'])
        shutil.rmtree(folder)


if __name__ == '__main__':
    main()
