<?php

namespace Tests\Feature;

use App\Services\Backups;

/** Dump database berbasis PHP — cadangan saat mysqldump/proc_open tidak tersedia (shared hosting). */
class BackupPhpDumpTest extends KrafioTestCase
{
    public function test_php_dump_writes_structure_and_data_including_special_characters(): void
    {
        $u = $this->user();
        $u->forceFill(['name' => "O'Brien \"Kopi\" \\ Gula Aren"])->save();

        $path = tempnam(sys_get_temp_dir(), 'krafio-dump-');
        Backups::phpDump($path);
        $sql = gzdecode((string) file_get_contents($path));
        @unlink($path);

        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=0;', $sql);
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('INSERT INTO `users` VALUES', $sql);
        $this->assertStringContainsString($u->email, $sql);
        // Kutip & backslash di-escape lewat PDO::quote
        $this->assertStringContainsString("O\\'Brien \\\"Kopi\\\" \\\\ Gula Aren", $sql);
        $this->assertStringEndsWith("SET FOREIGN_KEY_CHECKS=1;\n", $sql);
    }

    public function test_database_backup_succeeds_with_php_fallback_when_mysqldump_missing(): void
    {
        config(['krafio.mysqldump' => '/nonexistent/mysqldump']);

        $b = Backups::database('manual');

        $this->assertSame('success', $b->status);
        $this->assertGreaterThan(0, $b->size);
        $this->assertStringStartsWith('Dump PHP', (string) $b->message);
        @unlink(Backups::dir().DIRECTORY_SEPARATOR.$b->file);
    }
}
