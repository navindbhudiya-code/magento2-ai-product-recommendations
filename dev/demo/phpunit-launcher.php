<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * Module unit-test launcher.
 *
 * WHY THIS EXISTS
 * ---------------
 * The Magento root composer.json ships the standard autoload rule:
 *     "exclude-from-classmap": ["**\/dev\/**", "**\/update\/**", "**\/Test\/**"]
 * Magento 2.4.x is designed around PHPUnit 9.5, whose classes never live under a
 * path segment named "Test". PHPUnit 10 relocated its event classes to
 *     vendor/phpunit/phpunit/src/Event/Events/Test/Lifecycle/*Subscriber.php
 * so the "**\/Test\/**" glob strips them from the optimized classmap and PHPUnit
 * cannot even boot ("Subscriber ... does not exist or is not an interface").
 *
 * This launcher restores those omitted PHPUnit classes via a fallback autoloader
 * (registered AFTER Composer's, so it only fires for classes Composer missed),
 * then runs PHPUnit normally. It mutates nothing on the host — no composer.json,
 * no vendor — so it is safe and reproducible on any checkout.
 *
 * Usage (see dev/demo/run-tests.sh):
 *     php dev/demo/phpunit-launcher.php -c <config> [phpunit args...]
 *
 * @license MIT License
 */

declare(strict_types=1);

// Walk up from this file until we find the Magento root (vendor/autoload.php).
$root = __DIR__;
while ($root !== dirname($root) && !is_file($root . '/vendor/autoload.php')) {
    $root = dirname($root);
}
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Could not locate vendor/autoload.php from " . __DIR__ . PHP_EOL);
    exit(1);
}
require $autoload;

// Fallback autoloader: recover any PHPUnit\* classes that the host classmap omitted.
spl_autoload_register(static function (string $class) use ($root): void {
    if (strncmp($class, 'PHPUnit\\', 8) !== 0) {
        return;
    }
    static $map = null;
    if ($map === null) {
        $map = [];
        $src = $root . '/vendor/phpunit/phpunit/src';
        if (is_dir($src)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $code = file_get_contents($file->getPathname());
                if ($code === false
                    || !preg_match('/^namespace\s+([^;]+);/m', $code, $ns)
                    || !preg_match('/^\s*(?:abstract\s+|final\s+)?(?:class|interface|trait|enum)\s+([A-Za-z0-9_]+)/m', $code, $cn)
                ) {
                    continue;
                }
                $map[trim($ns[1]) . '\\' . $cn[1]] = $file->getPathname();
            }
        }
    }
    if (isset($map[$class])) {
        require $map[$class];
    }
}, true, false);

// Register the module's PSR-4 namespace. The module lives in app/code (it is not a
// Composer package), so Composer's autoloader does not know about it; Magento maps it
// at runtime. For isolated unit tests we map it here: module root is two levels up
// from dev/demo.
spl_autoload_register(static function (string $class): void {
    $prefix = 'NavinDBhudiya\\ProductRecommendation\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }
    $moduleRoot = dirname(__DIR__, 2);
    $file = $moduleRoot . '/' . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

exit((new PHPUnit\TextUI\Application())->run($_SERVER['argv']));
