<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Process;

class Tmux
{
    public static function sessionExists(string $sessionName): bool
    {
        $checkTmux = Process::run(["tmux", "has-session", "-t", $sessionName]);

        return $checkTmux->exitCode() === 0;
    }

    public static function attachSession(string $sessionName): void
    {
        if (getenv('TMUX')) {
            passthru("tmux switch-client -t " . escapeshellarg($sessionName));

            return;
        }

        passthru("tmux attach-session -t " . escapeshellarg($sessionName));
    }

    public static function killSession(string $sessionName): void
    {
        $checkTmux = Process::run(["tmux", "has-session", "-t", $sessionName]);

        if ($checkTmux->exitCode() !== 0) {
            return;
        }

        Process::run(["tmux", "kill-session", "-t", $sessionName]);
    }
}
