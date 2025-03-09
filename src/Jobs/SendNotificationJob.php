<?php

namespace RingleSoft\DbArchive\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;


class SendNotificationJob implements ShouldQueue
{
    use Queueable, SerializesModels, InteractsWithQueue, Dispatchable;

    protected String $email;

    public function __construct(String $email)
    {
        $this->email = $email;
    }

    public function handle(): void
    {
        // Send a notification to the user
    }

}
