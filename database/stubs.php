<?php
/**
 * FILE: database/stubs.php
 * Định nghĩa đầy đủ Seeder, Hash, Model (create, updateOrCreate, belongsTo, hasOne, hasMany...),
 * trait HasFactory, trait Notifiable, Migration, Blueprint, Request, Auth cho Intelephense.
 */

namespace Illuminate\Contracts\Validation {
    if (!interface_exists(ValidationRule::class)) {
        interface ValidationRule {
            public function validate(string $attribute, mixed $value, \Closure $fail): void;
        }
    }
}

namespace Illuminate\Database {
    if (!class_exists('Illuminate\Database\Seeder', false)) {
        abstract class Seeder {
            abstract public function run(): void;
        }
    }
}

namespace Illuminate\Support\Facades {
    if (!class_exists('Illuminate\Support\Facades\Hash', false)) {
        class Hash {
            public static function make(string $value): string {
                return password_hash($value, PASSWORD_BCRYPT);
            }
            public static function check(string $value, string $hashedValue): bool {
                return password_verify($value, $hashedValue);
            }
        }
    }
    if (!class_exists('Illuminate\Support\Facades\Schema', false)) {
        class Schema {
            public static function create(string $table, \Closure $callback): void {}
            public static function dropIfExists(string $table): void {}
            public static function hasTable(string $table): bool { return true; }
        }
    }
    if (!class_exists('Illuminate\Support\Facades\DB', false)) {
        class DB {
            public static function raw(string $value): mixed { return $value; }
            public static function select(string $query, array $bindings = []): array { return []; }
            public static function table(string $table): mixed { return null; }
            public static function transaction(\Closure $callback): mixed { return $callback(); }
        }
    }
    if (!class_exists('Illuminate\Support\Facades\Auth', false)) {
        class Auth {
            public static function user(): mixed { return null; }
            public static function id(): ?int { return null; }
            public static function check(): bool { return false; }
            public static function guest(): bool { return true; }
            public static function attempt(array $credentials = [], bool $remember = false): bool { return false; }
            public static function login(mixed $user, bool $remember = false): void {}
            public static function logout(): void {}
        }
    }
}

namespace Illuminate\Database\Eloquent {
    if (!class_exists('Illuminate\Database\Eloquent\Model', false)) {
        abstract class Model {
            public mixed $id;

            public static function create(array $attributes = []): static {
                return new static();
            }

            public static function updateOrCreate(array $attributes, array $values = []): static {
                return new static();
            }

            public static function find(mixed $id): ?static {
                return new static();
            }

            public static function findOrFail(mixed $id): static {
                return new static();
            }

            public static function where(mixed ...$args): static {
                return new static();
            }

            public static function whereIn(mixed ...$args): static {
                return new static();
            }

            public static function whereNull(mixed ...$args): static {
                return new static();
            }

            public static function whereNotNull(mixed ...$args): static {
                return new static();
            }

            public static function select(mixed ...$args): static {
                return new static();
            }

            public static function with(mixed ...$args): static {
                return new static();
            }

            public static function orderBy(mixed ...$args): static {
                return new static();
            }

            public static function orderByDesc(mixed ...$args): static {
                return new static();
            }

            public static function all(): mixed {
                return [];
            }

            public static function count(): int {
                return 0;
            }

            public static function query(): static {
                return new static();
            }

            public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): mixed {
                return $this;
            }

            public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): mixed {
                return $this;
            }

            public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): mixed {
                return $this;
            }

            public function belongsToMany(string $related, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null): mixed {
                return $this;
            }

            public function withPivot(mixed ...$args): static {
                return $this;
            }

            public function save(array $options = []): bool {
                return true;
            }

            public function update(array $attributes = [], array $options = []): bool {
                return true;
            }

            public function delete(): ?bool {
                return true;
            }

            public function fill(array $attributes): static {
                return $this;
            }

            public function toArray(): array {
                return [];
            }

            public function tags(): mixed { return $this; }
            public function achievements(): mixed { return $this; }
            public function syncWithoutDetaching(mixed $ids): void {}
        }
    }

    if (!class_exists('Illuminate\Database\Eloquent\Builder', false)) {
        class Builder {
            public function with(mixed ...$args): static { return $this; }
            public function select(mixed ...$args): static { return $this; }
            public function where(mixed ...$args): static { return $this; }
            public function whereIn(mixed ...$args): static { return $this; }
            public function whereNull(mixed ...$args): static { return $this; }
            public function whereNotNull(mixed ...$args): static { return $this; }
            public function groupBy(mixed ...$args): static { return $this; }
            public function orderBy(mixed ...$args): static { return $this; }
            public function orderByDesc(mixed ...$args): static { return $this; }
            public function limit(int $value): static { return $this; }
            public function get(): mixed { return []; }
            public function first(): mixed { return null; }
            public function count(): int { return 0; }
        }
    }
}

namespace Illuminate\Database\Eloquent\Factories {
    if (!trait_exists('Illuminate\Database\Eloquent\Factories\HasFactory', false)) {
        trait HasFactory {
            public static function factory(mixed ...$parameters): mixed {
                return new static();
            }
        }
    }

    if (!class_exists('Illuminate\Database\Eloquent\Factories\Factory', false)) {
        abstract class Factory {
            abstract public function definition(): array;
            public function count(int $count): static { return $this; }
            public function create(array $attributes = []): mixed { return null; }
        }
    }
}

namespace Illuminate\Notifications {
    if (!trait_exists('Illuminate\Notifications\Notifiable', false)) {
        trait Notifiable {}
    }
}

namespace Illuminate\Foundation\Auth {
    if (!class_exists('Illuminate\Foundation\Auth\User', false)) {
        class User extends \Illuminate\Database\Eloquent\Model {}
    }
}

namespace Illuminate\Http {
    if (!class_exists('Illuminate\Http\Request', false)) {
        class Request {
            public mixed $username;
            public mixed $email;
            public mixed $password;
            public mixed $password_confirmation;

            public function input(string $key, mixed $default = null): mixed { return $_REQUEST[$key] ?? $default; }
            public function validate(array $rules, array $messages = []): array { return []; }
            public function session(): mixed {
                return new class {
                    public function regenerate(): void {}
                    public function invalidate(): void {}
                    public function regenerateToken(): void {}
                };
            }
        }
    }
}

namespace Illuminate\Database\Migrations {
    if (!class_exists('Illuminate\Database\Migrations\Migration', false)) {
        abstract class Migration {
            abstract public function up(): void;
            abstract public function down(): void;
        }
    }
}

namespace Illuminate\Database\Schema {
    if (!class_exists('Illuminate\Database\Schema\Blueprint', false)) {
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
}

namespace Illuminate\Support {
    if (!class_exists('Illuminate\Support\Str', false)) {
        class Str {
            public static function slug(string $title, string $separator = '-'): string {
                return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', $separator, $title)));
            }
        }
    }
}

namespace {
    if (!function_exists('fake')) {
        class HuitFakerInstance {
            public function name(): string { return 'Demo Name'; }
            public function unique(): self { return $this; }
            public function safeEmail(): string { return 'demo@huit.edu.vn'; }
            public function words(int $nb = 3, bool $asText = false): mixed { return $asText ? 'pipe game map' : ['pipe', 'game', 'map']; }
            public function sentence(int $nbWords = 6): string { return 'Màn chơi giải đố nối ống nước hấp dẫn.'; }
            public function numberBetween(int $min = 0, int $max = 100): int { return mt_rand($min, $max); }
            public function randomElement(array $elements = []): mixed { return $elements[array_rand($elements)]; }
        }
        function fake(): HuitFakerInstance {
            return new HuitFakerInstance();
        }
    }
}