<?php

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Core\Database\Seeders\RoleSeeder;
use Modules\Pendaftaran\Database\Seeders\PendaftaranSeeder;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource\Pages\ListApplicants;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource\Pages\ViewApplicant;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Services\KkStorage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PendaftaranSeeder::class);
    Filament::setCurrentPanel('admin');

    $this->kkRoot = storage_path('framework/testing/kk-'.Str::random(10));
    config(['filesystems.disks.kk.root' => $this->kkRoot]);
    Storage::forgetDisk('kk');
});

afterEach(function () {
    File::deleteDirectory($this->kkRoot);
});

function applicantActor(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Role uji dengan izin tertentu saja (mis. tanpa Update/RevealNik). */
function applicantActorWith(array $permissions): User
{
    $role = Role::findOrCreate('uji_'.Str::random(6), 'web');
    $role->givePermissionTo($permissions);

    return applicantActor($role->name);
}

/** Pendaftar dengan nilai teks tetap supaya pencarian tidak cocok kebetulan dengan data acak faker. */
function applicantRow(array $attributes = []): Applicant
{
    return Applicant::factory()->create(array_merge([
        'nama_lengkap' => 'Santri '.Str::random(6),
        'ayah_nama' => 'Ayah Contoh',
        'ibu_nama' => 'Ibu Contoh',
        'ayah_hp' => '081200000001',
        'desa' => 'Sukamaju',
        'kecamatan' => 'Ciawi',
        'kabupaten' => 'Bogor',
        'tempat_lahir' => 'Bogor',
        'jalan_dusun' => 'Jalan Melati',
    ], $attributes));
}

function kkPngBytes(): string
{
    $img = imagecreatetruecolor(8, 8);
    ob_start();
    imagepng($img);

    return (string) ob_get_clean();
}

test('test_panitia_can_list_search_by_name_and_by_nik', function () {
    $ahmad = applicantRow(['nama_lengkap' => 'Ahmad Fauzi', 'nik' => '3201011111110001']);
    $budi = applicantRow(['nama_lengkap' => 'Budi Santoso', 'nik' => '3201012222220002']);

    $this->actingAs(applicantActor('panitia_pendaftaran'));
    $this->get(ApplicantResource::getUrl('index'))->assertOk()->assertSee('NIK: ketik 16 digit lengkap');

    Livewire::test(ListApplicants::class)
        ->assertCanSeeTableRecords([$ahmad, $budi])
        ->searchTable('fauzi')
        ->assertCanSeeTableRecords([$ahmad])
        ->assertCanNotSeeTableRecords([$budi])
        ->searchTable('3201012222220002')
        ->assertCanSeeTableRecords([$budi])
        ->assertCanNotSeeTableRecords([$ahmad])
        // Spasi/titik/tanda hubung dibuang sebelum di-hash.
        ->searchTable(' 3201 0122.2222-0002 ')
        ->assertCanSeeTableRecords([$budi])
        ->assertCanNotSeeTableRecords([$ahmad])
        // NIK sebagian tidak bisa dicari (kolom terenkripsi).
        ->searchTable('320101222222')
        ->assertCanNotSeeTableRecords([$ahmad, $budi]);
});

test('search covers parents, phones and region but never LIKE on the encrypted nik', function () {
    $a = applicantRow(['ibu_nama' => 'Siti Maryam', 'kabupaten' => 'Malang']);
    $b = applicantRow(['ayah_hp' => '085711112222']);
    $c = applicantRow(['orang_tua_meninggal' => true, 'wali_nama' => 'Paman Rahmat', 'wali_hp' => '081399998888']);

    $this->actingAs(applicantActor('panitia_pendaftaran'));

    DB::enableQueryLog();
    Livewire::test(ListApplicants::class)
        ->searchTable('maryam')->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b, $c])
        ->searchTable('malang')->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b, $c])
        ->searchTable('0857-1111')->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a, $c])
        ->searchTable('rahmat')->assertCanSeeTableRecords([$c])->assertCanNotSeeTableRecords([$a, $b])
        ->searchTable('81399998888')->assertCanSeeTableRecords([$c])->assertCanNotSeeTableRecords([$a, $b]);

    $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
    expect($sql)->not->toMatch('/"nik"\s+like/i')->not->toMatch('/\bnik\s+like/i');
});

test('search escapes LIKE wildcards', function () {
    $plain = applicantRow(['nama_lengkap' => 'Abdul Karim']);
    $underscore = applicantRow(['nama_lengkap' => 'Data A_B']);
    $percent = applicantRow(['nama_lengkap' => 'Diskon 50% Persen']);

    $this->actingAs(applicantActor('panitia_pendaftaran'));

    Livewire::test(ListApplicants::class)
        ->searchTable('%')->assertCanSeeTableRecords([$percent])->assertCanNotSeeTableRecords([$plain, $underscore])
        ->searchTable('_')->assertCanSeeTableRecords([$underscore])->assertCanNotSeeTableRecords([$plain, $percent])
        ->searchTable('A_B')->assertCanSeeTableRecords([$underscore])->assertCanNotSeeTableRecords([$plain, $percent])
        ->searchTable('AxB')->assertCanNotSeeTableRecords([$plain, $underscore, $percent])
        ->searchTable('!')->assertCanNotSeeTableRecords([$plain, $underscore, $percent]);
});

test('test_filter_by_each_status_flag', function () {
    $dihubungi = applicantRow();
    $dihubungi->forceFill(['sudah_dihubungi' => true])->save();
    $bayar = applicantRow();
    $bayar->forceFill(['sudah_bayar' => true])->save();
    $masuk = applicantRow();
    $masuk->forceFill(['sudah_masuk' => true])->save();
    $none = applicantRow();

    $this->actingAs(applicantActor('panitia_pendaftaran'));

    $expectations = [
        'sudah_dihubungi' => $dihubungi,
        'sudah_bayar' => $bayar,
        'sudah_masuk' => $masuk,
    ];

    foreach ($expectations as $flag => $match) {
        $others = collect([$dihubungi, $bayar, $masuk, $none])->reject(fn ($a) => $a->is($match))->values()->all();

        Livewire::test(ListApplicants::class)
            ->filterTable($flag, true)
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords($others);

        Livewire::test(ListApplicants::class)
            ->filterTable($flag, false)
            ->assertCanSeeTableRecords($others)
            ->assertCanNotSeeTableRecords([$match]);
    }
});

test('filters combine with search, jenjang, kelas, pernah mondok and date range', function () {
    $jenjang = Jenjang::create(['name' => 'SMP/MTs', 'sort_order' => 1, 'is_active' => true]);
    $other = Jenjang::create(['name' => 'SMA/MA', 'sort_order' => 2, 'is_active' => true]);

    $target = applicantRow(['nama_lengkap' => 'Fauzan Hakim', 'jenjang_id' => $jenjang->id, 'kelas' => 'Kelas 8', 'pernah_mondok' => true, 'pondok_sebelumnya' => 'PP Lama']);
    $target->forceFill(['sudah_bayar' => true])->save();
    $sameNameUnpaid = applicantRow(['nama_lengkap' => 'Fauzan Rahman', 'jenjang_id' => $jenjang->id, 'kelas' => 'Kelas 8']);
    $paidOtherName = applicantRow(['nama_lengkap' => 'Zaki Mubarak', 'jenjang_id' => $jenjang->id]);
    $paidOtherName->forceFill(['sudah_bayar' => true])->save();
    $otherJenjang = applicantRow(['nama_lengkap' => 'Fauzan Lain', 'jenjang_id' => $other->id, 'kelas' => 'Kelas 10']);
    $otherJenjang->forceFill(['sudah_bayar' => true])->save();
    $old = applicantRow(['nama_lengkap' => 'Fauzan Lama', 'jenjang_id' => $jenjang->id, 'kelas' => 'Kelas 8']);
    $old->forceFill(['sudah_bayar' => true, 'created_at' => now()->subMonths(3)])->save();

    $this->actingAs(applicantActor('panitia_pendaftaran'));

    Livewire::test(ListApplicants::class)
        ->searchTable('fauzan')
        ->filterTable('sudah_bayar', true)
        ->assertCanSeeTableRecords([$target, $otherJenjang, $old])
        ->assertCanNotSeeTableRecords([$sameNameUnpaid, $paidOtherName])
        ->filterTable('jenjang_id', $jenjang->id)
        ->assertCanSeeTableRecords([$target, $old])
        ->assertCanNotSeeTableRecords([$otherJenjang])
        ->filterTable('created_at', ['dari' => now()->subWeek()->toDateString(), 'sampai' => now()->toDateString()])
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$old, $otherJenjang, $sameNameUnpaid, $paidOtherName]);

    Livewire::test(ListApplicants::class)
        ->filterTable('pernah_mondok', true)
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$sameNameUnpaid, $old]);

    Livewire::test(ListApplicants::class)
        ->filterTable('kelas', 'Kelas 10')
        ->assertCanSeeTableRecords([$otherJenjang])
        ->assertCanNotSeeTableRecords([$target, $old]);
});

test('list is newest first, paginated by 25, masks nik and survives undecryptable nik', function () {
    $older = applicantRow(['nik' => '3201013333330003']);
    $older->forceFill(['created_at' => now()->subDay()])->save();
    $newer = applicantRow(['nik' => '3201014444440004']);
    $broken = applicantRow(['nik' => '3201015555550005']);
    DB::table('applicants')->where('id', $broken->id)->update(['nik' => 'bukan-payload-enkripsi']);

    $this->actingAs(applicantActor('panitia_pendaftaran'));

    Livewire::test(ListApplicants::class)
        ->assertCanSeeTableRecords([$broken, $newer, $older], inOrder: true)
        ->assertSee('************0004')
        ->assertDontSee('3201014444440004')
        ->assertSee('— (tidak terbaca)');

    expect(Livewire::test(ListApplicants::class)->instance()->getTable()->getDefaultPaginationPageOption())->toBe(25);
});

test('test_status_flags_can_be_toggled_independently', function () {
    $applicant = applicantRow();
    $this->actingAs(applicantActor('panitia_pendaftaran'));

    $list = Livewire::test(ListApplicants::class);
    $list->call('updateTableColumnState', 'sudah_bayar', (string) $applicant->getKey(), true);

    $fresh = $applicant->fresh();
    expect($fresh->sudah_bayar)->toBeTrue()
        ->and($fresh->sudah_dihubungi)->toBeFalse()
        ->and($fresh->sudah_masuk)->toBeFalse();

    $list->call('updateTableColumnState', 'sudah_masuk', (string) $applicant->getKey(), true);
    $list->call('updateTableColumnState', 'sudah_bayar', (string) $applicant->getKey(), false);

    $fresh = $applicant->fresh();
    expect($fresh->sudah_bayar)->toBeFalse()
        ->and($fresh->sudah_dihubungi)->toBeFalse()
        ->and($fresh->sudah_masuk)->toBeTrue();

    $logs = Activity::where('event', 'pendaftaran.status')->orderBy('id')->get();
    expect($logs)->toHaveCount(3)
        ->and($logs[0]->properties->toArray())->toBe(['flag' => 'sudah_bayar', 'value' => true, 'applicant_id' => $applicant->id])
        ->and($logs[2]->properties->toArray())->toBe(['flag' => 'sudah_bayar', 'value' => false, 'applicant_id' => $applicant->id])
        ->and($logs[0]->subject_id)->toBe($applicant->id);
});

test('status toggle requires Update permission and status action on the view page is audited', function () {
    $applicant = applicantRow();

    $this->actingAs(applicantActorWith(['ViewAny:Applicant', 'View:Applicant']));
    Livewire::test(ListApplicants::class)
        ->call('updateTableColumnState', 'sudah_bayar', (string) $applicant->getKey(), true);
    expect($applicant->fresh()->sudah_bayar)->toBeFalse()
        ->and(Activity::where('event', 'pendaftaran.status')->count())->toBe(0);
    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()])->assertActionHidden('ubahStatus');

    $this->actingAs(applicantActor('panitia_pendaftaran'));
    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()])
        ->callAction('ubahStatus', ['sudah_dihubungi' => true, 'sudah_bayar' => false, 'sudah_masuk' => false, 'catatan_panitia' => 'Sudah ditelepon, minta jadwal'])
        ->assertHasNoActionErrors();

    $fresh = $applicant->fresh();
    expect($fresh->sudah_dihubungi)->toBeTrue()
        ->and($fresh->sudah_bayar)->toBeFalse()
        ->and($fresh->catatan_panitia)->toBe('Sudah ditelepon, minta jadwal');

    // Hanya bendera yang berubah yang dicatat; catatan dicatat tanpa isinya.
    expect(Activity::where('event', 'pendaftaran.status')->count())->toBe(1);
    $note = Activity::where('event', 'pendaftaran.catatan')->sole();
    expect($note->properties->toArray())->toBe(['applicant_id' => $applicant->id]);

    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()])
        ->callAction('ubahStatus', ['sudah_dihubungi' => true, 'sudah_bayar' => false, 'sudah_masuk' => false, 'catatan_panitia' => str_repeat('a', 2001)])
        ->assertHasActionErrors(['catatan_panitia']);
});

test('test_penulis_berita_cannot_open_applicants', function () {
    $applicant = applicantRow();
    $this->actingAs(applicantActor('penulis_berita'));

    expect(ApplicantResource::canViewAny())->toBeFalse();
    $this->get(ApplicantResource::getUrl('index'))->assertForbidden();
    $this->get(ApplicantResource::getUrl('view', ['record' => $applicant]))->assertForbidden();
    $this->get(route('pendaftar.kk', $applicant))->assertForbidden();
});

test('guest is redirected to the admin login', function () {
    $applicant = applicantRow();

    $this->get(ApplicantResource::getUrl('index'))->assertRedirect(Filament::getLoginUrl());
    $this->get(ApplicantResource::getUrl('view', ['record' => $applicant]))->assertRedirect(Filament::getLoginUrl());
});

test('resource has no create page and is not globally searchable', function () {
    expect(ApplicantResource::getPages())->not->toHaveKey('create')
        ->and(ApplicantResource::canGloballySearch())->toBeFalse();

    $this->actingAs(applicantActor('admin'));
    expect(ApplicantResource::canCreate())->toBeFalse();
});

test('panitia cannot delete single or bulk, admin can', function () {
    $a = applicantRow();
    $b = applicantRow();

    $panitia = applicantActor('panitia_pendaftaran');
    $this->actingAs($panitia);
    expect($panitia->can('delete', $a))->toBeFalse()->and($panitia->can('deleteAny', Applicant::class))->toBeFalse();

    Livewire::test(ListApplicants::class)
        ->assertActionHidden(TestAction::make('delete')->table($a))
        ->assertTableBulkActionHidden('delete')
        // Lewati helper tes (yang menolak aksi tersembunyi): panggilan Livewire langsung tetap tidak menghapus.
        ->set('selectedTableRecords', [(string) $a->id, (string) $b->id])
        ->call('mountAction', 'delete', [], ['table' => true, 'bulk' => true]);
    Livewire::test(ViewApplicant::class, ['record' => $a->getKey()])->assertActionHidden('delete');
    expect(Applicant::count())->toBe(2);

    // Pemegang DeleteAny tanpa Delete: aksi massal tetap memeriksa tiap rekaman.
    $this->actingAs(applicantActorWith(['ViewAny:Applicant', 'View:Applicant', 'DeleteAny:Applicant']));
    Livewire::test(ListApplicants::class)->callTableBulkAction('delete', [$a, $b]);
    expect(Applicant::count())->toBe(2);

    $this->actingAs(applicantActor('admin'));
    Livewire::test(ListApplicants::class)->callTableBulkAction('delete', [$a]);
    expect(Applicant::find($a->id))->toBeNull();
    Livewire::test(ViewApplicant::class, ['record' => $b->getKey()])->callAction('delete');
    expect(Applicant::count())->toBe(0)
        ->and(Activity::where('event', 'pendaftaran.dihapus')->count())->toBe(2);

    $super = applicantActor('superadmin');
    $c = applicantRow();
    expect($super->can('delete', $c))->toBeTrue()
        ->and($super->can('export', Applicant::class))->toBeTrue()
        ->and($super->can('revealNik', $c))->toBeTrue()
        ->and($super->can('downloadKk', $c))->toBeTrue();
});

test('deleting an applicant removes its KK file', function () {
    $path = app(KkStorage::class)->store(UploadedFile::fake()->createWithContent('kk.png', kkPngBytes()));
    $applicant = applicantRow();
    $applicant->forceFill(['kk_path' => $path])->save();

    $this->actingAs(applicantActor('admin'));
    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()])->callAction('delete');

    expect(Storage::disk('kk')->exists($path))->toBeFalse();
});

test('permissions seeder is idempotent and grants the applicant matrix', function () {
    $this->seed(PendaftaranSeeder::class);
    $this->seed(PendaftaranSeeder::class);

    $all = ['ViewAny', 'View', 'Update', 'Delete', 'DeleteAny', 'Export', 'RevealNik', 'DownloadKk'];
    $panitia = ['ViewAny', 'View', 'Update', 'Export', 'RevealNik', 'DownloadKk'];

    $admin = applicantActor('admin');
    $p = applicantActor('panitia_pendaftaran');
    $writer = applicantActor('penulis_berita');

    foreach ($all as $action) {
        expect($admin->can("$action:Applicant"))->toBeTrue("admin $action")
            ->and($p->can("$action:Applicant"))->toBe(in_array($action, $panitia, true), "panitia $action")
            ->and($writer->can("$action:Applicant"))->toBeFalse("penulis $action");
    }

    // Panitia tidak mendapat data master.
    expect($p->can('ViewAny:Jenjang'))->toBeFalse();
});

test('view page shows sections, masks nik, and logs one view per mount', function () {
    $applicant = applicantRow(['nik' => '3201016666660006', 'nama_lengkap' => 'Hasan Basri']);
    $this->actingAs(applicantActor('panitia_pendaftaran'));

    $this->get(ApplicantResource::getUrl('view', ['record' => $applicant]))
        ->assertOk()
        ->assertSee('Data Calon Santri')
        ->assertSee('Akademik')
        ->assertSee('Orang Tua')
        ->assertSee('Status')
        ->assertSee('Dokumen')
        ->assertDontSee('Data Wali')
        ->assertSee('************0006')
        ->assertDontSee('3201016666660006');

    expect(Activity::where('event', 'pendaftaran.dilihat')->count())->toBe(1);

    $page = Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()]);
    $page->call('$refresh')->call('$refresh');
    expect(Activity::where('event', 'pendaftaran.dilihat')->count())->toBe(2);

    $log = Activity::where('event', 'pendaftaran.dilihat')->latest('id')->first();
    expect($log->subject_id)->toBe($applicant->id)
        ->and($log->properties->toArray())->toBe([]);
});

test('wali section appears only when both parents passed away', function () {
    $applicant = applicantRow(['orang_tua_meninggal' => true, 'wali_nama' => 'Paman Yusuf', 'wali_hp' => '081311112222']);
    $this->actingAs(applicantActor('panitia_pendaftaran'));

    $this->get(ApplicantResource::getUrl('view', ['record' => $applicant]))
        ->assertOk()->assertSee('Data Wali')->assertSee('Paman Yusuf');
});

test('reveal nik is permission gated and audited without the nik', function () {
    $applicant = applicantRow(['nik' => '3201017777770007']);

    $this->actingAs(applicantActorWith(['ViewAny:Applicant', 'View:Applicant']));
    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()])
        ->assertActionHidden('tampilkanNik')
        ->assertActionHidden('unduhKk');

    $this->actingAs(applicantActor('panitia_pendaftaran'));
    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()])
        ->assertDontSee('3201017777770007')
        ->mountAction('tampilkanNik')
        ->assertMountedActionModalSee('3201017777770007');

    $log = Activity::where('event', 'pendaftaran.nik_dilihat')->sole();
    expect($log->subject_id)->toBe($applicant->id)
        ->and(json_encode($log->properties))->not->toContain('3201017777770007')
        ->and(json_encode($log->properties))->not->toContain('7777');
});

test('view page handles undecryptable nik gracefully', function () {
    $applicant = applicantRow();
    DB::table('applicants')->where('id', $applicant->id)->update(['nik' => 'rusak']);
    $this->actingAs(applicantActor('panitia_pendaftaran'));

    $this->get(ApplicantResource::getUrl('view', ['record' => $applicant]))->assertOk()->assertSee('— (tidak terbaca)');
    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()])
        ->mountAction('tampilkanNik')
        ->assertMountedActionModalSee('(tidak terbaca)');
});

test('test_guest_cannot_download_kk', function () {
    $applicant = applicantRow();

    $this->get(route('pendaftar.kk', $applicant))->assertRedirect(Filament::getLoginUrl());
    expect(Activity::where('event', 'pendaftaran.kk_diunduh')->count())->toBe(0);

    // Akun tanpa role tidak boleh masuk panel.
    $this->actingAs(User::factory()->create());
    $this->get(route('pendaftar.kk', $applicant))->assertForbidden();
});

test('test_kk_download_is_decrypted_and_logged', function () {
    $bytes = kkPngBytes();
    $path = app(KkStorage::class)->store(UploadedFile::fake()->createWithContent('KK Asli Keluarga.png', $bytes));
    $applicant = applicantRow();
    $applicant->forceFill(['kk_path' => $path])->save();

    $this->actingAs(applicantActor('panitia_pendaftaran'));
    $response = $this->get('/admin/pendaftar/'.$applicant->id.'/kk');

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'attachment; filename="kk-'.$applicant->id.'.png"')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'")
        ->assertHeader('Content-Length', (string) strlen($bytes));
    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and($response->getContent())->toBe($bytes);

    $log = Activity::where('event', 'pendaftaran.kk_diunduh')->sole();
    expect($log->subject_id)->toBe($applicant->id)
        ->and($log->properties->toArray())->toBe([]);
});

test('kk download: missing or unreadable file is a clean 404 and only numeric ids route', function () {
    $this->actingAs(applicantActor('panitia_pendaftaran'));

    $noFile = applicantRow();
    $this->get(route('pendaftar.kk', $noFile))->assertNotFound();

    $gone = applicantRow();
    $gone->forceFill(['kk_path' => '2026/11/tidakada.kk'])->save();
    $response = $this->get(route('pendaftar.kk', $gone))->assertNotFound();
    expect($response->getContent())->not->toContain('tidakada')->not->toContain('storage');

    $corrupt = applicantRow();
    Storage::disk('kk')->put('2026/11/rusak.kk', 'bukan-terenkripsi');
    $corrupt->forceFill(['kk_path' => '2026/11/rusak.kk'])->save();
    $response = $this->get(route('pendaftar.kk', $corrupt))->assertNotFound();
    expect($response->getContent())->not->toContain('rusak.kk')->not->toContain('DecryptException');

    $this->get('/admin/pendaftar/..%2F..%2F.env/kk')->assertNotFound();
    $this->get('/admin/pendaftar/abc/kk')->assertNotFound();
    $this->get('/admin/pendaftar/999999/kk')->assertNotFound();

    expect(Activity::where('event', 'pendaftaran.kk_diunduh')->count())->toBe(0);
});

test('view page shows KK download link only with DownloadKk and when a file exists', function () {
    $path = app(KkStorage::class)->store(UploadedFile::fake()->createWithContent('kk.png', kkPngBytes()));
    $applicant = applicantRow();
    $applicant->forceFill(['kk_path' => $path])->save();

    $this->actingAs(applicantActor('panitia_pendaftaran'));
    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()])
        ->assertActionVisible('unduhKk')
        ->assertActionHasUrl('unduhKk', route('pendaftar.kk', $applicant));

    $without = applicantRow();
    Livewire::test(ViewApplicant::class, ['record' => $without->getKey()])->assertActionHidden('unduhKk');
});
