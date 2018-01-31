<?php

namespace CipeMotion\Medialibrary\Observers;

use Ramsey\Uuid\Uuid;
use CipeMotion\Medialibrary\Entities\File;
use Illuminate\Foundation\Bus\DispatchesJobs;
use CipeMotion\Medialibrary\Jobs\DeleteFileJob;

class FileObserver
{
    use DispatchesJobs;

    public function creating(File $file)
    {
        if (is_null($file->getAttribute('id'))) {
            $file->id = Uuid::uuid4()->toString();
        }
    }

    public function created(File $file)
    {
        $groupTransformations = $file->getGroupTransformations();

        if (count($groupTransformations)) {
            foreach (array_keys($groupTransformations) as $name) {
                $file->requestTransformation($name);
            }
        }
    }

    public function deleted(File $file)
    {
        $this->dispatch(new DeleteFileJob($file->id, $file->disk));
    }
}
