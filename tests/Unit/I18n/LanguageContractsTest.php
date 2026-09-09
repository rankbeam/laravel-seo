<?php

declare(strict_types=1);

use Rankbeam\Seo\I18n\Hreflang;

it('validates emitted Google codes without repairing the input', function (string $code) {
    expect(Hreflang::isValid($code))->toBeFalse();
})->with(['en_US', ' en-US ', 'en--US', 'es-419', 'fil', 'yue', 'en-EU', 'en-UN', 'en-UK']);

it('keeps malformed application separators visible to validation', function () {
    expect(Hreflang::normalize('en__US'))->toBe('en--US');
});
