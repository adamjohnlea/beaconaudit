<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Services;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Notification\Domain\Repositories\EmailSubscriptionRepositoryInterface;
use App\Modules\Url\Domain\Models\Url;
use Twig\Environment;

final readonly class AlertNotifier
{
    public function __construct(
        private EmailSubscriptionRepositoryInterface $subscriptionRepository,
        private EmailServiceInterface $emailService,
        private Environment $twig,
    ) {
    }

    public function notifyIfThresholdBreached(Url $url, Audit $audit, ?Audit $previousAudit): void
    {
        if (!$url->isAlertsEnabled()) {
            return;
        }

        if ($url->getProjectId() === null) {
            return;
        }

        $currentScore = $audit->getScore()->getValue();
        $previousScore = $previousAudit?->getScore()->getValue();

        $scoreThresholdBreached = $url->getAlertThresholdScore() !== null
            && $currentScore <= $url->getAlertThresholdScore();

        $dropThresholdBreached = $url->getAlertThresholdDrop() !== null
            && $previousScore !== null
            && ($previousScore - $currentScore) >= $url->getAlertThresholdDrop();

        if (!$scoreThresholdBreached && !$dropThresholdBreached) {
            return;
        }

        $subscribers = $this->subscriptionRepository->findByProjectId($url->getProjectId());
        if ($subscribers === []) {
            return;
        }

        $scoreDrop = $previousScore !== null ? $previousScore - $currentScore : null;

        $body = $this->twig->render('emails/alert-score.twig', [
            'urlName' => $url->getName() ?? $url->getUrl()->getValue(),
            'urlAddress' => $url->getUrl()->getValue(),
            'currentScore' => $currentScore,
            'previousScore' => $previousScore,
            'scoreDrop' => $scoreDrop,
            'thresholdScore' => $url->getAlertThresholdScore(),
            'thresholdDrop' => $url->getAlertThresholdDrop(),
            'scoreThresholdBreached' => $scoreThresholdBreached,
            'dropThresholdBreached' => $dropThresholdBreached,
        ]);

        $subject = 'Score Alert: ' . ($url->getName() ?? $url->getUrl()->getValue());

        foreach ($subscribers as $subscriber) {
            try {
                $this->emailService->send(
                    $subscriber->getEmail()->getValue(),
                    $subject,
                    $body,
                );
            } catch (\Throwable $e) {
                error_log(
                    'Failed to send alert email to '
                    . $subscriber->getEmail()->getValue()
                    . ' for URL ' . $url->getUrl()->getValue()
                    . ': ' . $e->getMessage(),
                );
            }
        }
    }
}
