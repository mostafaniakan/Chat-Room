<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneExpiredMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete messages (and voice files) older than configured TTL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $ttlMinutes = max(1, (int) config('chat.message_ttl_minutes', 10));
        $threshold = now()->subMinutes($ttlMinutes);

        $expiredMessages = Message::query()
            ->where('created_at', '<=', $threshold)
            ->get(['id', 'voice_path']);

        if ($expiredMessages->isEmpty()) {
            $this->info('No expired messages to prune.');

            return self::SUCCESS;
        }

        foreach ($expiredMessages as $message) {
            if ($message->voice_path) {
                $this->destroyVoiceFile($message->voice_path);
            }
        }

        Message::query()
            ->whereIn('id', $expiredMessages->pluck('id'))
            ->delete();

        $this->sanitizeSqliteStorage();

        $this->info("Pruned {$expiredMessages->count()} expired messages.");

        return self::SUCCESS;
    }

    private function destroyVoiceFile(string $relativePath): void
    {
        $disk = Storage::disk('public');
        $absolutePath = $disk->path($relativePath);
        $wipePasses = max(1, (int) config('chat.voice_wipe_passes', 1));

        try {
            if (File::exists($absolutePath) && is_writable($absolutePath)) {
                $size = File::size($absolutePath);

                if ($size > 0) {
                    $handle = fopen($absolutePath, 'c+b');

                    if (is_resource($handle)) {
                        for ($pass = 0; $pass < $wipePasses; $pass++) {
                            $remaining = $size;
                            fseek($handle, 0);

                            while ($remaining > 0) {
                                $chunkSize = min($remaining, 8192);
                                fwrite($handle, random_bytes($chunkSize));
                                $remaining -= $chunkSize;
                            }
                        }

                        fflush($handle);
                        fclose($handle);
                    }
                }
            }
        } catch (Throwable) {
            // Best-effort secure wipe before deletion.
        }

        $disk->delete($relativePath);
    }

    private function sanitizeSqliteStorage(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $connection->statement('PRAGMA secure_delete = ON');

        $databasePath = (string) $connection->getConfig('database');

        if ($databasePath === ':memory:') {
            return;
        }

        try {
            $connection->statement('PRAGMA wal_checkpoint(TRUNCATE)');
            $connection->statement('VACUUM');
        } catch (Throwable) {
            // Best-effort SQLite page / WAL cleanup.
        }
    }
}
