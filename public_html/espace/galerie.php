<?php
/*
 * Galerie privée : chaque adhérent n'y voit que SES PROPRES photos (choix
 * explicite de l'utilisateur, 21/08/2026 — revient sur un premier
 * comportement où tous les adhérents voyaient les photos de tout le monde).
 * Un responsable continue de tout voir, pour la modération — même logique
 * que la suppression, déjà réservée à l'auteur ou à un responsable. Mêmes
 * catégories et mêmes champs que la Galerie du Club (voir galerie-club.php
 * et inc/galerie_categories.php). telecharger.php applique la même
 * restriction sur le fichier lui-même, pas seulement sur cette liste.
 *
 * Le titre reste inscrit d'une photo à l'autre (choix explicite de
 * l'utilisateur, 26/08/2026, $_SESSION['dernier_titre_galerie_privee']) —
 * pratique pour déposer une série sous le même titre sans le retaper.
 *
 * Dépôt de plusieurs photos à la fois, par sélection multiple ou
 * glissé-déposé (choix explicite de l'utilisateur, 26/08/2026 — même
 * principe que documents.php : <input type="file" multiple>, qui accepte
 * nativement le glissé-déposé de plusieurs fichiers sans JavaScript).
 * Toutes les photos d'un même dépôt partagent le titre, la catégorie, le
 * nom affiché et la note saisis une seule fois — fichiers_multiples()
 * (inc/televersement.php) éclate $_FILES['photos'] en une liste, un appel à
 * enregistrer_fichier_envoye() par photo ; un fichier refusé (mauvais
 * format, trop lourd) n'empêche pas les autres d'être déposés.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/televersement.php';
require_once __DIR__ . '/inc/galerie_categories.php';

$adherent = exige_connexion();
$pdo      = base_de_donnees();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    if (($_POST['action'] ?? '') === 'supprimer') {
        $id      = (int) ($_POST['id'] ?? 0);
        $requete = $pdo->prepare('SELECT fichier, depose_par FROM photos_privees WHERE id = ?');
        $requete->execute([$id]);
        $photo = $requete->fetch();

        if ($photo && ((int) $photo['depose_par'] === $adherent['id'] || est_administrateur())) {
            $pdo->prepare('DELETE FROM photos_privees WHERE id = ?')->execute([$id]);
            @unlink(__DIR__ . '/photos/' . basename((string) $photo['fichier']));
            definir_message('succes', "Photo supprimée.");
        } else {
            definir_message('erreur', "Vous ne pouvez supprimer que vos propres photos.");
        }
    } elseif (($_POST['action'] ?? '') === 'modifier_photo') {
        // Reclasse une photo déjà déposée et/ou corrige son nom affiché,
        // sans la supprimer/redéposer (choix explicite de l'utilisatrice,
        // 09/09/2026 pour les catégories, étendu le 10/09/2026 au nom
        // affiché — « quand un adhérent s'est trompé... pouvoir aussi en
        // tant que responsable changer ce nom ») — même règle d'auteur que
        // la suppression. definir_categories_photo() remplace entièrement
        // les catégories existantes (voir inc/galerie_categories.php).
        $id          = (int) ($_POST['id'] ?? 0);
        $nom_affiche = trim((string) ($_POST['nom_affiche'] ?? '')) ?: null;
        $requete = $pdo->prepare('SELECT depose_par FROM photos_privees WHERE id = ?');
        $requete->execute([$id]);
        $photo      = $requete->fetch();
        $categories = categories_galerie($pdo);
        $categorie_ids = array_values(array_intersect(
            array_map('intval', (array) ($_POST['categorie_ids'] ?? [])),
            array_keys($categories)
        ));

        if (!$photo || ((int) $photo['depose_par'] !== $adherent['id'] && !est_administrateur())) {
            definir_message('erreur', "Vous ne pouvez modifier que vos propres photos.");
        } elseif (!$categorie_ids) {
            definir_message('erreur', "Choisissez au moins une catégorie.");
        } else {
            definir_categories_photo($pdo, 'photos_privees_categories', $id, $categorie_ids);
            $pdo->prepare('UPDATE photos_privees SET categorie_id = ?, nom_affiche = ? WHERE id = ?')
                ->execute([$categorie_ids[0], $nom_affiche, $id]);
            definir_message('succes', "Photo mise à jour.");
        }
    } elseif (($_POST['action'] ?? '') === 'ajouter_au_club') {
        // Partage une photo déjà déposée vers la Galerie du Club, sans la
        // retéléverser — choix explicite de l'utilisatrice, 06/09/2026. Même
        // règle d'auteur que la suppression : son propre dépôt, ou un
        // responsable pour n'importe lequel (utile en modération).
        $id      = (int) ($_POST['id'] ?? 0);
        $requete = $pdo->prepare(
            'SELECT titre, description, nom_affiche, categorie_id, fichier, depose_par, copie_club_id
               FROM photos_privees WHERE id = ?'
        );
        $requete->execute([$id]);
        $photo = $requete->fetch();

        if (!$photo || ((int) $photo['depose_par'] !== $adherent['id'] && !est_administrateur())) {
            definir_message('erreur', "Vous ne pouvez partager que vos propres photos.");
        } elseif ($photo['copie_club_id'] !== null) {
            definir_message('erreur', "Cette photo est déjà dans la Galerie du Club.");
        } else {
            $nom_fichier_club = copier_fichier_depot(
                __DIR__ . '/photos/' . basename((string) $photo['fichier']),
                __DIR__ . '/photos_club'
            );

            if ($nom_fichier_club === null) {
                definir_message('erreur', "Impossible de copier la photo vers la Galerie du Club.");
            } else {
                // Reprend TOUTES les catégories d'origine (choix explicite de
                // l'utilisatrice, 09/09/2026), pas seulement la première.
                $categorie_ids = categories_dune_photo($pdo, 'photos_privees_categories', $id);
                $pdo->prepare(
                    'INSERT INTO photos_club (titre, description, nom_affiche, fichier, categorie_id, depose_par)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $photo['titre'],
                    $photo['description'],
                    $photo['nom_affiche'],
                    $nom_fichier_club,
                    $categorie_ids[0] ?? $photo['categorie_id'],
                    $photo['depose_par'],
                ]);
                $nouvel_id = (int) $pdo->lastInsertId();
                if ($categorie_ids) {
                    definir_categories_photo($pdo, 'photos_club_categories', $nouvel_id, $categorie_ids);
                }
                $pdo->prepare('UPDATE photos_privees SET copie_club_id = ? WHERE id = ?')->execute([$nouvel_id, $id]);
                definir_message('succes', "Photo ajoutée à la Galerie (Galerie du Club). Elle apparaît aussi sur la page Galerie, ouverte à tous.");
            }
        }
    } else {
        $titre         = trim((string) ($_POST['titre'] ?? ''));
        $categories    = categories_galerie($pdo);
        // Plusieurs catégories possibles par photo (choix explicite de
        // l'utilisatrice, 09/09/2026) : cases à cocher plutôt qu'un menu à
        // choix unique — voir schema.sql / inc/galerie_categories.php.
        $categorie_ids = array_values(array_intersect(
            array_map('intval', (array) ($_POST['categorie_ids'] ?? [])),
            array_keys($categories)
        ));
        $nom_affiche   = trim((string) ($_POST['nom_affiche'] ?? '')) ?: null;
        $description   = trim((string) ($_POST['description'] ?? '')) ?: null;
        $fichiers      = fichiers_multiples($_FILES['photos'] ?? ['name' => []]);
        // Partage immédiat vers la Galerie du Club, sans repasser par un
        // second dépôt (choix explicite de l'utilisatrice, 06/09/2026) —
        // même copie de fichier que le partage a posteriori, voir plus haut.
        $aussi_club   = isset($_POST['aussi_club']);

        if ($titre === '') {
            definir_message('erreur', "Donnez un titre à la photo.");
        } elseif (!$categorie_ids) {
            definir_message('erreur', "Choisissez au moins une catégorie — créez-en une dans Réglages du site si aucune ne convient.");
        } elseif (!$fichiers) {
            definir_message('erreur', "Sélectionnez au moins une photo.");
        } else {
            $reussis       = 0;
            $ajoutees_club = 0;
            $erreurs       = [];

            foreach ($fichiers as $fichier) {
                $resultat = enregistrer_fichier_envoye(
                    $fichier,
                    __DIR__ . '/photos',
                    'image',
                    TAILLE_MAX_PHOTO_ADHERENT,
                    "Photo trop lourde, ne pas dépasser 1000 Ko. Merci."
                );

                if ($resultat['erreur'] !== null) {
                    $erreurs[] = "« " . basename((string) $fichier['name']) . " » : {$resultat['erreur']}";
                    continue;
                }

                $pdo->prepare(
                    'INSERT INTO photos_privees (titre, description, nom_affiche, fichier, categorie_id, depose_par)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $titre,
                    $description,
                    $nom_affiche,
                    $resultat['nom'],
                    $categorie_ids[0],
                    $adherent['id'],
                ]);
                $nouvel_id_prive = (int) $pdo->lastInsertId();
                definir_categories_photo($pdo, 'photos_privees_categories', $nouvel_id_prive, $categorie_ids);
                $reussis++;

                if ($aussi_club) {
                    $nom_fichier_club = copier_fichier_depot(__DIR__ . '/photos/' . $resultat['nom'], __DIR__ . '/photos_club');

                    if ($nom_fichier_club !== null) {
                        $pdo->prepare(
                            'INSERT INTO photos_club (titre, description, nom_affiche, fichier, categorie_id, depose_par)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        )->execute([
                            $titre,
                            $description,
                            $nom_affiche,
                            $nom_fichier_club,
                            $categorie_ids[0],
                            $adherent['id'],
                        ]);
                        $nouvel_id_club = (int) $pdo->lastInsertId();
                        definir_categories_photo($pdo, 'photos_club_categories', $nouvel_id_club, $categorie_ids);
                        $pdo->prepare('UPDATE photos_privees SET copie_club_id = ? WHERE id = ?')
                            ->execute([$nouvel_id_club, $nouvel_id_prive]);
                        $ajoutees_club++;
                    }
                }
            }

            if ($reussis > 0) {
                // Le titre reste inscrit pour le dépôt suivant (choix
                // explicite de l'utilisateur, 26/08/2026) — pratique pour
                // déposer une série sous le même titre sans le retaper.
                $_SESSION['dernier_titre_galerie_privee'] = $titre;
            }

            $noms_categories = array_map(function ($id) use ($categories) { return $categories[$id]; }, $categorie_ids);
            $parts = [];
            if ($reussis > 0) {
                $parts[] = "{$reussis} photo" . ($reussis > 1 ? 's' : '') . " ajoutée" . ($reussis > 1 ? 's' : '')
                    . " à la galerie privée, dans « " . implode(' », « ', $noms_categories) . " ».";
                if ($aussi_club && $ajoutees_club > 0) {
                    $parts[] = ($ajoutees_club > 1 ? 'Elles ont' : 'Elle a')
                        . " aussi été ajoutée" . ($ajoutees_club > 1 ? 's' : '') . " à la Galerie (Galerie du Club).";
                }
            }
            array_push($parts, ...$erreurs);
            definir_message($erreurs ? 'erreur' : 'succes', implode(' ', $parts));
        }
    }

    // Redirection après envoi : évite qu'un rafraîchissement renvoie le formulaire.
    header('Location: galerie.php');
    exit;
}

$categories = categories_galerie($pdo);

// Chacun ne voit que ses propres photos ; un responsable les voit toutes.
if (est_administrateur()) {
    $photos = $pdo->query(
        'SELECT p.id, p.titre, p.description, p.nom_affiche, p.categorie_id, p.depose_par, p.copie_club_id, p.cree_le, a.nom AS auteur
           FROM photos_privees p
           LEFT JOIN adherents a ON a.id = p.depose_par
          ORDER BY p.cree_le DESC'
    )->fetchAll();
} else {
    $requete_photos = $pdo->prepare(
        'SELECT p.id, p.titre, p.description, p.nom_affiche, p.categorie_id, p.depose_par, p.copie_club_id, p.cree_le, a.nom AS auteur
           FROM photos_privees p
           LEFT JOIN adherents a ON a.id = p.depose_par
          WHERE p.depose_par = ?
          ORDER BY p.cree_le DESC'
    );
    $requete_photos->execute([$adherent['id']]);
    $photos = $requete_photos->fetchAll();
}

// Même rangement par catégorie que galerie-club.php — voir ce fichier pour
// le détail du raisonnement (ordre stable, « Sans catégorie » en repli, une
// photo dans plusieurs catégories apparaît dans chacun de ses groupes).
$categoriesParPhoto = categories_par_photo($pdo, 'photos_privees_categories');
$groupes        = [];
$sans_categorie = [];
foreach ($photos as $photo) {
    $ids_categories = array_intersect($categoriesParPhoto[(int) $photo['id']] ?? [], array_keys($categories));
    if ($ids_categories) {
        foreach ($ids_categories as $categorie_id) {
            $groupes[$categorie_id][] = $photo;
        }
    } else {
        $sans_categorie[] = $photo;
    }
}

debut_page("Galerie privée", 'galerie');
titre_page(
    "Galerie privée",
    est_administrateur()
        ? "Vos photos et celles des autres adhérents (vue responsable) — invisibles hors connexion."
        : "Vos photos, réservées à vous seul — personne d'autre ne les voit ici."
);
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <?php if ($categories): ?>
    <div class="alerte alerte-avertissement">
      Les photos doivent être au format JPEG et ne pas dépasser <?= e(taille_lisible(TAILLE_MAX_PHOTO_ADHERENT)) ?>.
    </div>
    <div class="alerte alerte-avertissement">
      Si vous avez des difficultés pour redimensionner une photo, vous pouvez consulter les fiches
      <a href="documents.php?recherche=<?= urlencode('Fiche_Export_darktable_1000Ko') ?>">Fiche_Export_darktable_1000Ko</a>,
      <a href="documents.php?recherche=<?= urlencode('Fiche_Export_Lightroom_1000Ko') ?>">Fiche_Export_Lightroom_1000Ko</a> et
      <a href="documents.php?recherche=<?= urlencode('Fiche Export_XnConvert') ?>">Fiche Export_XnConvert</a>,
      disponibles dans les Documents du Club.
    </div>
    <details class="depot-bloc">
      <summary>Ajouter une photo</summary>
      <form method="post" enctype="multipart/form-data" class="form-card" style="margin-top:16px;">
        <?= champ_csrf() ?>
        <div class="field">
          <label for="titre">Titre</label>
          <input type="text" id="titre" name="titre" required maxlength="190"
                 value="<?= e($_SESSION['dernier_titre_galerie_privee'] ?? '') ?>">
        </div>
        <div class="field">
          <span class="label-comme">Catégories (une ou plusieurs, s'applique à toutes les photos déposées ici)</span>
          <div class="categories-a-cocher">
            <?php foreach ($categories as $categorie_id => $nom_categorie): ?>
              <label class="case-a-cocher">
                <input type="checkbox" name="categorie_ids[]" value="<?= $categorie_id ?>">
                <?= e($nom_categorie) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field">
          <label for="nom_affiche">Nom affiché (facultatif, s'applique à toutes les photos déposées ici)</label>
          <input type="text" id="nom_affiche" name="nom_affiche" maxlength="120"
                 placeholder="<?= e($adherent['nom']) ?>">
        </div>
        <div class="field">
          <label for="description">Note (facultatif, s'applique à toutes les photos déposées ici)</label>
          <textarea id="description" name="description" rows="2" placeholder="Un mot pour expliquer vos photos…"></textarea>
        </div>
        <div class="field">
          <label for="photo">Photos (JPEG, PNG, WebP ou GIF — <?= taille_lisible(TAILLE_MAX_PHOTO_ADHERENT) ?> maximum chacune)</label>
          <input type="file" id="photo" name="photos[]" accept="image/*" multiple required
                 data-taille-max="<?= TAILLE_MAX_PHOTO_ADHERENT ?>"
                 data-taille-max-lisible="<?= e(taille_lisible(TAILLE_MAX_PHOTO_ADHERENT)) ?>">
          <p class="form-note">
            Plusieurs photos peuvent être sélectionnées ou glissées-déposées d'un coup :
            elles partagent alors le même titre, la même catégorie et la même note.
          </p>
          <p class="form-avertissement" data-avertissement-taille hidden></p>
        </div>
        <label class="case-a-cocher">
          <input type="checkbox" name="aussi_club" value="1">
          Ajouter aussi ces photos à la Galerie (Galerie du Club) — elles deviendront alors publiques
        </label>
        <button type="submit" class="btn btn-primary" style="margin-top:16px;">Envoyer les photos</button>
      </form>
    </details>
  <?php else: ?>
    <div class="empty-state">
      <p>
        Aucune catégorie n'est encore définie.
        <?php if (est_administrateur()): ?>
          Créez-en une depuis <a href="parametres.php">Réglages du site</a> avant de pouvoir déposer une photo.
        <?php endif; ?>
      </p>
    </div>
  <?php endif; ?>

  <?php if (!$photos): ?>
    <div class="empty-state" style="margin-top:28px;">
      <p>Aucune photo pour l'instant. Soyez le premier à en déposer une !</p>
    </div>
  <?php else: ?>
    <?php foreach ($categories as $categorie_id => $nom_categorie): ?>
      <?php if (empty($groupes[$categorie_id])) continue; ?>
      <div class="groupe-galerie">
        <h2><?= e($nom_categorie) ?></h2>
        <div class="photo-grid">
          <?php foreach ($groupes[$categorie_id] as $photo): ?>
            <?php $type = 'photo'; include __DIR__ . '/inc/photo-carte.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if ($sans_categorie): ?>
      <div class="groupe-galerie">
        <h2>Sans catégorie</h2>
        <div class="photo-grid">
          <?php foreach ($sans_categorie as $photo): ?>
            <?php $type = 'photo'; include __DIR__ . '/inc/photo-carte.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div></section>

<script type="application/json" data-categories-disponibles data-csrf="<?= e(jeton_csrf()) ?>"><?= json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>

<div class="lightbox" data-lightbox role="dialog" aria-modal="true" aria-label="Photo en grand format">
  <button class="lightbox-close" aria-label="Fermer">✕</button>
  <button class="lightbox-prev" aria-label="Photo précédente">‹</button>
  <button class="lightbox-next" aria-label="Photo suivante">›</button>
  <div class="lightbox-content">
    <div class="lightbox-frame"></div>
    <div class="lightbox-caption">
      <strong class="lightbox-title"></strong>
      <span class="lightbox-meta"></span>
      <p class="lightbox-description" hidden></p>
    </div>
  </div>
</div>
<?php
fin_page();
