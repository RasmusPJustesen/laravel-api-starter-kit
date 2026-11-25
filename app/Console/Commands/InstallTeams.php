<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallTeams extends Command
{
    protected $signature = 'starter-kit:teams {--team-name=} {--team-plural=} {--personal-teams}';

    protected $description = 'Add the optional collaboration module to an existing starter-kit application';

    public function handle(TeamScaffolder $scaffolder): int
    {
        if (! File::exists(base_path('.starter-kit-installed'))) {
            $this->error('Run starter-kit:install first.');
            return self::FAILURE;
        }

        $interactive = $this->input->isInteractive();
        $name = trim((string) ($this->option('team-name') ?? ($interactive ? $this->ask('Singular team name', 'team') : 'team'))) ?: 'team';
        $plural = (string) ($this->option('team-plural') ?? Str::plural(Str::snake($name)));
        $personal = $this->option('personal-teams') || ($interactive && $this->confirm('Create personal teams for existing and future users?', false));
        try {
            $scaffolder->install($name, $plural, $personal);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Module installed. Run composer dump-autoload, php artisan optimize:clear, and php artisan migrate. Commit the generated files.');

        return self::SUCCESS;
    }
}
