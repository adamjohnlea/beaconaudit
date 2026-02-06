<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Api;

final readonly class ApiResponse
{
    /**
     * @param int                                                                                                       $score
     * @param array<array{id: string, title: string, description: string, score: float|null, scoreDisplayMode: string}> $audits
     * @param string                                                                                                    $rawJson
     */
    public function __construct(
        private int $score,
        private array $audits,
        private string $rawJson,
    ) {
    }

    public static function fromJson(string $json): self
    {
        /** @var array{lighthouseResult?: array{categories?: array{accessibility?: array{score?: float|int|null}}, audits?: array<string, array{id: string, title: string, description: string, score: float|int|null, scoreDisplayMode: string}>}} $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $score = 0;
        if (isset($data['lighthouseResult']['categories']['accessibility']['score'])) {
            $rawScore = $data['lighthouseResult']['categories']['accessibility']['score'];
            $score = (int) round($rawScore * 100);
        }

        $audits = [];
        $rawAudits = $data['lighthouseResult']['audits'] ?? [];

        foreach ($rawAudits as $audit) {
            if ($audit['scoreDisplayMode'] === 'binary' && $audit['score'] !== null && $audit['score'] < 1) {
                $audits[] = [
                    'id' => $audit['id'],
                    'title' => $audit['title'],
                    'description' => $audit['description'],
                    'score' => (float) $audit['score'],
                    'scoreDisplayMode' => $audit['scoreDisplayMode'],
                ];
            }
        }

        return new self($score, $audits, $json);
    }

    public function getScore(): int
    {
        return $this->score;
    }

    /**
     * @return array<array{id: string, title: string, description: string, score: float|null, scoreDisplayMode: string}>
     */
    public function getFailingAudits(): array
    {
        return $this->audits;
    }

    public function getRawJson(): string
    {
        return $this->rawJson;
    }
}
