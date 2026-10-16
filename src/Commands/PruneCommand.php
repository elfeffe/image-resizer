<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Deletes the cached resizes nobody has asked for in a while. A resize is made again on the next request, so the cache
 * can be cut without losing anything; without this it only grows (one site reached 520,000 files and 57 GB in six weeks).
 *
 * On a local disk a file's last read (atime, which relatime keeps to the day) counts as its last use; on any other disk
 * only its age does. Folders left empty on a local disk are removed with their files.
 */
class PruneCommand extends Command
{
    public $signature = 'image-resizer:prune
        {--days= : Delete the variants unused for this many days (default: image-resizer.prune.days)}
        {--dry-run : Count what would be deleted and delete nothing}';

    public $description = 'Delete the cached resizes nobody has asked for in the given number of days';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('image-resizer.prune.days', 30));

        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return self::INVALID;
        }

        $cutoff = now()->subDays($days)->getTimestamp();
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk((string) config('image-resizer.storage.disk', 'image_resizer'));

        [$files, $bytes] = $disk instanceof FilesystemAdapter && $disk->getAdapter() instanceof LocalFilesystemAdapter
            ? $this->pruneLocal($disk->path(''), $cutoff, $dryRun)
            : $this->pruneRemote($disk, $cutoff, $dryRun);

        $this->info(sprintf(
            '%s %d cached resizes (%s) unused for %d days.',
            $dryRun ? 'Would delete' : 'Deleted', $files, $this->humanBytes($bytes), $days,
        ));

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int} files and bytes deleted (or counted) */
    private function pruneLocal(string $root, int $cutoff, bool $dryRun): array
    {
        if (! is_dir($root)) {
            return [0, 0];
        }

        $files = 0;
        $bytes = 0;
        // Children before their folder, so a folder emptied by this pass is removed in the same pass.
        $nodes = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $node */
        foreach ($nodes as $node) {
            if ($node->isDir()) {
                // rmdir refuses a folder that still has something in it, which is the whole test.
                if (! $dryRun) {
                    @rmdir($node->getPathname());
                }

                continue;
            }

            if (max($node->getATime(), $node->getMTime()) >= $cutoff) {
                continue;
            }

            $files++;
            $bytes += $node->getSize();

            if (! $dryRun) {
                @unlink($node->getPathname());
            }
        }

        return [$files, $bytes];
    }

    /** @return array{0: int, 1: int} */
    private function pruneRemote(FilesystemAdapter $disk, int $cutoff, bool $dryRun): array
    {
        $files = 0;
        $bytes = 0;

        /** @var StorageAttributes $entry */
        foreach ($disk->getDriver()->listContents('', true) as $entry) {
            if (! $entry->isFile()) {
                continue;
            }

            if (($entry->lastModified() ?? $disk->lastModified($entry->path())) >= $cutoff) {
                continue;
            }

            $files++;
            $bytes += (int) ($entry->fileSize() ?? 0);

            if (! $dryRun) {
                $disk->delete($entry->path());
            }
        }

        return [$files, $bytes];
    }

    private function humanBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || $unit === 'TB') {
                return round($bytes, 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return (string) $bytes;
    }
}
