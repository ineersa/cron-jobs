<?php

namespace App\Service;

class DatabaseBackupService
{
    public function __construct(
        private readonly string $blogDatabaseUrl,
    ) {
    }

    public function backup(string $backupFilePath): void
    {
        $parsedUrl = parse_url($this->blogDatabaseUrl);
        $user = $parsedUrl['user'];
        $pass = $parsedUrl['pass'];
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'];
        $dbName = trim($parsedUrl['path'], '/');

        $command = "mysqldump --no-tablespaces -h {$host} -P {$port} -u {$user} -p'{$pass}' {$dbName} | gzip > {$backupFilePath}";
        shell_exec($command);
    }
}
