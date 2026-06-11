<?php

declare(strict_types=1);

use Fibonoir\LaravelSEO\Services\Schema\SchemaCollection;

it('does not allow </script> in schema values to break out of toScript()', function () {
    $script = SchemaCollection::make()
        ->add([
            '@type' => 'WebPage',
            'name' => 'Title</script><script>alert(1)</script>',
        ])
        ->toScript();

    expect($script)->not->toContain('</script><script>alert(1)')
        ->and(substr_count($script, '</script>'))->toBe(1);

    // The payload survives decoding intact.
    preg_match('#<script type="application/ld\+json">(.*)</script>#s', $script, $m);
    $decoded = json_decode(trim($m[1]), true);

    expect($decoded['name'])->toBe('Title</script><script>alert(1)</script>');
});

it('escapes angle brackets in toJson() for multiple schemas', function () {
    $json = SchemaCollection::make()
        ->add(['@type' => 'WebPage', 'name' => 'a</script>b'])
        ->add(['@type' => 'Organization', 'name' => 'plain'])
        ->toJson();

    expect($json)->not->toContain('</script>')
        ->and(json_decode($json, true)[0]['name'])->toBe('a</script>b');
});
