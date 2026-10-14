<?php

$files = glob(__DIR__.'/../database/migrations/*.php');
foreach ($files as $file) {
    $c = file_get_contents($file);
    $table = null;
    if (preg_match("/Schema::create\\('([^']+)'/", $c, $tm)) {
        // multiple creates possible; approximate per match blocks later
    }

    // Explicit index names longer than 64
    if (preg_match_all("/->(?:index|unique)\\([^;]*'([A-Za-z0-9_]+)'\\s*\\)/", $c, $m)) {
        foreach ($m[1] as $name) {
            if (strlen($name) > 64) {
                echo basename($file)." EXPLICIT {$name} (".strlen($name).")\n";
            }
        }
    }

    // Auto names: table_col_col_index — find create blocks
    if (preg_match_all("/Schema::create\\('([^']+)',\\s*function\\s*\\(Blueprint\\s*\\\$table\\)\\s*\\{(.*?)\\}\\);/s", $c, $blocks, PREG_SET_ORDER)) {
        foreach ($blocks as $block) {
            $t = $block[1];
            $body = $block[2];
            if (preg_match_all('/->index\(\[([^\]]+)\]\)/', $body, $idxs)) {
                foreach ($idxs[1] as $colsRaw) {
                    $cols = array_map(static function ($x) {
                        return trim($x, " \t\n\r'\"");
                    }, explode(',', $colsRaw));
                    $name = $t.'_'.implode('_', $cols).'_index';
                    if (strlen($name) > 64) {
                        echo basename($file)." AUTO {$name} (".strlen($name).")\n";
                    }
                }
            }
            if (preg_match_all('/->unique\(\[([^\]]+)\]\)/', $body, $idxs)) {
                foreach ($idxs[1] as $colsRaw) {
                    $cols = array_map(static function ($x) {
                        return trim($x, " \t\n\r'\"");
                    }, explode(',', $colsRaw));
                    $name = $t.'_'.implode('_', $cols).'_unique';
                    if (strlen($name) > 64) {
                        echo basename($file)." AUTO {$name} (".strlen($name).")\n";
                    }
                }
            }
        }
    }
}

echo "done\n";
