<?php

// Target-side fixture for the auto-detection scenario. Keeping the canonical
// filename means the framework detector recognises it the same way it would in a
// real project, so the crossing of auto-detection and target-side features
// (anonymization, post_sql) is exercised end to end.
define('DB_NAME', 'db');
define('DB_USER', 'db');
define('DB_PASSWORD', 'db');
define('DB_HOST', 'db2');
