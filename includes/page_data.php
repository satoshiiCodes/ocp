<?php
/**
 * includes/page_data.php
 *
 * Centralised bridge between the PHP pages and the JavaScript in assets/js/.
 *
 * Every page renders ONE JSON "data island" into the document:
 *
 *     <script>window.OCP_PAGE_PAGE = { ... };</script>
 *
 * and the matching script reads it:
 *
 *     const DATA = window.OCP_PAGE_DASHBOARD || {};
 *
 * Usage inside a page (after config/db_config.php has been loaded):
 *
 *     require_once __DIR__ . '/includes/page_data.php';
 *     ...
 *     ocp_page_data('dashboard', [
 *         'noStockCount' => $no_stock_count,   // scalar
 *         'monthLabels'  => $months,           // array becomes a JSON array
 *         'guard12'      => $guard12,          // pre-rendered text
 *     ]);
 *
 * Values are JSON encoded with JSON_HEX_TAG/AMP/APOS/QUOT, so a value can never
 * terminate the surrounding script element. Anything that cannot be encoded
 * (a PDO handle, a closure, ...) becomes null instead of breaking the page.
 *
 * Helpers used by the generated markup:
 *   ocp_page_data()  renders the island
 *   ocp_js_string()  escapes a value for a position INSIDE a JS string literal
 *   ocp_js_raw()     encodes a value for a position where JS expects a literal
 *   ocp_raw_value()  emits a fragment verbatim
 */

if (!class_exists('OCP_Json_Slice', false)) {
    /** Internal marker: carries a JSON fragment that must not be re-encoded. */
    class OCP_Json_Slice
    {
        public $json;

        public function __construct($json)
        {
            $this->json = $json;
        }
    }
}

if (!defined('OCP_JSON_FLAGS')) {
    define('OCP_JSON_FLAGS', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
}

if (!function_exists('ocp_json')) {
    /**
     * Marks a value that is already a JSON string, so ocp_page_data() passes it
     * through instead of encoding it twice.
     *
     * @param mixed $value
     *
     * @return string
     */
    function ocp_json($value)
    {
        if ($value === null) {
            return 'null';
        }
        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? 'null' : $value;
        }
        $encoded = json_encode($value, OCP_JSON_FLAGS);
        return $encoded === false ? 'null' : $encoded;
    }
}

if (!function_exists('ocp_slice')) {
    /**
     * Wraps a value so it is encoded exactly once, here.
     *
     * @param mixed $value
     *
     * @return OCP_Json_Slice
     */
    function ocp_slice($value)
    {
        return new OCP_Json_Slice(ocp_encode_value($value));
    }
}

if (!function_exists('ocp_encode_value')) {
    /**
     * Encodes one value for the data island and returns a JSON fragment.
     *
     * @param mixed $value
     *
     * @return string
     */
    function ocp_encode_value($value)
    {
        if ($value instanceof OCP_Json_Slice) {
            return $value->json;
        }

        if (is_resource($value) || $value instanceof Closure) {
            return 'null';
        }

        // Plain objects (stdClass) encode fine; anything else (PDOStatement,
        // DOMDocument, ...) is not worth serialising.
        if (is_object($value) && !($value instanceof JsonSerializable) && get_class($value) !== 'stdClass') {
            return 'null';
        }

        if (is_float($value) && (is_nan($value) || is_infinite($value))) {
            return 'null';
        }

        $encoded = json_encode($value, OCP_JSON_FLAGS);
        return $encoded === false ? 'null' : $encoded;
    }
}

if (!function_exists('ocp_capture')) {
    /**
     * Renders one of the PHP fragments under includes/partials/ and returns its
     * output.
     *
     * Those fragments started life as the PHP half of an inline script block.
     * Keeping them in a real file preserves the exact PHP open and close tag
     * boundaries of the original markup, so a captured block behaves precisely
     * as it did inline - and nothing is ever eval()d.
     *
     * @param string $file Absolute path to the fragment.
     *
     * @return string
     */
    function ocp_capture($file)
    {
        if (!is_file($file)) {
            return '';
        }

        ob_start();
        include $file;
        return ob_get_clean();
    }
}

if (!function_exists('ocp_js_string')) {
    /**
     * Encodes a value for a position INSIDE a JavaScript string literal.
     *
     * The result is a JSON string without the surrounding quotes, so it can be
     * dropped straight into an existing single or double quoted literal without
     * breaking out of it (quotes, backslashes and newlines are escaped).
     *
     * @param mixed $value
     *
     * @return string
     */
    function ocp_js_string($value)
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        $encoded = json_encode((string) $value, OCP_JSON_FLAGS);
        if ($encoded === false || $encoded === 'null') {
            return '';
        }
        // strip the quotes json_encode() added, and keep the literal on one line
        $inner = substr($encoded, 1, -1);
        return str_replace(["\r\n", "\n", "\r"], ['\\n', '\\n', '\\n'], $inner);
    }
}

if (!function_exists('ocp_js_raw')) {
    /**
     * Encodes a value for a position where JavaScript expects a literal value
     * rather than text: numbers, booleans, JSON payloads, plain strings.
     *
     * Strings that already contain JSON are passed through unchanged.
     *
     * @param mixed $value
     *
     * @return string
     */
    function ocp_js_raw($value)
    {
        if ($value === null) {
            return 'null';
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return 'null';
            }
            json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $trimmed;
            }
            $encoded = json_encode($value, OCP_JSON_FLAGS);
            return $encoded === false ? 'null' : $encoded;
        }
        $encoded = json_encode($value, OCP_JSON_FLAGS);
        return $encoded === false ? 'null' : $encoded;
    }
}

if (!function_exists('ocp_raw_value')) {
    /**
     * Emits a value verbatim. Used for fragments PHP already produced as
     * JavaScript source (a JSON payload, true, false, ...).
     *
     * @param mixed $value
     *
     * @return string
     */
    function ocp_raw_value($value)
    {
        if ($value === null) {
            return '';
        }
        return (string) $value;
    }
}

if (!function_exists('ocp_island_get')) {
    /**
     * Reads one member of the data island.
     *
     * A server-rendered script (assets/js/<page>.js.php) is normally printed by
     * its page, which has already filled the island. When such a file is
     * requested on its own there is no page and no island, and reading a missing
     * member would emit a notice into the JavaScript. In that case a missing key
     * answers null - the script stays valid and simply does nothing.
     *
     * @param array  $island
     * @param string $key
     *
     * @return mixed
     */
    function ocp_island_get($island, $key)
    {
        if (is_array($island) && array_key_exists($key, $island)) {
            return $island[$key];
        }
        if (!empty($GLOBALS['OCP_SCRIPT_STANDALONE'])) {
            return null;
        }
        // Not standalone: report the missing member the way plain PHP would.
        return $island[$key];
    }
}

if (!function_exists('ocp_island_name')) {
    /**
     * Converts a page slug into the global data-island variable name.
     *
     * @param string $slug
     *
     * @return string
     */
    function ocp_island_name($slug)
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', (string) $slug);
        return 'OCP_PAGE_' . strtoupper(trim($slug, '_'));
    }
}

if (!function_exists('ocp_page_data')) {
    /**
     * Renders the JSON data island for one page.
     *
     * @param string $slug  Page slug (the PHP file name without .php).
     * @param array  $data  Key => value pairs exposed to assets/js/PAGE.js.
     * @param bool   $print False to return the markup instead of echoing it.
     *
     * @return string|null
     */
    function ocp_page_data($slug, array $data = [], $print = true)
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = json_encode((string) $key) . ':' . ocp_encode_value($value);
        }

        $html = '<script>window.' . ocp_island_name($slug) . ' = {' . implode(',', $pairs) . '};</script>';

        if ($print) {
            echo $html;
            return null;
        }

        return $html;
    }
}
