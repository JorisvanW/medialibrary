<?php

namespace CipeMotion\Medialibrary\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string                                 name
 * @property string                                 type
 * @property \CipeMotion\Medialibrary\Entities\File file
 * @property int                                    size
 * @property int                                    width
 * @property int                                    height
 * @property bool                                   filename
 * @property int                                    raw_size
 * @property string                                 mime_type
 * @property string                                 extension
 * @property bool                                   completed
 */
class Transformation extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'medialibrary_transformations';

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
        'name',
        'size',
        'width',
        'height',
        'type',
        'url',
        'created_at',
        'updated_at',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'url',
    ];

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->type;
    }

    /**
     * Get the url to this resource.
     *
     * @return string
     */
    public function getUrlAttribute()
    {
        return $this->file->getUrl($this->name);
    }

    /**
     * Get if the transformation is completed.
     *
     * @return string
     */
    public function getIsCompletedAttribute()
    {
        return (bool)$this->attributes['completed'];
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
     * The file this is a transformation for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
