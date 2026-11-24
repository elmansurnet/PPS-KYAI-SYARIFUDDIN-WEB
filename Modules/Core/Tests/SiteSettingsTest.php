<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Database\Seeders\RoleSeeder;
use Modules\Core\Filament\Pages\ManageSiteSettings;
use Modules\Core\Settings\SiteSettings;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(RoleSeeder::class);
});

function settingsUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('default settings are provided by the settings migration', function () {
    $settings = app(SiteSettings::class);

    expect($settings->site_name)->toBe('PPs Kyai Syarifuddin');
    expect($settings->whatsapp_number)->toBe('');
    expect($settings->whatsapp_message_template)->toContain('{nama}')->toContain('{nik_akhir}');
    expect($settings->social_links)->toBe([]);
    expect($settings->logo_path)->toBeNull();
    expect($settings->fee_pdf_path)->toBeNull();
});

test('test_superadmin_can_open_settings_page', function () {
    $this->actingAs(settingsUser('superadmin'));

    expect(ManageSiteSettings::canAccess())->toBeTrue();
    $this->get('/admin/manage-site-settings')->assertOk();
});

test('test_admin_can_open_settings_page', function () {
    $this->actingAs(settingsUser('admin'));

    expect(ManageSiteSettings::canAccess())->toBeTrue();
    $this->get('/admin/manage-site-settings')->assertOk();
});

test('test_penulis_berita_cannot_open_settings_page', function () {
    $this->actingAs(settingsUser('penulis_berita'));

    expect(ManageSiteSettings::canAccess())->toBeFalse();
    $this->get('/admin/manage-site-settings')->assertForbidden();
});

test('test_panitia_pendaftaran_cannot_open_settings_page', function () {
    $this->actingAs(settingsUser('panitia_pendaftaran'));

    expect(ManageSiteSettings::canAccess())->toBeFalse();
    $this->get('/admin/manage-site-settings')->assertForbidden();
});

test('guest is redirected to login', function () {
    $this->get('/admin/manage-site-settings')->assertRedirect('/admin/login');
});

test('admin can save settings', function () {
    $this->actingAs(settingsUser('admin'));

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_name' => 'PPs Baru',
            'contact_email' => 'info@example.test',
            'whatsapp_number' => '0812 3456 789',
            'whatsapp_message_template' => 'Halo {nama} ...{nik_akhir}',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(SiteSettings::class);
    expect($settings->site_name)->toBe('PPs Baru');
    expect($settings->whatsapp_number)->toBe('628123456789');
});

test('invalid email and empty site name are rejected', function () {
    $this->actingAs(settingsUser('admin'));

    Livewire::test(ManageSiteSettings::class)
        ->fillForm(['site_name' => '', 'contact_email' => 'bukan-email'])
        ->call('save')
        ->assertHasFormErrors(['site_name', 'contact_email']);
});

test('logo accepts images only and rejects svg and oversized files', function () {
    Storage::fake('public');
    $this->actingAs(settingsUser('admin'));

    Livewire::test(ManageSiteSettings::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
        ->call('save')
        ->assertHasFormErrors(['logo_path']);

    Livewire::test(ManageSiteSettings::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->image('logo.png')->size(3000)])
        ->call('save')
        ->assertHasFormErrors(['logo_path']);

    Livewire::test(ManageSiteSettings::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->image('logo.png', 100, 100)])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = app(SiteSettings::class)->logo_path;
    expect($path)->not->toBeNull()->not->toContain('logo.png');
    Storage::disk('public')->assertExists($path);
});

test('fee pdf accepts pdf only', function () {
    Storage::fake('public');
    $this->actingAs(settingsUser('admin'));

    Livewire::test(ManageSiteSettings::class)
        ->fillForm(['fee_pdf_path' => UploadedFile::fake()->create('biaya.exe', 10, 'application/x-msdownload')])
        ->call('save')
        ->assertHasFormErrors(['fee_pdf_path']);

    Livewire::test(ManageSiteSettings::class)
        ->fillForm(['fee_pdf_path' => UploadedFile::fake()->create('biaya.pdf', 100, 'application/pdf')])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = app(SiteSettings::class)->fee_pdf_path;
    expect($path)->not->toBeNull()->not->toContain('biaya.pdf');
    Storage::disk('public')->assertExists($path);
});

test('seeder grants Manage:SiteSettings to admin only and is idempotent', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::findByName('admin')->hasPermissionTo('Manage:SiteSettings'))->toBeTrue();
    expect(Role::findByName('penulis_berita')->permissions)->toBeEmpty();
    expect(Role::findByName('panitia_pendaftaran')->permissions)->toBeEmpty();
});
