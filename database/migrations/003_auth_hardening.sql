CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email));
CREATE INDEX idx_login_attempts_ip_time ON login_attempts(ip_address, attempted_at DESC);
