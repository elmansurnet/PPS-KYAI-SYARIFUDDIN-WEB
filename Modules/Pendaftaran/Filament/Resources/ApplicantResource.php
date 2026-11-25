<?php

namespace Modules\Pendaftaran\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Pendaftaran\Exports\ApplicantRow;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource\ApplicantExportActions;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource\Pages\ListApplicants;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource\Pages\ViewApplicant;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Models\KelasOption;
use Modules\Pendaftaran\Services\ApplicantStatus;
use Modules\Pendaftaran\Support\ApplicantSearch;
use Modules\Pendaftaran\Support\NikDisplay;

/**
 * Data pendaftar untuk panitia/admin. Tanpa halaman buat/edit: pendaftar hanya masuk dari formulir
 * publik; panitia hanya mengubah tiga bendera status dan catatan (izin update).
 *
 * Privasi: pencarian global Filament dimatikan; NIK selalu tersamar di tabel dan halaman detail
 * (NIK lengkap lewat aksi "Tampilkan NIK" dengan izin revealNik, dicatat). Lihat README modul.
 */
class ApplicantResource extends Resource
{
    protected static ?string $model = Applicant::class;

    protected static ?string $slug = 'pendaftar';

    protected static ?string $modelLabel = 'pendaftar';

    protected static ?string $pluralModelLabel = 'pendaftar';

    protected static ?string $navigationLabel = 'Pendaftar';

    protected static string|\UnitEnum|null $navigationGroup = 'Penerimaan Santri Baru';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $recordTitleAttribute = 'nama_lengkap';

    protected static bool $isGloballySearchable = false;

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->searchable()
            ->searchUsing(fn (Builder $query, string $search) => ApplicantSearch::apply($query, $search))
            ->searchPlaceholder('Nama, orang tua, HP, desa... NIK: ketik 16 digit lengkap')
            ->searchDebounce('600ms')
            ->columns([
                TextColumn::make('nama_lengkap')
                    ->label('Nama')
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (Applicant $record): ?string => $record->kabupaten),
                TextColumn::make('nik_tersamar')
                    ->label('NIK')
                    ->state(fn (Applicant $record): string => self::maskedNik($record))
                    ->fontFamily('mono'),
                TextColumn::make('jenjang.name')->label('Jenjang')->placeholder('-'),
                TextColumn::make('kelas')->label('Kelas')->placeholder('-'),
                TextColumn::make('kabupaten')->label('Kabupaten')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ayah_hp')->label('HP ayah')->placeholder('-')->toggleable(),
                TextColumn::make('ibu_hp')->label('HP ibu')->placeholder('-')->toggleable(),
                TextColumn::make('created_at')
                    ->label('Mendaftar')
                    ->since()
                    ->dateTimeTooltip('d-m-Y H:i')
                    ->sortable(),
                ...array_map(fn (string $flag, string $label) => ToggleColumn::make($flag)
                    ->label(match ($flag) {
                        'sudah_dihubungi' => 'Dihubungi',
                        'sudah_bayar' => 'Bayar',
                        default => 'Masuk',
                    })
                    ->tooltip($label)
                    ->authorize('update')
                    ->updateStateUsing(function (Applicant $record, mixed $state) use ($flag): bool {
                        ApplicantStatus::set($record, $flag, (bool) $state);

                        return (bool) $state;
                    }), array_keys(ApplicantStatus::FLAGS), ApplicantStatus::FLAGS),
            ])
            ->filters([
                ...array_map(fn (string $flag, string $label) => TernaryFilter::make($flag)
                    ->label($label)
                    ->placeholder('Semua')
                    ->trueLabel('Ya')
                    ->falseLabel('Belum'), array_keys(ApplicantStatus::FLAGS), ApplicantStatus::FLAGS),
                SelectFilter::make('jenjang_id')
                    ->label('Jenjang')
                    ->relationship('jenjang', 'name'),
                SelectFilter::make('kelas')
                    ->label('Kelas')
                    ->options(fn (): array => self::kelasOptions()),
                TernaryFilter::make('pernah_mondok')
                    ->label('Pernah mondok')
                    ->placeholder('Semua')
                    ->trueLabel('Pernah')
                    ->falseLabel('Belum pernah'),
                Filter::make('created_at')
                    ->label('Tanggal daftar')
                    ->schema([
                        DatePicker::make('dari')->label('Daftar dari'),
                        DatePicker::make('sampai')->label('Daftar sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari'] ?? null) {
                            $indicators[] = Indicator::make('Daftar dari '.Carbon::parse($data['dari'])->format('d-m-Y'))->removeField('dari');
                        }
                        if ($data['sampai'] ?? null) {
                            $indicators[] = Indicator::make('Daftar sampai '.Carbon::parse($data['sampai'])->format('d-m-Y'))->removeField('sampai');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ApplicantExportActions::excelBulk(),
                    ApplicantExportActions::pdfBulk(),
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $yesNo = fn (?bool $state): string => $state ? 'Ya' : 'Tidak';

        return $schema->components([
            Section::make('Data Calon Santri')
                ->columns(2)
                ->schema([
                    TextEntry::make('nama_lengkap')->label('Nama lengkap'),
                    TextEntry::make('nik_tersamar')
                        ->label('NIK')
                        ->state(fn (Applicant $record): string => self::maskedNik($record))
                        ->fontFamily('mono')
                        ->helperText('Tersamar. Gunakan "Tampilkan NIK" bila perlu (tercatat di log).'),
                    TextEntry::make('tempat_lahir')->label('Tempat lahir'),
                    TextEntry::make('tanggal_lahir')->label('Tanggal lahir')->date('d-m-Y'),
                    TextEntry::make('alamat')
                        ->label('Alamat')
                        ->state(fn (Applicant $record): string => ApplicantRow::address($record, neutralise: false))
                        ->columnSpanFull(),
                ]),
            Section::make('Akademik')
                ->columns(2)
                ->schema([
                    TextEntry::make('pernah_mondok')->label('Pernah mondok')->formatStateUsing(fn ($state) => $yesNo((bool) $state)),
                    TextEntry::make('pondok_sebelumnya')->label('Pondok sebelumnya')->placeholder('-')
                        ->visible(fn (Applicant $record): bool => $record->pernah_mondok),
                    TextEntry::make('pendidikan_pondok_sebelumnya')->label('Pendidikan di pondok sebelumnya')->placeholder('-')
                        ->visible(fn (Applicant $record): bool => $record->pernah_mondok),
                    TextEntry::make('pendidikanTerakhir.name')->label('Pendidikan terakhir')->placeholder('-'),
                    TextEntry::make('jenjang.name')->label('Jenjang dituju')->placeholder('-'),
                    TextEntry::make('kelas')->label('Kelas')->placeholder('-'),
                ]),
            Section::make('Data Orang Tua')
                ->columns(2)
                ->schema([
                    TextEntry::make('ayah_nama')->label('Nama ayah')->placeholder('-'),
                    TextEntry::make('ibu_nama')->label('Nama ibu')->placeholder('-'),
                    TextEntry::make('ayah_pekerjaan')->label('Pekerjaan ayah')->placeholder('-'),
                    TextEntry::make('ibu_pekerjaan')->label('Pekerjaan ibu')->placeholder('-'),
                    TextEntry::make('ayah_hp')->label('HP ayah')->placeholder('-')->copyable(),
                    TextEntry::make('ibu_hp')->label('HP ibu')->placeholder('-')->copyable(),
                    TextEntry::make('alamat_ortu')
                        ->label('Alamat orang tua')
                        ->state(fn (Applicant $record): string => ApplicantRow::address($record, 'ortu_', neutralise: false))
                        ->columnSpanFull(),
                    TextEntry::make('orang_tua_meninggal')
                        ->label('Kedua orang tua sudah meninggal')
                        ->formatStateUsing(fn ($state) => $yesNo((bool) $state)),
                ]),
            Section::make('Data Wali')
                ->columns(2)
                ->visible(fn (Applicant $record): bool => $record->orang_tua_meninggal)
                ->schema([
                    TextEntry::make('wali_nama')->label('Nama wali')->placeholder('-'),
                    TextEntry::make('wali_pekerjaan')->label('Pekerjaan wali')->placeholder('-'),
                    TextEntry::make('wali_hp')->label('HP wali')->placeholder('-')->copyable(),
                    TextEntry::make('alamat_wali')
                        ->label('Alamat wali')
                        ->state(fn (Applicant $record): string => ApplicantRow::address($record, 'wali_', neutralise: false))
                        ->columnSpanFull(),
                ]),
            Section::make('Status')
                ->columns(3)
                ->schema([
                    ...array_map(fn (string $flag, string $label) => IconEntry::make($flag)->label($label)->boolean(),
                        array_keys(ApplicantStatus::FLAGS), ApplicantStatus::FLAGS),
                    TextEntry::make('catatan_panitia')->label('Catatan panitia')->placeholder('Belum ada catatan')->columnSpanFull(),
                    TextEntry::make('created_at')->label('Tanggal daftar')->dateTime('d-m-Y H:i'),
                ]),
            Section::make('Dokumen')
                ->schema([
                    TextEntry::make('kk_status')
                        ->label('Kartu keluarga')
                        ->state(fn (Applicant $record): string => filled($record->kk_path)
                            ? 'Tersedia (terenkripsi). Gunakan tombol "Unduh KK" di atas.'
                            : 'Belum ada file'),
                ]),
        ])->columns(1);
    }

    public static function maskedNik(Applicant $record): string
    {
        $masked = NikDisplay::masked($record);

        return $masked === NikDisplay::UNREADABLE ? '— '.NikDisplay::UNREADABLE : $masked;
    }

    /** @return array<string, string> label kelas aktif di data master + kelas yang sudah dipakai pendaftar. */
    private static function kelasOptions(): array
    {
        $labels = KelasOption::query()->orderBy('sort_order')->pluck('name')
            ->merge(Applicant::query()->whereNotNull('kelas')->distinct()->orderBy('kelas')->pluck('kelas'))
            ->unique()
            ->values();

        return $labels->combine($labels)->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApplicants::route('/'),
            'view' => ViewApplicant::route('/{record}'),
        ];
    }
}
