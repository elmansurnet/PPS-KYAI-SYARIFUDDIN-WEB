<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Core\Database\Seeders\RoleSeeder;
use Modules\Pendaftaran\Database\Seeders\PendaftaranSeeder;
use Modules\Pendaftaran\Exports\ApplicantRow;
use Modules\Pendaftaran\Exports\ApplicantsExport;
use Modules\Pendaftaran\Exports\ApplicantsPdf;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource\Pages\ListApplicants;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource\Pages\ViewApplicant;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Models\PendidikanTerakhir;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PendaftaranSeeder::class);
    Filament::setCurrentPanel('admin');
});

const EXPORT_HEADINGS = [
    'No', 'Nama', 'NIK', 'TTL', 'Alamat lengkap', 'Pernah mondok', 'Pondok sebelumnya', 'Pendidikan terakhir',
    'Jenjang', 'Kelas', 'Nama ayah', 'HP ayah', 'Nama ibu', 'HP ibu', 'Wali', 'Sudah dihubungi', 'Sudah bayar',
    'Sudah masuk', 'Tanggal daftar',
];

function exportActor(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function exportActorWith(array $permissions): User
{
    $role = Role::findOrCreate('uji_'.Str::random(6), 'web');
    $role->givePermissionTo($permissions);

    return exportActor($role->name);
}

function exportRow(array $attributes = []): Applicant
{
    return Applicant::factory()->create(array_merge([
        'nama_lengkap' => 'Santri '.Str::random(6),
        'ayah_nama' => 'Ayah Contoh',
        'ibu_nama' => 'Ibu Contoh',
        'ayah_hp' => '081200000001',
        'desa' => 'Sukamaju',
        'kecamatan' => 'Ciawi',
        'kabupaten' => 'Bogor',
    ], $attributes));
}

function sheetFromBinary(string $binary): Worksheet
{
    $file = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($file, $binary);

    try {
        return IOFactory::load($file)->getActiveSheet();
    } finally {
        @unlink($file);
    }
}

function downloadedBinary(Testable $component): string
{
    $download = data_get($component->effects, 'download');
    expect($download)->not->toBeNull('tidak ada unduhan');

    return base64_decode($download['content']);
}

/** Baris data (tanpa judul) dari lembar, kolom B (nama). */
function exportedNames(Worksheet $sheet): array
{
    $names = [];
    for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
        $names[] = $sheet->getCell('B'.$row)->getValue();
    }

    return array_values(array_filter($names, fn ($v) => $v !== null && $v !== ''));
}

test('test_excel_export_respects_active_filters', function () {
    $paidFauzan = exportRow(['nama_lengkap' => 'Fauzan Hakim']);
    $paidFauzan->forceFill(['sudah_bayar' => true])->save();
    $paidFauzan2 = exportRow(['nama_lengkap' => 'Fauzan Kedua']);
    $paidFauzan2->forceFill(['sudah_bayar' => true, 'created_at' => now()->subHour()])->save();
    exportRow(['nama_lengkap' => 'Fauzan Belum']);
    $paidOther = exportRow(['nama_lengkap' => 'Zaki Mubarak']);
    $paidOther->forceFill(['sudah_bayar' => true])->save();

    // Lebih dari satu halaman: ekspor harus memuat seluruh hasil saringan, bukan halaman aktif saja.
    for ($i = 0; $i < 30; $i++) {
        exportRow(['nama_lengkap' => 'Fauzan Massal '.$i])->forceFill(['sudah_bayar' => true, 'created_at' => now()->subDays(2)])->save();
    }

    $this->actingAs(exportActor('panitia_pendaftaran'));

    $component = Livewire::test(ListApplicants::class)
        ->searchTable('fauzan')
        ->filterTable('sudah_bayar', true)
        ->callAction('eksporExcel');

    $component->assertFileDownloaded();
    $sheet = sheetFromBinary(downloadedBinary($component));
    $names = exportedNames($sheet);

    expect($names)->toHaveCount(32)
        ->and(array_slice($names, 0, 2))->toBe(['Fauzan Hakim', 'Fauzan Kedua'])
        ->and($names)->not->toContain('Fauzan Belum')
        ->and($names)->not->toContain('Zaki Mubarak');

    $log = Activity::where('event', 'pendaftaran.ekspor')->sole();
    expect($log->properties->toArray())->toBe(['format' => 'xlsx', 'count' => 32]);
});

test('bulk excel export contains only the selected records', function () {
    $a = exportRow(['nama_lengkap' => 'Pilih Satu']);
    $b = exportRow(['nama_lengkap' => 'Pilih Dua']);
    exportRow(['nama_lengkap' => 'Tidak Dipilih']);

    $this->actingAs(exportActor('panitia_pendaftaran'));
    $component = Livewire::test(ListApplicants::class)->callTableBulkAction('eksporExcelTerpilih', [$a, $b]);

    $names = exportedNames(sheetFromBinary(downloadedBinary($component)));
    sort($names);
    expect($names)->toBe(['Pilih Dua', 'Pilih Satu']);
});

test('excel export columns, ordering, nik as text and status as Ya/Belum', function () {
    $jenjang = Jenjang::create(['name' => 'SMP/MTs', 'sort_order' => 1, 'is_active' => true]);
    $pendidikan = PendidikanTerakhir::create(['name' => 'SD/MI', 'sort_order' => 1, 'is_active' => true]);
    $applicant = exportRow([
        'nik' => '3201010101120003',
        'nama_lengkap' => 'Ahmad Fauzi',
        'tempat_lahir' => 'Bogor',
        'tanggal_lahir' => '2012-01-02',
        'jalan_dusun' => 'Jl. Melati',
        'rt' => '001', 'rw' => '002', 'desa' => 'Sukamaju', 'kecamatan' => 'Ciawi', 'kabupaten' => 'Bogor', 'provinsi' => 'Jawa Barat',
        'pernah_mondok' => true, 'pondok_sebelumnya' => 'PP Al Hikmah', 'pendidikan_pondok_sebelumnya' => 'Ula',
        'pendidikan_terakhir_id' => $pendidikan->id, 'jenjang_id' => $jenjang->id, 'kelas' => 'Kelas 7',
        'ayah_nama' => 'Abdullah', 'ayah_hp' => '081234567890', 'ibu_nama' => 'Aminah', 'ibu_hp' => '081298765432',
    ]);
    $applicant->forceFill(['sudah_dihubungi' => true, 'created_at' => '2026-11-20 08:30:00'])->save();

    $this->actingAs(exportActor('panitia_pendaftaran'));
    $sheet = sheetFromBinary(ApplicantsExport::fromQuery(Applicant::query())->toBinary());

    $headings = [];
    foreach (range(1, count(EXPORT_HEADINGS)) as $col) {
        $headings[] = $sheet->getCell([$col, 1])->getValue();
    }
    expect($headings)->toBe(EXPORT_HEADINGS);

    $row = [];
    foreach (range(1, count(EXPORT_HEADINGS)) as $col) {
        $row[EXPORT_HEADINGS[$col - 1]] = $sheet->getCell([$col, 2])->getValue();
    }

    expect($row['No'])->toBe(1)
        ->and($row['Nama'])->toBe('Ahmad Fauzi')
        ->and($row['NIK'])->toBe('3201010101120003')
        ->and($row['TTL'])->toBe('Bogor, 02-01-2012')
        ->and($row['Alamat lengkap'])->toBe('Jl. Melati, RT 001/RW 002, Sukamaju, Ciawi, Bogor, Jawa Barat')
        ->and($row['Pernah mondok'])->toBe('Ya')
        ->and($row['Pondok sebelumnya'])->toBe('PP Al Hikmah (Ula)')
        ->and($row['Pendidikan terakhir'])->toBe('SD/MI')
        ->and($row['Jenjang'])->toBe('SMP/MTs')
        ->and($row['Kelas'])->toBe('Kelas 7')
        ->and($row['Nama ayah'])->toBe('Abdullah')
        ->and($row['HP ayah'])->toBe('081234567890')
        ->and($row['Nama ibu'])->toBe('Aminah')
        ->and($row['HP ibu'])->toBe('081298765432')
        ->and($row['Wali'])->toBe('-')
        ->and($row['Sudah dihubungi'])->toBe('Ya')
        ->and($row['Sudah bayar'])->toBe('Belum')
        ->and($row['Sudah masuk'])->toBe('Belum')
        ->and($row['Tanggal daftar'])->toBe('20-11-2026 08:30');

    // NIK dan HP tetap teks (tanpa kehilangan angka 0 / presisi 16 digit).
    expect($sheet->getCell('C2')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($sheet->getStyle('C2')->getNumberFormat()->getFormatCode())->toBe('@')
        ->and($sheet->getCell('L2')->getDataType())->toBe(DataType::TYPE_STRING);
});

test('excel export neutralises formula injection', function () {
    $applicant = exportRow([
        'nama_lengkap' => '=HYPERLINK("http://jahat.test","klik")',
        'ayah_nama' => '+SUM(1,2)',
        'ibu_nama' => '-2+3',
        'jalan_dusun' => '@cmd',
        'pondok_sebelumnya' => "\tTAB",
        'pernah_mondok' => true,
        'ibu_pekerjaan' => 'aman',
    ]);
    DB::table('applicants')->where('id', $applicant->id)->update(['kelas' => "\r=1+1"]);

    $this->actingAs(exportActor('panitia_pendaftaran'));
    $sheet = sheetFromBinary(ApplicantsExport::fromQuery(Applicant::query())->toBinary());

    foreach ($sheet->getRowIterator(2, 2) as $row) {
        foreach ($row->getCellIterator() as $cell) {
            expect($cell->getDataType())->not->toBe(DataType::TYPE_FORMULA, $cell->getCoordinate());
            $value = (string) $cell->getValue();
            // "-" adalah penanda kosong buatan ekspor (bukan isian), tidak bisa menjadi formula.
            expect($value === '' || $value === '-' || ! in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true))->toBeTrue($cell->getCoordinate().': '.$value);
        }
    }

    expect($sheet->getCell('B2')->getValue())->toBe("'=HYPERLINK(\"http://jahat.test\",\"klik\")")
        ->and($sheet->getCell('K2')->getValue())->toBe("'+SUM(1,2)")
        ->and($sheet->getCell('M2')->getValue())->toBe("'-2+3");
});

test('excel export masks nik without RevealNik and survives undecryptable rows', function () {
    $ok = exportRow(['nik' => '3201018888880008', 'nama_lengkap' => 'Baris Baik']);
    $ok->forceFill(['created_at' => now()->subMinute()])->save();
    $broken = exportRow(['nama_lengkap' => 'Baris Rusak']);
    DB::table('applicants')->where('id', $broken->id)->update(['nik' => 'rusak']);

    $this->actingAs(exportActorWith(['ViewAny:Applicant', 'View:Applicant', 'Export:Applicant']));
    $sheet = sheetFromBinary(ApplicantsExport::fromQuery(Applicant::query()->latest())->toBinary());

    expect($sheet->getCell('B2')->getValue())->toBe('Baris Rusak')
        ->and($sheet->getCell('C2')->getValue())->toBe('(tidak terbaca)')
        ->and($sheet->getCell('B3')->getValue())->toBe('Baris Baik')
        ->and($sheet->getCell('C3')->getValue())->toBe('************0008');

    $this->actingAs(exportActor('panitia_pendaftaran'));
    $sheet = sheetFromBinary(ApplicantsExport::fromQuery(Applicant::query()->latest())->toBinary());
    expect($sheet->getCell('C3')->getValue())->toBe('3201018888880008');
});

test('test_pdf_export_respects_active_filters', function () {
    $paid = exportRow(['nama_lengkap' => 'Fauzan Hakim', 'nik' => '3201019999990009']);
    $paid->forceFill(['sudah_bayar' => true])->save();
    exportRow(['nama_lengkap' => 'Fauzan Belum']);
    $paidOther = exportRow(['nama_lengkap' => 'Zaki Mubarak']);
    $paidOther->forceFill(['sudah_bayar' => true])->save();

    $this->actingAs(exportActor('panitia_pendaftaran'));

    $component = Livewire::test(ListApplicants::class)
        ->searchTable('fauzan')
        ->filterTable('sudah_bayar', true)
        ->callAction('eksporPdf');

    $component->assertFileDownloaded();
    expect(downloadedBinary($component))->toStartWith('%PDF-');

    $log = Activity::where('event', 'pendaftaran.ekspor')->sole();
    expect($log->properties->toArray())->toBe(['format' => 'pdf', 'count' => 1]);

    // Isi PDF (HTML sumber) mengikuti query terfilter yang sama.
    $query = Applicant::query()->where('sudah_bayar', true)->where('nama_lengkap', 'like', '%Fauzan%');
    $html = ApplicantsPdf::html($query, ['Sudah bayar: Ya', 'Pencarian: fauzan']);
    expect($html)->toContain('Fauzan Hakim')
        ->not->toContain('Fauzan Belum')
        ->not->toContain('Zaki Mubarak')
        ->toContain('Sudah bayar: Ya')
        ->toContain('Jumlah: 1');
});

test('pdf hides full nik without RevealNik and escapes html', function () {
    exportRow(['nik' => '3201012121210021', 'nama_lengkap' => '<b>Tebal</b><img src=http://jahat.test/x.png>']);

    $this->actingAs(exportActorWith(['ViewAny:Applicant', 'View:Applicant', 'Export:Applicant']));
    $html = ApplicantsPdf::html(Applicant::query());

    expect($html)->not->toContain('3201012121210021')
        ->toContain('************0021')
        ->not->toContain('<b>Tebal</b>')
        ->not->toContain('<img src=http')
        ->toContain('&lt;b&gt;Tebal&lt;/b&gt;');

    $this->actingAs(exportActor('panitia_pendaftaran'));
    expect(ApplicantsPdf::html(Applicant::query()))->toContain('3201012121210021');
});

test('pdf export is capped by pendaftaran.pdf_max_rows', function () {
    config(['pendaftaran.pdf_max_rows' => 2]);
    exportRow();
    exportRow();
    exportRow();

    $this->actingAs(exportActor('panitia_pendaftaran'));
    Livewire::test(ListApplicants::class)
        ->callAction('eksporPdf')
        ->assertNoFileDownloaded()
        ->assertNotified();

    expect(Activity::where('event', 'pendaftaran.ekspor')->count())->toBe(0);

    Livewire::test(ListApplicants::class)
        ->filterTable('sudah_bayar', false)
        ->searchTable('tidak-ada-yang-cocok')
        ->callAction('eksporPdf')
        ->assertFileDownloaded();
});

test('test_export_with_zero_rows_returns_valid_file', function () {
    $this->actingAs(exportActor('panitia_pendaftaran'));

    $component = Livewire::test(ListApplicants::class)->callAction('eksporExcel');
    $sheet = sheetFromBinary(downloadedBinary($component));
    expect($sheet->getCell('A1')->getValue())->toBe('No')
        ->and($sheet->getCell('S1')->getValue())->toBe('Tanggal daftar')
        ->and($sheet->getHighestDataRow())->toBe(1);

    $component = Livewire::test(ListApplicants::class)->callAction('eksporPdf');
    expect(downloadedBinary($component))->toStartWith('%PDF-');
    expect(ApplicantsPdf::html(Applicant::query()))->toContain('Tidak ada data')->toContain('Jumlah: 0');

    expect(ApplicantsPdf::render(Applicant::query()))->toStartWith('%PDF-');

    $logs = Activity::where('event', 'pendaftaran.ekspor')->orderBy('id')->get();
    expect($logs->map(fn ($l) => $l->properties->toArray())->all())->toBe([
        ['format' => 'xlsx', 'count' => 0],
        ['format' => 'pdf', 'count' => 0],
    ]);
});

test('test_viewing_and_exporting_applicants_creates_activity_log', function () {
    $applicant = exportRow(['nik' => '3201013131310031', 'nama_lengkap' => 'Nama Rahasia']);
    $user = exportActor('panitia_pendaftaran');
    $this->actingAs($user);

    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()]);
    Livewire::test(ListApplicants::class)->callAction('eksporExcel');
    Livewire::test(ListApplicants::class)->callAction('eksporPdf');
    Livewire::test(ListApplicants::class)->callTableBulkAction('eksporPdfTerpilih', [$applicant]);

    $viewed = Activity::where('event', 'pendaftaran.dilihat')->sole();
    expect($viewed->causer_id)->toBe($user->id)->and($viewed->subject_id)->toBe($applicant->id);

    $exports = Activity::where('event', 'pendaftaran.ekspor')->orderBy('id')->get();
    expect($exports)->toHaveCount(3)
        ->and($exports->pluck('causer_id')->unique()->all())->toBe([$user->id]);

    foreach ($exports as $log) {
        expect(array_keys($log->properties->toArray()))->toBe(['format', 'count']);
    }

    $everything = Activity::all()->toJson();
    expect($everything)->not->toContain('3201013131310031')
        ->not->toContain('Nama Rahasia');
});

test('export actions require Export permission', function () {
    exportRow();

    $this->actingAs(exportActorWith(['ViewAny:Applicant', 'View:Applicant']));
    Livewire::test(ListApplicants::class)
        ->assertActionHidden('eksporExcel')
        ->assertActionHidden('eksporPdf')
        ->assertTableBulkActionHidden('eksporExcelTerpilih')
        ->assertTableBulkActionHidden('eksporPdfTerpilih')
        // Panggilan Livewire langsung (melewati UI) tetap ditolak.
        ->call('mountAction', 'eksporExcel')
        ->call('callMountedAction')
        ->assertNoFileDownloaded();

    expect(Activity::where('event', 'pendaftaran.ekspor')->count())->toBe(0);
});

test('formula triggers after leading whitespace or line breaks are neutralised', function () {
    foreach ([' =cmd', "\n=cmd", "\t=cmd", "\r\n+1", '   @x', '  -2'] as $value) {
        expect(ApplicantRow::clean($value))->toBe("'".$value, json_encode($value));
    }
    foreach (['Ahmad', '0812', '-', 'a=b', ' Budi'] as $value) {
        expect(ApplicantRow::clean($value))->toBe($value === '-' ? "'-" : $value);
    }

    $applicant = exportRow(['nama_lengkap' => ' =cmd', 'ayah_nama' => "\n=cmd"]);
    DB::table('applicants')->where('id', $applicant->id)->update(['kelas' => "\t=cmd"]);
    $this->actingAs(exportActor('panitia_pendaftaran'));
    $sheet = sheetFromBinary(ApplicantsExport::fromQuery(Applicant::query())->toBinary());

    expect($sheet->getCell('B2')->getValue())->toBe("' =cmd")
        ->and($sheet->getCell('K2')->getValue())->toBe("'\n=cmd")
        ->and($sheet->getCell('J2')->getValue())->toBe("'\t=cmd")
        ->and($sheet->getCell('B2')->getDataType())->toBe(DataType::TYPE_STRING);
});

test('a failing audit aborts the export and leaves no temporary file', function () {
    exportRow(['nama_lengkap' => 'Data Pribadi']);
    $user = exportActor('panitia_pendaftaran');
    $this->actingAs($user);

    $tempDir = config('excel.temporary_files.local_path');
    $before = is_dir($tempDir) ? scandir($tempDir) : [];

    Schema::drop(config('activitylog.table_name', 'activity_log'));

    expect(fn () => Livewire::test(ListApplicants::class)->callAction('eksporExcel'))->toThrow(QueryException::class);
    expect(fn () => Livewire::test(ListApplicants::class)->callAction('eksporPdf'))->toThrow(QueryException::class);

    $after = is_dir($tempDir) ? scandir($tempDir) : [];
    expect(array_values(array_diff($after, $before)))->toBe([]);
});
