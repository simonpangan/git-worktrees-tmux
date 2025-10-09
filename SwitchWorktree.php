<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SwitchWorktree extends Command
{
    protected $signature = 'worktree:switch 
                            {worktree?}';

    protected $description = 'Switch worktree, open PhpStorm, and start artisan serve in a new tmux session';

    public function handle(): int
    {
        $worktree = $this->argument('worktree') ?? $this->selectWorktree();

        $this->deleteCurrentTmuxSession();
        $this->openPhpStorm($worktree);

        return self::SUCCESS;
    }

    protected function selectWorktree(): string
    {
        $this->info("Fetching git worktrees...");

        $process = Process::run(['git', 'worktree', 'list', '--porcelain']);

        if (!$process->successful()) {
            $this->error("Failed to get git worktrees.");
            exit(1);
        }

        preg_match_all('/^worktree (.+)$/m', $process->output(), $matches);
        $worktrees = $matches[1] ?? [];

        if (empty($worktrees)) {
            $this->error("No git worktrees found.");
            exit(1);
        }

        return $this->choice('Select a worktree to switch', $worktrees);
    }

    protected function verifyWorktree(string $worktreePath): bool
    {
        if (!is_dir($worktreePath)) {
            $this->error("Worktree directory '{$worktreePath}' does not exist.");

            return false;
        }

        return true;
    }

    private function deleteCurrentTmuxSession(): void
    {
        $currentWorktree = Worktree::getCurrentWorktreePath();
        $currentWorktreeSessionName = basename($currentWorktree);

        Tmux::killSession($currentWorktreeSessionName);
    }

    private function openPhpStorm(string $worktreePath): void
    {
        Process::run(["phpstorm64.exe", $worktreePath]);
    }
}
