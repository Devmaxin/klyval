<?php
// throwaway: dump legacy + Postgres column names side by side to spot drift
foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') as $f) {
    $s = file_get_contents($f);
    if (!preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?(\w+)\s*\((.*?)\)\s*(?:ENGINE|;)/is', $s, $m, PREG_SET_ORDER)) continue;
    foreach ($m as $t) {
        $cols = [];
        foreach (preg_split('/\r?\n/', $t[2]) as $l) { $l = trim($l); if (preg_match('/^(\w+)\s+[A-Z]/i', $l, $c)) $cols[] = $c[1]; }
        printf("%-25s %s\n", $t[1], implode(', ', $cols));
    }
}
