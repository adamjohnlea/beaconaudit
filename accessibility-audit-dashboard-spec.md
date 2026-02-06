# Accessibility Audit Dashboard - Project Specification

## Project Overview

Build a production-grade, cloud-based accessibility monitoring tool that automatically audits web pages using Google Lighthouse, tracks changes over time, and alerts when accessibility scores degrade. This tool will help monitor Superhive Market pages and other websites for accessibility compliance and regression.

**Development Philosophy:** Every feature is built with Test-Driven Development (TDD), passes PHPStan level 9 static analysis, and is architecturally modular for maintainability and extensibility.

## Technology Stack

### Backend
- **PHP 8.4** - Primary application language with strict types
- **SQLite** - Embedded database for storing URLs, audit results, and historical data
- **Composer** - Dependency management

### Code Quality & Testing
- **PHPUnit 11.x** - Unit and integration testing framework
- **PHPStan Level 9** - Static analysis with strictest rules
- **PHP-CS-Fixer** - Code style enforcement (PSR-12)
- **Infection** - Mutation testing for test quality validation (optional but recommended)

### Frontend
- **Twig 3.x** - Template engine for all views
- **Tailwind CSS 3.x** - Utility-first CSS framework for styling
- **Alpine.js 3.x** (optional) - Lightweight JavaScript for interactive elements if needed

### Development Environment
- **Laravel Herd** - Local development environment (PHP 8.4 server, easy database access)
- **Git** - Version control with conventional commits
- **Composer scripts** - Automated testing and quality checks

### Deployment Target
- **DigitalOcean Droplet** ($6/month tier) or similar VPS
- **PHP 8.4-FPM** - FastCGI Process Manager
- **Nginx** - Web server
- **Cron** - Automated scheduling for background audits
- **Supervisor** - Process monitoring for background workers (optional)

### External Services
- **Google PageSpeed Insights API** - Free tier for running Lighthouse audits programmatically

## Development Standards

### Test-Driven Development (TDD)

**Mandatory TDD Workflow for Every Feature:**

1. **Red Phase** - Write failing test first
   - Write test that describes desired behavior
   - Test must fail initially (proves test is valid)
   - Use descriptive test names: `test_audit_service_throws_exception_when_api_returns_error`

2. **Green Phase** - Write minimum code to pass test
   - Implement only what's needed to make test pass
   - No extra features, no premature optimization

3. **Refactor Phase** - Improve code while keeping tests green
   - Clean up implementation
   - Ensure PHPStan level 9 compliance
   - Run full test suite after each refactor

**Test Coverage Requirements:**
- Minimum 95% code coverage for all business logic
- 100% coverage for critical paths (audit execution, data storage, comparisons)
- Integration tests for all API interactions
- Unit tests for all services, models, and value objects

**Test Organization:**
```
/tests
├── Unit/              # Pure unit tests (no dependencies)
│   ├── Models/
│   ├── Services/
│   └── ValueObjects/
├── Integration/       # Tests with database or API
│   ├── Repositories/
│   └── Services/
├── Feature/           # End-to-end feature tests
│   ├── AuditWorkflow/
│   └── Dashboard/
└── TestCase.php       # Base test case with helpers
```

### PHPStan Level 9 Compliance

**Strict Type Safety:**
```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Audit;
use App\Repositories\AuditRepositoryInterface;

final readonly class AuditService
{
    public function __construct(
        private AuditRepositoryInterface $auditRepository,
    ) {}
    
    public function createAudit(int $urlId, int $score): Audit
    {
        // All types explicitly declared, no mixed types
    }
}
```

**Required PHPStan Configuration:**
```neon
# phpstan.neon
parameters:
    level: 9
    paths:
        - src
    excludePaths:
        - src/Database/migrations
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    reportUnmatchedIgnoredErrors: true
```

**Zero Tolerance for:**
- Mixed types (must use union types or generics)
- Undefined variables
- Undefined array keys
- Unsafe calls to methods/properties
- Unused variables
- Dead code

**Type Coverage:**
- All class properties must have explicit types
- All method parameters must have type hints
- All method return types must be declared
- Use `@param` and `@return` PHPDoc only for complex generics

### Modularity & Architecture

**Principles:**
- **SOLID** principles strictly enforced
- **Dependency Injection** throughout (constructor injection preferred)
- **Interface Segregation** - depend on abstractions, not concretions
- **Single Responsibility** - each class has one reason to change
- **Open/Closed** - open for extension, closed for modification

**Layered Architecture:**
```
Presentation Layer (Controllers, Views)
    ↓
Application Layer (Services, Use Cases)
    ↓
Domain Layer (Models, Value Objects, Interfaces)
    ↓
Infrastructure Layer (Repositories, External APIs)
```

**Module Structure Example:**
```
/src/Modules/Audit
├── Application/
│   ├── Services/
│   │   └── AuditService.php
│   └── UseCases/
│       ├── RunAuditUseCase.php
│       └── CompareAuditsUseCase.php
├── Domain/
│   ├── Models/
│   │   └── Audit.php
│   ├── ValueObjects/
│   │   ├── AccessibilityScore.php
│   │   └── AuditStatus.php
│   └── Repositories/
│       └── AuditRepositoryInterface.php
└── Infrastructure/
    ├── Repositories/
    │   └── SqliteAuditRepository.php
    └── Api/
        └── PageSpeedApiClient.php
```

### Code Quality Standards

**Automated Quality Gates:**
```bash
# All must pass before commit
composer test          # PHPUnit test suite
composer phpstan       # PHPStan level 9 analysis
composer cs-fix        # PHP-CS-Fixer (PSR-12)
composer coverage      # Generate coverage report (min 95%)
```

**Pre-commit Hook (required):**
```bash
#!/bin/bash
composer test && composer phpstan && composer cs-fix
```

**Continuous Integration Requirements:**
- All tests must pass
- PHPStan level 9 must pass with zero errors
- Code coverage must be ≥95%
- No CS-Fixer violations

## Core Functionality

### 1. URL Management Module

**Features:**
- Add individual URLs to monitor
- Bulk import URLs (paste list, CSV upload)
- Organize URLs by project/site groups
- Edit/delete monitored URLs
- Set custom audit frequency per URL or per group
- Enable/disable monitoring for specific URLs

**TDD Test Cases:**
```php
// Unit Tests
test_url_can_be_created_with_valid_data()
test_url_creation_throws_exception_for_invalid_url()
test_url_can_be_updated()
test_url_can_be_soft_deleted()
test_url_can_be_assigned_to_project()
test_url_frequency_can_be_changed()

// Integration Tests
test_url_is_persisted_to_database()
test_url_can_be_retrieved_with_relationships()
test_bulk_import_creates_multiple_urls()
test_bulk_import_validates_all_urls_before_saving()
```

**Value Objects:**
- `UrlAddress` - Validates and normalizes URLs
- `AuditFrequency` - Enum: DAILY, WEEKLY, BIWEEKLY, MONTHLY
- `ProjectName` - Validates project naming rules

**Interfaces:**
- `UrlRepositoryInterface` - CRUD operations
- `UrlValidatorInterface` - URL validation logic
- `BulkImportServiceInterface` - Batch operations

### 2. Audit Engine Module

**Features:**
- Trigger manual audits on-demand for any URL
- Run scheduled audits via cron jobs
- Use Google PageSpeed Insights API to fetch Lighthouse data
- Store audit results with timestamp in SQLite database
- Capture comprehensive metrics
- Handle API rate limiting gracefully
- Retry failed audits with exponential backoff

**TDD Test Cases:**
```php
// Unit Tests
test_audit_service_creates_audit_with_valid_data()
test_audit_service_throws_exception_when_url_not_found()
test_pagespeed_client_parses_api_response_correctly()
test_pagespeed_client_throws_exception_on_api_error()
test_pagespeed_client_handles_rate_limit_response()
test_retry_strategy_backs_off_exponentially()
test_audit_status_transitions_correctly()

// Integration Tests
test_audit_is_persisted_with_all_relationships()
test_audit_captures_all_issues_from_api_response()
test_failed_audit_is_retried_with_backoff()
test_successful_audit_updates_url_last_audited_timestamp()
test_concurrent_audits_for_same_url_are_prevented()
```

**Value Objects:**
- `AccessibilityScore` - 0-100 with validation
- `AuditStatus` - Enum: PENDING, IN_PROGRESS, COMPLETED, FAILED
- `IssueSeverity` - Enum: CRITICAL, SERIOUS, MODERATE, MINOR
- `IssueCategory` - Enum: COLOR_CONTRAST, ARIA, FORMS, IMAGES, etc.

**Interfaces:**
- `PageSpeedClientInterface` - API communication
- `AuditRepositoryInterface` - Audit persistence
- `RetryStrategyInterface` - Retry logic
- `RateLimiterInterface` - Rate limiting

**Domain Events:**
- `AuditStarted` - When audit begins
- `AuditCompleted` - When audit succeeds
- `AuditFailed` - When audit fails
- `RateLimitEncountered` - When API throttles request

### 3. Historical Tracking & Comparison Module

**Features:**
- Store all audit results indefinitely
- Compare current audit against previous audit
- Track score trends over time
- Identify new issues vs. resolved issues
- Calculate change deltas

**TDD Test Cases:**
```php
// Unit Tests
test_comparison_service_identifies_new_issues()
test_comparison_service_identifies_resolved_issues()
test_comparison_service_calculates_score_delta()
test_trend_calculator_determines_improving_trend()
test_trend_calculator_determines_degrading_trend()
test_trend_calculator_determines_stable_trend()
test_comparison_handles_first_audit_edge_case()

// Integration Tests
test_historical_audits_are_retrieved_in_correct_order()
test_comparison_persists_delta_information()
test_trend_graph_data_is_generated_correctly()
```

**Value Objects:**
- `ScoreDelta` - Score change with direction
- `Trend` - Enum: IMPROVING, DEGRADING, STABLE
- `IssueComparison` - New, resolved, persistent issues

**Interfaces:**
- `ComparisonServiceInterface` - Audit comparison logic
- `TrendCalculatorInterface` - Trend analysis
- `HistoricalRepositoryInterface` - Time-series queries

### 4. Dashboard & Reporting Module

**Features:**
- Main dashboard with overview statistics
- Individual URL detail pages
- Historical trend visualization
- Issue breakdown and categorization
- Export functionality (PDF, CSV)
- Filtering and search

**TDD Test Cases:**
```php
// Unit Tests
test_dashboard_statistics_calculator_computes_averages()
test_dashboard_statistics_identifies_critical_count()
test_url_filter_applies_score_range_correctly()
test_url_search_matches_partial_names()
test_export_service_generates_valid_csv()
test_pdf_generator_creates_valid_pdf()

// Integration Tests
test_dashboard_loads_with_correct_data()
test_url_detail_page_shows_all_audits()
test_filtering_returns_correct_subset()
test_exported_csv_contains_all_expected_columns()
```

**Interfaces:**
- `DashboardStatisticsInterface` - Aggregate calculations
- `ReportGeneratorInterface` - Report creation
- `ExportServiceInterface` - Data export
- `FilterServiceInterface` - Query filtering

### 5. Notification & Alert Module (Future Phase)

**Features:**
- Configure alert thresholds
- Email notifications
- Slack webhooks (optional)
- Notification preferences per user
- Weekly digest reports

**TDD Test Cases:**
```php
// Unit Tests
test_alert_evaluator_triggers_on_score_threshold()
test_alert_evaluator_triggers_on_score_drop()
test_alert_evaluator_triggers_on_critical_issues()
test_notification_composer_creates_valid_email()
test_notification_is_not_sent_for_stable_scores()
test_digest_aggregator_groups_changes_correctly()

// Integration Tests
test_alert_is_sent_when_threshold_exceeded()
test_duplicate_alerts_are_prevented()
test_weekly_digest_includes_all_changes()
```

**Interfaces:**
- `NotificationServiceInterface` - Notification delivery
- `AlertEvaluatorInterface` - Threshold checking
- `DigestGeneratorInterface` - Summary creation

## Database Schema

### Table Definitions with Constraints

```sql
-- Projects
CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE INDEX idx_projects_name ON projects(name);

-- URLs
CREATE TABLE urls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER,
    url TEXT NOT NULL UNIQUE,
    name TEXT,
    audit_frequency TEXT NOT NULL CHECK(audit_frequency IN ('daily', 'weekly', 'biweekly', 'monthly')),
    enabled BOOLEAN NOT NULL DEFAULT 1,
    alert_threshold_score INTEGER CHECK(alert_threshold_score >= 0 AND alert_threshold_score <= 100),
    alert_threshold_drop INTEGER CHECK(alert_threshold_drop >= 0),
    last_audited_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE INDEX idx_urls_project_id ON urls(project_id);
CREATE INDEX idx_urls_enabled ON urls(enabled);
CREATE INDEX idx_urls_audit_frequency ON urls(audit_frequency);
CREATE INDEX idx_urls_last_audited_at ON urls(last_audited_at);

-- Audits
CREATE TABLE audits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url_id INTEGER NOT NULL,
    score INTEGER NOT NULL CHECK(score >= 0 AND score <= 100),
    status TEXT NOT NULL CHECK(status IN ('pending', 'in_progress', 'completed', 'failed')),
    audit_date DATETIME NOT NULL,
    raw_response TEXT,
    error_message TEXT,
    retry_count INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE
);

CREATE INDEX idx_audits_url_id ON audits(url_id);
CREATE INDEX idx_audits_audit_date ON audits(audit_date);
CREATE INDEX idx_audits_status ON audits(status);
CREATE INDEX idx_audits_url_date ON audits(url_id, audit_date DESC);

-- Issues
CREATE TABLE issues (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    audit_id INTEGER NOT NULL,
    severity TEXT NOT NULL CHECK(severity IN ('critical', 'serious', 'moderate', 'minor')),
    category TEXT NOT NULL,
    description TEXT NOT NULL,
    element_selector TEXT,
    help_url TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
);

CREATE INDEX idx_issues_audit_id ON issues(audit_id);
CREATE INDEX idx_issues_severity ON issues(severity);
CREATE INDEX idx_issues_category ON issues(category);

-- Audit Comparisons (denormalized for performance)
CREATE TABLE audit_comparisons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    current_audit_id INTEGER NOT NULL,
    previous_audit_id INTEGER NOT NULL,
    score_delta INTEGER NOT NULL,
    new_issues_count INTEGER NOT NULL DEFAULT 0,
    resolved_issues_count INTEGER NOT NULL DEFAULT 0,
    persistent_issues_count INTEGER NOT NULL DEFAULT 0,
    trend TEXT NOT NULL CHECK(trend IN ('improving', 'degrading', 'stable')),
    created_at DATETIME NOT NULL,
    FOREIGN KEY (current_audit_id) REFERENCES audits(id) ON DELETE CASCADE,
    FOREIGN KEY (previous_audit_id) REFERENCES audits(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX idx_audit_comparisons_current ON audit_comparisons(current_audit_id);
CREATE INDEX idx_audit_comparisons_previous ON audit_comparisons(previous_audit_id);

-- Notifications
CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url_id INTEGER NOT NULL,
    audit_id INTEGER NOT NULL,
    notification_type TEXT NOT NULL,
    channel TEXT NOT NULL CHECK(channel IN ('email', 'slack', 'in_app')),
    sent_at DATETIME,
    failed_at DATETIME,
    error_message TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE,
    FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
);

CREATE INDEX idx_notifications_url_id ON notifications(url_id);
CREATE INDEX idx_notifications_sent_at ON notifications(sent_at);
CREATE INDEX idx_notifications_channel ON notifications(channel);
```

## Application Structure

```
/project-root
├── /public                          # Web root
│   ├── index.php                   # Front controller
│   ├── /css
│   │   └── app.css                 # Compiled Tailwind CSS
│   └── /js
│       └── app.js                  # Optional Alpine.js
├── /src
│   ├── /Shared                     # Shared kernel
│   │   ├── /ValueObjects
│   │   │   ├── Email.php
│   │   │   └── DateTimeImmutable.php
│   │   ├── /Interfaces
│   │   │   ├── RepositoryInterface.php
│   │   │   └── EventDispatcherInterface.php
│   │   └── /Exceptions
│   │       ├── DomainException.php
│   │       └── ValidationException.php
│   ├── /Modules
│   │   ├── /Url
│   │   │   ├── /Application
│   │   │   │   ├── /Services
│   │   │   │   │   ├── UrlService.php
│   │   │   │   │   └── BulkImportService.php
│   │   │   │   └── /UseCases
│   │   │   │       ├── CreateUrlUseCase.php
│   │   │   │       ├── UpdateUrlUseCase.php
│   │   │   │       └── DeleteUrlUseCase.php
│   │   │   ├── /Domain
│   │   │   │   ├── /Models
│   │   │   │   │   ├── Url.php
│   │   │   │   │   └── Project.php
│   │   │   │   ├── /ValueObjects
│   │   │   │   │   ├── UrlAddress.php
│   │   │   │   │   ├── AuditFrequency.php
│   │   │   │   │   └── ProjectName.php
│   │   │   │   ├── /Repositories
│   │   │   │   │   ├── UrlRepositoryInterface.php
│   │   │   │   │   └── ProjectRepositoryInterface.php
│   │   │   │   └── /Events
│   │   │   │       ├── UrlCreated.php
│   │   │   │       └── UrlDeleted.php
│   │   │   └── /Infrastructure
│   │   │       ├── /Repositories
│   │   │       │   ├── SqliteUrlRepository.php
│   │   │       │   └── SqliteProjectRepository.php
│   │   │       └── /Validators
│   │   │           └── UrlValidator.php
│   │   ├── /Audit
│   │   │   ├── /Application
│   │   │   │   ├── /Services
│   │   │   │   │   ├── AuditService.php
│   │   │   │   │   ├── ComparisonService.php
│   │   │   │   │   └── TrendCalculator.php
│   │   │   │   └── /UseCases
│   │   │   │       ├── RunAuditUseCase.php
│   │   │   │       ├── CompareAuditsUseCase.php
│   │   │   │       └── GetHistoricalTrendsUseCase.php
│   │   │   ├── /Domain
│   │   │   │   ├── /Models
│   │   │   │   │   ├── Audit.php
│   │   │   │   │   ├── Issue.php
│   │   │   │   │   └── AuditComparison.php
│   │   │   │   ├── /ValueObjects
│   │   │   │   │   ├── AccessibilityScore.php
│   │   │   │   │   ├── AuditStatus.php
│   │   │   │   │   ├── IssueSeverity.php
│   │   │   │   │   ├── IssueCategory.php
│   │   │   │   │   ├── ScoreDelta.php
│   │   │   │   │   └── Trend.php
│   │   │   │   ├── /Repositories
│   │   │   │   │   ├── AuditRepositoryInterface.php
│   │   │   │   │   └── IssueRepositoryInterface.php
│   │   │   │   └── /Events
│   │   │   │       ├── AuditStarted.php
│   │   │   │       ├── AuditCompleted.php
│   │   │   │       └── AuditFailed.php
│   │   │   └── /Infrastructure
│   │   │       ├── /Repositories
│   │   │       │   ├── SqliteAuditRepository.php
│   │   │       │   └── SqliteIssueRepository.php
│   │   │       ├── /Api
│   │   │       │   ├── PageSpeedApiClient.php
│   │   │       │   ├── ApiResponse.php
│   │   │       │   └── ApiException.php
│   │   │       └── /RateLimiting
│   │   │           ├── RateLimiter.php
│   │   │           └── RetryStrategy.php
│   │   ├── /Dashboard
│   │   │   ├── /Application
│   │   │   │   ├── /Services
│   │   │   │   │   ├── DashboardStatistics.php
│   │   │   │   │   └── FilterService.php
│   │   │   │   └── /UseCases
│   │   │   │       ├── GetDashboardDataUseCase.php
│   │   │   │       └── GetUrlDetailUseCase.php
│   │   │   └── /Domain
│   │   │       ├── /ValueObjects
│   │   │       │   ├── OverviewStatistics.php
│   │   │       │   └── FilterCriteria.php
│   │   │       └── /Repositories
│   │   │           └── DashboardRepositoryInterface.php
│   │   ├── /Reporting
│   │   │   ├── /Application
│   │   │   │   ├── /Services
│   │   │   │   │   ├── PdfReportGenerator.php
│   │   │   │   │   └── CsvExportService.php
│   │   │   │   └── /UseCases
│   │   │   │       ├── GenerateReportUseCase.php
│   │   │   │       └── ExportDataUseCase.php
│   │   │   └── /Domain
│   │   │       ├── /ValueObjects
│   │   │       │   ├── ReportFormat.php
│   │   │       │   └── ExportFormat.php
│   │   │       └── /Interfaces
│   │   │           ├── ReportGeneratorInterface.php
│   │   │           └── ExportServiceInterface.php
│   │   └── /Notification
│   │       ├── /Application
│   │       │   ├── /Services
│   │       │   │   ├── NotificationService.php
│   │       │   │   ├── AlertEvaluator.php
│   │       │   │   └── DigestGenerator.php
│   │       │   └── /UseCases
│   │       │       ├── SendAlertUseCase.php
│   │       │       └── GenerateDigestUseCase.php
│   │       ├── /Domain
│   │       │   ├── /Models
│   │       │   │   └── Notification.php
│   │       │   ├── /ValueObjects
│   │       │   │   ├── NotificationType.php
│   │       │   │   ├── NotificationChannel.php
│   │       │   │   └── AlertThreshold.php
│   │       │   ├── /Repositories
│   │       │   │   └── NotificationRepositoryInterface.php
│   │       │   └── /Events
│   │       │       └── AlertTriggered.php
│   │       └── /Infrastructure
│   │           ├── /Channels
│   │           │   ├── EmailChannel.php
│   │           │   └── SlackChannel.php
│   │           └── /Repositories
│   │               └── SqliteNotificationRepository.php
│   ├── /Http
│   │   ├── /Controllers
│   │   │   ├── DashboardController.php
│   │   │   ├── UrlController.php
│   │   │   ├── AuditController.php
│   │   │   └── ReportController.php
│   │   ├── /Middleware
│   │   │   ├── AuthenticationMiddleware.php
│   │   │   └── CsrfMiddleware.php
│   │   └── Router.php
│   ├── /Database
│   │   ├── Database.php            # PDO wrapper with type safety
│   │   ├── /Migrations
│   │   │   ├── 001_create_projects_table.sql
│   │   │   ├── 002_create_urls_table.sql
│   │   │   ├── 003_create_audits_table.sql
│   │   │   ├── 004_create_issues_table.sql
│   │   │   ├── 005_create_audit_comparisons_table.sql
│   │   │   └── 006_create_notifications_table.sql
│   │   └── MigrationRunner.php
│   ├── /Views                      # Twig templates
│   │   ├── layouts/
│   │   │   └── base.twig
│   │   ├── dashboard/
│   │   │   ├── index.twig
│   │   │   └── statistics.twig
│   │   ├── urls/
│   │   │   ├── index.twig
│   │   │   ├── show.twig
│   │   │   ├── create.twig
│   │   │   └── edit.twig
│   │   ├── audits/
│   │   │   ├── show.twig
│   │   │   └── history.twig
│   │   └── reports/
│   │       └── index.twig
│   └── bootstrap.php               # Application bootstrap
├── /cron                           # Scheduled task scripts
│   ├── run-scheduled-audits.php
│   └── send-weekly-digests.php
├── /storage
│   ├── database.sqlite
│   ├── /logs
│   │   ├── app.log
│   │   └── cron.log
│   └── /cache
├── /config
│   ├── config.php                  # Application configuration
│   ├── database.php
│   └── services.php                # DI container configuration
├── /tests
│   ├── Unit/
│   │   ├── Modules/
│   │   │   ├── Url/
│   │   │   │   ├── UrlServiceTest.php
│   │   │   │   ├── UrlAddressTest.php
│   │   │   │   └── AuditFrequencyTest.php
│   │   │   ├── Audit/
│   │   │   │   ├── AuditServiceTest.php
│   │   │   │   ├── ComparisonServiceTest.php
│   │   │   │   ├── AccessibilityScoreTest.php
│   │   │   │   └── TrendCalculatorTest.php
│   │   │   └── Notification/
│   │   │       ├── AlertEvaluatorTest.php
│   │   │       └── DigestGeneratorTest.php
│   │   └── Shared/
│   │       └── ValueObjects/
│   │           └── EmailTest.php
│   ├── Integration/
│   │   ├── Repositories/
│   │   │   ├── SqliteUrlRepositoryTest.php
│   │   │   └── SqliteAuditRepositoryTest.php
│   │   └── Api/
│   │       └── PageSpeedApiClientTest.php
│   ├── Feature/
│   │   ├── AuditWorkflowTest.php
│   │   ├── DashboardTest.php
│   │   └── NotificationTest.php
│   └── TestCase.php
├── composer.json
├── composer.lock
├── phpunit.xml
├── phpstan.neon
├── .php-cs-fixer.php
├── tailwind.config.js
├── package.json
└── README.md
```

## Configuration Files

### composer.json
```json
{
    "name": "accessibility-audit/dashboard",
    "description": "Production-grade accessibility monitoring dashboard",
    "type": "project",
    "require": {
        "php": "^8.4",
        "ext-pdo": "*",
        "ext-json": "*",
        "twig/twig": "^3.8",
        "symfony/http-foundation": "^7.0",
        "symfony/routing": "^7.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^1.10",
        "phpstan/phpstan-strict-rules": "^1.5",
        "friendsofphp/php-cs-fixer": "^3.48",
        "infection/infection": "^0.27"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "test:coverage": "phpunit --coverage-html coverage",
        "phpstan": "phpstan analyse --memory-limit=512M",
        "cs-fix": "php-cs-fixer fix --config=.php-cs-fixer.php",
        "cs-check": "php-cs-fixer fix --config=.php-cs-fixer.php --dry-run --diff",
        "infection": "infection --min-msi=90 --min-covered-msi=95",
        "quality": [
            "@cs-check",
            "@phpstan",
            "@test"
        ]
    },
    "config": {
        "sort-packages": true,
        "optimize-autoloader": true
    }
}
```

### phpunit.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.0/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
         stopOnFailure="false"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
        <exclude>
            <directory>src/Database/Migrations</directory>
        </exclude>
    </source>
    <coverage>
        <report>
            <html outputDirectory="coverage"/>
            <text outputFile="php://stdout" showUncoveredFiles="true"/>
        </report>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_PATH" value=":memory:"/>
    </php>
</phpunit>
```

### phpstan.neon
```neon
parameters:
    level: 9
    paths:
        - src
    excludePaths:
        - src/Database/Migrations
    tmpDir: .phpstan
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    reportUnmatchedIgnoredErrors: true
    strictRules:
        allRules: true
```

### .php-cs-fixer.php
```php
<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRules([
        '@PSR12' => true,
        '@PHP84Migration' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'phpdoc_scalar' => true,
        'phpdoc_align' => ['align' => 'vertical'],
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
```

## Development Workflow

### Phase 1: Project Setup & Foundation (Week 1)

**TDD Cycle 1: Database Layer**
1. Write tests for database connection and migrations
2. Implement database wrapper with type safety
3. Create migration runner with rollback capability
4. Run PHPStan, ensure level 9 compliance
5. Run tests, achieve 100% coverage

**TDD Cycle 2: URL Module - Value Objects**
1. Write tests for `UrlAddress` value object (validation, normalization)
2. Implement `UrlAddress` with strict types
3. Write tests for `AuditFrequency` enum
4. Implement `AuditFrequency` enum
5. Write tests for `ProjectName` value object
6. Implement `ProjectName`
7. Run PHPStan level 9, run tests

**TDD Cycle 3: URL Module - Repository**
1. Write integration tests for `SqliteUrlRepository`
2. Define `UrlRepositoryInterface`
3. Implement `SqliteUrlRepository`
4. Run PHPStan, run tests, check coverage (≥95%)

**TDD Cycle 4: URL Module - Service Layer**
1. Write unit tests for `UrlService::create()`
2. Implement `UrlService::create()`
3. Write unit tests for `UrlService::update()`
4. Implement `UrlService::update()`
5. Write unit tests for `UrlService::delete()`
6. Implement `UrlService::delete()`
7. Run full test suite, PHPStan, coverage check

**TDD Cycle 5: URL Module - HTTP Layer**
1. Write feature tests for creating URL via HTTP
2. Implement `UrlController::create()`
3. Write feature tests for listing URLs
4. Implement `UrlController::index()`
5. Write feature tests for editing URLs
6. Implement `UrlController::edit()`
7. Run full quality checks

**Deliverable:** Working URL management with full CRUD, 95%+ coverage, PHPStan level 9 passing

### Phase 2: Audit Engine (Week 2-3)

**TDD Cycle 1: Value Objects & Domain Models**
1. Write tests for `AccessibilityScore` (0-100 validation)
2. Implement `AccessibilityScore`
3. Write tests for `AuditStatus` enum
4. Implement `AuditStatus`
5. Write tests for `IssueSeverity` and `IssueCategory` enums
6. Implement both enums
7. Write tests for `Audit` domain model
8. Implement `Audit` model
9. Run PHPStan level 9

**TDD Cycle 2: PageSpeed API Client**
1. Write unit tests for `PageSpeedApiClient` (mocked HTTP)
2. Implement API client with proper error handling
3. Write tests for API response parsing
4. Implement response parser
5. Write tests for rate limiting detection
6. Implement rate limit handling
7. Run tests with coverage check

**TDD Cycle 3: Retry Strategy**
1. Write tests for exponential backoff algorithm
2. Implement `RetryStrategy`
3. Write tests for max retry limits
4. Implement retry limits
5. Write integration tests with mocked API failures
6. Verify retry behavior
7. Run PHPStan, coverage check

**TDD Cycle 4: Audit Repository**
1. Write integration tests for `SqliteAuditRepository::save()`
2. Implement save method
3. Write tests for `findById()`, `findByUrlId()`, `findLatestByUrlId()`
4. Implement query methods
5. Write tests for issue persistence
6. Implement issue storage
7. Run full test suite

**TDD Cycle 5: Audit Service**
1. Write unit tests for `AuditService::runAudit()` (happy path)
2. Implement happy path
3. Write tests for error scenarios (API failure, invalid URL)
4. Implement error handling
5. Write tests for retry logic integration
6. Implement retry integration
7. Write tests for issue extraction from API response
8. Implement issue extraction
9. Run quality checks

**TDD Cycle 6: Scheduled Audits (Cron)**
1. Write tests for cron script (mocked time)
2. Implement cron script
3. Write tests for frequency filtering logic
4. Implement frequency checks
5. Write tests for batch audit execution
6. Implement batch processing
7. Run full suite with integration tests

**Deliverable:** Complete audit engine, API integration, scheduled audits, 95%+ coverage

### Phase 3: Historical Tracking & Comparison (Week 4)

**TDD Cycle 1: Comparison Value Objects**
1. Write tests for `ScoreDelta` calculation
2. Implement `ScoreDelta`
3. Write tests for `Trend` enum and logic
4. Implement `Trend` determination
5. Write tests for `IssueComparison` data structure
6. Implement `IssueComparison`

**TDD Cycle 2: Comparison Service**
1. Write unit tests for `ComparisonService::compare()` (two audits)
2. Implement comparison logic
3. Write tests for identifying new issues
4. Implement new issue detection
5. Write tests for identifying resolved issues
6. Implement resolved issue detection
7. Write tests for calculating deltas
8. Implement delta calculations
9. Run PHPStan, tests

**TDD Cycle 3: Trend Calculator**
1. Write tests for trend analysis (improving/degrading/stable)
2. Implement trend calculator
3. Write tests for historical data aggregation
4. Implement aggregation logic
5. Run quality checks

**TDD Cycle 4: Integration with Audit Flow**
1. Write tests for automatic comparison after audit completion
2. Implement post-audit comparison hook
3. Write tests for comparison persistence
4. Implement comparison storage
5. Run full test suite

**Deliverable:** Working comparison engine, trend analysis, automated comparison after audits

### Phase 4: Dashboard & Reporting (Week 5)

**TDD Cycle 1: Dashboard Statistics**
1. Write tests for `DashboardStatistics::calculate()`
2. Implement statistics calculator
3. Write tests for filtering logic
4. Implement filter service
5. Run tests

**TDD Cycle 2: Dashboard Controller**
1. Write feature tests for dashboard rendering
2. Implement `DashboardController::index()`
3. Write tests for data aggregation
4. Implement aggregation
5. Run quality checks

**TDD Cycle 3: Export Services**
1. Write tests for CSV export
2. Implement `CsvExportService`
3. Write tests for PDF generation
4. Implement `PdfReportGenerator`
5. Run tests with coverage

**TDD Cycle 4: Twig Templates**
1. Create Twig templates for dashboard
2. Create templates for URL detail pages
3. Create templates for reports
4. Test rendering with sample data

**Deliverable:** Fully functional dashboard, export capabilities, professional UI

### Phase 5: Deployment & Production Readiness (Week 6)

**Tasks:**
1. Set up DigitalOcean droplet
2. Configure Nginx with PHP 8.4-FPM
3. Deploy application
4. Configure cron jobs
5. Set up SSL with Let's Encrypt
6. Configure monitoring and logging
7. Run full test suite in production environment
8. Load testing (optional)

**Deliverable:** Live production application

### Phase 6: Notifications (Future - Week 7+)

**TDD Cycles for Alert System:**
1. Alert threshold evaluation
2. Notification composition
3. Email channel integration
4. Digest generation
5. Notification preferences

## API Integration Details

### Google PageSpeed Insights API

**Endpoint:**
```
GET https://www.googleapis.com/pagespeedonline/v5/runPagespeed
```

**Parameters:**
- `url` (required) - The URL to audit
- `category=accessibility` (required) - Focus on accessibility
- `strategy=desktop` or `strategy=mobile` - Device type

**Example Implementation (TDD Approach):**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Api;

use App\Modules\Audit\Infrastructure\Api\ApiException;

final readonly class PageSpeedApiClient implements PageSpeedClientInterface
{
    private const API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}
    
    /**
     * @throws ApiException
     */
    public function runAudit(string $url): ApiResponse
    {
        $queryParams = http_build_query([
            'url' => $url,
            'category' => 'accessibility',
            'strategy' => 'desktop',
        ]);
        
        $response = $this->httpClient->get(self::API_URL . '?' . $queryParams);
        
        if ($response->getStatusCode() === 429) {
            throw new ApiException('Rate limit exceeded', 429);
        }
        
        if ($response->getStatusCode() !== 200) {
            throw new ApiException('API request failed', $response->getStatusCode());
        }
        
        return ApiResponse::fromJson($response->getBody());
    }
}
```

**Test Example:**

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use Tests\TestCase;
use App\Modules\Audit\Infrastructure\Api\PageSpeedApiClient;
use App\Modules\Audit\Infrastructure\Api\ApiException;

final class PageSpeedApiClientTest extends TestCase
{
    public function test_run_audit_returns_valid_response(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn(new HttpResponse(200, file_get_contents(__DIR__ . '/fixtures/valid_response.json')));
        
        $client = new PageSpeedApiClient($httpClient);
        $response = $client->runAudit('https://example.com');
        
        $this->assertInstanceOf(ApiResponse::class, $response);
        $this->assertGreaterThanOrEqual(0, $response->getScore());
        $this->assertLessThanOrEqual(100, $response->getScore());
    }
    
    public function test_run_audit_throws_exception_on_rate_limit(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('get')
            ->willReturn(new HttpResponse(429, ''));
        
        $client = new PageSpeedApiClient($httpClient);
        
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Rate limit exceeded');
        
        $client->runAudit('https://example.com');
    }
}
```

**Rate Limits:**
- Free tier: 25,000 queries per day
- Implement request spacing (minimum 1 second between requests)
- Cache results for minimum 1 hour

## Deployment Guide

### Server Setup (DigitalOcean)

**1. Create Droplet**
- Ubuntu 24.04 LTS
- Basic plan: $6/month
- Add SSH key

**2. Initial Server Configuration**
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Nginx
sudo apt install nginx -y

# Install PHP 8.4 and extensions
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-mbstring php8.4-xml php8.4-curl -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js (for Tailwind)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install nodejs -y
```

**3. Configure Nginx**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/accessibility-dashboard/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # PHP-FPM performance
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /storage {
        deny all;
    }
}
```

**4. Deploy Application**
```bash
# Create directory
sudo mkdir -p /var/www/accessibility-dashboard
sudo chown $USER:$USER /var/www/accessibility-dashboard

# Clone repository (or upload files)
cd /var/www/accessibility-dashboard
git clone your-repo.git .

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Set permissions
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage

# Run migrations
php src/Database/MigrationRunner.php
```

**5. Configure Cron**
```bash
# Edit crontab for www-data user
sudo crontab -e -u www-data

# Add cron jobs
# Run scheduled audits daily at 2 AM
0 2 * * * /usr/bin/php /var/www/accessibility-dashboard/cron/run-scheduled-audits.php >> /var/www/accessibility-dashboard/storage/logs/cron.log 2>&1

# Send weekly digests every Monday at 9 AM
0 9 * * 1 /usr/bin/php /var/www/accessibility-dashboard/cron/send-weekly-digests.php >> /var/www/accessibility-dashboard/storage/logs/digest.log 2>&1
```

**6. SSL with Let's Encrypt**
```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d your-domain.com
sudo certbot renew --dry-run
```

### Monitoring & Logging

**Application Logs:**
- `/var/www/accessibility-dashboard/storage/logs/app.log`
- `/var/www/accessibility-dashboard/storage/logs/cron.log`

**Nginx Logs:**
- `/var/log/nginx/access.log`
- `/var/log/nginx/error.log`

**PHP-FPM Logs:**
- `/var/log/php8.4-fpm.log`

## Testing Strategy

### Unit Testing Philosophy

Every unit test should:
1. Test a single unit of behavior
2. Be independent of other tests
3. Run in milliseconds
4. Have no external dependencies
5. Use mocks/stubs for collaborators

**Example Unit Test:**
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use Tests\TestCase;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\Exceptions\InvalidScoreException;

final class AccessibilityScoreTest extends TestCase
{
    public function test_score_can_be_created_with_valid_value(): void
    {
        $score = new AccessibilityScore(85);
        
        $this->assertEquals(85, $score->getValue());
    }
    
    public function test_score_throws_exception_when_below_zero(): void
    {
        $this->expectException(InvalidScoreException::class);
        
        new AccessibilityScore(-1);
    }
    
    public function test_score_throws_exception_when_above_hundred(): void
    {
        $this->expectException(InvalidScoreException::class);
        
        new AccessibilityScore(101);
    }
    
    public function test_score_can_be_compared(): void
    {
        $score1 = new AccessibilityScore(80);
        $score2 = new AccessibilityScore(90);
        
        $this->assertTrue($score2->isGreaterThan($score1));
        $this->assertFalse($score1->isGreaterThan($score2));
    }
}
```

### Integration Testing Philosophy

Integration tests should:
1. Test interaction between components
2. Use real database (in-memory SQLite for speed)
3. Test repository implementations
4. Test API client interactions (with mocked HTTP)

**Example Integration Test:**
```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use Tests\TestCase;
use App\Modules\Url\Infrastructure\Repositories\SqliteUrlRepository;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;

final class SqliteUrlRepositoryTest extends TestCase
{
    private SqliteUrlRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SqliteUrlRepository($this->database);
        $this->runMigrations();
    }
    
    public function test_url_can_be_saved_and_retrieved(): void
    {
        $url = new Url(
            id: null,
            url: new UrlAddress('https://example.com'),
            name: 'Example Site',
            frequency: AuditFrequency::WEEKLY,
            enabled: true,
        );
        
        $savedUrl = $this->repository->save($url);
        
        $this->assertNotNull($savedUrl->getId());
        
        $retrievedUrl = $this->repository->findById($savedUrl->getId());
        
        $this->assertEquals($savedUrl->getId(), $retrievedUrl->getId());
        $this->assertEquals('https://example.com', $retrievedUrl->getUrl()->getValue());
    }
    
    public function test_repository_enforces_unique_url_constraint(): void
    {
        $url1 = new Url(
            id: null,
            url: new UrlAddress('https://example.com'),
            name: 'Example Site',
            frequency: AuditFrequency::WEEKLY,
            enabled: true,
        );
        
        $this->repository->save($url1);
        
        $url2 = new Url(
            id: null,
            url: new UrlAddress('https://example.com'),
            name: 'Duplicate Site',
            frequency: AuditFrequency::DAILY,
            enabled: true,
        );
        
        $this->expectException(\PDOException::class);
        
        $this->repository->save($url2);
    }
}
```

### Feature Testing Philosophy

Feature tests should:
1. Test complete user workflows
2. Test HTTP endpoints end-to-end
3. Simulate real user interactions
4. Use database transactions for isolation

**Example Feature Test:**
```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class AuditWorkflowTest extends TestCase
{
    public function test_user_can_create_url_and_trigger_audit(): void
    {
        // Create URL via HTTP POST
        $response = $this->post('/urls', [
            'url' => 'https://example.com',
            'name' => 'Example Site',
            'frequency' => 'weekly',
        ]);
        
        $this->assertEquals(302, $response->getStatusCode());
        
        // Verify URL exists in database
        $url = $this->database->query(
            'SELECT * FROM urls WHERE url = ?',
            ['https://example.com']
        )->fetch();
        
        $this->assertNotNull($url);
        
        // Trigger manual audit
        $response = $this->post("/audits/run/{$url['id']}");
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify audit was created
        $audit = $this->database->query(
            'SELECT * FROM audits WHERE url_id = ?',
            [$url['id']]
        )->fetch();
        
        $this->assertNotNull($audit);
        $this->assertEquals('completed', $audit['status']);
        $this->assertGreaterThanOrEqual(0, $audit['score']);
    }
}
```

## Code Quality Metrics

### Required Metrics

| Metric | Minimum Threshold | Tool |
|--------|-------------------|------|
| Test Coverage | 95% | PHPUnit |
| PHPStan Level | 9 (strictest) | PHPStan |
| Code Style | PSR-12 | PHP-CS-Fixer |
| Mutation Score | 90% | Infection (optional) |

### Continuous Integration Pipeline

**GitHub Actions Example:**
```yaml
name: CI

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP 8.4
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: pdo, sqlite3, mbstring
          
      - name: Install dependencies
        run: composer install --prefer-dist
        
      - name: Run tests
        run: composer test
        
      - name: Run PHPStan
        run: composer phpstan
        
      - name: Check code style
        run: composer cs-check
        
      - name: Generate coverage
        run: composer test:coverage
```

## Security Considerations

### Input Validation
- All user inputs validated with value objects
- URL validation prevents SSRF attacks
- SQL injection prevented with prepared statements

### Authentication (Future)
- Basic HTTP authentication for MVP
- Session-based authentication for multi-user
- CSRF protection on all forms

### Database Security
- Database file outside web root
- Read-only permissions for cron user
- Regular backups

### API Security
- Rate limiting for API calls
- User-Agent header to identify application
- Timeout limits on HTTP requests

## Performance Optimization

### Database Optimization
- Indexes on all foreign keys
- Indexes on frequently queried columns
- Query optimization with EXPLAIN

### Caching Strategy (Future)
- Cache API responses for 1 hour
- Cache dashboard statistics for 5 minutes
- Redis for distributed caching

### Background Jobs
- Process audits asynchronously
- Queue system for batch operations
- Supervisor for job workers

## Success Criteria

This project is successful when:

1. **Functional Requirements:**
   - ✅ Can add and manage URLs
   - ✅ Audits run automatically on schedule
   - ✅ Dashboard shows current status of all URLs
   - ✅ Historical comparisons identify regressions
   - ✅ Reports can be exported as PDF/CSV

2. **Technical Requirements:**
   - ✅ All code passes PHPStan level 9
   - ✅ Test coverage ≥95%
   - ✅ All features built with TDD
   - ✅ Zero deprecation warnings
   - ✅ PSR-12 compliant

3. **Production Requirements:**
   - ✅ Deployed to DigitalOcean
   - ✅ SSL enabled
   - ✅ Cron jobs running reliably
   - ✅ Error logging configured
   - ✅ Monitoring in place

4. **User Experience:**
   - ✅ Dashboard loads in <2 seconds
   - ✅ Audit results easy to understand
   - ✅ Responsive on mobile devices
   - ✅ Professional appearance

## Future Enhancements

### Phase 7+
- Multi-user support with role-based access
- GraphQL API for external integrations
- Mobile app (iOS/Android)
- Webhook support for CI/CD integration
- Browser extension for quick URL addition
- AI-powered issue prioritization
- Performance audits (in addition to accessibility)
- SEO audits
- Custom audit rules engine
- White-label deployments

## Conclusion

This specification defines a production-grade accessibility audit dashboard built with professional software engineering practices:

- **TDD ensures correctness** - Every feature is tested before implementation
- **PHPStan level 9 ensures type safety** - No runtime type errors
- **Modular architecture ensures maintainability** - Easy to extend and modify
- **Comprehensive testing ensures reliability** - High confidence in production

The project prioritizes code quality, maintainability, and extensibility over rapid feature development. This foundation enables long-term success and easy addition of future features.

---

**For Claude Code:**

This is a professional-grade project. Every single line of code must:
1. Be preceded by a failing test (TDD)
2. Pass PHPStan level 9 analysis
3. Follow PSR-12 code style
4. Be fully type-hinted with no mixed types
5. Be modular and follow SOLID principles

Do not compromise on quality. Build it right the first time.
