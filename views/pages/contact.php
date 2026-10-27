<?php
$v = fn(string $k) => e($old[$k] ?? '');
$has = fn(string $k) => isset($errors[$k]);
$err = fn(string $k) => $has($k) ? '<span class="field__error" id="err-' . $k . '">' . e($errors[$k]) . '</span>' : '';
$attr = fn(string $k, string $hint = '') => ($has($k) ? ' aria-invalid="true"' : '') . (($has($k) || $hint !== '') ? ' aria-describedby="' . trim(($hint !== '' ? $hint . ' ' : '') . ($has($k) ? 'err-' . $k : '')) . '"' : '');
$mail = setting('contact_email'); $phone = setting('phone'); $wa = preg_replace('/\D+/', '', (string)setting('whatsapp'));
$fieldErrors = array_diff_key($errors, ['form' => 1]);
?>
<div class="contact-bar theme-deep" aria-hidden="true"></div>
<div class="contact-split">
  <section class="contact-intro theme-deep" aria-labelledby="contact-title">
    <div class="contact-intro__inner">
      <h1 id="contact-title" class="page-hero__title">Let's make it real.</h1>
      <p class="lede">Tell us what you're working on. A few details are enough to start.</p>
      <ol class="steps">
        <li><strong>You send the details.</strong> What you need, and what success looks like.</li>
        <li><strong>We read it properly.</strong> Then we get back to you by email.</li>
        <li><strong>We agree the next step.</strong> A call, a proposal, or a few questions.</li>
      </ol>
      <?php if ($mail || $phone || $wa): ?>
      <ul class="contact-details">
        <?php if ($mail): ?><li><span>Email</span><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></li><?php endif ?>
        <?php if ($phone): ?><li><span>Phone</span><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif ?>
        <?php if ($wa): ?><li><span>WhatsApp</span><a href="https://wa.me/<?= e($wa) ?>" rel="noopener" target="_blank">Message us ↗</a></li><?php endif ?>
      </ul>
      <?php endif ?>
    </div>
  </section>
  <section class="contact-form theme-soft" aria-label="Project enquiry form">
    <div class="contact-form__inner">
    <?php if ($sent): ?>
      <div class="form-sent" role="status">
        <h2 class="h2" tabindex="-1" data-form-sent>Message sent.</h2>
        <p class="lede">Thanks. We'll read it properly and get back to you.</p>
        <?php View::partial('button', ['label' => 'Back home', 'href' => '/', 'variant' => 'ghost']) ?>
      </div>
    <?php else: ?>
      <form method="post" action="/contact" novalidate class="form">
        <?= csrf_field() ?>
        <?php if ($errors): ?>
        <div class="form-error" role="alert" tabindex="-1" data-form-errors>
          <?php if (isset($errors['form'])): ?><p><?= e($errors['form']) ?></p><?php endif ?>
          <?php if ($fieldErrors): ?><p>Please check the following:</p><ul><?php foreach ($fieldErrors as $k => $m): ?><li><a href="#f-<?= e($k) ?>"><?= e($m) ?></a></li><?php endforeach ?></ul><?php endif ?>
        </div>
        <?php endif ?>
        <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="field-row">
          <label class="field"><span>Name <b aria-hidden="true">*</b></span><input id="f-name" name="name" value="<?= $v('name') ?>" autocomplete="name" required aria-required="true" maxlength="120"<?= $attr('name') ?>><?= $err('name') ?></label>
          <label class="field"><span>Email <b aria-hidden="true">*</b></span><input id="f-email" type="email" name="email" value="<?= $v('email') ?>" autocomplete="email" required aria-required="true" maxlength="190"<?= $attr('email') ?>><?= $err('email') ?></label>
        </div>
        <label class="field"><span>Company or organisation <em>(optional)</em></span><input id="f-company" name="company" value="<?= $v('company') ?>" autocomplete="organization" maxlength="160"<?= $attr('company') ?>><?= $err('company') ?></label>
        <label class="field"><span>What do you need? <b aria-hidden="true">*</b></span>
          <select id="f-project_type" name="project_type" required aria-required="true"<?= $attr('project_type') ?>>
            <option value="">Choose one</option>
            <?php foreach ($groups as $label => $opts): $opts = array_values(array_intersect($opts, $types)); if (!$opts) continue; ?>
            <optgroup label="<?= e($label) ?>"><?php foreach ($opts as $t): ?><option<?= ($old['project_type'] ?? '') === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach ?></optgroup>
            <?php endforeach ?>
          </select><?= $err('project_type') ?></label>
        <label class="field"><span>Budget <em>(optional)</em></span><input id="f-budget" name="budget" value="<?= $v('budget') ?>" maxlength="80"<?= $attr('budget', 'hint-budget') ?>><small class="hint" id="hint-budget">A range or a rough figure helps us suggest the right scope.</small><?= $err('budget') ?></label>
        <label class="field"><span>Tell us about the project <b aria-hidden="true">*</b></span><textarea id="f-message" name="message" rows="6" required aria-required="true" minlength="10" maxlength="5000"<?= $attr('message', 'hint-message') ?>><?= $v('message') ?></textarea>
          <small class="hint" id="hint-message">What are you trying to achieve, and who is it for? Mention any deadline, or links we should look at. <span data-count aria-hidden="true"></span></small><?= $err('message') ?></label>
        <button class="btn btn--primary" type="submit"><span class="btn__label">Start a project</span><span class="btn__arrow" aria-hidden="true">→</span></button>
        <p class="hint form__note"><b aria-hidden="true">*</b> Required</p>
      </form>
    <?php endif ?>
    </div>
  </section>
</div>
