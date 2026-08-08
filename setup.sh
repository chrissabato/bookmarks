#!/bin/bash
set -e

# The schema self-initializes on first request (see db.php init_schema()),
# so touch the db file first if it doesn't exist yet, then fix permissions
# so Apache can write to it.
touch bookmarks.db
chgrp apache bookmarks.db
chmod 664 bookmarks.db
chgrp apache .
chmod g+s .

echo "Done. You can now delete setup.sh."
