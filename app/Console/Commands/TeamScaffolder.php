<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TeamScaffolder
{
    public function install(string $name, string $plural, bool $personal): void
    {
        $singular = Str::snake($name);
        $plural = Str::snake($plural);

        foreach ([$singular, $plural] as $value) {
            if (! preg_match('/^[a-z][a-z0-9]*(?:_[a-z][a-z0-9]*)*$/D', $value) || strlen($value) > 30) {
                throw new InvalidArgumentException('Use a valid English identifier of at most 30 characters.');
            }
        }

        if ($singular === $plural || in_array($singular, ['user', 'session', 'token', 'auth', 'membership', 'member', 'invitation', 'class', 'trait', 'enum', 'interface', 'match', 'self', 'parent', 'static', 'resource'], true)) {
            throw new InvalidArgumentException('This name conflicts with a reserved identifier or has an identical plural. Supply a different name or plural.');
        }

        $map = [
            'Teams' => Str::studly($plural),
            'Team' => Str::studly($singular),
            'teams' => $plural,
            'team' => $singular,
        ];

        // Apply the explicit vocabulary in one pass, never recursively.
        $render = static fn (string $value): string => strtr($value, $map);
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
                $output[$target] = $content;
            }
        }

        $model = Str::studly($singular);
        $models = Str::studly($plural);
        $route = $plural;
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

        $apiPath = base_path('routes/api.php');
        $api = File::get($apiPath);
        $consolePath = base_path('routes/console.php');
        $console = File::get($consolePath);
        foreach ($output as $path => $content) {
            if (File::exists(base_path($path))) {
                throw new InvalidArgumentException('File already exists: '.$path);
            }
        }
        foreach ($output as $path => $content) {
            File::ensureDirectoryExists(dirname(base_path($path)));
            File::put(base_path($path), $content);
        }

        File::put($userPath, $modified);
        File::put($providerPath, $newProvider);
        File::put($apiPath, rtrim($api)."\n\nrequire __DIR__.'/".$route.".php';\n");
        File::put($consolePath, rtrim($console)."\n\n\\Illuminate\\Support\\Facades\\Schedule::call(fn () => \\App\\Models\\".$model."Invitation::query()->whereNull('accepted_at')->where('expires_at', '<=', now())->delete())->daily();\n");
    }
}
