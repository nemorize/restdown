<?php

namespace App\Services;

class GitService
{
    /**
     * Cache for commit timestamps.
     *
     * @var array
     */
    private static array $timestamps = [];

    /**
     * Get the first commit timestamp of a file.
     *
     * @param string $path
     * @return ?int
     */
    public function getCreatedAt (string $path): ?int
    {
        $dates = $this->getCommitTimestamps($path);
        if (empty($dates)) {
            return null;
        }

        return min($dates);
    }

    /**
     * Get the last commit timestamp of a file.
     *
     * @param string $path
     * @return ?int
     */
    public function getUpdatedAt (string $path): ?int
    {
        $dates = $this->getCommitTimestamps($path);
        if (empty($dates)) {
            return null;
        }

        return max($dates);
    }

    /**
     * Get all commit timestamps of a file.
     *
     * @param string $path
     * @return array
     */
    public function getCommitTimestamps (string $path): array
    {
        if (isset(self::$timestamps[$path])) {
            return self::$timestamps[$path];
        }

        $cmd = 'git log --pretty="format:%ct" ' . $path;
        $dates = shell_exec($cmd);
        if (!is_string($dates)) {
            return [];
        }

        self::$timestamps[$path] = array_map('intval', explode(PHP_EOL, $dates));
        return self::$timestamps[$path];
    }

    /**
     * Pull the latest changes from the remote repository.
     *
     * @param string $path
     * @return ?string
     */
    public function pull (string $path): ?string
    {
        return shell_exec('cd ' . $path . ' && git pull');
    }
}