<?php
final class TeamAdminController extends AdminBase {
    public function index(): void {
        $state = (string)($_GET['state'] ?? '');
        $this->render('team/index', ['title' => 'Team', 'rows' => TeamMember::adminList($state), 'state' => $state]);
    }
    public function newForm(): void { $this->form(null, ['featured' => 0, 'is_active' => 1, 'display_order' => 0, 'is_placeholder' => 0], ['skills' => '', 'socials' => [], 'projects' => []], []); }
    public function edit(string $id): void {
        $id = $this->id($id); $m = TeamMember::find($id) or not_found();
        $this->form($id, $m, TeamMember::relations($id), []);
    }
    private function form(?int $id, array $v, array $rel, array $errors): void {
        $this->render('team/form', ['title' => $id ? 'Edit team member' : 'New team member', 'id' => $id, 'v' => $v, 'rel' => $rel, 'errors' => $errors,
            'media' => MediaService::all(), 'projects' => Project::adminList()]);
    }

    public function save(?string $idRaw = null): void {
        $id = $idRaw === null ? null : $this->id($idRaw);
        if ($id && !TeamMember::find($id)) not_found();
        $f = null; $in = $this->slugFrom($f, $_POST, (string)($_POST['name'] ?? ''), 'team_members', 'team_slug_history', 'member_id', $id);
        $name = $f->str('name', 'Name', 120, true);
        $slug = $f->slug('slug', 'Address');
        if ($slug !== '' && !isset($f->errors['slug']) && Slug::taken('team_members', 'team_slug_history', 'member_id', $slug, $id)) $f->errors['slug'] = 'That address is already used by another profile, or was used before.';
        $d = [
            'slug' => $slug, 'name' => $name, 'role' => $f->str('role', 'Role', 120, true), 'short_bio' => $f->str('short_bio', 'Short bio', 280),
            'full_bio' => $f->text('full_bio', 'Full bio', 20000), 'portfolio_url' => $f->url('portfolio_url', 'Portfolio link'), 'featured' => $f->bool('featured'),
            'display_order' => $f->int('display_order', 'Display order', 0, 1000000), 'is_active' => $f->bool('is_active'), 'is_placeholder' => $f->bool('is_placeholder'),
            'portrait_media_id' => $this->mediaId('portrait_media_id'),
        ];
        $skills = $f->str('skills', 'Skills', 600);
        $socials = []; $urls = post_map('social_url');
        foreach (Taxonomy::PLATFORMS as $p => $label) {
            $u = is_string($urls[$p] ?? null) ? trim($urls[$p]) : ''; if ($u === '') continue;
            $sf = new Form(['u' => $u]); $sf->url('u', "$label link");
            if ($sf->ok()) $socials[$p] = $u; else $f->errors["social_$p"] = $sf->errors['u'];
        }
        $rel = ['skills' => $skills, 'socials' => $socials, 'projects' => post_ints('project_ids')];
        if (!$f->ok()) { http_response_code(422); $this->form($id, $d + $in, $rel, $f->errors); return; }
        try {
            $newId = TeamMember::save($id, $d, ['skills' => Vocabulary::parse($skills), 'socials' => $socials, 'projects' => $rel['projects']]);
            $this->flash($id ? 'Profile saved.' : 'Team member added.');
            redirect("/admin/team/$newId/edit");
        } catch (Throwable $e) { $this->failed($e); http_response_code(500); $this->form($id, $d + $in, $rel, ['form' => 'That could not be saved. Nothing was changed.']); }
    }

    public function active(string $id): void {
        $id = $this->id($id); $on = ($_POST['to'] ?? '') === '1';
        if (TeamMember::find($id)) { TeamMember::setActive($id, $on); $this->flash($on ? 'Profile is active.' : 'Profile is inactive and hidden from the site.'); }
        redirect('/admin/team');
    }
    public function delete(string $id): void {
        $id = $this->id($id); $m = TeamMember::find($id) or not_found();
        if ($m['is_active']) { $this->flash('Make the profile inactive first. Only inactive profiles can be deleted.', 'err'); redirect("/admin/team/$id/edit"); }
        if (!hash_equals($m['slug'], trim((string)($_POST['confirm'] ?? '')))) { $this->flash('The address you typed did not match. Nothing was deleted.', 'err'); redirect("/admin/team/$id/edit"); }
        TeamMember::delete($id); $this->flash('Profile permanently deleted.'); redirect('/admin/team?state=inactive');
    }
}
