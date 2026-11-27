ALTER TABLE comments ADD COLUMN attention TEXT NOT NULL DEFAULT 'none'
    CHECK (attention IN ('none', 'requested', 'resolved'));
CREATE INDEX comments_pending_attention ON comments(task_id) WHERE attention = 'requested';
