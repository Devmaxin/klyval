<?php // $team (members), $project_index (slug => project) ?>
<ul class="team__list">
  <?php foreach ($team as $m):
    $mp = [];
    foreach ($m['projects'] as $slug) if (isset($project_index[$slug])) $mp[] = $project_index[$slug];   // published projects only (index is built from published rows)
  ?>
  <li class="member">
    <a class="member__id" href="/team/<?= e($m['slug']) ?>" data-cursor="PROFILE">
      <span class="member__portrait ph"><?php if (!empty($m['image'])): ?><?= kv_img($m['image'], 0, 0, '') ?><?php endif ?></span>
      <span class="member__body">
        <span class="member__name"><?= e($m['name']) ?></span>
        <span class="member__role"><?= e($m['role']) ?><?php if (!empty($m['placeholder'])): ?> <span class="ph-tag">Placeholder</span><?php endif ?></span>
        <?php if ($m['short_bio'] !== ''): ?><span class="member__bio"><?= e($m['short_bio']) ?></span><?php endif ?>
      </span>
    </a>
    <?php if ($mp) View::partial('project-strip', ['projects' => $mp, 'label' => 'Projects ' . $m['name'] . ' worked on']) ?>
  </li>
  <?php endforeach ?>
</ul>
