CREATE TABLE IF NOT EXISTS issues (
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

CREATE INDEX IF NOT EXISTS idx_issues_audit_id ON issues(audit_id);
CREATE INDEX IF NOT EXISTS idx_issues_severity ON issues(severity);
CREATE INDEX IF NOT EXISTS idx_issues_category ON issues(category);
