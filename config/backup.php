<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/*
 * Cadangan terenkripsi (spatie/laravel-backup). Isi: dump database + storage/app/kk (file KK, sudah terenkripsi
 * APP_KEY) + storage/app/public (unggahan). Arsip zip dienkripsi dengan sandi BACKUP_ARCHIVE_PASSWORD (AES).
 *
 * PERINGATAN: NIK dan KK di dalam cadangan terenkripsi dengan APP_KEY, dan hash NIK memakai NIK_HMAC_KEY.
 * Cadangan TIDAK berguna tanpa APP_KEY; simpan APP_KEY, NIK_HMAC_KEY, dan BACKUP_ARCHIVE_PASSWORD terpisah dari
 * cadangan dan di luar server (lihat docs/DEPLOY-aapanel.md).
 *
 * Notifikasi surel mati secara bawaan; isi BACKUP_NOTIFICATION_EMAIL (dan konfigurasi MAIL_*) untuk menyalakannya.
 */

$notifyTo = env('BACKUP_NOTIFICATION_EMAIL');

return [

    'backup' => [

        'name' => env('APP_NAME', 'laravel-backup'),

        'source' => [

            'files' => [
                'include' => [
                    storage_path('app/kk'),
                    storage_path('app/public'),
                ],
                'exclude' => [],
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => base_path(),
            ],

            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => null,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => '',

            // Disk 'backups' (config/filesystems.php): storage/app/backups, di luar public/. Salin keluar server
            // (rclone/rsync) agar cadangan selamat bila server rusak.
            'disks' => ['backups'],

            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        // Wajib diisi di production. Tanpa sandi, arsip TIDAK terenkripsi (jadwal di routes/console.php menolak berjalan).
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',

        'verify_backup' => false,
        'tries' => 1,
        'retry_delay' => 0,
    ],

    'notifications' => [

        'notifications' => filled($notifyTo) ? [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ] : [
            BackupHasFailedNotification::class => [],
            UnhealthyBackupWasFoundNotification::class => [],
            CleanupHasFailedNotification::class => [],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => $notifyTo ?: 'backup@example.invalid',
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'PPs Kyai Syarifuddin'),
            ],
        ],

        'slack' => ['webhook_url' => '', 'channel' => null, 'username' => null, 'icon' => null],
        'discord' => ['webhook_url' => '', 'username' => '', 'avatar_url' => ''],
        'webhook' => ['url' => ''],
    ],

    'log_channel' => null,

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'laravel-backup'),
            'disks' => ['backups'],
            'health_checks' => [
                MaximumAgeInDays::class => 2,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            // Semua cadangan 7 hari terakhir; lalu satu per hari 16 hari, satu per minggu 8 minggu,
            // satu per bulan 6 bulan, satu per tahun 1 tahun. Batas total ruang 5000 MB.
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 6,
            'keep_yearly_backups_for_years' => 1,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        'tries' => 1,
        'retry_delay' => 0,
    ],

];
