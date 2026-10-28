<?php
/**
 * =========================================================================================
 * TEST RUNNER TỰ ĐỘNG CHO DỰ ÁN GAME NỐI ỐNG NƯỚC (HUIT)
 * Chạy toàn bộ 23 ca kiểm thử Unit & Feature Tests cho Core Game & PvP Real-time
 * =========================================================================================
 */

namespace PHPUnit\Framework {
    if (!class_exists('PHPUnit\Framework\TestCase')) {
        class TestCase {
            public function assertTrue($condition, $message = '') {
                if (!$condition) throw new \Exception('Failed asserting that true: ' . $message);
            }
            public function assertFalse($condition, $message = '') {
                if ($condition) throw new \Exception('Failed asserting that false: ' . $message);
            }
            public function assertEquals($expected, $actual, $message = '') {
                if ($expected != $actual) throw new \Exception("Failed asserting that " . var_export($actual, true) . " matches expected " . var_export($expected, true) . ". " . $message);
            }
            public function assertNotEquals($expected, $actual, $message = '') {
                if ($expected == $actual) throw new \Exception('Failed asserting not equal: ' . $message);
            }
            public function assertEqualsCanonicalizing($expected, $actual, $message = '') {
                sort($expected); sort($actual);
                if ($expected != $actual) throw new \Exception("Failed asserting canonical: " . json_encode($actual) . " vs " . json_encode($expected) . ". " . $message);
            }
            public function assertGreaterThan($expected, $actual, $message = '') {
                if ($actual <= $expected) throw new \Exception("Failed asserting {$actual} > {$expected}. " . $message);
            }
            public function assertLessThan($expected, $actual, $message = '') {
                if ($actual >= $expected) throw new \Exception("Failed asserting {$actual} < {$expected}. " . $message);
            }
            public function assertCount($expected, $array, $message = '') {
                if (count($array) !== $expected) throw new \Exception("Failed count: " . count($array) . " vs {$expected}. " . $message);
            }
            public function assertNotNull($val, $message = '') {
                if ($val === null) throw new \Exception('Failed asserting not null: ' . $message);
            }
            public function assertStringContainsString($needle, $haystack, $message = '') {
                if (!str_contains((string)$haystack, (string)$needle)) throw new \Exception("Failed asserting that '{$haystack}' contains '{$needle}'. " . $message);
            }
            public function markTestSkipped($message = '') {
                echo "[SKIPPED] " . $message . "\n";
            }
        }
    }
}

namespace {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        $autoloadPath = '/var/www/html/vendor/autoload.php';
    }
    require_once $autoloadPath;

    $testFilePath = __DIR__ . '/Feature/GameProjectTest.php';
    if (!file_exists($testFilePath)) {
        $testFilePath = '/var/www/html/tests/Feature/GameProjectTest.php';
    }
    require_once $testFilePath;

    echo "====================================================================\n";
    echo "  HUIT GAME NỐI ỐNG NƯỚC - BỘ KIỂM THỬ TỰ ĐỘNG (ACCEPTANCE TESTS)  \n";
    echo "====================================================================\n\n";

    $tester = new \Tests\Feature\GameProjectTest();
    $methods = get_class_methods($tester);
    $passed = 0;
    $failed = 0;

    foreach ($methods as $m) {
        if (str_starts_with($m, 'test_')) {
            try {
                $tester->$m();
                echo "[PASS] " . $m . "\n";
                $passed++;
            } catch (\Throwable $e) {
                echo "[FAIL] " . $m . ": " . $e->getMessage() . "\n";
                $failed++;
            }
        }
    }

    echo "\n--------------------------------------------------------------------\n";
    echo "Tổng kết kiểm thử: {$passed} PASSED, {$failed} FAILED.\n";
    echo "--------------------------------------------------------------------\n";

    if ($failed > 0) {
        exit(1);
    }
    exit(0);
}
