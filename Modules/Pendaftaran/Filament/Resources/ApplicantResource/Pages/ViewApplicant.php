<?php

namespace Modules\Pendaftaran\Filament\Resources\ApplicantResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;
use Modules\Core\Support\AuditLog;
use Modules\Pendaftaran\Filament\Resources\ApplicantResource;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Services\ApplicantStatus;
use Modules\Pendaftaran\Support\NikDisplay;

/**
 * Detail pendaftar. Setiap pembukaan halaman dicatat sekali (di mount, bukan di render ulang Livewire)
 * sebagai "pendaftaran.dilihat" berisi subjek saja.
 *
 * @property Applicant $record
 */
class ViewApplicant extends ViewRecord
{
    protected static string $resource = ApplicantResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        AuditLog::record('pendaftaran.dilihat', $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tampilkanNik')
                ->label('Tampilkan NIK')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->authorize('revealNik')
                ->modalHeading('NIK lengkap')
                ->modalDescription('Pembukaan NIK ini tercatat di log aktivitas. Jangan dibagikan di luar keperluan pendaftaran.')
                ->mountUsing(fn (Applicant $record) => AuditLog::record('pendaftaran.nik_dilihat', $record))
                ->modalContent(fn (Applicant $record): HtmlString => new HtmlString(
                    '<p style="font-family: ui-monospace, monospace; font-size: 1.25rem; letter-spacing: .08em; user-select: all;">'
                    .e(NikDisplay::full($record))
                    .'</p>'
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),
            Action::make('unduhKk')
                ->label('Unduh KK')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->authorize('downloadKk')
                ->visible(fn (Applicant $record): bool => filled($record->kk_path))
                ->url(fn (Applicant $record): string => route('pendaftar.kk', $record)),
            Action::make('ubahStatus')
                ->label('Ubah status')
                ->icon('heroicon-o-check-circle')
                ->authorize('update')
                ->modalHeading('Ubah status dan catatan')
                ->fillForm(fn (Applicant $record): array => [
                    ...collect(ApplicantStatus::FLAGS)->mapWithKeys(fn ($label, $flag) => [$flag => (bool) $record->getAttribute($flag)])->all(),
                    'catatan_panitia' => $record->catatan_panitia,
                ])
                ->schema([
                    ...collect(ApplicantStatus::FLAGS)->map(fn (string $label, string $flag) => Toggle::make($flag)->label($label))->values()->all(),
                    Textarea::make('catatan_panitia')
                        ->label('Catatan panitia (internal)')
                        ->rows(4)
                        ->maxLength(ApplicantStatus::NOTE_MAX)
                        ->regex('/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*$/u')
                        ->validationMessages(['regex' => 'Catatan tidak boleh berisi karakter kontrol.']),
                ])
                ->action(function (Applicant $record, array $data): void {
                    foreach (array_keys(ApplicantStatus::FLAGS) as $flag) {
                        ApplicantStatus::set($record, $flag, (bool) ($data[$flag] ?? false));
                    }
                    ApplicantStatus::setNote($record, $data['catatan_panitia'] ?? null);

                    Notification::make()->success()->title('Status disimpan')->send();
                }),
            DeleteAction::make(),
        ];
    }
}
