<?php

use Modules\Core\Support\SafeHref;

test('safe href blocks whitespace and control character tricks', function (string $href) {
    expect(SafeHref::sanitize($href))->toBe('#');
})->with([
    'tab protocol relative' => "/\t/evil.com",
    'newline protocol relative' => "/\n/evil.com",
    'cr protocol relative' => "/\r/evil.com",
    'nul' => "/\0/evil.com",
    'leading control javascript' => "\x01javascript:alert(1)",
    'inner tab javascript' => "java\tscript:alert(1)",
    'slash tab backslash' => "/\t\\evil.com",
    'plain protocol relative' => '//evil.com',
    'javascript' => 'javascript:alert(1)',
]);

test('safe href keeps legitimate links', function (string $href) {
    expect(SafeHref::sanitize($href))->toBe($href);
})->with(['/berita', '/daftar?x=1', '#kontak', 'https://example.com/a', 'mailto:a@b.id', 'tel:+62812']);
