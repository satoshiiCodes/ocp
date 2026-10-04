<?php
// Puts one value into an existing session file, so a page can be loaded with a message
// already waiting for it - the state its handler would have left behind.
//
//   php _verify/seed-session.php <sid> <key> <json>
//
// The file is parsed into key/value pairs and rebuilt, rather than having text spliced into
// it: a hand-spliced edit left the serialised data malformed and PHP refused the session
// entirely ("Failed to decode session object"), which every page then answered by
// redirecting to the login. Only the given key is replaced; everything else is preserved.
$sid = $argv[1] ?? '';
$key = $argv[2] ?? '';
$json = $argv[3] ?? 'null';

if (!preg_match('/^[A-Za-z0-9]+$/', $sid) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
    fwrite(STDERR, "usage: seed-session.php <sid> <key> <json>\n");
    exit(2);
}
$value = json_decode($json, true);
if (!is_array($value)) {
    fwrite(STDERR, "value must be a JSON object\n");
    exit(2);
}

$savePath = session_save_path();
if ($savePath === '' || strpos($savePath, ';') !== false) {
    $savePath = sys_get_temp_dir();
}
$file = rtrim($savePath, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sid;
if (!is_file($file)) {
    fwrite(STDERR, "no session file at $file\n");
    exit(1);
}

/** Splits "a|s:3:\"x\";b|i:2;" into [key => serialised value]. */
function splitSession($raw)
{
    $out = [];
    $i = 0;
    $len = strlen($raw);
    while ($i < $len) {
        $bar = strpos($raw, '|', $i);
        if ($bar === false) break;
        $name = substr($raw, $i, $bar - $i);
        $j = $bar + 1;
        // skip one serialised value
        $type = $raw[$j] ?? '';
        if ($type === 'N') {
            $j += 2;
        } elseif ($type === 'i' || $type === 'b' || $type === 'd') {
            $semi = strpos($raw, ';', $j);
            $j = $semi === false ? $len : $semi + 1;
        } elseif ($type === 's') {
            $colon = strpos($raw, ':', $j);
            $quote = strpos($raw, '"', $colon);
            $close = strpos($raw, '"', $quote + 1);
            $j = $close === false ? $len : $close + 2;   // past the closing ";
        } elseif ($type === 'a') {
            // a:N:{...} - walk the braces
            $colon = strpos($raw, ':', $j);
            $open = strpos($raw, '{', $colon);
            $depth = 0;
            $k = $open;
            for (; $k < $len; $k++) {
                if ($raw[$k] === '{') $depth++;
                elseif ($raw[$k] === '}') { $depth--; if ($depth === 0) { $k++; break; } }
            }
            $j = $k;
        } else {
            break;
        }
        $out[$name] = substr($raw, $bar + 1, $j - ($bar + 1));
        $i = $j;
    }
    return $out;
}

$raw = (string) file_get_contents($file);
$pairs = splitSession($raw);

$pairs[$key] = serialize($value);

$rebuilt = '';
foreach ($pairs as $name => $serialised) {
    $rebuilt .= $name . '|' . $serialised;
}

if (file_put_contents($file, $rebuilt) === false) {
    fwrite(STDERR, "could not write $file\n");
    exit(1);
}

// prove it parses before reporting success
$check = splitSession((string) file_get_contents($file));
if (!isset($check[$key])) {
    fwrite(STDERR, "seeded value did not survive the rewrite\n");
    exit(1);
}
echo "seeded $key (" . count($check) . " keys)";
