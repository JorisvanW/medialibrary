<?php

namespace CipeMotion\Medialibrary\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use CipeMotion\Medialibrary\Exceptions\UnknownTransformerException;

class TransformFileQueuedJob extends TransformFileJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            parent::handle();
        } catch (UnknownTransformerException $e) {
            // This should fail immediatly since it's unrecoverable
            $this->fail($e);
        } catch (Exception $e) {
            // Fail with the exception the fifth time
            if ($this->attempts() >= $this->tries) {
                throw $e;
            }

            // Retry with an exponential backoff schedule (1, 4, 8, 16 minutes)
            $this->release(now()->addMinutes($this->attempts() <= 1 ? 1 : pow(2, $this->attempts() * 1)));
        }
    }
}
