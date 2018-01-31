<?php

namespace CipeMotion\Medialibrary\Transformers\Image;

use Intervention\Image\Constraint;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use CipeMotion\Medialibrary\Entities\File;
use Illuminate\Support\Facades\File as Filesystem;
use CipeMotion\Medialibrary\Entities\Transformation;
use CipeMotion\Medialibrary\Transformers\ITransformer;

class ResizeImageTransformer implements ITransformer
{
    /**
     * The transformation name.
     *
     * @var string
     */
    protected $name;

    /**
     * The configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Initialize the transformer.
     *
     * @param string $name
     * @param array  $config
     */
    public function __construct($name, array $config)
    {
        $this->name   = $name;
        $this->config = $config;
    }

    /**
     * Transform the source file.
     *
     * @param \CipeMotion\Medialibrary\Entities\File $file
     *
     * @return \CipeMotion\Medialibrary\Entities\Transformation
     */
    public function transform(File $file)
    {
        // Get a temp path to work with
        $destination = get_temp_path();

        // Get a temp path to store the file we are transforming in
        $localPath = get_temp_path();

        // Get a Image instance from the file
        /** @var \Intervention\Image\Image $image */
        $image = Image::make($file->getLocalPath($localPath));

        // Resize either with the fit strategy or just force the resize to the size
        if (array_get($this->config, 'fit', false)) {
            $image->fit(
                array_get($this->config, 'size.w', null),
                array_get($this->config, 'size.h', null),
                function (Constraint $constraint) {
                    if (!array_get($this->config, 'upsize', true)) {
                        $constraint->upsize();
                    }
                }
            );
        } else {
            $image->resize(
                array_get($this->config, 'size.w', null),
                array_get($this->config, 'size.h', null),
                function (Constraint $constraint) {
                    if (array_get($this->config, 'aspect', true)) {
                        $constraint->aspectRatio();
                    }

                    if (!array_get($this->config, 'upsize', true)) {
                        $constraint->upsize();
                    }
                }
            );
        }

        // Save the image to the temp path
        $image->save($destination);

        // Setup the transformation properties
        $transformation            = new Transformation;
        $transformation->name      = $this->name;
        $transformation->type      = $file->type;
        $transformation->size      = Filesystem::size($destination);
        $transformation->width     = $image->width();
        $transformation->height    = $image->height();
        $transformation->mime_type = $file->mime_type;
        $transformation->extension = $file->extension;
        $transformation->completed = true;

        // Cleanup the image
        $image->destroy();

        // Get the disk and a stream from the cropped image location
        $disk   = Storage::disk($file->disk);
        $stream = fopen($destination, 'rb');

        // Either overwrite the original uploaded file or write to the transformation path
        if (array_get($this->config, 'default', false)) {
            $disk->put("{$file->id}/upload.{$transformation->extension}", $stream);
        } else {
            $disk->put("{$file->id}/{$transformation->name}.{$transformation->extension}", $stream);
        }

        // Close the stream again
        if (is_resource($stream)) {
            fclose($stream);
        }

        // Cleanup our temp file
        if (!is_null($destination)) {
            @unlink($destination);
        }

        // Cleanup the local copy of the file
        if (!is_null($localPath)) {
            $file->setLocalPath(null);

            @unlink($localPath);
        }

        // Return the transformation
        return $transformation;
    }
}
