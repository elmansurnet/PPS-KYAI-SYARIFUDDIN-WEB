<?php

use Modules\Core\Support\WhatsAppLink;

test('normalizeNumber handles common Indonesian formats', function (string $raw, string $expected) {
    expect(WhatsAppLink::normalizeNumber($raw))->toBe($expected);
})->with([
    'leading zero with spaces' => ['0812 3456 789', '628123456789'],
    'plus 62 with dashes' => ['+62 812-3456-789', '628123456789'],
    'already normalized' => ['628123456789', '628123456789'],
    'extra zero after 62' => ['6208123456789', '628123456789'],
    'plus 62 with extra zero' => ['+620812 3456 789', '628123456789'],
    'letters and punctuation stripped' => ['(0812) 3456-789 wa', '628123456789'],
    'empty' => ['', ''],
    'whitespace only' => ["  \t\n ", ''],
    'no digits' => ['abc', ''],
    'bare number without prefix' => ['8123456789', '628123456789'],
]);

test('make builds a wa.me link with an encoded message', function () {
    $link = WhatsAppLink::make('0812 3456 789', "Halo & selamat\npagi 100%");

    expect($link)->toBe('https://wa.me/628123456789?text=Halo%20%26%20selamat%0Apagi%20100%25');
});

test('make does not leak raw separators into the query', function () {
    $link = WhatsAppLink::make('628123456789', "a b\nc&d=e");

    expect($link)->not->toContain(' ')->not->toContain("\n");
    expect(substr_count($link, '&'))->toBe(0);
    expect(urldecode(explode('text=', $link)[1]))->toBe("a b\nc&d=e");
});

test('make with an empty number yields a link without a number', function () {
    expect(WhatsAppLink::make('', 'Halo'))->toBe('https://wa.me/?text=Halo');
});
