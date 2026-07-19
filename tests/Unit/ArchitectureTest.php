<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Machine-enforced dependency direction.
 *
 * After the PSR-4 migration, compat/ (the frozen legacy layer) may depend on
 * src/, but src/ must NOT depend back on the legacy `IP_Location_Block*` global
 * class names — that inversion is exactly what this pass removed. This test
 * tokenises every src/**\/*.php file and asserts that any remaining static
 * reference to a legacy class name (`Name::`, `new Name`, or a bare class-name
 * string literal used as a callable / class_exists() argument) is on a tight,
 * explicitly-justified allowlist.
 *
 * The allowlist covers ONLY "bucket 1" — contract-bound legacy identities that
 * MUST stay under their legacy names and therefore cannot be namespaced:
 *
 *   - src/Plugin.php — the `plugins_loaded` / `rest_api_init` hook callables.
 *     Deployed mu-plugin copies remove_action() the literal string identity
 *     `array('IP_Location_Block','get_instance')`, and the classic + Beta admin
 *     classes only exist under their legacy names (frozen admin layer).
 *   - src/Rest/RestApi.php, src/Support/FileSystem.php, src/Logging/Logs.php —
 *     class_exists() probes and static calls into the FROZEN classic admin
 *     (IP_Location_Block_Admin / IP_Location_Block_Admin_Ajax), which is served
 *     verbatim by the compat layer and never namespaced.
 *   - src/Core/Validator.php — IP_Location_Block_Rewrite is an external add-on
 *     that only ever exists under its legacy name; the 'IP_Location_Block_Util'
 *     entry is a filter callable whose literal ID third parties remove_filter().
 *
 * Deprecation-notice identifier strings such as 'IP_Location_Block::validate_country'
 * are intentionally NOT flagged: they are `Class::method` strings (not bare class
 * names) that name the legacy public API for the end user.
 */
final class ArchitectureTest extends TestCase
{
    /**
     * Map of `src/`-relative path => the ONLY legacy symbols allowed there.
     *
     * @var array<string,list<string>>
     */
    private const ALLOWLIST = [
        'src/Plugin.php' => [
            'IP_Location_Block',       // mu-plugin remove_action() identity
            'IP_Location_Block_Rest',  // REST routes callable
            'IP_Location_Block_Admin', // frozen classic admin (is_admin only)
            'IP_Location_Block_Beta',  // frozen Beta admin bootstrap
        ],
        'src/Core/Validator.php' => [
            'IP_Location_Block_Rewrite', // external add-on, legacy-only name
            'IP_Location_Block_Util',    // removable filter callable ID
        ],
        'src/Rest/RestApi.php' => [
            'IP_Location_Block_Admin',      // frozen classic admin
            'IP_Location_Block_Admin_Ajax', // frozen classic admin ajax
        ],
        'src/Support/FileSystem.php' => [
            'IP_Location_Block_Admin', // frozen classic admin notice
        ],
        'src/Logging/Logs.php' => [
            'IP_Location_Block_Admin', // frozen classic admin notice
        ],
    ];

    public function test_src_contains_no_unapproved_legacy_class_references(): void
    {
        $root       = dirname(__DIR__, 2);
        $srcDir      = $root . '/src';
        $violations = [];
        $hitCount   = 0;

        foreach ($this->phpFiles($srcDir) as $file) {
            $relative = 'src/' . str_replace('\\', '/', ltrim(substr($file, strlen($srcDir)), '/\\'));

            foreach ($this->legacyReferences($file) as [$symbol, $line]) {
                $hitCount++;
                $allowed = self::ALLOWLIST[$relative] ?? [];
                if (!in_array($symbol, $allowed, true)) {
                    $violations[] = sprintf('%s:%d references legacy %s', $relative, $line, $symbol);
                }
            }
        }

        // Sanity: the scanner must actually find the known bucket-1 references,
        // otherwise a tokenizer regression could make this test pass vacuously.
        $this->assertGreaterThan(
            0,
            $hitCount,
            'The scanner found no legacy references at all — it is almost certainly broken.'
        );

        $this->assertSame(
            [],
            $violations,
            "src/ must not reference legacy IP_Location_Block* class names outside the "
            . "bucket-1 allowlist (dependency direction must be compat -> src):\n"
            . implode("\n", $violations)
        );
    }

    /**
     * Collect legacy static references in one file.
     *
     * @return list<array{0:string,1:int}> [ symbol, line ]
     */
    private function legacyReferences(string $file): array
    {
        $tokens = token_get_all((string) file_get_contents($file), TOKEN_PARSE);

        // Significant tokens only (drop whitespace + comments), normalised to
        // [id, text, line]; single-char tokens become [text, text, 0].
        $significant = [];
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $significant[] = [$token[0], $token[1], $token[2]];
            } else {
                $significant[] = [$token, $token, 0];
            }
        }

        // PHP 8 tokenises `\Foo\Bar` as a single T_NAME_* token; a bare `Foo` is
        // still T_STRING. Treat all of them as name tokens.
        $nameTokens = [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE];

        $isLegacyClass = static function (string $symbol): bool {
            return (bool) preg_match('/^IP_Location_Block[A-Za-z0-9_]*$/', $symbol);
        };

        $hits  = [];
        $count = count($significant);
        for ($i = 0; $i < $count; $i++) {
            [$id, $text, $line] = $significant[$i];

            // `Name::` (static call/const/property) or `new Name`.
            if (in_array($id, $nameTokens, true)) {
                $symbol = ltrim($text, '\\');
                if ($isLegacyClass($symbol)) {
                    $next = $significant[$i + 1] ?? null;
                    $prev = $significant[$i - 1] ?? null;
                    $usedStatically = $next !== null && $next[0] === T_DOUBLE_COLON;
                    $usedWithNew    = $prev !== null && $prev[0] === T_NEW;
                    if ($usedStatically || $usedWithNew) {
                        $hits[] = [$symbol, $line];
                    }
                }
            }

            // Bare class-name string literal (string callable / class_exists arg).
            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $inner = ltrim(substr($text, 1, -1), '\\');
                if ($isLegacyClass($inner)) {
                    $hits[] = [$inner, $line];
                }
            }
        }

        return $hits;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $entry) {
            if ($entry->isFile() && strtolower($entry->getExtension()) === 'php') {
                $files[] = $entry->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
