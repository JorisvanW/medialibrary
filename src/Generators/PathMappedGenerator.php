<?php

namespace CipeMotion\Medialibrary\Generators;

use CipeMotion\Medialibrary\Entities\File;
use CipeMotion\Medialibrary\Entities\Transformation;

class PathMappedGenerator implements IPathGenerator
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
     *
     * @return string
     * @throws \Exception
     */
    public function getPathForTransformation(File $file, Transformation $transformation = null)
    {
        if (null === $transformation) {
            return "{$file->id}/upload/{$file->filename}.{$file->extension}";
        }

        return "{$file->id}/{$transformation->name}/{$file->filename}.{$transformation->extension}";
    }
}
