<?php
/*
 * Une carte de la Galerie du Club (galerie-club.php) — extraite en partiel
 * pour être incluse une fois par catégorie sans dupliquer le HTML. Attend
 * $photo (une ligne de la requête sur `photos_club`) dans la portée
 * appelante. La catégorie n'est volontairement pas répétée ici : elle est
 * déjà donnée par le titre du groupe qui contient cette carte.
 */
declare(strict_types=1);

$nom_affiche = $photo['nom_affiche'] ?: ($photo['auteur'] ?: 'Adhérent retiré');
?>
        <figure class="photo-card">
          <img class="photo-privee" src="telecharger.php?type=galerie_club&amp;id=<?= (int) $photo['id'] ?>"
               alt="<?= e($photo['titre']) ?>" loading="lazy">
          <figcaption class="photo-caption">
            <strong><?= e($photo['titre']) ?></strong>
            <?php if ($photo['description']): ?>
              <span class="photo-description"><?= e($photo['description']) ?></span>
            <?php endif; ?>
            <span class="photo-meta">
              <?= e($nom_affiche) ?> — <?= e(date_en_francais($photo['cree_le'], false)) ?>
            </span>
            <?php if ((int) $photo['depose_par'] === $adherent['id'] || est_administrateur()): ?>
              <form method="post" onsubmit="return confirm('Supprimer cette photo ?');" style="margin-top:8px;">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
                <button type="submit" class="lien-danger">Supprimer</button>
              </form>
            <?php endif; ?>
          </figcaption>
        </figure>
