<?php

namespace Modules\Pendaftaran\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use Modules\Pendaftaran\Filament\Resources\KelasOptionResource\Pages\CreateKelasOption;
use Modules\Pendaftaran\Filament\Resources\KelasOptionResource\Pages\EditKelasOption;
use Modules\Pendaftaran\Filament\Resources\KelasOptionResource\Pages\ListKelasOptions;
use Modules\Pendaftaran\Models\KelasOption;

class KelasOptionResource extends Resource
{
    protected static ?string $model = KelasOption::class;

    protected static ?string $modelLabel = 'pilihan kelas';

    protected static ?string $pluralModelLabel = 'pilihan kelas';

    protected static ?string $navigationLabel = 'Pilihan Kelas';

    protected static string|\UnitEnum|null $navigationGroup = 'Data Master Pendaftaran';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('jenjang_id')
                ->label('Jenjang')
                ->relationship('jenjang', 'name')
                ->required()
                ->preload(),
            TextInput::make('name')
                ->label('Nama kelas')
                ->required()
                ->maxLength(100)
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, callable $get) => $rule->where('jenjang_id', $get('jenjang_id')),
                ),
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
                TextColumn::make('jenjang.name')->label('Jenjang')->sortable(),
                TextColumn::make('name')->label('Nama kelas')->searchable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('jenjang_id')->label('Jenjang')->relationship('jenjang', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKelasOptions::route('/'),
            'create' => CreateKelasOption::route('/create'),
            'edit' => EditKelasOption::route('/{record}/edit'),
        ];
    }
}
