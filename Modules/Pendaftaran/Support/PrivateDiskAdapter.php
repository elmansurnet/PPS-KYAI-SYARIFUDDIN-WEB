<?php

namespace Modules\Pendaftaran\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use RuntimeException;

/**
 * Disk lokal untuk file rahasia (driver 'kk_private'): tidak ada URL publik maupun URL sementara.
 * url()/temporaryUrl() selalu melempar pengecualian sehingga kode tidak bisa tanpa sengaja membuat
 * tautan ke file KK. Unduhan hanya lewat route yang mengecek login dan izin (Task 12).
 */
class PrivateDiskAdapter extends FilesystemAdapter
{
    /** @param  array<string, mixed>  $config */
    public static function fromConfig(array $config): self
    {
        $adapter = new LocalAdapter(
            (string) $config['root'],
            PortableVisibilityConverter::fromArray($config['permissions'] ?? [], Visibility::PRIVATE),
            LOCK_EX,
            LocalAdapter::DISALLOW_LINKS,
        );

        return new self(new Filesystem($adapter, ['visibility' => Visibility::PRIVATE, 'directory_visibility' => Visibility::PRIVATE]), $adapter, $config);
    }

    public function url($path)
    {
        throw new RuntimeException('Disk privat tidak memiliki URL publik.');
    }

    public function providesTemporaryUrls()
    {
        return false;
    }

    public function temporaryUrl($path, $expiration, array $options = [])
    {
        throw new RuntimeException('Disk privat tidak memiliki URL sementara.');
    }

    public function temporaryUploadUrl($path, $expiration, array $options = [])
    {
        throw new RuntimeException('Disk privat tidak memiliki URL unggah.');
    }
}
