<?php
/**
 * 数据库连接单例
 * 支持 MySQL 和 SQLite
 */
class DB {
    private static ?PDO $instance = null;
    private static array $config = [];
    private static ?string $driver = null;

    public static function init(array $config): void {
        self::$config = $config;
    }

    public static function conn(): PDO {
        if (self::$instance === null) {
            $c = self::$config;
            $driver = $c['driver'] ?? 'mysql';

            if ($driver === 'sqlite') {
                // SQLite 模式
                $dbPath = $c['database'] ?? '/var/www/html/storage/database.sqlite';
                // 确保目录存在
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $dsn = "sqlite:$dbPath";
                self::$instance = new PDO($dsn, null, null, $c['options'] ?? []);
                // 启用外键约束
                self::$instance->exec('PRAGMA foreign_keys = ON');
            } else {
                // MySQL 模式（默认）
                $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['dbname']};charset={$c['charset']}";
                self::$instance = new PDO($dsn, $c['username'], $c['password'], $c['options']);
            }

            self::$driver = $driver;
        }
        return self::$instance;
    }

    public static function getDriver(): ?string {
        return self::$driver;
    }

    public static function isSQLite(): bool {
        return self::$driver === 'sqlite';
    }

    public static function isMySQL(): bool {
        return self::$driver === 'mysql';
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchOne(string $sql, array $params = []): ?array {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int {
        $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `$table` ($cols) VALUES ($placeholders)", array_values($data));
        return (int)self::conn()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
        $stmt = self::query("UPDATE `$table` SET $sets WHERE $where", [...array_values($data), ...$whereParams]);
        return $stmt->rowCount();
    }

    public static function beginTransaction(): void { self::conn()->beginTransaction(); }
    public static function commit(): void { self::conn()->commit(); }
    public static function rollback(): void { self::conn()->rollBack(); }

    public static function count(string $table, string $where = '1=1', array $params = []): int {
        $row = self::fetchOne("SELECT COUNT(*) as c FROM `$table` WHERE $where", $params);
        return (int)($row['c'] ?? 0);
    }
}
