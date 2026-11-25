<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Pendaftaran\Services\KkStorage;

beforeEach(function () {
    $this->kkRoot = storage_path('framework/testing/kk-'.Str::random(10));
    config(['filesystems.disks.kk.root' => $this->kkRoot]);
    Storage::forgetDisk('kk');
    $this->storage = app(KkStorage::class);
});

afterEach(function () {
    File::deleteDirectory($this->kkRoot);
});

function kkPng(): string
{
    $img = imagecreatetruecolor(8, 8);
    ob_start();
    imagepng($img);

    return (string) ob_get_clean();
}

test('store encrypts the file under a random name and contents decrypts it', function () {
    $bytes = kkPng();
    $path = $this->storage->store(UploadedFile::fake()->createWithContent('KK Keluarga Budi.png', $bytes));

    expect($path)->toMatch('~^\d{4}/\d{2}/[0-9a-z]{26}\.kk$~')
        ->and(Storage::disk('kk')->exists($path))->toBeTrue();

    $raw = Storage::disk('kk')->get($path);
    expect($raw)->not->toContain($bytes)
        ->not->toContain(base64_encode($bytes))
        ->not->toContain('image/png')
        ->and($this->storage->contents($path))->toBe($bytes)
        ->and($this->storage->mime($path))->toBe('image/png');
});

test('two stores of the same file get different names', function () {
    $a = $this->storage->store(UploadedFile::fake()->createWithContent('kk.png', kkPng()));
    $b = $this->storage->store(UploadedFile::fake()->createWithContent('kk.png', kkPng()));

    expect($a)->not->toBe($b);
});

test('delete removes the stored file', function () {
    $path = $this->storage->store(UploadedFile::fake()->createWithContent('kk.png', kkPng()));
    $this->storage->delete($path);

    expect(Storage::disk('kk')->exists($path))->toBeFalse();
});

test('store refuses unsupported content even when called directly', function () {
    expect(fn () => $this->storage->store(UploadedFile::fake()->createWithContent('kk.png', '<?php echo 1;')))
        ->toThrow(InvalidArgumentException::class);
    expect(File::isDirectory($this->kkRoot) ? File::allFiles($this->kkRoot) : [])->toBeEmpty();
});

test('store refuses files over the configured limit', function () {
    config(['pendaftaran.kk_max_kb' => 1]);

    expect(fn () => $this->storage->store(UploadedFile::fake()->createWithContent('kk.pdf', "%PDF-1.4\n".str_repeat('x', 2048)."\n%%EOF")))
        ->toThrow(InvalidArgumentException::class);
});

test('tampered ciphertext is rejected', function () {
    $path = $this->storage->store(UploadedFile::fake()->createWithContent('kk.png', kkPng()));
    Storage::disk('kk')->put($path, 'bukan-ciphertext');

    expect(fn () => $this->storage->contents($path))->toThrow(DecryptException::class);
});

test('kk disk is private, outside public and has no url', function () {
    $root = realpath(config('filesystems.disks.kk.root')) ?: config('filesystems.disks.kk.root');
    $default = str_replace('\\', '/', (string) app('config')->get('filesystems.disks.kk.root'));

    expect(str_replace('\\', '/', $root))->not->toStartWith(str_replace('\\', '/', public_path()))
        ->and(str_replace('\\', '/', storage_path('app/private/kk')))->not->toStartWith(str_replace('\\', '/', public_path()))
        ->and(config('filesystems.disks.kk.driver'))->not->toBe('local')
        ->and(config('filesystems.disks.kk'))->not->toHaveKey('url')
        ->and(Route::has('storage.kk'))->toBeFalse()
        ->and($default)->not->toContain('/public');

    expect(fn () => Storage::disk('kk')->url('x.kk'))->toThrow(RuntimeException::class)
        ->and(fn () => Storage::disk('kk')->temporaryUrl('x.kk', now()->addMinute()))->toThrow(RuntimeException::class);
});

test('production kk disk root is storage/app/private/kk', function () {
    $config = require base_path('config/filesystems.php');

    expect(str_replace('\\', '/', $config['disks']['kk']['root']))->toBe(str_replace('\\', '/', storage_path('app/private/kk')));
});
