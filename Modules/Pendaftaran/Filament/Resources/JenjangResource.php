<?php

namespace Modules\Pendaftaran\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Pendaftaran\Filament\Resources\JenjangResource\Pages\CreateJenjang;
use Modules\Pendaftaran\Filament\Resources\JenjangResource\Pages\EditJenjang;
use Modules\Pendaftaran\Filament\Resources\JenjangResource\Pages\ListJenjangs;
use Modules\Pendaftaran\Models\Jenjang;

class JenjangResource extends Resource
{
    protected static ?string $model = Jenjang::class;

    protected static ?string $modelLabel = 'jenjang';

    protected static ?string $pluralModelLabel = 'jenjang';

    protected static ?string $navigationLabel = 'Jenjang';

    protected static string|\UnitEnum|null $navigationGroup = 'Data Master Pendaftaran';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->minValue(0)->default(0),
            Toggle::make('is_active')->label('Aktif (tampil di formulir)')->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('kelas_options_count')->label('Jumlah kelas')->counts('kelasOptions'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                // Jenjang yang sudah dipakai kelas atau pendaftar tidak bisa dihapus (cukup nonaktifkan).
                DeleteAction::make()->hidden(
                    fn (Jenjang $record): bool => $record->kelasOptions()->exists() || $record->applicants()->exists()
                ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJenjangs::route('/'),
            'create' => CreateJenjang::route('/create'),
            'edit' => EditJenjang::route('/{record}/edit'),
        ];
    }
}
