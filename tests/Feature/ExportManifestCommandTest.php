<?php

use Illuminate\Support\Facades\Artisan;
use Schemastud\Blockdoc\Contracts\ManifestNodeSource;

it('exports a bound profile manifest as pretty JSON to stdout', function () {
    app()->instance(ManifestNodeSource::class, new ManifestSourceFixture);

    expect(Artisan::call('blockdoc:export-manifest', ['profile' => 'fixture']))->toBe(0);

    $manifest = json_decode(Artisan::output(), true);

    expect($manifest['profile'])->toBe('fixture')
        ->and($manifest['version'])->toBe(1)
        ->and(array_column($manifest['nodes'], 'name'))->toBe(['manifest_container', 'manifest_section']);
});

it('writes the manifest to --path', function () {
    app()->instance(ManifestNodeSource::class, new ManifestSourceFixture);

    $path = sys_get_temp_dir().'/blockdoc-manifest-'.uniqid().'/fixture.manifest.json';

    $this->artisan('blockdoc:export-manifest', ['profile' => 'fixture', '--path' => $path])
        ->assertSuccessful();

    expect(is_file($path))->toBeTrue();

    $manifest = json_decode((string) file_get_contents($path), true);
    expect($manifest['profile'])->toBe('fixture');

    @unlink($path);
    @rmdir(dirname($path));
});

it('falls back to the canonical base manifest for the base profile', function () {
    // No ManifestNodeSource bound at all — base still exports.
    expect(Artisan::call('blockdoc:export-manifest', ['profile' => 'base']))->toBe(0);

    $manifest = json_decode(Artisan::output(), true);

    expect($manifest['profile'])->toBe('base')
        ->and(array_column($manifest['marks'], 'name'))->toContain('annotation');
});

it('fails for an unknown profile', function () {
    app()->instance(ManifestNodeSource::class, new ManifestSourceFixture);

    $this->artisan('blockdoc:export-manifest', ['profile' => 'nope'])
        ->assertFailed();
});

it('fails for a non-base profile when no ManifestNodeSource is bound', function () {
    $this->artisan('blockdoc:export-manifest', ['profile' => 'content'])
        ->assertFailed();
});
