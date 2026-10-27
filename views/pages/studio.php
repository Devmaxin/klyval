<?php View::partial('page-hero', ['title' => $copy['studio_title'], 'lede' => $copy['studio_text'], 'theme' => 'soft', 'eyebrow' => 'The studio']) ?>
<section class="theme-light studio-fit" aria-labelledby="fit-title">
  <div class="wrap studio__grid">
    <h2 id="fit-title" class="h2 studio__title">How the work fits together.</h2>
    <div class="studio__body">
      <p class="lede">Build, Create and Launch aren't separate departments. The people who build something also shape how it looks and sounds, and plan how it reaches the world, so each part supports the others.</p>
      <div class="studio__links">
        <?php if ($team): ?><?php View::partial('button', ['label' => 'Meet the team', 'href' => '#team', 'variant' => 'ghost']) ?><?php endif ?>
        <?php View::partial('button', ['label' => 'Our services', 'href' => '/services', 'variant' => 'ghost']) ?>
        <?php View::partial('button', ['label' => 'Start a project', 'href' => '/contact', 'variant' => 'ghost']) ?>
      </div>
    </div>
  </div>
</section>
<?php if ($team): ?>
<section class="theme-soft team" id="team" aria-labelledby="team-title">
  <div class="wrap">
    <p class="eyebrow">Team</p>
    <h2 id="team-title" class="h2 team__title">Who you'll work with.</h2>
    <?php View::partial('team-list', ['team' => $team, 'project_index' => $project_index]) ?>
  </div>
</section>
<?php endif ?>
<?php View::section('pages/home/process', ['process' => $process]) ?>
<?php View::section('pages/home/cta', ['copy' => $copy]) ?>
