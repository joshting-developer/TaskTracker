#!/bin/sh
# Use a pinned upstream scanner and verify the downloaded archive before execution.
set -eu
umask 077
folder=$(mktemp -d)
trap 'rm -rf "$folder"' EXIT
version=8.30.1
archive="gitleaks_${version}_linux_x64.tar.gz"
base="https://github.com/gitleaks/gitleaks/releases/download/v${version}"
curl --fail --silent --show-error --location "$base/$archive" -o "$folder/$archive"
curl --fail --silent --show-error --location "$base/gitleaks_${version}_checksums.txt" -o "$folder/checksums.txt"
python3 - "$folder" "$archive" <<'PY'
import hashlib,sys,tarfile
from pathlib import Path
folder=Path(sys.argv[1]);name=sys.argv[2]
expected=next(line.split()[0] for line in (folder/'checksums.txt').read_text().splitlines() if line.split()[-1]==name)
assert hashlib.sha256((folder/name).read_bytes()).hexdigest()==expected
with tarfile.open(folder/name) as archive:
    member=archive.getmember('gitleaks')
    assert member.isfile()
    output=folder/'gitleaks';output.write_bytes(archive.extractfile(member).read());output.chmod(0o700)
PY
"$folder/gitleaks" git --redact=100 --log-opts=--all .
