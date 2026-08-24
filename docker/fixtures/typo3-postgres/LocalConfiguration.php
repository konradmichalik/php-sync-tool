<?php

// Fixture for the TYPO3 auto-detection integration test against PostgreSQL.
// The `driver` decides which client the tool reaches for, and the connection
// spells out no port so that the default has to come from the driver too.
return [
    'DB' => [
        'Connections' => [
            'Default' => [
                'dbname' => 'db',
                'host' => 'pgdb1',
                'user' => 'db',
                'password' => 'db',
                'driver' => 'pdo_pgsql',
            ],
        ],
    ],
];
