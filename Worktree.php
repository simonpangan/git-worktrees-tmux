<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Process;

class Worktree
{
    public static function getCurrentWorktreePath(): ?string
    {
        $process = Process::run(['git', 'rev-parse', '--show-toplevel']);

        if (!$process->successful()) {
            return null;
        }

        $path = trim($process->output());

        if (! is_dir($path)) {
            return null;
        }

        return $path;
    }
}
