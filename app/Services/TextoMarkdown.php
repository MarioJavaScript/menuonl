<?php

namespace App\Services;

/**
 * Makes text written by users safe to place inside a Markdown mail template.
 *
 * Mail templates go through GitHub-flavoured Markdown after Blade, so `*`, `_`, `[`, `` ` ``, `![`, bare URLs and
 * e-mail addresses in a restaurant name would become emphasis, links or images. Every ASCII punctuation mark is
 * backslash-escaped, which CommonMark renders as the literal character.
 *
 * `& < > " '` are left alone on purpose: Blade's `{{ }}` already turns them into entities, and a backslash in front of
 * an entity would break it (`\&#039;` is an escaped ampersand followed by the text `#039;`).
 */
class TextoMarkdown
{
    /**
     * Single-line, literal version of the text for a Markdown template.
     */
    public static function escapar(string $texto): string
    {
        $linea = trim((string) preg_replace('/\s+/u', ' ', $texto));

        return (string) preg_replace('/(?![&<>"\'])[[:punct:]]/', '\\\\$0', $linea);
    }
}
