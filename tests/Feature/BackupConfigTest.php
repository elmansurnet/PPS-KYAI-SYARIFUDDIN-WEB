<?php

use Illuminate\Console\Scheduling\Schedule;

function bkPath(string $path): string
{
    return rtrim(str_replace(chr(92), '/', $path), '/').'/';
}

test('backup config encrypts, destination outside public, schedule registered', function () {
    // Enkripsi arsip: sandi dari env + algoritma bawaan (AES).
    config(['backup.backup.password' => 'rahasia']);
    expect(config('backup.backup.encryption'))->toBe('default')
        ->and(file_get_contents(base_path('config/backup.php')))->toContain("env('BACKUP_ARCHIVE_PASSWORD')");

    // Sumber: hanya KK dan unggahan publik; tidak menyertakan .env atau seluruh base_path.
    $include = array_map(bkPath(...), config('backup.backup.source.files.include'));
    expect($include)->toBe([bkPath(storage_path('app/kk')), bkPath(storage_path('app/public'))]);

    // Tujuan: disk 'backups' di luar public/, tanpa url, tidak dilayani.
    expect(config('backup.backup.destination.disks'))->toBe(['backups']);
    $disk = config('filesystems.disks.backups');
    expect(bkPath($disk['root']))->not->toStartWith(bkPath(public_path()))
        ->and($disk)->not->toHaveKey('url')
        ->and($disk['serve'])->toBeFalse()
        ->and(bkPath($disk['root']))->not->toStartWith(bkPath(config('filesystems.disks.kk.root')))
        ->and(bkPath(config('filesystems.disks.kk.root')))->not->toStartWith(bkPath($disk['root']));

    // Notifikasi mati secara bawaan.
    expect(collect(config('backup.notifications.notifications'))->flatten()->all())->toBe([]);

    // Jadwal: backup:run dan backup:clean terdaftar harian.
    $commands = collect(app(Schedule::class)->events())->map(fn ($e) => (string) $e->command)->implode("\n");
    expect($commands)->toContain('backup:run')->toContain('backup:clean');
});

test('backup runs only when the archive password is set', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->command, 'backup:run'));

    expect($events)->toHaveCount(1);

    config(['backup.backup.password' => null]);
    expect($events->first()->filtersPass(app()))->toBeFalse();

    config(['backup.backup.password' => 'rahasia']);
    expect($events->first()->filtersPass(app()))->toBeTrue();
});

test('mysql and mariadb dumps skip tablespaces so a non-root db user can back up', function (string $connection) {
    expect(config("database.connections.{$connection}.dump.add_extra_option"))->toBe('--no-tablespaces')
        ->and(config("database.connections.{$connection}.dump.use_single_transaction"))->toBeTrue();
})->with(['mysql', 'mariadb']);

test('manual backup:run refuses in production without an archive password', function () {
    $event = fn () => new Illuminate\Console\Events\CommandStarting(
        'backup:run',
        new Symfony\Component\Console\Input\ArrayInput([]),
        new Symfony\Component\Console\Output\NullOutput,
    );

    config(['backup.backup.password' => null]);
    app()->detectEnvironment(fn () => 'production');
    expect(fn () => event($event()))->toThrow(RuntimeException::class, 'BACKUP_ARCHIVE_PASSWORD');

    config(['backup.backup.password' => 'rahasia']);
    expect(fn () => event($event()))->not->toThrow(RuntimeException::class);

    // Di luar production (dev/tes) tidak diblokir.
    app()->detectEnvironment(fn () => 'local');
    config(['backup.backup.password' => null]);
    expect(fn () => event($event()))->not->toThrow(RuntimeException::class);
});

test('activity log is cleaned daily with a one year retention', function () {
    expect(config('activitylog.delete_records_older_than_days'))->toBe(365);

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'activitylog:clean'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 3 * * *');
});
