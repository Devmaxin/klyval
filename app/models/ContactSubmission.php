<?php
final class ContactSubmission {
    public static function create(array $d): int {
        Database::query(
            'INSERT INTO contact_submissions (name, email, company, project_type, budget, message, ip_hash) VALUES (?,?,?,?,?,?,?)',
            [$d['name'], $d['email'], $d['company'] ?: null, $d['project_type'], $d['budget'] ?: null, $d['message'], Security::ipHash()]
        );
        return Database::insert(
            'INSERT INTO contact_submissions (name, email, company, project_type, budget, message, ip_hash) VALUES (?,?,?,?,?,?,?)',
            [$d['name'], $d['email'], $d['company'] ?: null, $d['project_type'], $d['budget'] ?: null, $d['message'], Security::ipHash()]
        );
    }
    public static function countRecent(int $minutes): int {
        return (int)Database::query('SELECT COUNT(*) FROM contact_submissions WHERE ip_hash = ? AND created_at >= now() - make_interval(mins => ' . (int)$minutes . ')', [Security::ipHash()])->fetchColumn();
    }
}
