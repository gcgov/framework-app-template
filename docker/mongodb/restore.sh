#!/usr/bin/env bash
#
# Restore the development database from a mongodump backup in db/backup/.
#
# It runs in the same mongo image as the database it restores into, beside the dev
# replica set, so a developer needs no MongoDB tools on the host.
#
# It reaches that database by compose service name, built here rather than read from
# MONGO_URI. The only database this script can write to is therefore the one in this
# stack, whatever `.env` happens to hold — which is the point. A workstation restore
# that could be aimed at another Environment is what retired `gf db:restore` in v7.
#
#   docker compose run --rm mongo-restore                             # db/backup/$MONGO_DATABASE
#   docker compose run --rm -e MONGO_RESTORE_FROM=other mongo-restore # a directory under another name
#   docker compose run --rm mongo-restore --numParallelCollections=1  # extra mongorestore arguments
#
# Arguments after the service name reach mongorestore unchanged.

set -euo pipefail
shopt -s nullglob

# The dump root, as docker-compose.yml mounts db/backup. Each subdirectory in it is one
# database named for that database, which is the layout `mongodump --out` writes.
readonly BACKUP_ROOT=/backup

# The compose service that runs mongod, and the connection to it. 27017 is the port
# inside the compose network; MONGO_PORT publishes it to the host and does not apply
# here. replicaSet=rs0 for the same reason every other client uses it — see the mongodb
# service in docker-compose.yml.
readonly MONGO_SERVICE="${MONGO_SERVICE:-mongodb}"
readonly MONGO_URI="mongodb://${MONGO_SERVICE}:27017/?replicaSet=rs0"

log() { printf '==> %s\n' "$*"; }
die() { printf 'mongo-restore: %s\n' "$*" >&2; exit 1; }


# What db/backup holds right now, so an error answers the question it raises.
describe_backup_root() {
	local directories=( "$BACKUP_ROOT"/*/ )
	local names=()

	local directory
	for directory in "${directories[@]}"; do
		directory="${directory%/}"
		names+=( "${directory##*/}" )
	done

	if [[ ${#names[@]} -eq 0 ]]; then
		printf 'db/backup holds no database directories.'
		return
	fi

	printf 'db/backup holds: %s.' "${names[*]}"
}


main() {
	local target_database="${MONGO_DATABASE:-}"
	if [[ -z $target_database ]]; then
		die 'MONGO_DATABASE is not set. It is one of the variables `gf env --init` appends to .env; fill it in and run this again.'
	fi

	# Which directory to read. It defaults to the database being restored into, because a
	# dump of that database is what a developer has nine times out of ten. It differs when
	# the backup came from an Environment that names the database something else.
	local source_database="${MONGO_RESTORE_FROM:-$target_database}"
	local source_directory="$BACKUP_ROOT/$source_database"

	if [[ ! -d $source_directory ]]; then
		die "No backup at db/backup/$source_database. $(describe_backup_root) Produce one with: mongodump --uri=\"<source uri>\" --db=$source_database --out=db/backup"
	fi

	local dump_files=( "$source_directory"/*.bson "$source_directory"/*.bson.gz )
	if [[ ${#dump_files[@]} -eq 0 ]]; then
		die "db/backup/$source_database holds no .bson files, so it is not a mongodump of a database. Produce one with: mongodump --uri=\"<source uri>\" --db=$source_database --out=db/backup"
	fi

	# --drop replaces each collection the backup carries. Collections the backup does not
	# carry are left alone: this is a restore of what was dumped, not a reset of the
	# database. `docker compose down -v` is the reset.
	#
	# --nsInclude confines the run to one database even though mongorestore is pointed at
	# the whole dump root, which is what lets several databases sit in db/backup side by
	# side. Pointing it at the root rather than at the database directory is also what
	# keeps the namespaces coming from the directory names. mongorestore logs "don't know
	# what to do with file .gitignore, skipping..." for the file that keeps db/backup in
	# git — it is reading the root, and that line is expected.
	local restore_arguments=( --drop --nsInclude="$source_database.*" )

	local gzipped_files=( "$source_directory"/*.bson.gz )
	if [[ ${#gzipped_files[@]} -gt 0 ]]; then
		restore_arguments+=( --gzip )
	fi

	if [[ $source_database != "$target_database" ]]; then
		restore_arguments+=( --nsFrom="$source_database.*" --nsTo="$target_database.*" )
	fi

	log "Restoring db/backup/$source_database into $target_database on $MONGO_SERVICE"
	log 'Every collection the backup holds is dropped and rewritten.'

	mongorestore --uri="$MONGO_URI" "${restore_arguments[@]}" "$@" "$BACKUP_ROOT"

	log "Restored $target_database."
	log 'A restored account keeps its own password hash, so you sign in as a user whose password you know.'
	log 'For any other account: docker compose exec php vendor/bin/gf user:create --force --email=… --roles="…"'
}


main "$@"
