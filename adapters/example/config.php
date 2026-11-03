<?php

/**
 * Example adapter configuration. Copy this directory to adapters/<office>/
 * and fill in the real values there; everything under adapters/ except
 * `example` is ignored by git.
 */
return [
    // Database used by the host system (not used by the example itself).
    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=sales;charset=latin1',
        'user' => 'reader',
        'password' => '',
        'timeout' => 5, // seconds; the server is single-threaded, keep this short
    ],

    // Printer layout.
    'width' => 40,          // characters per line at the chosen pitch
    'encoding' => 'cp850',  // cp850 (Latin-American table, ESC R 12), cp437 or latin1
    'charset' => 12,        // ESC R n: 12 = Latin America, 7 = Spain, 0 = USA
    'page_length' => 0,     // lines per ticket for ESC C n, 0 = leave the printer default

    // Text printed at the top of every ticket.
    'header' => [
        'SAMPLE STORE',
        'Main Street 123',
        'Tel. 555-0100',
    ],
    'footer' => 'Thank you for your purchase',
];
