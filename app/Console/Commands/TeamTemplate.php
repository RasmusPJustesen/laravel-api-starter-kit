<?php

namespace App\Console\Commands;

use Illuminate\Support\Str;

final class TeamTemplate
{
    /**
     * Normalize the original Maestro source vocabulary to template tokens.
     * New templates may use these tokens directly.
     */
    public static function tokenize(string $source): string
    {
        if (str_contains($source, '{{ entity_')) {
            return $source;
        }

        return strtr($source, [
            'teamMemberships' => '{{ entity_camel }}Memberships',
            'teamMembers' => '{{ entity_camel }}Members',
            'teamRole' => '{{ entity_camel }}Role',
            'teamName' => '{{ entity_camel }}Name',
            'Teams' => '{{ entity_plural_studly }}',
            'Team' => '{{ entity_studly }}',
            'teams' => '{{ entity_plural_snake }}',
            'team' => '{{ entity_snake }}',
        ]);
    }

    public static function render(string $source, string $singular, string $plural, bool $personal = false): string
    {
        return strtr(self::tokenize($source), [
            '{{ entity_studly }}' => Str::studly($singular),
            '{{ entity_plural_studly }}' => Str::studly($plural),
            '{{ entity_camel }}' => Str::camel($singular),
            '{{ entity_plural_camel }}' => Str::camel($plural),
            '{{ entity_snake }}' => $singular,
            '{{ entity_plural_snake }}' => $plural,
            '{{ entity_label }}' => str_replace('_', ' ', $singular),
            '{{ entity_plural_label }}' => str_replace('_', ' ', $plural),
            '{{ entity_personal }}' => $personal ? 'true' : 'false',
        ]);
    }
}
