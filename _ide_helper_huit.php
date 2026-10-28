<?php
/**
 * FILE: _ide_helper_huit.php (Đặt tại thư mục gốc của dự án)
 * Khai báo Stub Type Hinting cho VS Code PHP Intelephense (Xóa sạch lỗi P1009, P1010)
 */

namespace Illuminate\Database\Migrations {
    abstract class Migration {
        abstract public function up(): void;
        abstract public function down(): void;
    }
}

namespace Illuminate\Database\Schema {
    class Blueprint {
        public function id(string $column = 'id') { return $this; }
        public function string(string $column, int $length = 255) { return $this; }
        public function text(string $column) { return $this; }
        public function integer(string $column) { return $this; }
        public function unsignedInteger(string $column) { return $this; }
        public function decimal(string $column, int $total = 8, int $places = 2) { return $this; }
        public function boolean(string $column) { return $this; }
        public function enum(string $column, array $allowed) { return $this; }
        public function json(string $column) { return $this; }
        public function date(string $column) { return $this; }
        public function timestamp(string $column) { return $this; }
        public function timestamps() { return $this; }
        public function foreignId(string $column) { return $this; }
        public function constrained(string $table = '') { return $this; }
        public function onDelete(string $action) { return $this; }
        public function nullOnDelete() { return $this; }
        public function unique() { return $this; }
        public function nullable() { return $this; }
        public function default(mixed $value) { return $this; }
        public function useCurrent() { return $this; }
        public function primary(string|array $columns) { return $this; }
    }
}

namespace Illuminate\Support\Facades {
    class Schema {
        public static function create(string $table, \Closure $callback): void {}
        public static function dropIfExists(string $table): void {}
        public static function hasTable(string $table): bool { return true; }
    }
}

namespace Illuminate\Database\Eloquent\Factories {
    abstract class Factory {
        abstract public function definition(): array;
        public function count(int $count) { return $this; }
        public function create(array $attributes = []) {}
    }
    trait HasFactory {
        public static function factory(mixed ...$parameters): mixed {
            return new static();
        }
    }
}

namespace Illuminate\Support {
    class Str {
        public static function slug(string $title, string $separator = '-'): string { return ''; }
    }
}

namespace {
    if (!function_exists('fake')) {
        class HuitFakerStub {
            public function name(): string { return ''; }
            public function unique(): self { return $this; }
            public function safeEmail(): string { return ''; }
            public function words(int $nb = 3, bool $asText = false): mixed { return ''; }
            public function sentence(int $nbWords = 6): string { return ''; }
            public function numberBetween(int $min = 0, int $max = 100): int { return 0; }
            public function randomElement(array $elements = []): mixed { return reset($elements); }
        }
        function fake(): HuitFakerStub {
            return new HuitFakerStub();
        }
    }
}