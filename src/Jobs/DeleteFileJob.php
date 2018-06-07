<?php

namespace CipeMotion\Medialibrary\Jobs;

use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use CipeMotion\Medialibrary\Entities\Attachable;

class DeleteFileJob implements ShouldQueue
{
    /**
     * The file id.
     *
     * @var string
     */
    protected $id;

    /**
     * The file disk.
     *
     * @var string
     */
    protected $disk;

    /**
     * Create a new file deleter job.
     *
     * @param string $id
     * @param string $disk
     */
    public function __construct($id, $disk)
    {
        $this->id   = $id;
        $this->disk = $disk;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Attachable::forFile($this->id)->delete();

        Storage::disk($this->disk)->deleteDirectory($this->id);
    }
}
