<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Berita\Models\Post;
use Modules\Berita\Support\HtmlCleaner;

uses(RefreshDatabase::class);

test('xss payloads are neutralised', function (string $payload) {
    $out = strtolower(HtmlCleaner::clean('<p>awal</p>'.$payload.'<p>akhir</p>'));

    foreach (['<script', 'onerror', 'onload', 'onclick', 'onmouseover', 'ontoggle', 'javascript:', '<iframe', '<svg', '<style', 'style=', '<form', '<object', '<embed', '<input', '<math', '<base', '<meta', '<link', 'srcdoc', 'vbscript:', 'data:text', '<img', 'alert('] as $bad) {
        expect($out)->not->toContain($bad);
    }

    // Gagal tertutup: markup yang merusak parser boleh membuang sisa isi, tetapi tidak boleh lolos.
    expect($out)->toContain('awal');
})->with([
    'script tag' => ['<script>alert(1)</script>'],
    'script uppercase' => ['<SCRIPT SRC=//evil.test/x.js></SCRIPT>'],
    'img onerror' => ['<img src=x onerror=alert(1)>'],
    'img onerror quoted' => ['<img src="x" onerror="alert(1)" alt="a">'],
    'a javascript' => ['<a href="javascript:alert(1)">klik</a>'],
    'a javascript obfuscated' => ['<a href="  jAvA&#x09;script:alert(1)">klik</a>'],
    'a javascript entity' => ['<a href="&#106;avascript:alert(1)">klik</a>'],
    'a data url' => ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">klik</a>'],
    'a vbscript' => ['<a href="vbscript:msgbox(1)">klik</a>'],
    'svg onload' => ['<svg onload=alert(1)></svg>'],
    'svg script' => ['<svg><script>alert(1)</script></svg>'],
    'iframe' => ['<iframe src="https://evil.test"></iframe>'],
    'iframe srcdoc' => ['<iframe srcdoc="<script>alert(1)</script>"></iframe>'],
    'style tag' => ['<style>body{display:none}</style>'],
    'style attribute' => ['<p style="background:url(javascript:alert(1))">x</p>'],
    'form' => ['<form action="https://evil.test"><input name=a><button>kirim</button></form>'],
    'object embed' => ['<object data="x.swf"></object><embed src="x.swf">'],
    'nested script' => ['<scr<script>ipt>alert(1)</scr</script>ipt>'],
    'math mutation' => ['<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>'],
    'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.test">'],
    'base' => ['<base href="https://evil.test/">'],
    'link stylesheet' => ['<link rel="stylesheet" href="https://evil.test/x.css">'],
    'body onload' => ['<body onload=alert(1)>'],
    'details ontoggle' => ['<details open ontoggle=alert(1)>x</details>'],
    'event handler on p' => ['<p onclick="alert(1)" onmouseover="alert(2)">x</p>'],
    'comment trick' => ['<!--><img src=x onerror=alert(1)>-->'],
    'null byte' => ["<img src=x\0 onerror=alert(1)>"],
]);

test('allowed formatting survives and links are hardened', function () {
    $in = '<h2>Judul</h2><p class="x" style="color:red"><strong>tebal</strong> <em>miring</em> <u>garis</u> <s>coret</s>'
        .'<br><a href="https://contoh.test/a?b=1" target="_blank" onclick="x()">tautan</a> '
        .'<a href="mailto:a@b.test">surel</a> <a href="tel:+62812">telepon</a></p>'
        .'<ul><li>satu</li></ul><ol><li>dua</li></ol><blockquote><p>kutipan</p></blockquote>'
        .'<pre><code>kode()</code></pre><h3>Anak</h3><h4>Cucu</h4>'
        .'<table><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table>';

    $out = HtmlCleaner::clean($in);

    foreach (['<h2>Judul</h2>', '<strong>tebal</strong>', '<em>miring</em>', '<u>garis</u>', '<s>coret</s>', '<ul><li>satu</li></ul>', '<ol><li>dua</li></ol>', '<blockquote>', '<pre><code>kode()</code></pre>', '<h3>Anak</h3>', '<table>', '<th>A</th>', '<td>1</td>'] as $needle) {
        expect($out)->toContain($needle);
    }

    expect($out)->toContain('href="https://contoh.test/a?b&#61;1"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('href="mailto:a&#64;b.test"')
        ->toContain('href="tel:&#43;62812"')
        ->not->toContain('class=')
        ->not->toContain('style=')
        ->not->toContain('onclick')
        ->not->toContain('target=');
});

test('images are not allowed in the body', function () {
    expect(HtmlCleaner::clean('<p>a</p><img src="https://evil.test/p.png" alt="x"><img src="/storage/a.jpg">'))->not->toContain('<img');
});

test('relative and protocol-relative links are dropped', function () {
    expect(HtmlCleaner::clean('<a href="//evil.test/x">a</a><a href="/admin">b</a>'))->not->toContain('href=');
});

test('saving a post stores sanitised html and cleaning is idempotent', function () {
    $post = Post::factory()->create(['body' => '<p onclick="x()">Halo</p><script>alert(1)</script>']);

    expect($post->fresh()->getRawOriginal('body'))->toBe('<p>Halo</p>');

    $once = HtmlCleaner::clean('<p>a <a href="https://x.test">b</a></p>');
    expect(HtmlCleaner::clean($once))->toBe($once);
    expect(HtmlCleaner::clean(''))->toBe('');
});
