# Planned Additions & Improvements

A roadmap of features, tools, and improvements to enhance Beacon Audit.

---

## Notifications & Alerts

### ~~Email PDF Reports After Audit~~ — DONE
~~Subscribe to projects and receive the PDF audit report by email (via AWS SES) when an audit completes. Per-user opt-in subscriptions, works from both manual audits and cron runs, one report per project per run.~~

### Email Alerts on Score Drops
Send email notifications when a URL's accessibility score drops below a configured threshold or falls by more than a set number of points between audits. The URL model already has `alertThresholdScore` and `alertThresholdDrop` fields — this connects them to the SES email infrastructure already in place.

### Slack Webhook Integration
Post audit results and score drop alerts to a Slack channel via incoming webhooks. Useful for teams that want real-time visibility without checking the dashboard.

### Weekly Digest Emails
Scheduled summary email sent to all users with: projects audited, average score changes, top issues discovered, and URLs that improved or degraded. Can build on the existing `SesEmailService` and subscription model.

### In-App Notification Centre
A notification bell in the dashboard header showing recent alerts (score drops, failed audits, new issues found). Mark as read/dismiss. The `notifications` database table already exists.

---

## Audit Engine Improvements

### API Response Caching
Cache PageSpeed API responses for a configurable period (default 1 hour) to avoid redundant API calls when re-auditing the same URL. Reduces API quota consumption and speeds up re-runs.

### Mobile Strategy Audits
Currently audits use `strategy=desktop` only. Add the option to audit with `strategy=mobile` as well, storing both results per audit. Many accessibility issues differ between viewport sizes.

### Batch Audit Queue
When running audits for an entire project, queue them with configurable concurrency and delay between requests to respect API rate limits. Show progress in the UI with a simple polling mechanism.

### Audit Scheduling Improvements
- **Custom cron expressions** — let users define exact schedules beyond the four fixed frequencies
- **Blackout windows** — skip audits during known deployment windows
- **Priority ordering** — audit URLs with worse scores more frequently

### Lighthouse CI Integration
Support running audits via a local Lighthouse CI server as an alternative to the PageSpeed API. Gives unlimited audits, more control over settings, and the ability to audit internal/staging sites.

---

## Dashboard & UI Enhancements

### Search & Filtering
Add a search bar and filter controls to URL lists, audit history, and issue tables. Filter by score range, severity, category, date range, or status.

### Pagination
Paginate audit history and issue lists for URLs with large numbers of audits. Currently all records load at once.

### Sortable Tables
Click column headers to sort by score, date, name, frequency, or issue count — both ascending and descending.

### Bulk Operations
Select multiple URLs with checkboxes and perform bulk actions: delete, move to a different project, enable/disable monitoring, change audit frequency, or trigger audits.

### Dark Mode Toggle
The app respects system preference via CSS, but adding a manual toggle in the header lets users override it. Store the preference in a cookie or user settings.

### Sparkline Score History
Show a small inline sparkline chart next to each URL in project tables, giving a quick visual of the score trend without needing to click into the detail view.

### Keyboard Shortcuts
Add keyboard navigation: `j`/`k` to move through URL lists, `Enter` to view details, `/` to focus search, `Escape` to go back.

---

## Reporting & Export

### Scheduled PDF Reports
Automatically generate and email project PDF reports on a configurable schedule (weekly/monthly). The email infrastructure and per-project subscriptions are already in place — this adds a dedicated cron script with its own schedule independent of audit runs.

### JSON/API Export
Add a JSON export option alongside CSV for programmatic consumption. Could serve as the foundation for a future REST API.

### Comparison Reports
Generate a report comparing two audits side-by-side for the same URL, highlighting new issues, resolved issues, and score changes. Export as PDF or CSV.

### Project-Wide Trend Report
A PDF report showing score trends across all URLs in a project over a configurable time period (30/60/90 days), with charts and summary statistics.

### Custom Report Builder
Let users pick which sections to include in PDF reports: summary only, issues only, specific categories, specific URLs, date ranges.

---

## New Tools

### CLI Audit Runner
A CLI tool to run a one-off audit for a specific URL and print results to stdout. Useful for scripting and CI/CD pipelines.

```bash
php cli/run-audit.php --url=https://example.com
```

### CLI Project Report
Generate a PDF report from the command line without needing the web UI.

```bash
php cli/generate-report.php --project=1 --output=report.pdf
```

### Import/Export Configuration
Export all projects, URLs, and settings as a JSON file for backup or migration. Import the same format to restore or replicate a setup.

```bash
php cli/export-config.php --output=config.json
php cli/import-config.php --input=config.json
```

### Database Maintenance Tool
A CLI tool for database housekeeping: prune old audits beyond a retention period, vacuum the SQLite database, and show database size statistics.

```bash
php cli/db-maintenance.php --prune-before=2024-01-01 --vacuum
```

### Health Check Endpoint
A `/health` endpoint returning JSON with: app version, database connectivity, disk space, last successful audit timestamp, and pending audit count. Useful for uptime monitoring.

---

## Security & Access Control

### Login Rate Limiting
Throttle failed login attempts (e.g. 5 attempts per 15 minutes per IP) to prevent brute-force attacks.

### Password Strength Requirements
Enforce minimum length, complexity rules, and check against common password lists during user creation and password changes.

### Audit Log
Record all significant user actions (login, URL changes, audit triggers, user management) in a queryable log table. Viewable by admins in the UI.

### API Key Management
If a REST API is added, provide API key generation, rotation, and revocation through the admin UI with per-key permission scopes.

---

## Performance & Infrastructure

### Database Query Optimisation
Add composite indexes for common query patterns, implement query result caching for dashboard statistics, and benchmark slow queries.

### Background Job Processing
Replace synchronous audit execution with a simple file-based or SQLite-based job queue. Audits run in background workers, and the UI polls for completion.

### Data Retention Policies
Configurable auto-archival of audits older than N days. Keep summary data (scores) but prune raw API responses and resolved issues to save disk space.

### SQLite Backup Automation
A cron-compatible script that creates timestamped SQLite backups with configurable retention (keep last N backups). Optionally upload to S3 or similar.

---

## Accessibility & Quality of Life

### Accessibility Self-Audit
Run Beacon Audit's own accessibility checks against the dashboard itself. An ironic but practical way to ensure the tool practises what it preaches.

### Onboarding Wizard
A first-run wizard that walks new users through: creating a project, adding URLs, configuring API key, running the first audit, and setting up cron.

### User Preferences
Per-user settings for: default project view, items per page, preferred export format, email notification preferences, and timezone.

### Issue Remediation Guides
Link each issue category to a curated guide explaining how to fix common accessibility problems, with code examples and before/after screenshots.

---

## Integrations

### GitHub/GitLab Integration
Automatically create issues in a linked repository when new accessibility problems are detected. Close them when resolved in subsequent audits.

### CI/CD Webhook Receiver
Accept incoming webhooks from CI/CD pipelines (GitHub Actions, GitLab CI) to trigger audits after deployments. Return pass/fail based on score thresholds.

### Zapier/Make Trigger
Expose audit completion events as a webhook that Zapier or Make can consume, enabling users to build custom automations without code.

### Sitemap Import
Import URLs directly from a site's `sitemap.xml` — fetch the sitemap, parse all URLs, and add them to a project in one step.
