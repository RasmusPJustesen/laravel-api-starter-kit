<?php

namespace App\Console\Commands;

use App\Support\FrontendClients;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallStarterKit extends Command
{
    protected $signature = 'starter-kit:install {--teams} {--no-teams} {--team-name=} {--team-plural=} {--personal-teams} {--auth=} {--frontend=* : JSON client definitions}';

    protected $description = 'Configure the headless starter kit before running migrations';

    public function handle(): int
    {
        if ($this->option('teams') && $this->option('no-teams')) {
            $this->error('Choose either --teams or --no-teams.');

            return self::FAILURE;
        }

        $interactive = $this->input->isInteractive() && (! defined('STDIN') || stream_isatty(STDIN));
        $auth = $this->option('auth') ?? ($interactive ? $this->choice('Authentication', ['both', 'stateless', 'stateful'], 'both') : 'both');

        if (! in_array($auth, ['both', 'stateless', 'stateful'], true)) {
            $this->error('Invalid authentication mode.');

            return self::FAILURE;
        }

        if (File::exists(base_path('.starter-kit-installed'))) {
            $this->error('The starter kit is already configured. Do not overwrite generated application files.');

            return self::FAILURE;
        }

        try {
            $frontends = $this->frontendConfiguration($interactive, $auth);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            $this->error($exception->getMessage());

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
        if ($frontends !== null) {
            File::put(config_path('frontend-clients.php'), "<?php\n\nreturn ".var_export($frontends, true).";\n");
        }

        File::put(base_path('.starter-kit-installed'), "configured\n");

        // Generated applications use ordinary application CI, not the template matrix.
        if (File::exists(base_path('stubs/project/tests.yml.stub'))) {
            File::ensureDirectoryExists(base_path('.github/workflows'));
            File::copy(base_path('stubs/project/tests.yml.stub'), base_path('.github/workflows/tests.yml'));
        }
        $this->info('Configuration saved. Run php artisan optimize:clear and php artisan migrate.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed>|null */
    private function frontendConfiguration(bool $interactive, string $auth): ?array
    {
        $definitions = [];
        foreach ($this->option('frontend') as $json) {
            $definitions[] = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }

        if ($interactive && $definitions === [] && $this->confirm('Configure frontend clients now?', false)) {
            do {
                $id = $this->ask('Frontend identifier', $definitions === [] ? 'web' : 'mobile');
                $url = $this->ask('Frontend URL (HTTPS recommended)', 'http://localhost:3000');
                $mode = $auth === 'both' ? $this->choice('Client authentication', ['stateful', 'stateless'], 'stateful') : $auth;
                $origin = $this->ask('Browser origin, if any (leave blank for none)', $mode === 'stateful' ? FrontendClients::origin($url) : null);
                $definitions[] = ['id' => $id, 'url' => $url, 'authentication' => $mode, 'origin' => $origin];
            } while ($this->confirm('Configure another frontend?', false));
        }

        if ($definitions === []) {
            return null;
        }

        if (File::exists(config_path('frontend-clients.php'))) {
            throw new \InvalidArgumentException('Frontend configuration already exists.');
        }

        $clients = [];
        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                throw new \InvalidArgumentException('Frontend definitions must be JSON objects.');
            }
            $id = $definition['id'] ?? '';
            $url = $definition['url'] ?? '';
            $mode = $definition['authentication'] ?? ($auth === 'both' ? 'stateful' : $auth);
            $origin = $definition['origin'] ?? ($mode === 'stateful' ? FrontendClients::origin($url) : null);
            if (! is_string($id) || ! preg_match('/^[a-z][a-z0-9_-]{0,39}$/D', $id) || isset($clients[$id]) ||
                ! is_string($url) || ! FrontendClients::origin($url) ||
                parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null ||
                ! in_array($mode, ['stateful', 'stateless'], true) || ($auth !== 'both' && $mode !== $auth) ||
                ($origin !== null && (! is_string($origin) || FrontendClients::origin($origin) !== $origin))) {
                throw new \InvalidArgumentException('Invalid or duplicate frontend definition.');
            }
            $clients[$id] = [
                'name' => $id,
                'url' => $url,
                'authentication' => $mode,
                'origin' => $origin,
                'password_reset_path' => '/reset-password',
                'email_verification_path' => '/verify-email',
                'login_path' => '/login',
            ];
        }

        return ['default' => array_key_first($clients), 'clients' => $clients];
    }
}
