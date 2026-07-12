<?php

namespace Schemastud\BlockSchema\Console;

use Illuminate\Console\Command;
use Schemastud\BlockSchema\Contracts\ManifestNodeSource;
use Schemastud\BlockSchema\Schema\ManifestBuilder;

/**
 * Export the node manifest for a profile as pretty JSON — the server half of the
 * manifest pipeline (splicewire-editor issue 06). Host profiles resolve through
 * the bound {@see ManifestNodeSource}; `base` falls back to the canonical
 * hand-authored base manifest shipped with the package.
 */
class ExportManifestCommand extends Command
{
    protected $signature = 'block-schema:export-manifest
        {profile : The manifest profile to export (e.g. content, base)}
        {--path= : Write the manifest JSON to this file instead of stdout}';

    protected $description = 'Export the node manifest (nodes, marks, containment, attrs schemas) for a profile as JSON.';

    public function handle(): int
    {
        $profile = (string) $this->argument('profile');

        $source = $this->laravel->bound(ManifestNodeSource::class)
            ? $this->laravel->make(ManifestNodeSource::class)
            : null;

        if ($source !== null && in_array($profile, $source->profiles(), true)) {
            $manifest = (new ManifestBuilder($source))->build($profile);
        } elseif ($profile === 'base') {
            $manifest = ManifestBuilder::baseManifest();
        } elseif ($source === null) {
            $this->error('No '.ManifestNodeSource::class.' binding found; the host application must bind one for profile manifests.');

            return self::FAILURE;
        } else {
            $this->error(sprintf(
                'Unknown manifest profile [%s]. Known profiles: %s.',
                $profile,
                implode(', ', array_unique([...$source->profiles(), 'base'])),
            ));

            return self::FAILURE;
        }

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (($path = $this->option('path')) !== null && $path !== '') {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            file_put_contents($path, $json.PHP_EOL);

            $this->info("Manifest for [{$profile}] written to {$path}.");
        } else {
            $this->line((string) $json);
        }

        return self::SUCCESS;
    }
}
