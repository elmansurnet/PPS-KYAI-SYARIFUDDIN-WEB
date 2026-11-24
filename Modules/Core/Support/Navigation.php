<?php

namespace Modules\Core\Support;

/**
 * Daftar tautan navigasi publik (navbar dan footer).
 *
 * Modul menambah atau mengganti item lewat kuncinya di boot() provider-nya, misalnya
 * app(Navigation::class)->add('berita', 'Berita', '/berita', 20). Dengan begitu navbar
 * milik Core tidak perlu mengenal modul mana pun. Item bawaan Core (beranda, berita,
 * pendaftaran) tetap ada walau modulnya belum aktif dan akan ditimpa modul bila perlu.
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
