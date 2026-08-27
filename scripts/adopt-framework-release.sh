#!/usr/bin/env bash
#
# Move this application off the framework's development branch and onto a published
# release.
#
#   scripts/adopt-framework-release.sh '^7.0@RC'   # at the release candidate
#   scripts/adopt-framework-release.sh '^7.0'      # at v7.0.0
#
# What it does, in order:
#   1. rewrites the gcgov/framework constraint in composer.json
#   2. deletes the `repositories` vcs entry, if the bridge is still there
#   3. regenerates composer.lock against the published package
#   4. verifies the lock installs and the suite passes
#
# The bridge it removes is temporary scaffolding: before v7.0.0-rc.1 existed there was no
# published version to depend on, so composer.json pointed at the framework's development
# branch through a `vcs` repository. Once a release is tagged, that indirection is exactly
# the kind of thing that quietly stays for years.
#
# config.platform.php stays. It pins resolution to the PHP the production image runs, and
# without it, resolving on a newer PHP locks packages that will not install in the image.
#
# If composer complains about a missing extension here, ignore that extension by name
# (--ignore-platform-req=ext-mongodb). NEVER reach for blanket --ignore-platform-reqs: it
# also discards the php pin, which silently locks packages the production image cannot
# install. The `composer install` below is what catches that, so do not skip it either.

set -euo pipefail

CONSTRAINT="${1:-}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ -z "$CONSTRAINT" ]; then
	sed -n '3,8p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
	exit 1
fi

command -v composer >/dev/null || { echo 'composer is not on PATH' >&2; exit 1; }
cd "$ROOT"

echo "==> setting gcgov/framework to $CONSTRAINT"
python3 - "$CONSTRAINT" <<'PY'
import collections, json, sys

constraint = sys.argv[1]
with open('composer.json') as handle:
    document = json.load(handle, object_pairs_hook=collections.OrderedDict)

document['require']['gcgov/framework'] = constraint

# The vcs bridge pointed at the framework's development branch. A published release makes
# it not just unnecessary but harmful: it would keep resolving from a moving branch.
removed = document.pop('repositories', None)
if removed:
    print('    removed the vcs repositories entry')

with open('composer.json', 'w') as handle:
    json.dump(document, handle, indent='\t')
    handle.write('\n')
PY

# --no-check-lock: the lock is deliberately stale at this point — the constraint has just
# changed and the regeneration is the next step. Only the file's own validity matters here.
composer validate --no-check-lock --no-check-publish --no-check-all

echo '==> regenerating composer.lock'
composer update gcgov/framework --with-all-dependencies --no-install --no-interaction

# Now the lock must agree with composer.json, so check that too.
composer validate --no-check-publish --no-check-all

echo '==> verifying'
composer install --no-interaction --no-progress --prefer-dist
composer ci

installed="$(python3 -c "
import json
lock = json.load(open('composer.lock'))
print(next(p['version'] for p in lock['packages'] if p['name'] == 'gcgov/framework'))
")"

case "$installed" in
	dev-*) echo "still on a development version ($installed) — the constraint did not take" >&2; exit 1 ;;
	*)     echo "==> locked to gcgov/framework $installed" ;;
esac

cat <<NEXT

Done. Review and commit:

  git add composer.json composer.lock
  git commit -m 'Adopt gcgov/framework $installed'

NEXT
