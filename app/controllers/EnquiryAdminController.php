<?php
final class EnquiryAdminController extends AdminBase {
    public function index(): void {
        $status = (string)($_GET['status'] ?? '');
        $this->render('enquiries/index', ['title' => 'Enquiries', 'rows' => Enquiry::list($status), 'status' => $status, 'counts' => Enquiry::counts()]);
    }
    public function show(string $id): void {
        $e = Enquiry::find($this->id($id)) or not_found();
        $this->render('enquiries/show', ['title' => 'Enquiry', 'e' => $e]);
    }
    public function update(string $id): void {
        $id = $this->id($id); Enquiry::find($id) or not_found();
        $f = new Form($_POST);
        $status = $f->enum('status', 'status', array_keys(Taxonomy::ENQUIRY_STATUSES));
        $notes = $f->text('notes', 'Notes', 5000);
        if (!$f->ok()) { $this->flash(reset($f->errors), 'err'); redirect("/admin/enquiries/$id"); }
        Enquiry::update($id, $status, $notes); $this->flash('Enquiry updated.'); redirect("/admin/enquiries/$id");
    }
}
