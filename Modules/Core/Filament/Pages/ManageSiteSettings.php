<?php

namespace Modules\Core\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Settings\SiteSettings;
use Modules\Core\Support\WhatsAppLink;

class ManageSiteSettings extends Page
{
    public const PERMISSION = 'Manage:SiteSettings';

    protected static ?string $title = 'Pengaturan Situs';

    protected static ?string $navigationLabel = 'Pengaturan Situs';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can(self::PERMISSION) ?? false;
    }

    public function mount(): void
    {
        $settings = app(SiteSettings::class);

        $this->form->fill([
            'site_name' => $settings->site_name,
            'logo_path' => $settings->logo_path,
            'address' => $settings->address,
            'contact_email' => $settings->contact_email,
            'social_links' => $settings->social_links,
            'whatsapp_number' => $settings->whatsapp_number,
            'whatsapp_message_template' => $settings->whatsapp_message_template,
            'fee_pdf_path' => $settings->fee_pdf_path,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('site_name')->label('Nama situs')->required()->maxLength(120),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->helperText('JPG, PNG, atau WebP, maksimal 2 MB.')
                    ->disk('public')
                    ->directory('site')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048),
                Textarea::make('address')->label('Alamat')->rows(3)->maxLength(500),
                TextInput::make('contact_email')->label('Email kontak')->email()->maxLength(255),
                Repeater::make('social_links')
                    ->label('Tautan media sosial')
                    ->schema([
                        TextInput::make('platform')->label('Platform')->required()->maxLength(50),
                        TextInput::make('url')->label('URL')->url()->regex('/^https?:\/\//i')->required()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0),
                TextInput::make('whatsapp_number')
                    ->label('Nomor WhatsApp')
                    ->helperText('Boleh diawali 0, +62, atau 62; disimpan dalam format 62...')
                    ->tel()
                    ->maxLength(30),
                Textarea::make('whatsapp_message_template')
                    ->label('Templat pesan WhatsApp')
                    ->helperText('Gunakan {nama} dan {nik_akhir} sebagai pengganti.')
                    ->rows(3)
                    ->required()
                    ->maxLength(1000),
                FileUpload::make('fee_pdf_path')
                    ->label('PDF rincian biaya')
                    ->helperText('PDF, maksimal 10 MB.')
                    ->disk('public')
                    ->directory('site')
                    ->visibility('public')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $settings = app(SiteSettings::class);

        $oldFiles = array_filter([$settings->logo_path, $settings->fee_pdf_path]);

        $settings->site_name = $data['site_name'];
        $settings->logo_path = $data['logo_path'] ?: null;
        $settings->address = $data['address'] ?: null;
        $settings->contact_email = $data['contact_email'] ?: null;
        $settings->social_links = array_values($data['social_links'] ?? []);
        $settings->whatsapp_number = WhatsAppLink::normalizeNumber((string) ($data['whatsapp_number'] ?? ''));
        $settings->whatsapp_message_template = $data['whatsapp_message_template'];
        $settings->fee_pdf_path = $data['fee_pdf_path'] ?: null;
        $settings->save();

        // Hapus berkas lama yang sudah diganti atau dilepas.
        $kept = array_filter([$settings->logo_path, $settings->fee_pdf_path]);
        foreach (array_diff($oldFiles, $kept) as $path) {
            Storage::disk('public')->delete($path);
        }

        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }
}
