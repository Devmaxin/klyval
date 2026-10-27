<?php
final class MediaController extends AdminBase {
    public function index(): void { $this->render('media/index', ['title' => 'Media', 'items' => MediaService::all(), 'maxMb' => MediaService::MAX_BYTES / 1048576]); }

    public function upload(): void {
        $f = new Form($_POST);
        $alt = $f->str('alt_text', 'Description', 250);
        if (!$f->ok()) { $this->flash($f->errors['alt_text'], 'err'); redirect('/admin/media'); }
        $r = MediaService::upload($_FILES['image'] ?? [], $alt, $this->userId());
        isset($r['error']) ? $this->flash($r['error'], 'err') : $this->flash('Image uploaded.');
        redirect('/admin/media');
    }

    public function alt(string $id): void {
        $id = $this->id($id); $f = new Form($_POST);
        $alt = $f->str('alt_text', 'Description', 250);
        if ($f->ok() && MediaService::find($id)) { Database::query('UPDATE media SET alt_text = ? WHERE id = ?', [$alt ?: null, $id]); $this->flash('Description saved.'); }
        else $this->flash($f->errors['alt_text'] ?? 'Image not found.', 'err');
        redirect('/admin/media');
    }

    public function delete(string $id): void {
        $err = MediaService::delete($this->id($id));
        $err ? $this->flash($err, 'err') : $this->flash('Image deleted.');
        redirect('/admin/media');
    }
}
