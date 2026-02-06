<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\Models\Issue;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Domain\ValueObjects\IssueCategory;
use App\Modules\Audit\Domain\ValueObjects\IssueSeverity;
use App\Modules\Audit\Infrastructure\Repositories\SqliteAuditRepository;
use App\Modules\Audit\Infrastructure\Repositories\SqliteIssueRepository;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use DateTimeImmutable;
use Tests\TestCase;

final class SqliteIssueRepositoryTest extends TestCase
{
    private SqliteIssueRepository $issueRepository;
    private SqliteAuditRepository $auditRepository;
    private SqliteUrlRepository $urlRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
        $this->issueRepository = new SqliteIssueRepository($this->database);
        $this->auditRepository = new SqliteAuditRepository($this->database);
        $this->urlRepository = new SqliteUrlRepository($this->database);
    }

    public function test_save_persists_issue_and_assigns_id(): void
    {
        $auditId = $this->createTestAudit();
        $issue = $this->createIssue($auditId);

        $saved = $this->issueRepository->save($issue);

        $this->assertNotNull($saved->getId());
        $this->assertSame('critical', $saved->getSeverity()->value);
    }

    public function test_save_many_persists_multiple_issues(): void
    {
        $auditId = $this->createTestAudit();

        $issues = [
            $this->createIssue($auditId, IssueSeverity::CRITICAL, IssueCategory::COLOR_CONTRAST),
            $this->createIssue($auditId, IssueSeverity::SERIOUS, IssueCategory::ARIA),
            $this->createIssue($auditId, IssueSeverity::MINOR, IssueCategory::IMAGES),
        ];

        $saved = $this->issueRepository->saveMany($issues);

        $this->assertCount(3, $saved);
        foreach ($saved as $issue) {
            $this->assertNotNull($issue->getId());
        }
    }

    public function test_find_by_audit_id_returns_all_issues(): void
    {
        $auditId = $this->createTestAudit();

        $this->issueRepository->saveMany([
            $this->createIssue($auditId, IssueSeverity::CRITICAL, IssueCategory::COLOR_CONTRAST),
            $this->createIssue($auditId, IssueSeverity::SERIOUS, IssueCategory::ARIA),
        ]);

        $found = $this->issueRepository->findByAuditId($auditId);

        $this->assertCount(2, $found);
    }

    public function test_find_by_audit_id_returns_empty_when_no_issues(): void
    {
        $this->assertSame([], $this->issueRepository->findByAuditId(999));
    }

    public function test_save_persists_all_fields(): void
    {
        $auditId = $this->createTestAudit();
        $issue = new Issue(
            id: null,
            auditId: $auditId,
            severity: IssueSeverity::SERIOUS,
            category: IssueCategory::FORMS,
            description: 'Form input missing label',
            elementSelector: 'input#email',
            helpUrl: 'https://dequeuniversity.com/rules/axe/4.4/label',
            createdAt: new DateTimeImmutable(),
        );

        $saved = $this->issueRepository->save($issue);
        $found = $this->issueRepository->findByAuditId($auditId);

        $this->assertCount(1, $found);
        $this->assertSame('serious', $found[0]->getSeverity()->value);
        $this->assertSame('forms', $found[0]->getCategory()->value);
        $this->assertSame('Form input missing label', $found[0]->getDescription());
        $this->assertSame('input#email', $found[0]->getElementSelector());
        $this->assertSame('https://dequeuniversity.com/rules/axe/4.4/label', $found[0]->getHelpUrl());
    }

    private function createTestAudit(): int
    {
        $now = new DateTimeImmutable();
        $url = new Url(
            id: null,
            projectId: null,
            url: new UrlAddress('https://example-' . uniqid() . '.com'),
            name: 'Test',
            auditFrequency: AuditFrequency::WEEKLY,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
        $savedUrl = $this->urlRepository->save($url);

        $audit = new Audit(
            id: null,
            urlId: $savedUrl->getId() ?? 0,
            score: new AccessibilityScore(85),
            status: AuditStatus::COMPLETED,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );
        $savedAudit = $this->auditRepository->save($audit);

        return $savedAudit->getId() ?? 0;
    }

    private function createIssue(
        int $auditId,
        IssueSeverity $severity = IssueSeverity::CRITICAL,
        IssueCategory $category = IssueCategory::COLOR_CONTRAST,
    ): Issue {
        return new Issue(
            id: null,
            auditId: $auditId,
            severity: $severity,
            category: $category,
            description: 'Test issue description',
            elementSelector: null,
            helpUrl: null,
            createdAt: new DateTimeImmutable(),
        );
    }
}
