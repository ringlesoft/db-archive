<?php

namespace RingleSoft\DbArchive\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Notification;
use RingleSoft\DbArchive\Notifications\DataArchivedNotification;
use RingleSoft\DbArchive\Notifications\DataArchivingFailedNotification;


class SendNotificationJob implements ShouldQueue
{
    use Queueable, SerializesModels, InteractsWithQueue, Dispatchable;

    protected String $email;
    protected bool $successful;

    public function __construct(String $email, bool $successful = true)
    {
        $this->email = $email;
        $this->successful = $successful;
    }

    public function handle(): void
    {
        Notification::route('mail', $this->email)->notify(
            $this->successful ? new DataArchivedNotification() : new DataArchivingFailedNotification(),
        );
    }

}
