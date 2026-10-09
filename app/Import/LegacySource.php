<?php

namespace App\Import;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The tkwiki database being imported, as the `legacy` connection (built at runtime from a DSN).
 */
class LegacySource
{
    public const CONNECTION = 'legacy';

    /** Tables every tkwiki 8 install has. */
    public const TABLES = ['auth', 'user', 'page', 'content', 'links', 'menu_item', 'secret', 'registry'];

    public function __construct(private string $dsn) {}

    /**
     * Connection config from mysql://user:pass@host:port/db or sqlite:///absolute/path.sqlite.
     *
     * @return array<string, mixed>
     */
    public static function config(string $dsn): array
    {
        // parse_url() rejects "sqlite:///path" (empty host), so take the path as-is.
        if (str_starts_with(strtolower($dsn), 'sqlite://')) {
            return ['driver' => 'sqlite', 'database' => substr($dsn, strlen('sqlite://')), 'prefix' => '', 'foreign_key_constraints' => false];
        }

        $parts = parse_url($dsn) ?: [];
        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['mysql', 'mariadb'], true) || empty($parts['host']) || empty(trim($parts['path'] ?? '', '/'))) {
            throw new InvalidArgumentException('Use --from=mysql://user:password@host:port/database');
        }

        return [
            'driver' => $scheme,
            'host' => $parts['host'],
            'port' => $parts['port'] ?? 3306,
            'database' => trim($parts['path'], '/'),
            'username' => rawurldecode($parts['user'] ?? ''),
            'password' => rawurldecode($parts['pass'] ?? ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ];
    }

    /** Open the connection and check it looks like tkwiki. */
    public function connect(): Connection
    {
        config(['database.connections.'.self::CONNECTION => self::config($this->dsn)]);
        DB::purge(self::CONNECTION);
        $db = DB::connection(self::CONNECTION);

        $missing = array_values(array_filter(self::TABLES, fn (string $t) => ! $db->getSchemaBuilder()->hasTable($t)));
        if ($missing !== []) {
            throw new InvalidArgumentException('Not a tkwiki database — missing tables: '.implode(', ', $missing));
        }

        return $db;
    }

    public function db(): Connection
    {
        return DB::connection(self::CONNECTION);
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return collect(self::TABLES)->mapWithKeys(fn (string $t) => [$t => $this->db()->table($t)->count()])->all();
    }
}
