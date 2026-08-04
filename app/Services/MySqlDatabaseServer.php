<?php

namespace App\Services;

use App\Contracts\DatabaseServer;
use App\Exceptions\DatabaseHostingException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class MySqlDatabaseServer implements DatabaseServer
{
    public function provision(string $database, string $username, string $password): void
    {
        $database = $this->identifier($database, 64);
        $username = $this->identifier($username, 32);
        $password = $this->literal($password);

        try {
            $this->connection()->unprepared("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->connection()->unprepared("CREATE USER IF NOT EXISTS '{$username}'@'%' IDENTIFIED BY {$password}");
            $this->connection()->unprepared("ALTER USER '{$username}'@'%' IDENTIFIED BY {$password}");
            $this->connection()->unprepared("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'%'");
        } catch (Throwable $exception) {
            throw new DatabaseHostingException('The database server could not provision the project database.', previous: $exception);
        }
    }

    public function drop(string $database, string $username): void
    {
        $database = $this->identifier($database, 64);
        $username = $this->identifier($username, 32);

        try {
            $this->connection()->unprepared("DROP DATABASE IF EXISTS `{$database}`");
            $this->connection()->unprepared("DROP USER IF EXISTS '{$username}'@'%'");
        } catch (Throwable $exception) {
            throw new DatabaseHostingException('The database server could not remove the project database.', previous: $exception);
        }
    }

    public function rotatePassword(string $username, string $password): void
    {
        $username = $this->identifier($username, 32);
        $password = $this->literal($password);

        try {
            $this->connection()->unprepared("ALTER USER '{$username}'@'%' IDENTIFIED BY {$password}");
        } catch (Throwable $exception) {
            throw new DatabaseHostingException('The database server could not rotate the database password.', previous: $exception);
        }
    }

    public function sizeBytes(string $database): int
    {
        $database = $this->identifier($database, 64);

        try {
            $row = $this->connection()->selectOne(
                'SELECT COALESCE(SUM(data_length + index_length), 0) AS size_bytes FROM information_schema.tables WHERE table_schema = ?',
                [$database],
            );

            return max(0, (int) ($row->size_bytes ?? 0));
        } catch (Throwable $exception) {
            throw new DatabaseHostingException('The database server could not measure database usage.', previous: $exception);
        }
    }

    public function setReadOnly(string $database, string $username, bool $readOnly): void
    {
        $database = $this->identifier($database, 64);
        $username = $this->identifier($username, 32);

        try {
            $this->connection()->unprepared("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '{$username}'@'%'");
            $privileges = $readOnly ? 'SELECT, SHOW VIEW' : 'ALL PRIVILEGES';
            $this->connection()->unprepared("GRANT {$privileges} ON `{$database}`.* TO '{$username}'@'%'");
        } catch (Throwable $exception) {
            throw new DatabaseHostingException('The database server could not update database quota access.', previous: $exception);
        }
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(config('hosting.database.admin_connection'));
    }

    private function literal(string $value): string
    {
        $quoted = $this->connection()->getPdo()->quote($value, PDO::PARAM_STR);

        if ($quoted === false) {
            throw new DatabaseHostingException('A database credential could not be encoded safely.');
        }

        return $quoted;
    }

    private function identifier(string $value, int $maxLength): string
    {
        if (strlen($value) > $maxLength || preg_match('/^[a-z][a-z0-9_]*$/', $value) !== 1) {
            throw new DatabaseHostingException('An invalid managed database identifier was supplied.');
        }

        return $value;
    }
}
