<?php
final class ContactSubmission {
    public static function create(array $d): int {
        Database::query(
            'INSERT INTO contact_submissions (name, email, company, project_type, budget, message, ip_hash) VALUES (?,?,?,?,?,?,?)',
            [$d['name'], $d['email'], $d['company'] ?: null, $d['project_type'], $d['budget'] ?: null, $d['message'], Security::ipHash()]
        );
        return (int)Database::pdo()->lastInsertId();
    }
    public static function countRecent(int $minutes): int {
        return (int)Database::query('SELECT COUNT(*) FROM contact_submissions WHERE ip_hash = ? AND created_at >= (NOW() - INTERVAL ' . (int)$minutes . ' MINUTE)', [Security::ipHash()])->fetchColumn();
    }
}
