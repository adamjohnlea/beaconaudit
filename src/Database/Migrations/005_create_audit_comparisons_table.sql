CREATE TABLE IF NOT EXISTS audit_comparisons (
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

CREATE UNIQUE INDEX IF NOT EXISTS idx_audit_comparisons_current ON audit_comparisons(current_audit_id);
CREATE INDEX IF NOT EXISTS idx_audit_comparisons_previous ON audit_comparisons(previous_audit_id);
