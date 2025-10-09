<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ServeWorktree extends Command
{
    protected $signature = 'worktree:serve 
                            {--port=8000}';

    protected $description = 'Start a tmux session named after the worktree and run php artisan serve';

    public function handle(): int
    {
        $port = $this->option('port');

        $worktreePath = Worktree::getCurrentWorktreePath();
        if (!$worktreePath) {
            return self::FAILURE;
        }

        $this->line("Using current worktree: <info>{$worktreePath}</info>");
        $this->line("Port: <info>{$port}</info>");

        $sessionName = basename($worktreePath);

        if (Tmux::sessionExists($sessionName)) {
            Tmux::attachSession($sessionName);

            return self::SUCCESS;
        }

        if (! $this->createTmuxSession($sessionName, $worktreePath)) {
            $this->error("Failed to start tmux session");

            return self::FAILURE;
        }

        Tmux::attachSession($sessionName);

        return self::SUCCESS;
    }

    private function createTmuxSession(string $sessionName, string $worktreePath): bool
    {
        $commands = [
            'cd '. escapeshellarg($worktreePath),
            'php artisan serve --port='. escapeshellarg($this->option('port')),
        ];

        $tmux = Process::run([
            "tmux",  "new-session", "-d",
            "-s",  $sessionName,
            "bash", "-c", implode(" && ", $commands)
        ]);

        if (!$tmux->successful()) {
            return false;
        }

        $tmuxNewWindow = Process::run(["tmux", "new-window", "-t", $sessionName, "-c", $worktreePath]);

        return $tmuxNewWindow->successful();
    }
}
