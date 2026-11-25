<?php

namespace Modules\Core\Support;

/**
 * Daftar tautan navigasi publik (navbar dan footer).
 *
 * Modul menambah atau mengganti item lewat kuncinya di boot() provider-nya, misalnya
 * app(Navigation::class)->add('berita', 'Berita', '/berita', 20). Dengan begitu navbar
 * milik Core tidak perlu mengenal modul mana pun. Core hanya mendaftarkan 'beranda'; item lain
 * hanya ada bila modulnya aktif (Berita: 'berita', Pendaftaran: 'pendaftaran').
 *
 * Modul lain memakai path() / has() untuk menautkan halaman modul lain tanpa mengimpor kelasnya,
 * mis. Landing menampilkan tombol daftar hanya bila 'pendaftaran' terdaftar.
 */
class Navigation
{
    /** @var array<string, array{key: string, label: string, path: string, order: int, emphasis: bool}> */
    private array $items = [];

    /**
     * @param  string  $path  path relatif situs (mis. "/daftar"), diubah ke URL penuh saat dirender
     * @param  bool  $emphasis  tampil sebagai tombol utama di navbar desktop
     */
    public function add(string $key, string $label, string $path, int $order = 100, bool $emphasis = false): static
    {
        $this->items[$key] = compact('key', 'label', 'path', 'order', 'emphasis');

        return $this;
    }

    public function remove(string $key): static
    {
        unset($this->items[$key]);

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->items[$key]);
    }

    /** Path item (mis. "/daftar"), atau null bila modul pemiliknya tidak aktif. */
    public function path(string $key): ?string
    {
        return $this->items[$key]['path'] ?? null;
    }

    /**
     * @return list<array{key: string, label: string, path: string, order: int, emphasis: bool}>
     */
    public function items(): array
    {
        $items = array_values($this->items);
        usort($items, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $items;
    }
}
