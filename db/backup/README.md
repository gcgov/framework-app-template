# db/backup

A `mongodump` of a database goes here, in a directory named for the database it came
from, and `docker compose run --rm mongo-restore` restores it into the development
database:

```bash
mongodump --uri="<source uri>" --db=myapp --out=db/backup   # writes db/backup/myapp/
docker compose run --rm mongo-restore
```

The restore is described in [LOCAL-DEVELOPMENT.md](../../LOCAL-DEVELOPMENT.md).

Everything in this directory is git-ignored, and it stays that way. A backup carries the
same data the database carries — treat the directory as you treat the database.
