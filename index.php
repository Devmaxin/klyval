<?php
declare(strict_types=1);
// Deployment entry point for hosts whose web root is the project folder (InfinityFree "htdocs").
// All routing and bootstrap logic lives in public/index.php. Do not add anything else here.
require __DIR__ . '/public/index.php';
