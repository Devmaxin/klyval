<section class="team theme-light" aria-labelledby="team-title">
  <div class="wrap">
    <p class="eyebrow">Team</p>
    <h2 id="team-title" class="h2 team__title">Who you'll work with.</h2>
    <?php View::partial('team-list', ['team' => $team, 'project_index' => $project_index]) ?>
  </div>
</section>
