<?php

namespace Modules\Pendaftaran\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use JsonException;

/**
 * Draf formulir pendaftaran di sesi (hanya sesi ini, tidak pernah lewat URL), terenkripsi utuh
 * dengan Crypt::encryptString(json_encode(...)) sehingga NIK dan data keluarga tidak pernah tersimpan
 * polos di penyimpanan sesi. Berlaku config('pendaftaran.draft_ttl_minutes') menit sejak terakhir disimpan.
 *
 * Isi per langkah:
 * - valid[n]: data langkah n yang sudah lolos validasi (dipakai untuk gating dan kiriman akhir);
 * - old[n]:   isian mentah terakhir yang belum lolos (salah isi atau tombol Kembali), untuk mengisi ulang form.
 * Menyimpan old[n] menghapus valid[n]: yang tampil di layar selalu sama dengan yang akan dikirim.
 */
class RegistrationDraft
{
    public const SESSION_KEY = 'pendaftaran.draft';

    public const STEPS = 4;

    /** @var array{expires_at: int, valid: array<int, array<string, mixed>>, old: array<int, array<string, mixed>>}|null */
    private ?array $state = null;

    public function __construct(private readonly Session $session) {}

    public static function for(Request $request): self
    {
        return new self($request->session());
    }

    /** @return array<string, mixed>|null */
    public function valid(int $step): ?array
    {
        return $this->load()['valid'][$step] ?? null;
    }

    /** Nilai untuk mengisi ulang form langkah: isian mentah terakhir, atau data sah yang tersimpan. */
    public function formValues(int $step): array
    {
        $state = $this->load();

        return $state['old'][$step] ?? $state['valid'][$step] ?? [];
    }

    public function isComplete(int $step): bool
    {
        return $this->valid($step) !== null;
    }

    /** Langkah pertama yang belum sah (1-based) di antara langkah sebelum $step; null bila semuanya sah. */
    public function firstIncompleteBefore(int $step): ?int
    {
        for ($i = 1; $i < $step; $i++) {
            if (! $this->isComplete($i)) {
                return $i;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $data */
    public function putValid(int $step, array $data): void
    {
        $state = $this->load();
        $state['valid'][$step] = $data;
        unset($state['old'][$step]);
        $this->save($state);
    }

    /** @param  array<string, mixed>  $input */
    public function putOld(int $step, array $input): void
    {
        $state = $this->load();
        $state['old'][$step] = $input;
        unset($state['valid'][$step]);
        $this->save($state);
    }

    public function isEmpty(): bool
    {
        $state = $this->load();

        return $state['valid'] === [] && $state['old'] === [];
    }

    public function clear(): void
    {
        $this->state = null;
        $this->session->forget(self::SESSION_KEY);
    }

    /** @return array{expires_at: int, valid: array<int, array<string, mixed>>, old: array<int, array<string, mixed>>} */
    private function load(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }

        $empty = ['expires_at' => 0, 'valid' => [], 'old' => []];
        $payload = $this->session->get(self::SESSION_KEY);

        if (! is_string($payload)) {
            return $this->state = $empty;
        }

        try {
            $state = json_decode(Crypt::decryptString($payload), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $state = null;
        }

        if (! is_array($state) || ! is_int($state['expires_at'] ?? null) || $state['expires_at'] < now()->getTimestamp()) {
            $this->clear();

            return $this->state = $empty;
        }

        return $this->state = [
            'expires_at' => $state['expires_at'],
            'valid' => $this->steps($state['valid'] ?? []),
            'old' => $this->steps($state['old'] ?? []),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function steps(mixed $steps): array
    {
        $clean = [];

        foreach (is_array($steps) ? $steps : [] as $step => $data) {
            if (is_numeric($step) && (int) $step >= 1 && (int) $step <= self::STEPS && is_array($data)) {
                $clean[(int) $step] = $data;
            }
        }

        return $clean;
    }

    private function save(array $state): void
    {
        $state['expires_at'] = now()->addMinutes((int) config('pendaftaran.draft_ttl_minutes', 120))->getTimestamp();
        $this->state = $state;
        $this->session->put(self::SESSION_KEY, Crypt::encryptString(json_encode($state, JSON_THROW_ON_ERROR)));
    }
}
