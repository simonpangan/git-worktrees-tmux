<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class CreateWorktree extends Command
{
    protected $signature = 'worktree:create 
                            {name? : Worktree name (also used as default branch name)}';

    protected $description = 'Create a new Git worktree for a branch at a target path.';

    public function handle(): int
    {
        $name = $this->argument('name')
            ?? $this->ask('Worktree name');

        if (! $name) {
            $this->error('A worktree name is required.');
            return self::FAILURE;
        }

        $branch = "feature/{$name}";
        $path = "../feature_{$name}";
        $startPoint = 'develop';

        $checkBranch = Process::run(['git', 'rev-parse', '--verify', $branch]);

        if ($checkBranch->successful()) {
            $this->info("✅ Branch '{$branch}' already exists. Adding worktree...");
            $command = ['git', 'worktree', 'add', $path, $branch];
        } else {
            $this->info("🌱 Creating new branch '{$branch}' from '{$startPoint}'...");
            $command = ['git', 'worktree', 'add', '-b', $branch, $path, $startPoint];
        }

        $process = Process::run($command);
        if ($process->failed()) {
            $this->error("❌ Failed to create worktree:\n" . $process->errorOutput());
            return self::FAILURE;
        }
        $this->info("✅ Worktree created at: {$path}");

        $sessionName = basename($path);
        $this->createTmuxSession($sessionName, $path);

        $this->openPhpStorm($path);
        $projectName = "Project - {$name}";
        $this->changePhpStormChangeProjectName($path, $projectName);

        return self::SUCCESS;
    }

    private function createTmuxSession(string $sessionName, string $worktreePath): bool
    {
        $commands = [
            'cd '. escapeshellarg($worktreePath),
            'php artisan serve',
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

    private function openPhpStorm(string $worktreePath): void
    {
        Process::run(["phpstorm64.exe", $worktreePath]);
    }

    private function changePhpStormChangeProjectName(string $worktreePath, string $projectName): void
    {
        $ideaPath = rtrim($worktreePath, '/') . '/.idea';

        $nameFile = $ideaPath . '/.name';

        // Ensure .idea exists
        if (!is_dir($ideaPath)) {
            $this->warn("⚠️ .idea folder not found at {$ideaPath}");
            return;
        }

        // Write project name to .idea/.name
        file_put_contents($nameFile, $projectName);

        $this->info("✅ PhpStorm project name set to: {$projectName}");
    }
}
