<?php

namespace Modules\Core\Filament\Resources;

use App\Models\User;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Filament\Resources\UserResource\Pages\CreateUser;
use Modules\Core\Filament\Resources\UserResource\Pages\EditUser;
use Modules\Core\Filament\Resources\UserResource\Pages\ListUsers;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'akun admin';

    protected static ?string $pluralModelLabel = 'akun admin';

    protected static ?string $navigationLabel = 'Akun Admin';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label('Kata sandi')
                ->password()
                ->revealable()
                ->minLength(12)
                ->maxLength(255)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Minimal 12 karakter. Kosongkan saat mengubah bila tidak diganti.'),
            Select::make('roles')
                ->label('Role')
                ->relationship(
                    name: 'roles',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query): Builder => static::actorIsSuperadmin()
                        ? $query
                        : $query->where('name', '!=', User::SUPERADMIN_ROLE),
                )
                ->multiple()
                ->preload()
                ->rules(fn (?User $record): array => [
                    static::forbidSuperadminRoleForNonSuperadmin(),
                    static::keepLastSuperadminRole($record),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->sortable(),
                TextColumn::make('roles.name')->label('Role')->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (User $record): bool => $record->is(Auth::user())),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    private static function actorIsSuperadmin(): bool
    {
        return Auth::user()?->isSuperadmin() ?? false;
    }

    /** Pagar sisi server: payload yang dipalsukan tetap tidak bisa memberi role superadmin. */
    private static function forbidSuperadminRoleForNonSuperadmin(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (Auth::user()?->isSuperadmin()) {
                    return;
                }

                $superadminId = Role::where('name', User::SUPERADMIN_ROLE)->value('id');

                if ($superadminId !== null && in_array($superadminId, (array) $value, false)) {
                    $fail('Role superadmin hanya dapat diberikan oleh superadmin.');
                }
            }
        };
    }

    /** Role superadmin tidak boleh dicabut dari superadmin terakhir (berlaku juga untuk superadmin). */
    private static function keepLastSuperadminRole(?User $record): ValidationRule
    {
        return new class($record) implements ValidationRule
        {
            public function __construct(private readonly ?User $record) {}

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (! $this->record?->isLastSuperadmin()) {
                    return;
                }

                $superadminId = Role::where('name', User::SUPERADMIN_ROLE)->value('id');

                if (! in_array($superadminId, (array) $value, false)) {
                    $fail('Role superadmin tidak boleh dicabut dari superadmin terakhir.');
                }
            }
        };
    }
}
