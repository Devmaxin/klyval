<?php
abstract class AdminBase {
    protected function render(string $view, array $data = []): void {
        $title = $data['title'] ?? 'Admin';
        View::render("admin/$view", $data + ['meta' => ['title' => "$title — Klyval admin", 'robots' => 'noindex']], 'layouts/admin');
    }
    protected function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = [$type, $msg]; }
    protected function id(string $s): int { $i = (int)$s; if ($i < 1) not_found(); return $i; }
    protected function userId(): ?int { return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null; }
    protected function isAdmin(): bool { return ($_SESSION['admin_role'] ?? '') === 'admin'; }
    /** Log the real error; show the editor a calm message. Nothing technical reaches the page. */
    protected function failed(Throwable $e): void { error_log(static::class . ': ' . $e->getMessage()); $this->flash('That could not be saved. Nothing was changed. Please try again.', 'err'); }
    /** Cover/portrait/visual id: only accepted if the image really exists. */
    protected function mediaId(string $key): ?int {
        $v = (int)($_POST[$key] ?? 0);
        return $v > 0 && Database::existing('media', [$v]) ? $v : null;
    }
    protected function slugFrom(&$f, array $in, string $name, string $table, ?string $hist, string $histCol, ?int $id): array {
        $slug = trim((string)($in['slug'] ?? ''));
        if ($slug === '') $in['slug'] = Slug::unique(Slug::make($name), $table, $hist, $histCol, $id);
        $f = new Form($in);
        return $in;
    }
}
