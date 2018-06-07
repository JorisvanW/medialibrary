<?php

namespace CipeMotion\Medialibrary\Transformers\Document;

use CloudConvert\Api;
use Illuminate\Support\Facades\Storage;
use CloudConvert\Exceptions\ApiException;
use CipeMotion\Medialibrary\Entities\File;
use Illuminate\Support\Facades\File as Filesystem;
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
     * @throws \CloudConvert\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @return Transformation
     */
    public function transform(File $file): Transformation
    {
        $extension = array_get($this->config, 'extension', 'pdf');

        $cloudconvertSettings = [
            'inputformat'      => $file->extension,
            'outputformat'     => $extension,
            'input'            => 'download',
            'wait'             => true,
            'file'             => $file->download_url,
            'converteroptions' => array_get($this->config, 'converteroptions', []),
        ];

        if (config('services.cloudconvert.timeout') !== null) {
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
            if ($destination !== null) {
                @unlink($destination);
            }
        }

        // We got it all, cleanup!
        if ($convert !== null) {
            try {
                $convert->delete();
            } catch (ApiException $e) {
                // If we could not delete, meh, it's probably already gone then
                if ($destination !== null) {
                    @unlink($destination);
                }
            }
        }

        // If we have no destination something went wrong and we abort here
        if ($destination === null) {
            return null;
        }

        // Build the transformation
        $transformation            = new Transformation;
        $transformation->name      = $this->name;
        $transformation->mime_type = $mimetype;
        $transformation->type      = File::getTypeForMime($transformation->mime_type);
        $transformation->extension = $extension;
        $transformation->completed = true;

        // Get the disk and a stream from the cropped image location
        $disk   = Storage::disk($file->disk);
        $stream = fopen($destination, 'rb');

        // Upload the preview
        $disk->put($file->getPath($transformation), $stream);

        // Cleanup our streams
        if (\is_resource($stream)) {
            fclose($stream);
        }

        // Retrieve the size from the filesystem
        $transformation->size = Filesystem::size($destination);

        // Store the preview
        $file->transformations()->save($transformation);

        // Cleanup our temp file
        if ($destination !== null) {
            @unlink($destination);
        }

        return $transformation;
    }
}
