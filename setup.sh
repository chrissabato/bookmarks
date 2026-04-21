#!/bin/bash
set -e

# Create the database and schema
php setup.php

# Set permissions for Apache
chgrp apache bookmarks.db
chmod 664 bookmarks.db
chgrp apache .
chmod g+s .

echo "Done. You can now delete setup.php and setup.sh."
