<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TeamScaffolder
{
    public function install(string $name, string $plural, bool $personal): void
    {
        if (File::exists(base_path('.starter-kit-teams.json'))) {
            throw new InvalidArgumentException('The team module is already installed.');
        }
        $singular = Str::snake(trim($name));
        $plural = Str::snake($plural);

        foreach ([$singular, $plural] as $value) {
            if (! preg_match('/^[a-z][a-z0-9]*(?:_[a-z][a-z0-9]*)*$/D', $value) || strlen($value) > 30) {
                throw new InvalidArgumentException('Use a valid English identifier of at most 30 characters.');
            }
        }

        if ($singular === $plural || in_array($singular, ['user', 'session', 'token', 'auth', 'membership', 'member', 'invitation', 'class', 'trait', 'enum', 'interface', 'match', 'self', 'parent', 'static', 'resource', 'string', 'int', 'float', 'bool', 'array', 'object', 'null', 'true', 'false', 'void', 'never', 'mixed', 'namespace', 'function', 'new', 'default', 'switch', 'case', 'return', 'list', 'global'], true)) {
            throw new InvalidArgumentException('This name conflicts with a reserved identifier or has an identical plural. Supply a different name or plural.');
        }

        $render = static fn (string $value): string => TeamTemplate::render($value, $singular, $plural, $personal);
        $root = base_path('stubs/teams');
        $output = [];

        foreach (['shared', 'api'] as $layer) {
            foreach (File::allFiles($root.'/'.$layer, true) as $file) {
                $relative = Str::beforeLast($file->getRelativePathname(), '.stub');
                if (in_array($relative, [
                    '.env.example', 'config/services.php', 'routes/api.php', 'routes/console.php',
                    'app/Models/User.php', 'app/Http/Controllers/Auth/RegisterController.php',
                    'database/factories/UserFactory.php', 'tests/Feature/Auth/RegisterTest.php',
                ], true)) {
                    continue;
                }
                $target = $render($relative);
                $content = $render($file->getContents());
                if (isset($output[$target])) {
                    throw new InvalidArgumentException('Duplicate generated path: '.$target);
                }
                $output[$target] = $content;
            }
        }

        $model = Str::studly($singular);
        $models = Str::studly($plural);
        $route = $plural;
        if (in_array($plural, ['users', 'user', 'sessions', 'invitations', 'docs', 'spa', 'email', 'ready', 'me', 'token', 'login', 'logout', 'register', 'storage', 'sanctum'], true)) {
            throw new InvalidArgumentException('The plural conflicts with an existing API route.');
        }
        $output['config/'.$plural.'.php'] = "<?php\n\nreturn ['personal' => ".($personal ? 'true' : 'false')."];\n";
        $userPath = app_path('Models/User.php');
        $user = File::get($userPath);
        $modified = str_replace('use HasApiTokens, HasFactory, Notifiable;', 'use HasApiTokens, HasFactory, Notifiable, \\App\\Concerns\\Has'.$models.';', $user, $count);
        if ($count !== 1) {
            throw new InvalidArgumentException('User model has been modified; refusing to overwrite it.');
        }

        $providerPath = app_path('Providers/AppServiceProvider.php');
        $provider = File::get($providerPath);
        $marker = '$this->configureDefaults();';
        $injection = $marker."\n        \\Illuminate\\Support\\Facades\\Event::listen(\\Illuminate\\Auth\\Events\\Registered::class, function (\\Illuminate\\Auth\\Events\\Registered \$event): void {\n            if (config('".$plural.".personal') && ! \$event->user->personal".$model."()) {\n                app(\\App\\Actions\\".$models."\\Create".$model."::class)->handle(\$event->user, \$event->user->name.\"'s ".$model."\", isPersonal: true);\n            }\n        });";
        $newProvider = str_replace($marker, $injection, $provider, $count);
        if ($count !== 1) {
            throw new InvalidArgumentException('Application provider has been modified.');
        }

        $profilePath = app_path('Http/Controllers/Auth/ProfileController.php');
        $profile = File::get($profilePath);
        $guard = '        $user = $request->user();'."\n\n".'        DB::transaction(function () use ($user): void {';
        $replacement = '        $user = $request->user();'."\n\n".'        DB::transaction(function () use ($user): void {'."\n".
            '            abort_if($user->owned'.$models.'()->where(\'is_personal\', false)->exists(), 409, __(\'Transfer ownership before deleting your account.\'));'."\n".
            '            $user->owned'.$models.'()->where(\'is_personal\', true)->get()->each(fn ($entity) => $entity->delete());';
        $newProfile = str_replace($guard, $replacement, $profile, $count);
        if ($count !== 1) {
            throw new InvalidArgumentException('Profile controller has been modified.');
        }

        $apiPath = base_path('routes/api.php');
        $api = File::get($apiPath);
        $consolePath = base_path('routes/console.php');
        $console = File::get($consolePath);
        foreach ($output as $path => $content) {
            if (File::exists(base_path($path))) {
                throw new InvalidArgumentException('File already exists: '.$path);
            }
        }
        $changes = [
            $profilePath => $newProfile,
            $userPath => $modified,
            $providerPath => $newProvider,
            $apiPath => rtrim($api)."\n\nrequire __DIR__.'/".$route.".php';\n",
            $consolePath => rtrim($console)."\n\n\\Illuminate\\Support\\Facades\\Schedule::call(fn () => \\App\\Models\\".$model."Invitation::query()->whereNull('accepted_at')->where('expires_at', '<=', now())->delete())->daily();\n",
        ];
        foreach ($output as $path => $content) {
            $changes[base_path($path)] = $content;
        }
        $changes[base_path('.starter-kit-teams.json')] = json_encode([
            'singular' => $singular,
            'plural' => $plural,
            'personal' => $personal,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";

        $backups = [];
        try {
            foreach ($changes as $path => $content) {
                $backups[$path] = File::exists($path) ? File::get($path) : null;
                File::ensureDirectoryExists(dirname($path));
                if (File::put($path, $content) === false) {
                    throw new \RuntimeException('Could not write '.$path);
                }
            }
        } catch (\Throwable $exception) {
            foreach (array_reverse($backups, true) as $path => $original) {
                if ($original === null) {
                    File::delete($path);
                } else {
                    File::put($path, $original);
                }
            }
            throw new InvalidArgumentException('Scaffolding failed; file changes rolled back.', previous: $exception);
        }
    }
}
