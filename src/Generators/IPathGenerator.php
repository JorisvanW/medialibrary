<?php

namespace CipeMotion\Medialibrary\Generators;

use CipeMotion\Medialibrary\Entities\File;
use CipeMotion\Medialibrary\Entities\Transformation;

interface IPathGenerator
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
     *
     * @return string
     */
    public function getPathForTransformation(File $file, Transformation $transformation = null);
}
