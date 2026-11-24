<?php

namespace Modules\Berita\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Berita\Filament\Resources\PostResource\Pages\CreatePost;
use Modules\Berita\Filament\Resources\PostResource\Pages\EditPost;
use Modules\Berita\Filament\Resources\PostResource\Pages\ListPosts;
use Modules\Berita\Models\Post;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $modelLabel = 'berita';

    protected static ?string $pluralModelLabel = 'berita';

    protected static ?string $navigationLabel = 'Berita';

    protected static string|\UnitEnum|null $navigationGroup = 'Berita';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(200),
            TextInput::make('slug')
                ->label('Alamat (slug)')
                ->maxLength(200)
                ->helperText('Kosongkan agar dibuat otomatis dari judul. Tidak berubah saat judul diubah.'),
            FileUpload::make('cover_path')
                ->label('Gambar sampul')
                ->disk('public')
                ->directory('berita')
                ->visibility('public')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(4096)
                ->helperText('JPG, PNG, atau WebP, maksimal 4 MB. Disarankan rasio 16:9.'),
            RichEditor::make('body')
                ->label('Isi berita')
                ->required()
                // Lampiran/gambar di dalam isi dimatikan: gambar hanya lewat sampul. Penyunting
                // juga tidak menawarkan perataan/warna karena style dibuang (CSP).
                ->fileAttachments(false)
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3'],
                    ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                    ['table'],
                    ['undo', 'redo'],
                ])
                ->columnSpanFull(),
            Select::make('category_id')
                ->label('Kategori')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->createOptionForm([
                    TextInput::make('name')->label('Nama kategori')->required()->maxLength(100),
                ]),
            Select::make('status')
                ->label('Status')
                ->options(Post::STATUSES)
                ->default(Post::STATUS_DRAFT)
                ->required()
                ->native(false),
            DateTimePicker::make('published_at')
                ->label('Tanggal terbit')
                ->helperText('Kosongkan untuk terbit sekarang. Isi tanggal mendatang untuk menjadwalkan.'),
            TextInput::make('author_name')
                ->label('Penulis')
                ->formatStateUsing(fn (?Post $record): ?string => $record?->author?->name)
                ->disabled()
                ->dehydrated(false)
                ->visible(fn (?Post $record): bool => (bool) $record?->exists),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->limit(60)->wrap(),
                TextColumn::make('category.name')->label('Kategori')->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Post::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === Post::STATUS_PUBLISHED ? 'success' : 'gray'),
                TextColumn::make('published_at')->label('Terbit')->dateTime('j M Y H:i')->sortable()->placeholder('-'),
                TextColumn::make('author.name')->label('Penulis')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Post::STATUSES),
                SelectFilter::make('category_id')->label('Kategori')->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
