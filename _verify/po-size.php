<?php
/**
 * Compares the printed signature size against the ORIGINAL canvas-based rendition, and reports the
 * horizontal centring and vertical placement.
 *
 * The baseline to match is the original: <img> with no width, max-height 60px, drawing the stored
 * canvas, so the image kept the size it had before any of this work.
 * Read-only. Throwaway harness.
 */
require 'E:/laragon/www/OCP1/config/db_config.php';
$php = 'E:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe';

$adv = json_decode(file_get_contents('E:/laragon/www/OCP1/vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ufm.json'), true)['C'];
$tw = function (string $s) use ($adv): float {
    $u = 0;
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) { $u += (float) ($adv[(string) mb_ord($ch)] ?? 0); }
    return $u / 1000 * 9.0;
};

$code = '<?php session_id($argv[2]); session_start(); $_SESSION["user_id"]=(int)$argv[1]; session_write_close(); echo session_id();';
$t = tempnam(sys_get_temp_dir(), 'm') . '.php'; file_put_contents($t, $code);

// The size the original produced: image drawn 1:1, so its page size is canvas * 0.75 (CSS px to pt).
$orig = [];
foreach ($pdo->query("SELECT id, approval_signature, completion_signature FROM gasoline_purchase_orders") as $r) {
    foreach ([['approval', $r['approval_signature']], ['completion', $r['completion_signature']]] as [$w, $d]) {
        if (empty($d) || strpos($d, 'data:image') !== 0) { continue; }
        $im = @imagecreatefromstring(base64_decode(substr($d, strpos($d, ',') + 1)));
        if (!$im) { continue; }
        $cw = imagesx($im); $chh = imagesy($im);
        imagedestroy($im);
        // Natural size capped at max-height 60px; keep aspect.
        $h = min($chh * 0.75, 60.0);
        $wd = $cw * 0.75 * ($h / ($chh * 0.75));
        $orig[$r['id']][$w] = ['w' => $wd, 'h' => $h, 'canvas' => "{$cw}x{$chh}"];
    }
}

foreach ([67, 68, 56, 49, 58, 51] as $po) {
    $sid = trim(shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($t) . ' 15 ' . bin2hex(random_bytes(13))));
    $ch = curl_init('http://localhost/OCP1/generate_gas_po_pdf.php?id=' . $po);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIE => 'PHPSESSID=' . $sid, CURLOPT_TIMEOUT => 60]);
    $pdf = curl_exec($ch); curl_close($ch);
    if (strlen($pdf) < 5000) { printf("PO %-4s PDF FAILED\n", $po); continue; }

    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m);
    $c = '';
    foreach ($m[1] as $s) { $p = @gzuncompress($s); if ($p === false) { $p = @gzinflate($s); } if ($p !== false) { $c .= $p . "\n"; } }

    $imgs = [];
    preg_match_all('/([\d\.\-]+) 0 0 ([\d\.\-]+) ([\d\.\-]+) ([\d\.\-]+) cm\s*\/\w+ Do/', $c, $rr, PREG_SET_ORDER);
    foreach ($rr as $r) {
        $w = (float) $r[1]; $h = (float) $r[2]; $x = (float) $r[3]; $y = (float) $r[4];
        if ($h <= 0 || $h >= 80 || $w / $h < 0.5) { continue; }   // skip the 45x45 logo
        if ($y > 870) { continue; }                              // skip the header logo
        $imgs[] = ['cx' => $x + $w / 2, 'w' => $w, 'h' => $h, 'bottom' => $y, 'top' => $y + $h, 'x' => $x];
    }
    usort($imgs, fn($a, $b) => $a['cx'] <=> $b['cx']);

    $runs = [];
    preg_match_all('/BT(.*?)ET/s', $c, $bl, PREG_SET_ORDER);
    foreach ($bl as $one) {
        if (!preg_match('/([\d\.\-]+)\s+([\d\.\-]+)\s+Td/', $one[1], $p)) { continue; }
        $txt = '';
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $one[1], $tj)) {
            foreach ($tj[1] as $chunk) { preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $chunk, $pc); foreach ($pc[0] as $q) { $txt .= trim($q, '()'); } }
        }
        $txt = trim(preg_replace('/\s+/', ' ', $txt));
        if ($txt === '' || !preg_match('/^(LESTER R\. ALIPIO|CEO)/', $txt)) { continue; }
        $runs[] = ['x' => (float) $p[1], 'y' => (float) $p[2], 'txt' => $txt];
    }

    echo "PO $po\n";
    $seen = [];
    foreach ($imgs as $im) {
        $best = null;
        foreach ($runs as $r) {
            $ncx = $r['x'] + $tw($r['txt']) / 2;
            $dy = $r['y'] < $im['bottom'] ? $im['bottom'] - $r['y'] : $r['y'] - $im['top'];
            if ($dy > 20) { continue; }
            if ($best === null || abs($ncx - $im['cx']) < abs($best['ncx'] - $im['cx'])) { $best = ['ncx' => $ncx, 'txt' => $r['txt'], 'y' => $r['y']]; }
        }
        $who = $best ? ($best['txt'] === 'LESTER R. ALIPIO' ? 'completion' : 'approval') : '?';
        $key = $who . round($im['w'], 1) . round($im['h'], 1);
        if (isset($seen[$key])) { continue; }
        $seen[$key] = true;

        $o = $orig[$po][$who] ?? null;
        printf("  %-11s printed %-7s original %-14s %s | centre offset %s | baseline %s\n",
            $who,
            round($im['w'], 1) . 'x' . round($im['h'], 1),
            $o ? round($o['w'], 1) . 'x' . round($o['h'], 1) . ' (' . $o['canvas'] . ')' : 'n/a',
            $o ? (abs($im['w'] - $o['w']) < 2 && abs($im['h'] - $o['h']) < 2 ? 'SAME SIZE' : 'DIFFERENT') : '',
            $best ? sprintf('%+.1f pt', $im['cx'] - $best['ncx']) : '?',
            $best ? (($best['y'] > $im['bottom'] && $best['y'] < $im['top']) ? 'in' : 'OUT') : '?');
    }
    echo "\n";
}
unlink($t);
