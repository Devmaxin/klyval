<?php
final class ContactController {
    public function show(): void { $this->form([], [], isset($_GET['sent'])); }

    public function submit(): void {
        $in = [];
        foreach (['name', 'email', 'company', 'project_type', 'budget', 'message'] as $k) $in[$k] = trim((string)($_POST[$k] ?? ''));
        $errors = [];

        // Honeypot + minimum-time check: bots fill hidden fields or submit instantly. Pretend success, store nothing.
        $tooFast = (time() - (int)($_SESSION['contact_t'] ?? 0)) < 3;
        if (!empty($_POST['website']) || $tooFast) redirect('/contact?sent=1');
        if ((time() - (int)($_SESSION['contact_last'] ?? 0)) < 30) $errors['form'] = 'Please wait a moment before sending another message.';

        if (!$errors && ContactSubmission::countRecent(60) >= 5) $errors['form'] = 'We have received several messages from your connection already. Please try again later.';
        if ($in['name'] === '' || mb_strlen($in['name']) > 120) $errors['name'] = 'Tell us your name.';
        if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || strlen($in['email']) > 190) $errors['email'] = 'Enter a valid email address.';
        if (mb_strlen($in['company']) > 160) $errors['company'] = 'That company name is too long.';
        if (!in_array($in['project_type'], Taxonomy::PROJECT_TYPES, true)) $errors['project_type'] = 'Choose what you have in mind.';
        if (mb_strlen($in['budget']) > 80) $errors['budget'] = 'Keep the budget note short.';
        if (mb_strlen($in['message']) < 10 || mb_strlen($in['message']) > 5000) $errors['message'] = 'Tell us a little about the project (10–5000 characters).';

        if (!$errors) {
            try {
                ContactSubmission::create($in);
                $_SESSION['contact_last'] = time();
                redirect('/contact?sent=1'); // post/redirect/get: refresh can't resubmit
            } catch (Throwable $e) {
                error_log('contact submit failed: ' . $e->getMessage());
                $errors['form'] = "Something went wrong on our side. Your message wasn't sent — please try again.";
            }
        }
        http_response_code(422);
        $this->form($in, $errors, false);
    }

    private function form(array $old, array $errors, bool $sent): void {
        $_SESSION['contact_t'] = $_SESSION['contact_t'] ?? time();
        if (!$sent && empty($errors)) $_SESSION['contact_t'] = time();
        View::render('pages/contact', [
            'meta' => ['title' => 'Contact — Klyval', 'description' => "Tell us what you're working on."],
            'headerTheme' => 'dark', 'styles' => ['css/pages.css'], 'scripts' => ['js/contact.js'],
            'old' => $old, 'errors' => $errors, 'sent' => $sent, 'types' => Taxonomy::PROJECT_TYPES, 'groups' => Taxonomy::PROJECT_TYPE_GROUPS,
        ]);
    }
}
