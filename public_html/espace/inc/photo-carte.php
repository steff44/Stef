<?php
/*
 * Une carte de photo, partagée par galerie.php (Galerie privée) et
 * galerie-club.php (Galerie du Club) — extraite en partiel pour ne pas
 * dupliquer le HTML entre les deux. Attend dans la portée appelante :
 *   - $photo : une ligne de photos_privees ou photos_club (mêmes colonnes
 *     utiles : id, titre, description, nom_affiche, auteur, cree_le,
 *     depose_par) ;
 *   - $type  : 'photo' (Galerie privée) ou 'galerie_club' (Galerie du Club),
 *     le paramètre attendu par telecharger.php ;
 *   - $adherent : l'adhérent connecté, pour savoir s'il peut supprimer.
 *
 * La carte n'affiche qu'un titre et un auteur, tronqués en CSS si trop
 * longs (voir .photo-caption .title/.meta) — la catégorie n'est volontairement
 * pas répétée ici, déjà donnée par le titre du groupe qui contient cette
 * carte. Le texte complet (description comprise) part dans des attributs
 * data-*, lus par le clic d'agrandissement générique (voir js/main.js) :
 * rien n'est perdu, seule la vignette est raccourcie.
 */
declare(strict_types=1);

$nom_affiche = $photo['nom_affiche'] ?: ($photo['auteur'] ?: 'Adhérent retiré');
$meta        = $nom_affiche . ' — ' . date_en_francais($photo['cree_le'], false);
$image       = 'telecharger.php?type=' . $type . '&id=' . (int) $photo['id'];
?>
        <figure class="photo-card"
                data-titre="<?= e($photo['titre']) ?>"
                data-description="<?= e((string) $photo['description']) ?>"
                data-meta="<?= e($meta) ?>"
                data-image="<?= e($image) ?>">
          <img class="photo-frame" src="<?= e($image) ?>" alt="<?= e($photo['titre']) ?>" loading="lazy">
          <figcaption class="photo-caption">
            <strong class="title"><?= e($photo['titre']) ?></strong>
            <span class="meta"><?= e($meta) ?></span>
          </figcaption>
          <?php if ((int) $photo['depose_par'] === $adherent['id'] || est_administrateur()): ?>
            <form method="post" class="photo-supprimer" onsubmit="return confirm('Supprimer cette photo ?');">
              <?= champ_csrf() ?>
              <input type="hidden" name="action" value="supprimer">
              <input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
              <button type="submit" class="photo-supprimer-bouton" aria-label="Supprimer cette photo" title="Supprimer cette photo">✕</button>
            </form>
          <?php endif; ?>
        </figure>
