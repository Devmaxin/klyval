<?php
final class ProjectAdminController extends AdminBase {
    public function index(): void {
        $status = (string)($_GET['status'] ?? '');
        $this->render('projects/index', ['title' => 'Projects', 'rows' => Project::adminList($status), 'status' => $status]);
    }
    public function newForm(): void {
        $this->form(null, ['kind' => 'client', 'status' => 'draft', 'display_order' => 0, 'featured' => 0, 'is_placeholder' => 0], ['technologies' => '', 'team' => [], 'gallery' => [], 'services' => []], []);
    }
    public function edit(string $id): void {
        $id = $this->id($id); $p = Project::find($id) or not_found();
        $this->form($id, $p, Project::relations($id), []);
    }
    private function form(?int $id, array $v, array $rel, array $errors): void {
        $this->render('projects/form', ['title' => $id ? 'Edit project' : 'New project', 'id' => $id, 'v' => $v, 'rel' => $rel, 'errors' => $errors,
            'media' => MediaService::all(), 'team' => TeamMember::options(), 'services' => Service::options()]);
    }

    public function save(?string $idRaw = null): void {
        $id = $idRaw === null ? null : $this->id($idRaw);
        $existing = $id ? Project::find($id) : null;
        if ($id && !$existing) not_found();
        $f = null; $in = $this->slugFrom($f, $_POST, (string)($_POST['name'] ?? ''), 'projects', 'project_slug_history', 'project_id', $id);
        $name = $f->str('name', 'Name', 160, true);
        $slug = $f->slug('slug', 'Address');
        if ($slug !== '' && !isset($f->errors['slug']) && Slug::taken('projects', 'project_slug_history', 'project_id', $slug, $id)) $f->errors['slug'] = 'That address is already used by another project, or was used before.';
        $d = [
            'slug' => $slug, 'name' => $name, 'client_name' => $f->str('client_name', 'Client', 160),
            'kind' => $f->enum('kind', 'category', array_keys(Taxonomy::KINDS)), 'description' => $f->text('description', 'Description', 2000),
            'case_study' => $f->text('case_study', 'Case study', 20000), 'video_url' => $f->url('video_url', 'Video link', true), 'project_url' => $f->url('project_url', 'Project link'),
            'status' => $f->enum('status', 'status', array_keys(Taxonomy::PROJECT_STATUSES), 'draft'), 'featured' => $f->bool('featured'),
            'display_order' => $f->int('display_order', 'Display order', 0, 1000000), 'seo_title' => $f->str('seo_title', 'SEO title', 160),
            'seo_description' => $f->str('seo_description', 'SEO description', 255), 'is_placeholder' => $f->bool('is_placeholder'),
            'cover_media_id' => $this->mediaId('cover_media_id'),
        ];
        // Relationships (view shape first, so a failed save re-renders exactly what the editor entered)
        $roles = post_map('team_role'); $tord = post_map('team_order'); $teamView = []; $teamSave = [];
        foreach (post_ints('team_ids') as $mid) {
            $role = mb_substr(trim((string)(is_string($roles[$mid] ?? null) ? $roles[$mid] : '')), 0, 120);
            $ord = max(0, min(1000000, (int)(is_string($tord[$mid] ?? null) ? $tord[$mid] : 0)));
            $teamView[$mid] = ['role_label' => $role, 'display_order' => $ord]; $teamSave[$mid] = ['role' => $role, 'order' => $ord];
        }
        $gord = post_map('gallery_order'); $gcap = post_map('gallery_caption'); $galView = []; $galSave = [];
        foreach (post_ints('gallery_ids') as $gid) {
            $ord = max(0, min(1000000, (int)(is_string($gord[$gid] ?? null) ? $gord[$gid] : 0)));
            $cap = mb_substr(trim((string)(is_string($gcap[$gid] ?? null) ? $gcap[$gid] : '')), 0, 250);
            $galView[$gid] = ['display_order' => $ord, 'caption' => $cap]; $galSave[$gid] = ['order' => $ord, 'caption' => $cap];
        }
        $tech = $f->str('technologies', 'Technologies', 600);
        $rel = ['technologies' => $tech, 'team' => $teamView, 'gallery' => $galView, 'services' => post_ints('service_ids')];

        if (!$f->ok()) { http_response_code(422); $this->form($id, $d + $in, $rel, $f->errors); return; }
        try {
            $newId = Project::save($id, $d, ['technologies' => Vocabulary::parse($tech), 'team' => $teamSave, 'gallery' => $galSave, 'services' => $rel['services']]);
            $this->flash($id ? 'Project saved.' : 'Project created.');
            redirect("/admin/projects/$newId/edit");
        } catch (Throwable $e) {
            $this->failed($e); http_response_code(500); $this->form($id, $d + $in, $rel, ['form' => 'That could not be saved. Nothing was changed.']);
        }
    }

    public function status(string $id): void {
        $id = $this->id($id); $to = (string)($_POST['to'] ?? '');
        if (Project::find($id) && isset(Taxonomy::PROJECT_STATUSES[$to])) { Project::setStatus($id, $to); $this->flash('Project is now ' . strtolower(Taxonomy::PROJECT_STATUSES[$to]) . '.'); }
        redirect('/admin/projects');
    }

    /** Permanent. Administrators only, archived projects only, and the address must be typed to confirm. */
    public function delete(string $id): void {
        $id = $this->id($id); $p = Project::find($id) or not_found();
        if ($p['status'] !== 'archived') { $this->flash('Archive the project first. Only archived projects can be deleted.', 'err'); redirect("/admin/projects/$id/edit"); }
        if (!hash_equals($p['slug'], trim((string)($_POST['confirm'] ?? '')))) { $this->flash('The address you typed did not match. Nothing was deleted.', 'err'); redirect("/admin/projects/$id/edit"); }
        Project::delete($id); $this->flash('Project permanently deleted.'); redirect('/admin/projects?status=archived');
    }
}
