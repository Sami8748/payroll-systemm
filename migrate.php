<?php

require_once 'db.php';

run_migrations(db());

echo "Migration completed";