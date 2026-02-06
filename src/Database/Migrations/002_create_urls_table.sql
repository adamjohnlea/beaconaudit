CREATE TABLE IF NOT EXISTS urls (
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

CREATE INDEX IF NOT EXISTS idx_urls_project_id ON urls(project_id);
CREATE INDEX IF NOT EXISTS idx_urls_enabled ON urls(enabled);
CREATE INDEX IF NOT EXISTS idx_urls_audit_frequency ON urls(audit_frequency);
CREATE INDEX IF NOT EXISTS idx_urls_last_audited_at ON urls(last_audited_at);
