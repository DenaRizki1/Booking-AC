<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Backup database MySQL harian (mysqldump → .sql.gz) di disk privat, simpan N hari terakhir.
 */
class BackupDatabase extends Command
{
    public const DIRECTORY = 'backups';

    protected $signature = 'backup:database {--keep=14 : Jumlah hari backup yang disimpan}';

    protected $description = 'Backup database ke storage/app/private/backups (dijadwalkan tiap hari 02:00)';

    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));

        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
            ->run([
                (string) config('database.mysqldump', 'mysqldump'),
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--user='.$connection['username'],
                '--single-transaction',
                '--quick',
                '--routines',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                $connection['database'],
            ]);

        if ($result->failed()) {
            $this->error('Backup gagal: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $path = self::DIRECTORY.'/'.$connection['database'].'-'.now()->format('Ymd-His').'.sql.gz';
        Storage::disk('local')->put($path, (string) gzencode($result->output(), 9));

        $removed = $this->prune((int) $this->option('keep'));
        $this->info("Backup tersimpan: storage/app/private/{$path}".($removed ? " ({$removed} backup lama dihapus)" : ''));

        return self::SUCCESS;
    }

    private function prune(int $keepDays): int
    {
        $limit = now()->subDays(max(1, $keepDays));
        $removed = 0;

        foreach (Storage::disk('local')->files(self::DIRECTORY) as $file) {
            if (Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file))->lt($limit)) {
                Storage::disk('local')->delete($file);
                $removed++;
            }
        }

        return $removed;
    }
}
