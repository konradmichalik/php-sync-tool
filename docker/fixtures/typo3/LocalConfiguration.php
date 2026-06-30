<?php

// Fixture for the TYPO3 credential auto-detection integration test.
// The tool runs `php -r "echo json_encode(include '<this>');"` on the origin
// node and extracts ['DB']['Connections']['Default'].
return [
    'DB' => [
        'Connections' => [
            'Default' => [
                'dbname' => 'db',
                'host' => 'db1',
                'user' => 'db',
                'password' => 'db',
                'port' => 3306,
            ],
        ],
    ],
];
