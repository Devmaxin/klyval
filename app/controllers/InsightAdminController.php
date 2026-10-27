<?php
final class InsightAdminController extends AdminBase {
    public function index(): void { $st = (string)($_GET['status'] ?? ''); $this->render('insights/index', ['title' => 'Insights', 'rows' => Article::adminList($st), 'status' => $st]); }
    public function newForm(): void { $this->form(null, ['category' => 'studio', 'status' => 'draft'], []); }
    public function edit(string $id): void { $id = $this->id($id); $a = Article::find($id) or not_found(); $this->form($id, $a, []); }
    private function form(?int $id, array $v, array $errors): void {
        $this->render('insights/form', ['title' => $id ? 'Edit article' : 'New article', 'id' => $id, 'v' => $v, 'errors' => $errors, 'media' => MediaService::all(), 'authors' => TeamMember::options()]);
    }
    public function save(?string $idRaw = null): void {
        $id = $idRaw === null ? null : $this->id($idRaw);
        if ($id && !Article::find($id)) not_found();
        $f = null; $in = $this->slugFrom($f, $_POST, (string)($_POST['title'] ?? ''), 'articles', 'article_slug_history', 'article_id', $id);
        $title = $f->str('title', 'Title', 200, true); $slug = $f->slug('slug', 'Address');
        if ($slug !== '' && !isset($f->errors['slug']) && Slug::taken('articles', 'article_slug_history', 'article_id', $slug, $id)) $f->errors['slug'] = 'That address is already used by another article, or was used before.';
        $author = (int)($_POST['author_id'] ?? 0);
        $d = ['slug' => $slug, 'title' => $title, 'excerpt' => $f->str('excerpt', 'Summary', 300), 'content' => $f->text('content', 'Article', 60000),
            'cover_media_id' => $this->mediaId('cover_media_id'), 'author_id' => ($author > 0 && Database::existing('team_members', [$author])) ? $author : null,
            'category' => $f->enum('category', 'category', array_keys(Taxonomy::ARTICLE_CATEGORIES)), 'status' => $f->enum('status', 'status', array_keys(Taxonomy::PROJECT_STATUSES), 'draft'),
            'published_at' => $f->datetime('published_at', 'Publish date'), 'seo_title' => $f->str('seo_title', 'SEO title', 160), 'seo_description' => $f->str('seo_description', 'SEO description', 255)];
        if (!$f->ok()) { http_response_code(422); $this->form($id, $d + $in, $f->errors); return; }
        try { $newId = Article::save($id, $d); $this->flash($id ? 'Article saved.' : 'Article created.'); redirect("/admin/insights/$newId/edit"); }
        catch (Throwable $e) { $this->failed($e); http_response_code(500); $this->form($id, $d + $in, ['form' => 'That could not be saved. Nothing was changed.']); }
    }
    public function status(string $id): void {
        $id = $this->id($id); $to = (string)($_POST['to'] ?? '');
        if (Article::find($id) && isset(Taxonomy::PROJECT_STATUSES[$to])) { Article::setStatus($id, $to); $this->flash('Article is now ' . strtolower(Taxonomy::PROJECT_STATUSES[$to]) . '.'); }
        redirect('/admin/insights');
    }
    public function delete(string $id): void {
        $id = $this->id($id); $a = Article::find($id) or not_found();
        if ($a['status'] !== 'archived') { $this->flash('Archive the article first. Only archived articles can be deleted.', 'err'); redirect("/admin/insights/$id/edit"); }
        if (!hash_equals($a['slug'], trim((string)($_POST['confirm'] ?? '')))) { $this->flash('The address you typed did not match. Nothing was deleted.', 'err'); redirect("/admin/insights/$id/edit"); }
        Article::delete($id); $this->flash('Article permanently deleted.'); redirect('/admin/insights?status=archived');
    }
}
