<?php

declare(strict_types=1);

namespace Utrust\Payment\Test\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class PropertyDeclarationTest extends TestCase
{
    /**
     * PHP 8.2 rejects constructor assignments to properties the class does not declare.
     *
     * @return void
     */
    public function testConstructorAssignmentsAreDeclared(): void
    {
        $failures = [];
        $root = dirname(__DIR__, 2);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            if ($this->isSkipped($path)) {
                continue;
            }
            $failures = array_merge($failures, $this->undeclaredAssignments($path));
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * @param string $path
     * @return bool
     */
    private function isSkipped(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return strpos($normalized, '/Test/') !== false
            || strpos($normalized, '/vendor/') !== false;
    }

    /**
     * @param string $path
     * @return string[]
     */
    private function undeclaredAssignments(string $path): array
    {
        $code = file_get_contents($path);
        if (!is_string($code) || !preg_match('/function\s+__construct\s*\(/', $code)) {
            return [];
        }

        $body = $this->constructorBody($code);
        if ($body === null) {
            return [];
        }

        preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)\s*=/', $body, $assignments);
        $failures = [];
        foreach (array_unique($assignments[1]) as $property) {
            $pattern = '/(?:public|protected|private|var)\s+(?:static\s+)?(?:[\w\\\\|]+\s+)?\$'
                . preg_quote($property, '/')
                . '\b/';
            if (!preg_match($pattern, $code)) {
                $failures[] = $path . ' assigns $this->' . $property . ' without declaring it';
            }
        }

        return $failures;
    }

    /**
     * @param string $code
     * @return string|null
     */
    private function constructorBody(string $code): ?string
    {
        $open = strpos($code, 'function __construct');
        if ($open === false) {
            return null;
        }
        $brace = strpos($code, '{', $open);
        if ($brace === false) {
            return null;
        }

        $depth = 0;
        $length = strlen($code);
        for ($index = $brace; $index < $length; $index++) {
            $character = $code[$index];
            if ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($code, $brace + 1, $index - $brace - 1);
                }
            }
        }

        return null;
    }
}
