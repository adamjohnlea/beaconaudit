CREATE TABLE IF NOT EXISTS notifications (
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

CREATE INDEX IF NOT EXISTS idx_notifications_url_id ON notifications(url_id);
CREATE INDEX IF NOT EXISTS idx_notifications_sent_at ON notifications(sent_at);
CREATE INDEX IF NOT EXISTS idx_notifications_channel ON notifications(channel);
