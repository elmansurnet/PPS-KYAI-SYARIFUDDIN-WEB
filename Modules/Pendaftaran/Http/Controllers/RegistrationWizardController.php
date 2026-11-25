<?php

namespace Modules\Pendaftaran\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Settings\SiteSettings;
use Modules\Core\Support\WhatsAppLink;
use Modules\Pendaftaran\Exceptions\DuplicateNikException;
use Modules\Pendaftaran\Exceptions\RegistrationFailedException;
use Modules\Pendaftaran\Http\Requests\AkademikRequest;
use Modules\Pendaftaran\Http\Requests\KirimRequest;
use Modules\Pendaftaran\Http\Requests\OrangTuaRequest;
use Modules\Pendaftaran\Http\Requests\SantriRequest;
use Modules\Pendaftaran\Http\Requests\WizardStepRequest;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Models\PendidikanTerakhir;
use Modules\Pendaftaran\Services\RegisterApplicant;
use Modules\Pendaftaran\Support\RegistrationDraft;
use Modules\Pendaftaran\Support\WizardSteps;
use Throwable;

/**
 * Wizard pendaftaran 4 langkah, server-rendered tanpa JavaScript (CSP publik ketat).
 * Data langkah disimpan di draf sesi terenkripsi (RegistrationDraft) dan baru masuk database saat
 * kiriman akhir, setelah seluruh draf divalidasi ulang.
 */
class RegistrationWizardController
{
    public const DUPLICATE_MESSAGE = 'Data dengan NIK ini sudah kami terima. Silakan hubungi panitia melalui WhatsApp bila perlu bantuan.';

    public const FAILED_MESSAGE = 'Maaf, pendaftaran belum berhasil disimpan. Silakan coba lagi beberapa saat lagi.';

    private const DONE_KEY = 'pendaftaran.selesai';

    /** @var array<int, class-string<WizardStepRequest>> */
    private const REQUESTS = [1 => SantriRequest::class, 2 => AkademikRequest::class, 3 => OrangTuaRequest::class];

    public function start(Request $request): View|RedirectResponse
    {
        return $this->renderStep($request, 1);
    }

    public function show(Request $request, string $langkah): View|RedirectResponse
    {
        $step = (int) $langkah;

        return $step === 1 ? redirect()->route('daftar') : $this->renderStep($request, $step);
    }

    public function store(Request $request, string $langkah): RedirectResponse
    {
        $step = (int) $langkah;
        $draft = RegistrationDraft::for($request);

        if ($redirect = $this->gate($draft, $step, 303)) {
            return $redirect;
        }

        $class = self::REQUESTS[$step];

        if ($step > 1 && $request->input('aksi') === 'kembali') {
            $draft->putOld($step, $class::oldInput($request->all()));

            return redirect()->to(WizardSteps::url($step - 1), 303);
        }

        // Resolusi FormRequest menjalankan validasi; gagal -> HttpResponseException (redirect ke langkah ini).
        $form = app($class);
        $draft->putValid($step, $form->validated());

        return redirect()->to(WizardSteps::url($step + 1), 303);
    }

    public function submit(Request $request, RegisterApplicant $register): RedirectResponse
    {
        $draft = RegistrationDraft::for($request);

        // Klik ganda setelah berhasil: draf sudah dibersihkan, tampilkan lagi halaman terima kasih.
        if ($draft->firstIncompleteBefore(4) !== null && $request->session()->has(self::DONE_KEY)) {
            $request->session()->reflash();

            return redirect()->route('daftar.selesai', [], 303);
        }

        if ($redirect = $this->gate($draft, 4, 303)) {
            return $redirect;
        }

        // Jangan percaya langkah sebelumnya: validasi ulang seluruh draf dengan aturan yang sama.
        $valid = [];
        foreach (self::REQUESTS as $step => $class) {
            $validator = $class::validatorFor($draft->valid($step));

            if ($validator->fails()) {
                $draft->putOld($step, $class::oldInput($draft->valid($step)));

                return redirect()->to(WizardSteps::url($step), 303)->withErrors($validator);
            }

            $valid[$step] = $validator->validated();
        }

        $form = app(KirimRequest::class);

        $data = SantriRequest::toAttributes($valid[1])
            + AkademikRequest::toAttributes($valid[2])
            + OrangTuaRequest::toAttributes($valid[3], $valid[1]);

        try {
            $applicant = $register->handle($data, $form->file('kk'));
        } catch (DuplicateNikException) {
            return redirect()->to(WizardSteps::url(4), 303)->with('pendaftaran.galat', self::DUPLICATE_MESSAGE);
        } catch (RegistrationFailedException) {
            return redirect()->to(WizardSteps::url(4), 303)->with('pendaftaran.galat', self::FAILED_MESSAGE);
        }

        $draft->clear();
        $request->session()->flash(self::DONE_KEY, Crypt::encryptString(json_encode([
            'nama' => $applicant->nama_lengkap,
            'nik_akhir' => $applicant->nikLast4(),
        ], JSON_THROW_ON_ERROR)));

        return redirect()->route('daftar.selesai', [], 303);
    }

    public function done(Request $request, SiteSettings $settings): View|RedirectResponse
    {
        $done = $this->doneData($request);

        if ($done === null) {
            return redirect()->route('daftar');
        }

        return view('pendaftaran::daftar.selesai', [
            'nama' => $done['nama'],
            'whatsappUrl' => $this->whatsappUrl($settings, $done['nama'], $done['nik_akhir']),
        ]);
    }

    private function renderStep(Request $request, int $step): View|RedirectResponse
    {
        $draft = RegistrationDraft::for($request);

        if ($redirect = $this->gate($draft, $step, 302)) {
            return $redirect;
        }

        $data = [
            'step' => $step,
            'steps' => WizardSteps::all(),
            'completed' => array_values(array_filter(range(1, WizardSteps::TOTAL), fn (int $i) => $draft->isComplete($i))),
            'values' => $draft->formValues($step),
        ];

        return view("pendaftaran::daftar.langkah-{$step}", $data + match ($step) {
            1 => ['feePdfUrl' => $this->feePdfUrl()],
            2 => [
                'pendidikan' => PendidikanTerakhir::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
                'jenjangs' => Jenjang::query()->where('is_active', true)
                    ->with(['kelasOptions' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
                    ->orderBy('sort_order')->orderBy('name')->get(),
            ],
            3 => [],
            4 => [
                'review' => $this->review($draft),
                'turnstileSiteKey' => KirimRequest::turnstileAvailable() ? (string) config('services.turnstile.site_key') : null,
                'kkMaxKb' => (int) config('pendaftaran.kk_max_kb', 2048),
            ],
        });
    }

    /** Redirect ke langkah pertama yang belum lengkap bila $step belum boleh dibuka. */
    private function gate(RegistrationDraft $draft, int $step, int $status): ?RedirectResponse
    {
        $missing = $draft->firstIncompleteBefore($step);

        if ($missing === null) {
            return null;
        }

        return redirect()->to(WizardSteps::url($missing), $status)->with('pendaftaran.info', $draft->isEmpty()
            ? 'Silakan mulai dari langkah 1. Data yang belum dikirim tersimpan paling lama '.(int) config('pendaftaran.draft_ttl_minutes', 120).' menit.'
            : 'Lengkapi langkah ini terlebih dahulu.');
    }

    /** @return array<string, mixed> */
    private function review(RegistrationDraft $draft): array
    {
        $akademik = $draft->valid(2) ?? [];

        return [
            1 => $draft->valid(1) ?? [],
            2 => $akademik + [
                'pendidikan_nama' => PendidikanTerakhir::find($akademik['pendidikan_terakhir_id'] ?? 0)?->name,
                'jenjang_nama' => Jenjang::find($akademik['jenjang_id'] ?? 0)?->name,
            ],
            3 => $draft->valid(3) ?? [],
        ];
    }

    private function feePdfUrl(): ?string
    {
        $path = app(SiteSettings::class)->fee_pdf_path;

        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    /** @return array{nama: string, nik_akhir: string}|null */
    private function doneData(Request $request): ?array
    {
        $payload = $request->session()->get(self::DONE_KEY);

        try {
            $data = is_string($payload) ? json_decode(Crypt::decryptString($payload), true) : null;
        } catch (Throwable) {
            $data = null;
        }

        return is_array($data) && is_string($data['nama'] ?? null) && is_string($data['nik_akhir'] ?? null) ? $data : null;
    }

    /** Tautan WhatsApp panitia, atau null bila nomor belum diatur / tidak sah. Hanya https://wa.me/... */
    private function whatsappUrl(SiteSettings $settings, string $nama, string $nikAkhir): ?string
    {
        $number = WhatsAppLink::normalizeNumber((string) $settings->whatsapp_number);

        if (! preg_match('/^62\d{8,13}$/', $number)) {
            return null;
        }

        $message = strtr((string) $settings->whatsapp_message_template, ['{nama}' => $nama, '{nik_akhir}' => $nikAkhir]);
        $url = WhatsAppLink::make($number, $message);

        return str_starts_with($url, 'https://wa.me/'.$number.'?text=') ? $url : null;
    }
}
