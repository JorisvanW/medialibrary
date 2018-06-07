<?php

namespace CipeMotion\Medialibrary\Generators;

use RuntimeException;
use CipeMotion\Medialibrary\FileTypes;
use CipeMotion\Medialibrary\Entities\File;
use CipeMotion\Medialibrary\Entities\Transformation;

class AzureUrlGenerator implements IUrlGenerator
{
    /**
     * The config.
     *
     * @var array
     */
    protected $config;

    /**
     * Instantiate the URL generator.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get a URL to the resource.
     *
     * @param \CipeMotion\Medialibrary\Entities\File                $file
     * @param \CipeMotion\Medialibrary\Entities\Transformation|null $transformation
     * @param bool                                                  $fullPreview
     * @param bool                                                  $download
     *
     * @return string
     */
    public function getUrlForTransformation(File $file, Transformation $transformation = null, $fullPreview = false, $download = false): string
    {
        if ($download) {
            throw new RuntimeException('The Azure url generator does not support forced download urls.');
        }

        $account           = array_get($this->config, 'account');
        $container         = array_get($this->config, 'container');
        $tranformationName = $transformation->name ?? 'upload';
        $extension         = $transformation->extension ?? $file->extension;

        if ($transformation === null && $fullPreview && $file->type !== FileTypes::TYPE_IMAGE) {
            $tranformationName = 'preview';
            $extension         = 'jpg';
        }

        return "https://{$account}.blob.core.windows.net/{$container}/{$file->id}/{$tranformationName}.{$extension}";
    }
}
