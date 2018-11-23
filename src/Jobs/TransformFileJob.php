<?php

namespace CipeMotion\Medialibrary\Jobs;

use CipeMotion\Medialibrary\Entities\File;
use CipeMotion\Medialibrary\Transformers\ITransformer;
use CipeMotion\Medialibrary\Exceptions\UnknownTransformerException;

abstract class TransformFileJob
{
    /**
     * The file to transform.
     *
     * @var \CipeMotion\Medialibrary\Entities\File
     */
    protected $file;

    /**
     * The transformation name.
     *
     * @var string
     */
    protected $name;

    /**
     * The transformer class.
     *
     * @var string
     */
    protected $transformer;

    /**
     * The transformer config.
     *
     * @var array
     */
    protected $config;

    /**
     * Create a transform job.
     *
     * @param \CipeMotion\Medialibrary\Entities\File $file
     * @param string                                 $name
     * @param string                                 $transformer
     * @param array                                  $config
     */
    public function __construct(File $file, $name, $transformer, array $config)
    {
        $this->file        = $file;
        $this->name        = $name;
        $this->transformer = $transformer;
        $this->config      = $config;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var \CipeMotion\Medialibrary\Transformers\ITransformer $transformer */
        $transformer = new $this->transformer($this->name, $this->config);

        if ($transformer instanceof ITransformer) {
            $transformation = $transformer->transform($this->file);

            // Could not transform this document, skip the transformation
            if ($transformation === null) {
                return;
            }

            if (array_get($this->config, 'default', false)) {
                $this->file->size      = $transformation->raw_size;
                $this->file->width     = $transformation->width;
                $this->file->height    = $transformation->height;
                $this->file->mime_type = $transformation->mime_type;
                $this->file->extension = $transformation->extension;

                $this->file->save();
            } else {
                $this->file->transformations()->save($transformation);
            }
        } else {
            throw new UnknownTransformerException("Unknown transformer:{$this->transformer}.");
        }
    }
}
