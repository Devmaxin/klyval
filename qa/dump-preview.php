<?php
declare(strict_types=1);
$raw = file_get_contents(dirname(__DIR__) . '/legacy-dump.sql');
foreach (['14:44', '11:15', '15:24', '+01', '+02', '+00'] as $needle) {
    $count = substr_count($raw, $needle);
    echo "$needle  x$count", PHP_EOL;
    if ($count && $count < 5) {
        $p = 0;
        while (($p = stripos($raw, $needle, $p)) !== false) {
            echo '   …', substr($raw, max(0, $p - 60), 100), PHP_EOL;
            if ($count > 2) break;
            $p += 4;
        }
    }
}
