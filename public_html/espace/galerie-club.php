<?php
/*
 * Galerie du Club : photos déposées par les adhérents, classées par
 * catégorie (voir inc/galerie_categories.php, partagé avec galerie.php).
 * Contrairement à la Galerie privée (galerie.php), ces photos sont
 * PUBLIQUES une fois en ligne — reprises sur la page publique galerie.html
 * (choix explicite de l'utilisateur, 20/08/2026). Tout adhérent peut
 * déposer ; seul l'auteur ou un responsable peut supprimer.
 *
 * Présentation calquée sur la page publique galerie.html (choix explicite
 * de l'utilisateur, 26/08/2026) : bandeau .gallery-hero, pastilles de
 * filtre par thème et bouton Diaporama juste au-dessus de la grille — voir
 * js/main.js (bloc « Agrandissement + diaporama ») pour le câblage
 * générique partagé avec la page publique et galerie.php.
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
        $requete = $pdo->prepare('SELECT fichier, depose_par FROM photos_club WHERE id = ?');
        $requete->execute([$id]);
        $photo = $requete->fetch();

        if ($photo && ((int) $photo['depose_par'] === $adherent['id'] || est_administrateur())) {
            $pdo->prepare('DELETE FROM photos_club WHERE id = ?')->execute([$id]);
            @unlink(__DIR__ . '/photos_club/' . basename((string) $photo['fichier']));
            definir_message('succes', "Photo supprimée.");
        } else {
            definir_message('erreur', "Vous ne pouvez supprimer que vos propres photos.");
        }
    } else {
        $titre        = trim((string) ($_POST['titre'] ?? ''));
        $categorie_id = (int) ($_POST['categorie_id'] ?? 0);
        $categories   = categories_galerie($pdo);
        $nom_affiche  = trim((string) ($_POST['nom_affiche'] ?? '')) ?: null;
        $description  = trim((string) ($_POST['description'] ?? '')) ?: null;
        $fichiers     = fichiers_multiples($_FILES['photos'] ?? ['name' => []]);

        if ($titre === '') {
            definir_message('erreur', "Donnez un titre à la photo.");
        } elseif (!isset($categories[$categorie_id])) {
            definir_message('erreur', "Choisissez une catégorie — créez-en une dans Réglages du site si aucune ne convient.");
        } elseif (!$fichiers) {
            definir_message('erreur', "Sélectionnez au moins une photo.");
        } else {
            $reussis = 0;
            $erreurs = [];

            foreach ($fichiers as $fichier) {
                $resultat = enregistrer_fichier_envoye(
                    $fichier,
                    __DIR__ . '/photos_club',
                    'image',
                    TAILLE_MAX_PHOTO_ADHERENT,
                    "Photo trop lourde, ne pas dépasser 1000 Ko. Merci."
                );

                if ($resultat['erreur'] !== null) {
                    $erreurs[] = "« " . basename((string) $fichier['name']) . " » : {$resultat['erreur']}";
                    continue;
                }

                $pdo->prepare(
                    'INSERT INTO photos_club (titre, description, nom_affiche, fichier, categorie_id, depose_par)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $titre,
                    $description,
                    $nom_affiche,
                    $resultat['nom'],
                    $categorie_id,
                    $adherent['id'],
                ]);
                $reussis++;
            }

            if ($reussis > 0) {
                // Le titre reste inscrit pour le dépôt suivant (choix
                // explicite de l'utilisateur, 26/08/2026) — pratique pour
                // déposer une série sous le même titre sans le retaper.
                $_SESSION['dernier_titre_galerie_club'] = $titre;
            }

            $parts = [];
            if ($reussis > 0) {
                $parts[] = "{$reussis} photo" . ($reussis > 1 ? 's' : '') . " ajoutée" . ($reussis > 1 ? 's' : '')
                    . " à la Galerie du Club, dans « {$categories[$categorie_id]} ». Elle" . ($reussis > 1 ? 's' : '')
                    . " apparai" . ($reussis > 1 ? 'ssent' : 't') . " aussi sur la page Galerie, ouverte à tous.";
            }
            array_push($parts, ...$erreurs);
            definir_message($erreurs ? 'erreur' : 'succes', implode(' ', $parts));
        }
    }

    header('Location: galerie-club.php');
    exit;
}

$categories = categories_galerie($pdo);

$photos = $pdo->query(
    'SELECT p.id, p.titre, p.description, p.nom_affiche, p.categorie_id, p.depose_par, p.cree_le, a.nom AS auteur
       FROM photos_club p
       LEFT JOIN adherents a ON a.id = p.depose_par
      ORDER BY p.cree_le DESC'
)->fetchAll();

// Rangement par catégorie, dans l'ordre de categories_galerie() — pas celui
// du résultat SQL — pour une présentation stable. « Sans catégorie » ne
// recueille que des photos dont la catégorie a été supprimée depuis leur
// dépôt (categorie_id remis à NULL par la contrainte ON DELETE SET NULL).
$groupes    = [];
$sans_categorie = [];
foreach ($photos as $photo) {
    $categorie_id = $photo['categorie_id'] !== null ? (int) $photo['categorie_id'] : null;
    if ($categorie_id !== null && isset($categories[$categorie_id])) {
        $groupes[$categorie_id][] = $photo;
    } else {
        $sans_categorie[] = $photo;
    }
}

debut_page("Galerie du Club", 'galerie-club');
?>
<section class="gallery-hero">
  <div class="container">
    <h1>Galerie du Club</h1>
    <p>Déposez vos photos et classez-les par catégorie — elles apparaissent aussi sur la page Galerie, ouverte à tous.</p>

    <?php if ($categories): ?>
      <div class="theme-filters" data-filtres-galerie aria-label="Filtrer par thème">
        <button type="button" class="theme-filter is-active" data-categorie="">Toutes</button>
        <?php foreach ($categories as $id_filtre => $nom_filtre): ?>
          <button type="button" class="theme-filter" data-categorie="<?= $id_filtre ?>"><?= e($nom_filtre) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
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
      <a href="documents.php?recherche=<?= urlencode('Fiche_Export_XnConvert') ?>">Fiche_Export_XnConvert</a>,
      disponibles dans les Documents du Club.
    </div>
    <details class="depot-bloc">
      <summary>Ajouter une photo</summary>
      <form method="post" enctype="multipart/form-data" class="form-card" style="margin-top:16px;">
        <?= champ_csrf() ?>
        <div class="field">
          <label for="titre">Titre</label>
          <input type="text" id="titre" name="titre" required maxlength="190"
                 value="<?= e($_SESSION['dernier_titre_galerie_club'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="categorie_id">Catégorie</label>
          <select id="categorie_id" name="categorie_id">
            <?php foreach ($categories as $categorie_id => $nom_categorie): ?>
              <option value="<?= $categorie_id ?>"><?= e($nom_categorie) ?></option>
            <?php endforeach; ?>
          </select>
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
        <button type="submit" class="btn btn-primary">Envoyer les photos</button>
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

  <div class="diaporama-bar">
    <button type="button" class="diaporama-trigger" data-start-diaporama>
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
      Diaporama
    </button>
  </div>

  <?php if (!$photos): ?>
    <div class="empty-state" style="margin-top:28px;">
      <p>Aucune photo pour l'instant. Soyez le premier à en déposer une !</p>
    </div>
  <?php else: ?>
    <?php foreach ($categories as $categorie_id => $nom_categorie): ?>
      <?php if (empty($groupes[$categorie_id])) continue; ?>
      <div class="groupe-galerie" data-categorie-id="<?= $categorie_id ?>">
        <h2><?= e($nom_categorie) ?></h2>
        <div class="photo-grid">
          <?php foreach ($groupes[$categorie_id] as $photo): ?>
            <?php $type = 'galerie_club'; include __DIR__ . '/inc/photo-carte.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if ($sans_categorie): ?>
      <div class="groupe-galerie" data-categorie-id="">
        <h2>Sans catégorie</h2>
        <div class="photo-grid">
          <?php foreach ($sans_categorie as $photo): ?>
            <?php $type = 'galerie_club'; include __DIR__ . '/inc/photo-carte.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div></section>

<div class="lightbox" data-lightbox role="dialog" aria-modal="true" aria-label="Photo en grand format">
  <button class="lightbox-close" aria-label="Fermer">✕</button>
  <button class="lightbox-prev" aria-label="Photo précédente">‹</button>
  <button class="lightbox-next" aria-label="Photo suivante">›</button>
  <div class="lightbox-content">
    <button type="button" class="diaporama-trigger lightbox-diaporama" aria-label="Lancer le diaporama">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
      Diaporama
    </button>
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
