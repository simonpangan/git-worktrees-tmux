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

        $worktreePath = $this->getWorktreePath();
        if (!$worktreePath) {
            return self::FAILURE;
        }

        $this->line("Using current worktree: <info>{$worktreePath}</info>");
        $this->line("Port: <info>{$port}</info>");

        $sessionName = basename($worktreePath);

        if ($this->tmuxSessionExists($sessionName)) {
            $this->error("A tmux session named '{$sessionName}' already exists.");
            $this->attachTmuxSession($sessionName);
        }

        if (! $this->createTmuxSession($sessionName, $worktreePath)) {
            $this->error("Failed to start tmux session:");
            return self::FAILURE;
        }

        $this->attachTmuxSession($sessionName);

        return self::SUCCESS;
    }

    private function getWorktreePath(): ?string
    {
        $process = Process::run(['git', 'rev-parse', '--show-toplevel']);

        if (!$process->successful()) {
            $this->error("Failed to detect current git worktree. Are you inside a git repository?");
            return null;
        }

        $path = trim($process->output());
        if (!is_dir($path)) {
            $this->error("Detected worktree path '{$path}' does not exist.");
            return null;
        }

        return $path;
    }

    private function tmuxSessionExists(string $sessionName): bool
    {
        $checkTmux = Process::run(["tmux", "has-session", "-t", $sessionName]);

        return $checkTmux->exitCode() === 0;
    }

    private function attachTmuxSession(string $sessionName): void
    {
        if (getenv('TMUX')) {
            $this->warn("You're already inside a tmux session.");
            $this->line("Switching to tmux session '{$sessionName}'...");

            passthru("tmux switch-client -t " . escapeshellarg($sessionName));

            return;
        }

        $this->line("Attaching to tmux session '{$sessionName}'...");
        passthru("tmux attach-session -t " . escapeshellarg($sessionName));
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
