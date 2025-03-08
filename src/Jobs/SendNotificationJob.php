<?php

namespace RingleSoft\DbArchive\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;


class SendNotificationJob implements ShouldQueue
{
    use Queueable, SerializesModels;

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
