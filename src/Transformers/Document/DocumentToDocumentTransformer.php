<?php

namespace CipeMotion\Medialibrary\Transformers\Document;

use Image;
use Storage;
use CloudConvert\Api;
use File as Filesystem;
use CloudConvert\Exceptions\ApiException;
use CipeMotion\Medialibrary\Entities\File;
use CipeMotion\Medialibrary\Entities\Transformation;
use CipeMotion\Medialibrary\Transformers\ITransformer;
use CloudConvert\Exceptions\ApiConversionFailedException;

class DocumentToDocumentTransformer implements ITransformer
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
     * The cloudconvert API.
     *
     * @var array
     */
    protected $api;

    /**
     * Initialize the transformer.
     *
     * @param string $name
     * @param array  $config
     */
    public function __construct($name, array $config)
    {
        $this->api    = new Api(config('services.cloudconvert.key'));
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
        $extension = array_get($this->config, 'extension', 'pdf');

        $cloudconvertSettings = [
            'inputformat'      => $file->extension,
            'outputformat'     => $extension,
            'input'            => 'download',
            'wait'             => true,
            'file'             => $file->downloadUrl,
            'converteroptions' => array_get($this->config, 'converteroptions', []),
        ];

        if (!is_null(config('services.cloudconvert.timeout'))) {
            $cloudconvertSettings['timeout'] = config('services.cloudconvert.timeout');
        }

        $convert     = null;
        $mimetype    = null;
        $destination = null;

        try {
            // Wait for the conversion to finish
            $convert = $this->api->convert($cloudconvertSettings)->wait();

            // Get a temp path
            $destination = get_temp_path();

            // Download the converted video file
            copy('https:' . $convert->output->url, $destination);

            // Get the mime type
            $mimetype = mime_content_type($destination);
        } catch (ApiConversionFailedException $e) {
            // So if we could not convert the file we ingore this transformation
            // The file is probably corrupt or unsupported or has some other shenanigans
            // The other exceptions are retryable so we fail and try again later
            if (!is_null($destination)) {
                @unlink($destination);
            }
        }

        // We got it all, cleanup!
        if ($convert !== null) {
            try {
                $convert->delete();
            } catch (ApiException $e) {
                // If we could not delete, meh, it's probably already gone then
                if (!is_null($destination)) {
                    @unlink($destination);
                }
            }
        }

        // If we have no destination something went wrong and we abort here
        if ($destination === null) {
            return null;
        }

        // Get the disk and a stream from the cropped image location
        $disk   = Storage::disk($file->disk);
        $stream = fopen($destination, 'rb');

        // Upload the preview
        $disk->put("{$file->id}/{$this->name}.{$extension}", $stream);

        // Cleanup our streams
        if (is_resource($stream)) {
            fclose($stream);
        }

        // Build the transformation
        $transformation            = new Transformation;
        $transformation->name      = $this->name;
        $transformation->size      = Filesystem::size($destination);
        $transformation->mime_type = $mimetype;
        $transformation->type      = File::getTypeForMime($transformation->mime_type);
        $transformation->extension = $extension;
        $transformation->completed = true;

        // Store the preview
        $file->transformations()->save($transformation);

        // Cleanup our temp file
        if (!is_null($destination)) {
            @unlink($destination);
        }

        return $transformation;
    }
}
