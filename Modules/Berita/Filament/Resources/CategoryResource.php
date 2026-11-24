<?php

namespace Modules\Berita\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Berita\Filament\Resources\CategoryResource\Pages\CreateCategory;
use Modules\Berita\Filament\Resources\CategoryResource\Pages\EditCategory;
use Modules\Berita\Filament\Resources\CategoryResource\Pages\ListCategories;
use Modules\Berita\Models\Category;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $modelLabel = 'kategori';

    protected static ?string $pluralModelLabel = 'kategori';

    protected static ?string $navigationLabel = 'Kategori';

    protected static string|\UnitEnum|null $navigationGroup = 'Berita';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(100),
            TextInput::make('slug')
                ->label('Alamat (slug)')
                ->maxLength(120)
                ->unique(ignoreRecord: true)
                ->helperText('Kosongkan agar dibuat otomatis dari nama.'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug'),
                TextColumn::make('posts_count')->label('Jumlah berita')->counts('posts'),
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
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
