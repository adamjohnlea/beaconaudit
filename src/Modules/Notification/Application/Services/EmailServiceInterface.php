<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Services;

interface EmailServiceInterface
{
    public function sendWithAttachment(
        string $to,
        string $subject,
        string $body,
        string $attachmentContent,
        string $attachmentFilename,
    ): void;
}
