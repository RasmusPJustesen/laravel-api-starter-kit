<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallStarterKit extends Command
{
    protected $signature = 'starter-kit:install {--teams} {--no-teams} {--team-name=} {--team-plural=} {--personal-teams} {--auth=}';

    protected $description = 'Configure the headless starter kit before running migrations';

    public function handle(): int
    {
        if ($this->option('teams') && $this->option('no-teams')) {
            $this->error('Choose either --teams or --no-teams.');
            return self::FAILURE;
        }

        $interactive = $this->input->isInteractive();
        $auth = $this->option('auth') ?? ($interactive ? $this->choice('Authentication', ['both', 'stateless', 'stateful'], 'both') : 'both');

        if (! in_array($auth, ['both', 'stateless', 'stateful'], true)) {
            $this->error('Invalid authentication mode.');
            return self::FAILURE;
        }

        if (File::exists(base_path('.starter-kit-installed'))) {
            $this->error('The starter kit is already configured. Do not overwrite generated application files.');
            return self::FAILURE;
        }

        $teams = $this->option('teams') || (! $this->option('no-teams') && $interactive && $this->confirm('Enable teams?', false));

        if ($teams) {
            $name = trim((string) ($this->option('team-name') ?? ($interactive ? $this->ask('Singular team name', 'team') : 'team'))) ?: 'team';
            $plural = trim((string) ($this->option('team-plural') ?? Str::plural(Str::snake($name))));
            $personal = $this->option('personal-teams') || ($interactive && $this->confirm('Automatically create personal teams?', false));
            try {
                app(TeamScaffolder::class)->install($name, $plural, $personal);
            } catch (\InvalidArgumentException $exception) {
                $this->error($exception->getMessage());
                return self::FAILURE;
            }
        }

        $env = base_path('.env');
        if (! File::exists($env)) {
            File::copy(base_path('.env.example'), $env);
        }
        $content = File::get($env);
        $line = 'STARTER_KIT_AUTH='.$auth;
        $content = preg_match('/^STARTER_KIT_AUTH=.*$/m', $content)
            ? preg_replace('/^STARTER_KIT_AUTH=.*$/m', $line, $content)
            : rtrim($content).PHP_EOL.$line.PHP_EOL;
        File::put($env, $content);
        File::put(base_path('.starter-kit-installed'), "configured\n");
        $this->info('Configuration saved. Run php artisan optimize:clear and php artisan migrate.');

        return self::SUCCESS;
    }
}
