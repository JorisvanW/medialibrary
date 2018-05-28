<?php

namespace CipeMotion\Medialibrary\Jobs;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class TransformFileQueuedJob extends TransformFileJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;
}
