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
use Modules\Pendaftaran\Filament\Resources\PendidikanTerakhirResource\Pages\CreatePendidikanTerakhir;
use Modules\Pendaftaran\Filament\Resources\PendidikanTerakhirResource\Pages\EditPendidikanTerakhir;
use Modules\Pendaftaran\Filament\Resources\PendidikanTerakhirResource\Pages\ListPendidikanTerakhirs;
use Modules\Pendaftaran\Models\PendidikanTerakhir;

class PendidikanTerakhirResource extends Resource
{
    protected static ?string $model = PendidikanTerakhir::class;

    protected static ?string $modelLabel = 'pendidikan terakhir';

    protected static ?string $pluralModelLabel = 'pendidikan terakhir';

    protected static ?string $navigationLabel = 'Pendidikan Terakhir';

    protected static string|\UnitEnum|null $navigationGroup = 'Data Master Pendaftaran';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 30;

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
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                // Yang sudah dipakai pendaftar tidak bisa dihapus (cukup nonaktifkan).
                DeleteAction::make()->hidden(fn (PendidikanTerakhir $record): bool => $record->applicants()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendidikanTerakhirs::route('/'),
            'create' => CreatePendidikanTerakhir::route('/create'),
            'edit' => EditPendidikanTerakhir::route('/{record}/edit'),
        ];
    }
}
