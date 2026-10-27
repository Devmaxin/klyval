<?php
final class ServiceAdminController extends AdminBase {
    public function index(): void {
        $state = (string)($_GET['state'] ?? '');
        $this->render('services/index', ['title' => 'Services', 'rows' => Service::adminList($state), 'state' => $state]);
    }
    public function newForm(): void { $this->form(null, ['pillar' => 'build', 'show_on_home' => 0, 'is_active' => 1, 'display_order' => 0, 'is_placeholder' => 0], '', []); }
    public function edit(string $id): void {
        $id = $this->id($id); $s = Service::find($id) or not_found();
        $this->form($id, $s, implode("\n", Service::features($id)), []);
    }
    private function form(?int $id, array $v, string $features, array $errors): void {
        $this->render('services/form', ['title' => $id ? 'Edit service' : 'New service', 'id' => $id, 'v' => $v, 'features' => $features, 'errors' => $errors, 'media' => MediaService::all()]);
    }

    public function save(?string $idRaw = null): void {
        $id = $idRaw === null ? null : $this->id($idRaw);
        if ($id && !Service::find($id)) not_found();
        $f = null; $in = $this->slugFrom($f, $_POST, (string)($_POST['name'] ?? ''), 'services', null, '', $id);
        $name = $f->str('name', 'Name', 120, true);
        $slug = $f->slug('slug', 'Address');
        if ($slug !== '' && !isset($f->errors['slug']) && Slug::taken('services', null, '', $slug, $id)) $f->errors['slug'] = 'That address is already used by another service.';
        $d = [
            'slug' => $slug, 'name' => $name, 'pillar' => $f->enum('pillar', 'group', array_keys(Taxonomy::PILLARS)), 'description' => $f->text('description', 'Description', 2000),
            'show_on_home' => $f->bool('show_on_home'), 'display_order' => $f->int('display_order', 'Display order', 0, 1000000), 'is_active' => $f->bool('is_active'),
            'is_placeholder' => $f->bool('is_placeholder'), 'visual_media_id' => $this->mediaId('visual_media_id'),
        ];
        $raw = $f->text('features', 'Features', 4000); $features = [];
        foreach (explode("\n", $raw) as $line) { $line = trim($line); if ($line === '') continue; if (mb_strlen($line) > 200) { $f->errors['features'] = 'Each feature must be 200 characters or fewer.'; break; } $features[] = $line; }
        if (count($features) > 20) $f->errors['features'] = 'Up to 20 features per service.';
        if (!$f->ok()) { http_response_code(422); $this->form($id, $d + $in, $raw, $f->errors); return; }
        try { $newId = Service::save($id, $d, $features); $this->flash($id ? 'Service saved.' : 'Service created.'); redirect("/admin/services/$newId/edit"); }
        catch (Throwable $e) { $this->failed($e); http_response_code(500); $this->form($id, $d + $in, $raw, ['form' => 'That could not be saved. Nothing was changed.']); }
    }

    public function active(string $id): void {
        $id = $this->id($id); $on = ($_POST['to'] ?? '') === '1';
        if (Service::find($id)) { Service::setActive($id, $on); $this->flash($on ? 'Service is active.' : 'Service is inactive and hidden from the site.'); }
        redirect('/admin/services');
    }
    public function delete(string $id): void {
        $id = $this->id($id); $s = Service::find($id) or not_found();
        if ($s['is_active']) { $this->flash('Make the service inactive first. Only inactive services can be deleted.', 'err'); redirect("/admin/services/$id/edit"); }
        if (!hash_equals($s['slug'], trim((string)($_POST['confirm'] ?? '')))) { $this->flash('The address you typed did not match. Nothing was deleted.', 'err'); redirect("/admin/services/$id/edit"); }
        Service::delete($id); $this->flash('Service permanently deleted.'); redirect('/admin/services?state=inactive');
    }
}
