<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Database\Seeders\RoleSeeder;
use Modules\Pendaftaran\Database\Seeders\MasterDataSeeder;
use Modules\Pendaftaran\Database\Seeders\PendaftaranSeeder;
use Modules\Pendaftaran\Filament\Resources\JenjangResource;
use Modules\Pendaftaran\Filament\Resources\JenjangResource\Pages\ListJenjangs;
use Modules\Pendaftaran\Filament\Resources\KelasOptionResource;
use Modules\Pendaftaran\Filament\Resources\KelasOptionResource\Pages\CreateKelasOption;
use Modules\Pendaftaran\Filament\Resources\PendidikanTerakhirResource;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Models\KelasOption;
use Modules\Pendaftaran\Models\PendidikanTerakhir;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PendaftaranSeeder::class);
    Filament::setCurrentPanel('admin');
});

function pendaftaranUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('master data seeder creates defaults and is idempotent without overwriting edits', function () {
    $this->seed(MasterDataSeeder::class);

    expect(Jenjang::count())->toBe(2)
        ->and(KelasOption::count())->toBe(6)
        ->and(PendidikanTerakhir::count())->toBe(4);

    $jenjang = Jenjang::where('name', 'SMP/MTs')->firstOrFail();
    $jenjang->update(['is_active' => false, 'sort_order' => 9]);

    $this->seed(MasterDataSeeder::class);

    expect(Jenjang::count())->toBe(2)
        ->and(KelasOption::count())->toBe(6)
        ->and($jenjang->fresh()->is_active)->toBeFalse()
        ->and($jenjang->fresh()->sort_order)->toBe(9)
        ->and(PendidikanTerakhir::count())->toBe(4);
});

test('permissions seeder is idempotent and gives master data to admin only', function () {
    $this->seed(PendaftaranSeeder::class);

    foreach (['Jenjang', 'KelasOption', 'PendidikanTerakhir'] as $model) {
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Reorder'] as $action) {
            expect(pendaftaranUser('admin')->can("$action:$model"))->toBeTrue()
                ->and(pendaftaranUser('penulis_berita')->can("$action:$model"))->toBeFalse()
                ->and(pendaftaranUser('panitia_pendaftaran')->can("$action:$model"))->toBeFalse();
        }
    }
});

test('admin and superadmin manage master data, panitia and penulis cannot', function () {
    $this->seed(MasterDataSeeder::class);

    foreach (['admin', 'superadmin'] as $role) {
        $this->actingAs(pendaftaranUser($role));
        expect(JenjangResource::canViewAny())->toBeTrue()
            ->and(KelasOptionResource::canViewAny())->toBeTrue()
            ->and(PendidikanTerakhirResource::canViewAny())->toBeTrue();
        $this->get('/admin/jenjangs')->assertOk();
        $this->get('/admin/kelas-options')->assertOk();
        $this->get('/admin/pendidikan-terakhirs')->assertOk();
    }

    foreach (['penulis_berita', 'panitia_pendaftaran'] as $role) {
        $this->actingAs(pendaftaranUser($role));
        expect(JenjangResource::canViewAny())->toBeFalse();
        $this->get('/admin/jenjangs')->assertForbidden();
        $this->get('/admin/kelas-options')->assertForbidden();
        $this->get('/admin/pendidikan-terakhirs')->assertForbidden();
    }
});

test('kelas option form creates a class under a jenjang', function () {
    $jenjang = Jenjang::create(['name' => 'SMP/MTs', 'sort_order' => 1, 'is_active' => true]);
    $this->actingAs(pendaftaranUser('admin'));

    Livewire::test(CreateKelasOption::class)
        ->fillForm(['jenjang_id' => $jenjang->id, 'name' => 'Kelas 7', 'sort_order' => 1, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(KelasOption::firstOrFail()->jenjang_id)->toBe($jenjang->id);
});

test('jenjang in use by an applicant cannot be deleted from the list', function () {
    $jenjang = Jenjang::create(['name' => 'SMP/MTs', 'sort_order' => 1, 'is_active' => true]);
    Applicant::factory()->create(['jenjang_id' => $jenjang->id]);
    $this->actingAs(pendaftaranUser('admin'));

    Livewire::test(ListJenjangs::class)
        ->assertTableActionHidden('delete', $jenjang);
});

test('master data deleted or renamed by admin is not resurrected by re-seeding', function () {
    $this->seed(MasterDataSeeder::class);

    Jenjang::where('name', 'SMA/MA/SMK')->firstOrFail()->kelasOptions()->delete();
    Jenjang::where('name', 'SMA/MA/SMK')->delete();
    Jenjang::where('name', 'SMP/MTs')->update(['name' => 'MTs']);
    KelasOption::where('name', 'Kelas 9')->delete();
    PendidikanTerakhir::where('name', 'SD/MI')->delete();

    $this->seed(MasterDataSeeder::class);

    expect(Jenjang::pluck('name')->all())->toBe(['MTs'])
        ->and(KelasOption::pluck('name')->sort()->values()->all())->toBe(['Kelas 7', 'Kelas 8'])
        ->and(PendidikanTerakhir::where('name', 'SD/MI')->exists())->toBeFalse()
        ->and(PendidikanTerakhir::count())->toBe(3);
});
