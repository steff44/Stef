<?php
/*
 * Une carte de photo, partagée par galerie.php (Galerie privée) et
 * galerie-club.php (Galerie du Club) — extraite en partiel pour ne pas
 * dupliquer le HTML entre les deux. Attend dans la portée appelante :
 *   - $photo : une ligne de photos_privees ou photos_club (mêmes colonnes
 *     utiles : id, titre, description, nom_affiche, auteur, cree_le,
 *     depose_par ; copie_club_id en plus pour photos_privees, voir
 *     ci-dessous) ;
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
 *
 * Sur la Galerie privée uniquement ($type === 'photo'), un bouton « Ajouter
 * au Club » propose à l'auteur (ou un responsable) de copier cette photo
 * vers la Galerie du Club sans la retéléverser (choix explicite de
 * l'utilisatrice, 06/09/2026, voir l'action ajouter_au_club de galerie.php)
 * — remplacé par un badge une fois la copie faite (copie_club_id posé).
 *
 * `data-auteur` (06/09/2026) porte le nom affiché brut (sans la date qui
 * accompagne `data-meta`) — lu par le filtre « Photographe » de
 * galerie-club.php (voir js/main.js) pour montrer/masquer une carte par
 * photographe, indépendamment de sa catégorie.
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
                data-auteur="<?= e($nom_affiche) ?>"
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
            <?php if ($type === 'photo'): ?>
              <?php if (!empty($photo['copie_club_id'])): ?>
                <span class="photo-partagee" aria-label="Déjà dans la Galerie du Club" title="Cette photo est aussi dans la Galerie du Club">✓</span>
              <?php else: ?>
                <form method="post" class="photo-partager" onsubmit="return confirm('Ajouter cette photo à la Galerie (Galerie du Club) ? Elle deviendra publique.');">
                  <?= champ_csrf() ?>
                  <input type="hidden" name="action" value="ajouter_au_club">
                  <input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
                  <button type="submit" class="photo-partager-bouton" aria-label="Ajouter à la Galerie du Club" title="Ajouter à la Galerie du Club">+</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
        </figure>
