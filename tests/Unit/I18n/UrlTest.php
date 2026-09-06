<?php

declare(strict_types=1);

use Rankbeam\Seo\I18n\Url;

it('accepts ordinary absolute http(s) URLs', function (string $url) {
    expect(Url::isValid($url))->toBeTrue();
})->with([
    'https://example.com/',
    'http://example.com/path?x=1#frag',
    'https://sub.example.co.uk/a/b/c',
    'https://localhost/page',
    'https://127.0.0.1:8080/page',
    'https://[2001:db8::1]/page',
    'HTTPS://EXAMPLE.COM/PAGE',
    'https://example.com./trailing-dot-host',
]);

it('accepts internationalised URLs that FILTER_VALIDATE_URL rejects', function (string $url) {
    expect(filter_var($url, FILTER_VALIDATE_URL))->toBeFalse()
        ->and(Url::isValid($url))->toBeTrue();
})->with([
    'IDN host' => ['https://münchen.example/'],
    'Unicode path' => ['https://example.com/straße'],
    'CJK path' => ['https://example.jp/検索/結果'],
    'Cyrillic host and path' => ['https://пример.рф/страница'],
    'IDN host with port and query' => ['https://bücher.example:8443/suche?q=größe'],
]);

it('accepts percent-encoded paths and Punycode hosts', function () {
    expect(Url::isValid('https://xn--mnchen-3ya.example/'))->toBeTrue()
        ->and(Url::isValid('https://example.com/stra%C3%9Fe'))->toBeTrue()
        ->and(Url::isValid('https://example.jp/%E6%A4%9C%E7%B4%A2'))->toBeTrue();
});

it('rejects what a search engine could not follow', function (?string $url) {
    expect(Url::isValid($url))->toBeFalse();
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'no scheme' => ['example.com/page'],
    'protocol-relative' => ['//example.com/page'],
    'ftp' => ['ftp://example.com/file'],
    'mailto' => ['mailto:someone@example.com'],
    'javascript' => ['javascript:alert(1)'],
    'space inside' => ['https://example.com/a page'],
    'control char' => ["https://example.com/\x01"],
    'no host' => ['https:///path'],
    'bad label' => ['https://-example.com/'],
    'label too long' => ['https://'.str_repeat('a', 64).'.com/'],
    'empty label' => ['https://example..com/'],
    'bad ipv6' => ['https://[not-an-ip]/'],
    'underscore host' => ['https://exa_mple.com/'],
]);

it('validates a host on its own', function () {
    expect(Url::isValidHost('example.com'))->toBeTrue()
        ->and(Url::isValidHost('münchen.example'))->toBeTrue()
        ->and(Url::isValidHost('[::1]'))->toBeTrue()
        ->and(Url::isValidHost('192.168.0.1'))->toBeTrue()
        ->and(Url::isValidHost(''))->toBeFalse()
        ->and(Url::isValidHost('bad host'))->toBeFalse();
});
