#!/bin/sh
# Generate disposable QA credentials locally. Never use production credentials here.
set -eu
cd "$(dirname "$0")"
if [ -e .env ]; then
    echo 'QA .env already exists; left unchanged.'
    exit 0
fi
umask 077
python3 - <<'PY'
from pathlib import Path
import secrets
with Path('.env').open('x') as env:
    env.write('WOODEX_QA_PASSWORD='+secrets.token_hex(24)+'\n')
    env.write('WOODEX_QA_ROOT_PASSWORD='+secrets.token_hex(24)+'\n')
print('Generated private disposable QA credentials.')
PY
