<?php
declare(strict_types=1);

namespace app\helpers {

    class Translation
    {
        private static $dictionary = [];
        private static $currentLang = 'es';

        public static function init()
        {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Default to Spanish or the one stored in session
            self::$currentLang = $_SESSION['lang'] ?? 'es';

            $filePath = __DIR__ . "/../lang/" . self::$currentLang . ".php";
            
            if (file_exists($filePath)) {
                self::$dictionary = require $filePath;
            } else {
                // Fallback to English if file not found
                $fallbackPath = __DIR__ . "/../lang/en.php";
                if (file_exists($fallbackPath)) {
                    self::$dictionary = require $fallbackPath;
                } else {
                    self::$dictionary = [];
                }
            }
        }

        public static function get(string $key, array $placeholders = []): string
        {
            $text = self::$dictionary[$key] ?? $key;

            foreach ($placeholders as $k => $v) {
                $text = str_replace("{{$k}}", (string)$v, $text);
            }

            return $text;
        }

        public static function getLang(): string
        {
            return self::$currentLang;
        }

        public static function getDictionary(): array
        {
            return self::$dictionary;
        }
    }
}

namespace {
    // Global helper function for brevity
    if (!function_exists('__')) {
        function __(string $key, array $placeholders = []): string
        {
            return \app\helpers\Translation::get($key, $placeholders);
        }
    }
}
