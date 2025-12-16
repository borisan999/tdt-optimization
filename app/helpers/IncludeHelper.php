<?php
// Lightweight include helper for robust requires. Use in entry PHP files before other requires.

if (!function_exists('require_one_of')) {
    /**
     * Attempt to require the first existing path from the array.
     * Throws Exception if none found and $throw is true.
     *
     * @param array $candidates list of file paths to try
     * @param bool $throw throw when none found (default true)
     * @return string|false path included or false if none and $throw==false
     */
    function require_one_of(array $candidates, $throw = true)
    {
        foreach ($candidates as $p) {
            if (is_file($p)) {
                require_once $p;
                return $p;
            }
        }
        $msg = "[IncludeHelper] Missing include. Looked for: " . implode(", ", $candidates);
        error_log($msg);
        if ($throw) {
            throw new \Exception($msg);
        }
        return false;
    }
}