<?php
final class AdminController extends AdminBase {
    public function dashboard(): void {
        $one = fn(string $sql) => (int)Database::query($sql)->fetchColumn();
        $this->render('dashboard', [
            'title' => 'Dashboard',
            'enq' => Enquiry::counts(),
            'recent' => Enquiry::list('', 5),
            'projects' => ['published' => $one("SELECT COUNT(*) FROM projects WHERE status='published'"), 'draft' => $one("SELECT COUNT(*) FROM projects WHERE status='draft'"), 'archived' => $one("SELECT COUNT(*) FROM projects WHERE status='archived'")],
            'team' => $one('SELECT COUNT(*) FROM team_members WHERE is_active = 1'),
            'services' => $one('SELECT COUNT(*) FROM services WHERE is_active = 1'),
            'media' => $one('SELECT COUNT(*) FROM media'),
            'placeholders' => $one('SELECT (SELECT COUNT(*) FROM projects WHERE is_placeholder=1) + (SELECT COUNT(*) FROM team_members WHERE is_placeholder=1) + (SELECT COUNT(*) FROM services WHERE is_placeholder=1)'),
            'maintenance' => Settings::get('maintenance_mode') === '1',
            'storageOk' => MediaService::storageHealthy(),
        ]);
    }
}
