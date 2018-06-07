<?php

namespace CipeMotion\Medialibrary\Generators;

use CipeMotion\Medialibrary\Entities\File;
use CipeMotion\Medialibrary\Entities\Transformation;

interface IUrlGenerator
{
    /**
     * Instantiate the URL generator.
     *
     * @param array $config
     */
    public function __construct(array $config);

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
    public function getUrlForTransformation(File $file, Transformation $transformation = null, $fullPreview = false, $download = false): string;
}
