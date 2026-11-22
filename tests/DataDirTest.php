<?php
declare(strict_types=1);
namespace ArcaFacturacion\Tests;
use ArcaFacturacion\Support\DataDir;
use PHPUnit\Framework\TestCase;
final class DataDirTest extends TestCase
{
    protected function tearDown(): void { putenv('ARCA_DIR'); }
    public function testUsesLibraryFolderWhenClonedStandalone(): void
    {
        self::assertSame(dirname(__DIR__), DataDir::resolve());
        self::assertSame('php bin/arca', DataDir::command());
    }
    public function testArcaDirOverridesEverything(): void
    {
        putenv('ARCA_DIR=/srv/datos-arca/');
        self::assertSame('/srv/datos-arca', DataDir::resolve());
    }
    public function testPrepareProtectsNewFolderAndLeavesExistingOnesAlone(): void
    {
        $dir = sys_get_temp_dir() . '/arca-datadir-' . bin2hex(random_bytes(4)) . '/arca';
        DataDir::prepare($dir);
        self::assertStringContainsString("*\n", (string) file_get_contents($dir . '/.gitignore'));
        self::assertSame("Require all denied\n", file_get_contents($dir . '/.htaccess'));
        file_put_contents($dir . '/.gitignore', 'propio');
        DataDir::prepare($dir);
        self::assertSame('propio', file_get_contents($dir . '/.gitignore'));
        array_map('unlink', [$dir . '/.gitignore', $dir . '/.htaccess']);
        rmdir($dir);
        rmdir(dirname($dir));
    }
}
