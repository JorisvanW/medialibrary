<?php

namespace CipeMotion\Medialibrary\Entities;

use Exception;
use Stringy\Stringy;
use Ramsey\Uuid\Uuid;
use Intervention\Image\Facades\Image;
use CipeMotion\Medialibrary\FileTypes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class File extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'medialibrary_files';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * The attributes that should be visible in arrays.
     *
     * @var array
     */
    protected $visible = [
        'id',
        'url',
        'name',
        'type',
        'size',
        'group',
        'width',
        'height',
        'preview',
        'extension',
        'created_at',
        'updated_at',
        'attachment_count',
        'preview_is_processing',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'url',
        'preview',
        'attachment_count',
        'preview_is_processing',
    ];

    /**
     * The URL generator instance for this file.
     *
     * @var \CipeMotion\Medialibrary\Generators\IUrlGenerator
     */
    protected $generator = null;

    /**
     * The local path to the file, used for transformations.
     *
     * @var string|null
     */
    protected $localPath = null;

    /**
     * The group transformations cache.
     *
     * @var array|null
     */
    protected $groupTransformationsCache = null;

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string $method
     * @param  array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $relations = config('medialibrary.relations.attachment');

        if (array_has($relations, $method)) {
            return $this->morphedByMany($relations[$method], 'attachable', 'medialibrary_attachable');
        }

        if ($method !== 'getUrlAttribute' && starts_with($method, 'getUrl') && ends_with($method, 'Attribute')) {
            return $this->getUrlAttribute();
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * Scope the query to exclude or show only hidden files.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool                                  $hidden
     */
    public function scopeHidden(Builder $query, $hidden = true)
    {
        $query->where('is_hidden', (bool)$hidden);
    }

    /**
     * Scope the query to the file type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array|string                          $group
     */
    public function scopeGroup(Builder $query, $group)
    {
        if (is_array($group)) {
            $query->whereIn('group', $group);
        } else {
            $query->where('group', $group);
        }
    }

    /**
     * Scope the query to files with owner.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeWithOwner(Builder $query)
    {
        $query->whereNotNull('owner_id');
    }

    /**
     * Scope the query to files without owner.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeWithoutOwner(Builder $query)
    {
        $query->whereNull('owner_id');
    }

    /**
     * Scope the query to files with user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeWithUser(Builder $query)
    {
        $query->whereNotNull('user_id');
    }

    /**
     * Scope the query to files without user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeWithoutUser(Builder $query)
    {
        $query->whereNull('user_id');
    }

    /**
     * Get the local path.
     *
     * @param string|null $path
     *
     * @return string
     */
    public function getLocalPath($path = null)
    {
        if (empty($this->localPath)) {
            if ($this->isDiskLocal($this->disk)) {
                $localPath = config("filesystems.disks.{$this->disk}.root") . "/{$this->id}/upload.{$this->extension}";

                if (!is_null($path)) {
                    copy($localPath, $path);
                }

                $this->setLocalPath(is_null($path) ? $localPath : $path);
            } else {
                $temp = is_null($path) ? get_temp_path() : $path;

                copy($this->getDownloadUrlAttribute(), $temp);

                $this->setLocalPath($temp);
            }
        }

        return $this->localPath;
    }

    /**
     * Set the local path.
     *
     * @param string $path
     */
    public function setLocalPath($path)
    {
        $this->localPath = $path;
    }

    /**
     * Get the url.
     *
     * @param string|null $transformation
     * @param bool        $fullPreview
     * @param bool        $download
     *
     * @return string
     */
    public function getUrl($transformation = null, $fullPreview = false, $download = false)
    {
        if (!empty($transformation)) {
            $transformationName = $transformation;

            /** @var \CipeMotion\Medialibrary\Entities\Transformation|null $transformation */
            $transformation = $this->transformations->where('name', $transformation)->where('completed', 1)->first();

            if (is_null($transformation)) {
                if (!empty(config("medialibrary.file_types.{$this->type}.thumb.defaults.{$transformationName}"))) {
                    return config("medialibrary.file_types.{$this->type}.thumb.defaults.{$transformationName}");
                } else {
                    return null;
                }
            }

            if (!is_null($transformation) && !$transformation->completed) {
                $transformation = null;
            }
        }

        return $this->getUrlGenerator()->getUrlForTransformation($this, $transformation, $fullPreview, $download);
    }

    /**
     * Get the url attribute.
     *
     * @return string
     */
    public function getUrlAttribute()
    {
        return $this->getUrl();
    }

    /**
     * Get the download url attribute.
     *
     * @return string
     */
    public function getDownloadUrlAttribute()
    {
        return $this->getUrl(null, false, true);
    }

    /**
     * Get the name attribute.
     *
     * @return string
     */
    public function getNameAttribute()
    {
        if (!is_null($this->attributes['name'])) {
            return $this->attributes['name'];
        }

        return $this->filename;
    }

    /**
     * Set the name attribute.
     *
     * @param string $value
     */
    public function setNameAttribute($value)
    {
        if (empty($value)) {
            $value = null;
        }

        $this->attributes['name'] = $value;
    }

    /**
     * Get the human readable file size.
     *
     * @return string
     */
    public function getSizeAttribute()
    {
        return filesize_to_human($this->attributes['size']);
    }

    /**
     * Get the raw file size.
     *
     * @return string
     */
    public function getRawSizeAttribute()
    {
        return $this->attributes['size'];
    }

    /**
     * Get the url to a file preview.
     *
     * @return string|null
     */
    public function getPreviewAttribute()
    {
        return $this->getUrl('thumb');
    }

    /**
     * Get the url to a file full preview.
     *
     * @return string|null
     */
    public function getPreviewFullAttribute()
    {
        if ($this->type === FileTypes::TYPE_IMAGE) {
            return $this->getUrl();
        }

        return $this->getUrl('preview', true);
    }

    /**
     * Get if the image preview is processing.
     *
     * @return bool $value
     */
    public function getPreviewIsProcessingAttribute()
    {
        if (in_array($this->type, [FileTypes::TYPE_IMAGE, FileTypes::TYPE_DOCUMENT, FileTypes::TYPE_VIDEO])
            && is_null($this->getPreviewFullAttribute())
        ) {
            $transformationName = $this->type === FileTypes::TYPE_IMAGE ? 'thumb' : 'preview';
            $transformation     = $this->transformations->where('name', $transformationName)->first();

            if (!is_null($transformation) && !$transformation->isCompleted) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get if the image is hidden.
     *
     * @return bool $value
     */
    public function getIsHiddenAttribute()
    {
        return (bool)$this->attributes['is_hidden'];
    }

    /**
     * Set if the image is hidden.
     *
     * @param bool $value
     */
    public function setIsHiddenAttribute($value)
    {
        $this->attributes['is_hidden'] = (bool)$value;
    }

    /**
     * Get the attachments count attribute.
     *
     * @return string
     */
    public function getAttachmentCountAttribute()
    {
        return $this->attachables->count();
    }

    /**
     * Set the group attribute.
     *
     * @param string $value
     */
    public function setGroupAttribute($value)
    {
        if (empty($value)) {
            $value = 'default';
        }

        $this->attributes['group'] = $value;
    }

    /**
     * The file owner.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @throws \Exception
     */
    public function owner()
    {
        if (is_null(config('medialibrary.relations.owner.model'))) {
            throw new Exception('Medialibrary: owner relation is not set in medialibrary.php');
        }

        return $this->belongsTo(config('medialibrary.relations.owner.model'));
    }

    /**
     * The file user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @throws \Exception
     */
    public function user()
    {
        if (is_null(config('medialibrary.relations.user.model'))) {
            throw new Exception('Medialibrary: user relation is not set in medialibrary.php');
        }

        return $this->belongsTo(config('medialibrary.relations.user.model'));
    }

    /**
     * The file category.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The transformations belonging to this file.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transformations()
    {
        return $this->hasMany(Transformation::class)->with(['file']);
    }

    /**
     * The models the file is attached to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attachables()
    {
        return $this->hasMany(Attachable::class);
    }

    /**
     * Get the url generator for this file.
     *
     * @return \CipeMotion\Medialibrary\Generators\IUrlGenerator
     */
    protected function getUrlGenerator()
    {
        if (is_null($this->generator)) {
            $generatorClass = config('medialibrary.generator.url');

            $this->generator = new $generatorClass(config("filesystems.disks.{$this->disk}"));
        }

        return $this->generator;
    }

    /**
     * Get the group attribute.
     *
     * @return array
     */
    public function getGroupTransformations()
    {
        if (is_null($this->groupTransformationsCache)) {
            $transformers      = config("medialibrary.file_types.{$this->attributes['type']}.transformations");
            $transformerGroups = config("medialibrary.file_types.{$this->attributes['type']}.transformationGroups");

            // Check if we have transformation group else use default
            $group = isset($this->attributes['group']) ? $this->attributes['group'] : null;
            if (is_null($group) || !array_has($transformerGroups, $group)) {
                $transformerGroup = array_get($transformerGroups, 'default', []);
            } else {
                $transformerGroup = array_get($transformerGroups, $group, []);
            }

            // Transformations array with default thumb generator
            $transformations = [
                'thumb' => config("medialibrary.file_types.{$this->attributes['type']}.thumb"),
            ];

            // Makes the transformation group complete with the transformation data
            foreach ($transformerGroup as $transformationName) {
                $transformations[$transformationName] = array_get($transformers, $transformationName);
            }

            $this->groupTransformationsCache = array_filter($transformations, function ($transformer) {
                return !is_null($transformer);
            });
        }

        return $this->groupTransformationsCache;
    }

    /**
     * Find the type for the mime type.
     *
     * @param string $mime
     *
     * @return string|null
     */
    public static function getTypeForMime($mime)
    {
        $types = config('medialibrary.file_types');

        foreach ($types as $type => $data) {
            $allowed = array_flatten(array_values(array_get($data, 'mimes')));

            if (in_array($mime, $allowed)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * File upload helper.
     *
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $upload
     * @param array                                               $attributes
     * @param string|null                                         $disk
     * @param bool|\Illuminate\Database\Eloquent\Model            $owner
     * @param bool|\Illuminate\Database\Eloquent\Model            $user
     *
     * @return bool|\CipeMotion\Medialibrary\Entities\File
     */
    public static function uploadFile(
        UploadedFile $upload,
        array $attributes = [],
        $disk = null,
        $owner = false,
        $user = false
    ) {
        // Start our journey with a fresh file Eloquent model and a fresh UUID
        $file     = new File;
        $file->id = Uuid::uuid4()->toString();

        // Retrieve the disk from the config unless it's given to us
        $disk = is_null($disk) ? call_user_func(config('medialibrary.disk')) : $disk;

        // Check if we need to resolve the owner
        if ($owner === false && !is_null(config('medialibrary.relations.owner.model'))) {
            $owner = call_user_func(config('medialibrary.relations.owner.resolver'));
        }

        // Check if we need to resolve the user
        if ($user === false && !is_null(config('medialibrary.relations.user.model'))) {
            $user = call_user_func(config('medialibrary.relations.user.resolver'));
        }

        // Attach the owner & user if supplied
        $file->owner_id = (is_null($owner) || $owner === false) ? null : $owner->getKey();
        $file->user_id  = (is_null($user) || $user === false) ? null : $user->getKey();

        // Fill in the fields from the attributes
        $file->group       = (!empty($group = array_get($attributes, 'group'))) ? $group : null;
        $file->caption     = (!empty($caption = array_get($attributes, 'caption'))) ? $caption : null;
        $file->category_id = (array_get($attributes, 'category', 0) > 0) ? array_get($attributes, 'category') : null;

        // If a filename is set use that, otherwise build a filename based on the original name
        if (!empty($name = array_get($attributes, 'name'))) {
            $file->name = $name;
        } else {
            $file->name = str_replace(".{$upload->getClientOriginalExtension()}", '', $upload->getClientOriginalName());
        }

        // Extract the mimetype from the file
        $mimeType = array_get($attributes, 'client_mime', false) === true ? $upload->getClientMimeType() : $upload->getMimeType();

        // Check if we are allowed to fallback to the client mime
        if ($mimeType === null && array_get($attributes, 'client_mime', false) === false && array_get($attributes, 'client_mime_fallback', true) === true) {
            $mimeType = $upload->getClientMimeType();
        }

        // Extract the type from the mime of the file
        $type = self::getTypeForMime($mimeType);

        // Abort if we cannot find a valid type, this file is probably not allowed
        if ($type === null) {
            return false;
        }

        // If the file is a image we also need to find out the dimensions
        if ($type === FileTypes::TYPE_IMAGE) {
            /** @var \Intervention\Image\Image $image */
            $image = Image::make($upload);

            $file->width  = $image->getWidth();
            $file->height = $image->getHeight();

            $image->destroy();
        }

        // Collect all the metadata we are going to save with the file entry in the database
        $file->type      = $type;
        $file->disk      = $disk;
        $file->filename  = (string)Stringy::create(
            str_replace(".{$upload->getClientOriginalExtension()}", '', $upload->getClientOriginalName())
        )->trim()->toLowerCase()->slugify();
        $file->extension = strtolower($upload->getClientOriginalExtension());
        $file->mime_type = $mimeType;
        $file->size      = $upload->getSize();
        $file->is_hidden = array_get($attributes, 'is_hidden', false);
        $file->completed = true;

        // Get a resource handle on the file so we can stream it to our disk
        $stream = fopen($upload->getRealPath(), 'rb');

        // Use Laravel' storage engine to store our file on a disk
        $success = Storage::disk($disk)->put("{$file->id}/upload.{$file->extension}", $stream);

        // Close the resource handle if we need to
        if (is_resource($stream)) {
            fclose($stream);
        }

        // Check if we succeeded
        if ($success) {
            $file->setLocalPath($upload->getRealPath());

            $file->save();

            return $file;
        }

        // Something went wrong and the file is not uploaded
        return false;
    }

    /**
     * Check if the disk is stored locally.
     *
     * @return bool
     */
    private function isDiskLocal($disk)
    {
        return config("filesystems.disks.{$disk}.driver") === 'local';
    }
}
