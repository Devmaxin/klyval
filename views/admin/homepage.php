<?php $E = $errors; ?>
<h1 class="h2">Homepage</h1>
<p class="lede-dark">Change what the homepage says and which items it shows. Layout, motion and the BUILD → CREATE → LAUNCH intro are part of the design and are not editable here.</p>
<?php if ($E): ?><p class="flash flash--err" role="alert">Please fix the highlighted fields.</p><?php endif ?>
<form class="form-admin" method="post" action="/admin/homepage" novalidate>
  <?= csrf_field() ?>
  <fieldset class="panel"><legend>Copy</legend>
    <?= fld('hero_lede', 'Supporting hero copy', $v['hero_lede'] ?? '', $E, ['type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 300]) ?>
    <?= fld('studio_title', 'Studio headline', $v['studio_title'] ?? '', $E, ['required' => true, 'max' => 80]) ?>
    <?= fld('studio_text', 'Studio text', $v['studio_text'] ?? '', $E, ['type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 400]) ?>
    <?= fld('cta_title', 'Closing headline', $v['cta_title'] ?? '', $E, ['required' => true, 'max' => 80]) ?>
    <?= fld('cta_text', 'Closing text', $v['cta_text'] ?? '', $E, ['required' => true, 'max' => 160]) ?>
  </fieldset>
  <fieldset class="panel"><legend>Announcement banner</legend>
    <?= fcheck('banner_active', 'Show the banner', ($v['banner_active'] ?? '0') === '1') ?>
    <?= fld('banner_text', 'Banner text', $v['banner_text'] ?? '', $E, ['max' => 160]) ?>
    <?= fld('banner_url', 'Banner link', $v['banner_url'] ?? '', $E, ['type' => 'url', 'max' => 500]) ?>
  </fieldset>
  <fieldset class="panel"><legend>Sections</legend>
    <p class="hint">Hide a section entirely. The hero and closing call to action always show.</p>
    <?php foreach (['services' => 'Services', 'work' => 'Selected work', 'studio' => 'Studio', 'stats' => 'Statistics', 'team' => 'Team', 'process' => 'How Klyval works'] as $k => $l): ?><?= fcheck("show_$k", $l, ($v["show_$k"] ?? '1') === '1') ?><?php endforeach ?>
    <p class="hint">Work, Team and Statistics also stay hidden while they have nothing to show.</p>
  </fieldset>
  <fieldset class="panel"><legend>Featured on the homepage</legend>
    <fieldset class="sub"><legend>Projects</legend><?php if (!$projects): ?><p class="hint">No projects yet.</p><?php endif ?>
      <?php foreach ($projects as $p): ?><?= fcheck('featured_projects[]', $p['name'] . ' (' . (Taxonomy::PROJECT_STATUSES[$p['status']] ?? '') . ')', isset($featured['projects']) && $featured ? in_array((int)$p['id'], $featured['projects'], true) : (bool)$p['featured'], $p['status'] !== 'published' ? 'Only published projects are shown.' : '', (string)(int)$p['id']) ?><?php endforeach ?></fieldset>
    <fieldset class="sub"><legend>Team (up to 3 are shown)</legend><?php if (!$team): ?><p class="hint">No active team members yet.</p><?php endif ?>
      <?php foreach ($team as $m): ?><?= fcheck('featured_team[]', $m['name'] . ' · ' . $m['role'], $featured ? in_array((int)$m['id'], $featured['team'], true) : (bool)$m['featured'], '', (string)(int)$m['id']) ?><?php endforeach ?></fieldset>
    <fieldset class="sub"><legend>Services listed under each group</legend><?php if (!$services): ?><p class="hint">No active services yet.</p><?php endif ?>
      <?php foreach ($services as $s): ?><?= fcheck('home_services[]', Taxonomy::PILLARS[$s['pillar']]['label'] . ' — ' . $s['name'], $featured ? in_array((int)$s['id'], $featured['services'], true) : (bool)$s['show_on_home'], '', (string)(int)$s['id']) ?><?php endforeach ?></fieldset>
    <p class="hint">Order follows each item's display order in its own section.</p>
  </fieldset>
  <fieldset class="panel"><legend>Statistics</legend>
    <p class="hint warn">Real figures only. The section stays hidden until at least one active statistic exists.</p>
    <?php if (isset($E['stats'])): ?><span class="field__error"><?= e($E['stats']) ?></span><?php endif ?>
    <?php $rows = $stats; for ($i = count($rows); $i < count($stats) + 3; $i++) $rows[] = ['label' => '', 'value' => '', 'display_order' => $i, 'is_active' => 1]; foreach ($rows as $i => $s): ?>
    <div class="row-pick"><input name="stat_label[<?= $i ?>]" value="<?= e($s['label']) ?>" maxlength="80" placeholder="Label" aria-label="Statistic label"><input name="stat_value[<?= $i ?>]" value="<?= e($s['value']) ?>" maxlength="40" placeholder="Value" aria-label="Statistic value"><input type="number" min="0" name="stat_order[<?= $i ?>]" value="<?= (int)$s['display_order'] ?>" aria-label="Order"><label><input type="checkbox" name="stat_active[<?= $i ?>]" value="1"<?= $s['is_active'] ? ' checked' : '' ?>> Active</label></div>
    <?php endforeach ?>
    <p class="hint">Clear both fields of a row to remove it. Save to get more empty rows.</p>
  </fieldset>
  <div class="actions"><button class="btn btn--primary" type="submit"><span class="btn__label">Save homepage</span></button></div>
</form>
