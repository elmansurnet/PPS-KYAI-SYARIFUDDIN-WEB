<?php

namespace Modules\Landing\Filament\Resources;

use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Landing\Filament\Resources\LandingSectionResource\Pages\EditLandingSection;
use Modules\Landing\Filament\Resources\LandingSectionResource\Pages\ListLandingSections;
use Modules\Landing\Models\LandingSection;
use Modules\Landing\Support\VideoEmbed;

class LandingSectionResource extends Resource
{
    protected static ?string $model = LandingSection::class;

    protected static ?string $modelLabel = 'bagian beranda';

    protected static ?string $pluralModelLabel = 'bagian beranda';

    protected static ?string $navigationLabel = 'Beranda';

    protected static string|\UnitEnum|null $navigationGroup = 'Konten Website';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $slug = 'landing-sections';

    protected static ?string $recordTitleAttribute = 'key';

    /** Nama bagian untuk admin, sekaligus petunjuk isi (bukan data yang tampil di publik). */
    public const LABELS = [
        LandingSection::KEY_HERO => 'Bagian utama (hero)',
        LandingSection::KEY_VIDEO => 'Video profil',
        LandingSection::KEY_HISTORY => 'Sejarah pondok',
        LandingSection::KEY_ACTIVITIES => 'Kegiatan unggulan',
        LandingSection::KEY_LEARNING => 'Proses belajar',
        LandingSection::KEY_CTA => 'Ajakan mendaftar',
    ];

    private const HINTS = [
        LandingSection::KEY_HERO => 'Judul kosong memakai nama situs. Teks pengantar kosong memakai kalimat netral. Gambar latar opsional.',
        LandingSection::KEY_VIDEO => 'Tempel tautan video YouTube atau Vimeo. Bagian ini tidak tampil bila tautan kosong atau tidak valid.',
        LandingSection::KEY_HISTORY => 'Tuliskan sejarah berdirinya pondok di sini. Bagian ini tidak tampil sebelum teks atau gambar diisi.',
        LandingSection::KEY_ACTIVITIES => 'Tambahkan kegiatan unggulan pondok, masing-masing dengan judul, uraian singkat, dan foto (opsional).',
        LandingSection::KEY_LEARNING => 'Jelaskan tahapan belajar santri, masing-masing dengan judul, uraian singkat, dan foto (opsional).',
        LandingSection::KEY_CTA => 'Judul dan teks kosong memakai kalimat netral. Tombol selalu menuju halaman pendaftaran.',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $imageRules = fn (FileUpload $field): FileUpload => $field
            ->disk('public')
            ->visibility('public')
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(4096);

        return $schema->components([
            TextInput::make('key')
                ->label('Bagian')
                ->formatStateUsing(fn (?string $state): string => self::LABELS[$state] ?? (string) $state)
                ->helperText(fn (?LandingSection $record): ?string => self::HINTS[$record?->key] ?? null)
                ->disabled()
                ->dehydrated(false),
            Toggle::make('is_visible')
                ->label('Tampilkan di beranda')
                ->helperText('Bagian tanpa isi tidak ditampilkan walau dinyalakan.'),
            TextInput::make('title')
                ->label('Judul')
                ->maxLength(150),
            Textarea::make('body')
                ->label('Teks')
                ->rows(6)
                ->maxLength(5000)
                ->hidden(fn (?LandingSection $record): bool => $record?->key === LandingSection::KEY_VIDEO),
            $imageRules(FileUpload::make('media_path')
                ->label(fn (?LandingSection $record): string => $record?->key === LandingSection::KEY_HERO ? 'Gambar latar' : 'Gambar')
                ->directory('landing')
                ->helperText('JPG, PNG, atau WebP, maksimal 4 MB.')
                ->hidden(fn (?LandingSection $record): bool => in_array($record?->key, [
                    LandingSection::KEY_VIDEO, LandingSection::KEY_ACTIVITIES, LandingSection::KEY_LEARNING, LandingSection::KEY_CTA,
                ], true))),
            TextInput::make('video_url')
                ->label('Tautan video (YouTube atau Vimeo)')
                ->url()
                ->maxLength(500)
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && ! VideoEmbed::isValid($value)) {
                        $fail('Tautan harus berupa video YouTube atau Vimeo (https).');
                    }
                }])
                ->visible(fn (?LandingSection $record): bool => $record?->key === LandingSection::KEY_VIDEO),
            Repeater::make('items')
                ->label('Daftar butir')
                ->schema([
                    TextInput::make('title')->label('Judul')->required()->maxLength(120),
                    Textarea::make('body')->label('Uraian singkat')->rows(3)->maxLength(600),
                    $imageRules(FileUpload::make('image_path')
                        ->label('Foto (opsional)')
                        ->directory('landing/items')
                        ->helperText('JPG, PNG, atau WebP, maksimal 4 MB.')),
                ])
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->collapsible()
                ->reorderable()
                ->maxItems(12)
                ->defaultItems(0)
                ->addActionLabel('Tambah butir')
                ->visible(fn (?LandingSection $record): bool => (bool) $record?->usesItems()),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('key')
                    ->label('Bagian')
                    ->formatStateUsing(fn (string $state): string => self::LABELS[$state] ?? $state)
                    ->description(fn (LandingSection $record): string => $record->key),
                TextColumn::make('title')->label('Judul')->placeholder('(otomatis)')->limit(60),
                IconColumn::make('is_visible')->label('Tampil')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandingSections::route('/'),
            'edit' => EditLandingSection::route('/{record}/edit'),
        ];
    }
}
