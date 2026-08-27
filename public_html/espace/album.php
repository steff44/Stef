<?php
/*
 * Dépôt de photos pour un album « Nos Sorties » hébergé sur ce site
 * (albums_sorties.type = 'local' — choix explicite de l'utilisateur,
 * 27/08/2026, réservé aux sorties avec peu de photos ; s'il y en a
 * beaucoup, le club continue d'utiliser un album Google Drive).
 *
 * Calqué sur galerie-club.php, en plus simple : pas de catégorie (l'album
 * suffit à classer les photos), pas de filtre par thème — une seule liste,
 * de la plus récente à la plus ancienne. Tout adhérent connecté peut
 * déposer ; seul l'auteur ou un responsable peut supprimer (même règle que
 * la Galerie du Club — l'éditeur n'a pas ce privilège de modération, voir
 * inc/photo-carte.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/televersement.php';
require_once __DIR__ . '/inc/albums.php';

$adherent = exige_connexion();
$pdo      = base_de_donnees();

$album_id = (int) ($_GET['id'] ?? 0);
$albums   = albums_sorties($pdo);

if (!isset($albums[$album_id]) || $albums[$album_id]['type'] !== 'local') {
    page_erreur("Album introuvable", "Cet album n'existe pas, ou ses photos sont hébergées sur Google Drive.", 404);
}

$album = $albums[$album_id];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    if (($_POST['action'] ?? '') === 'supprimer') {
        $id      = (int) ($_POST['id'] ?? 0);
        $requete = $pdo->prepare('SELECT fichier, depose_par FROM photos_sorties WHERE id = ? AND album_id = ?');
        $requete->execute([$id, $album_id]);
        $photo = $requete->fetch();

        if ($photo && ((int) $photo['depose_par'] === $adherent['id'] || est_administrateur())) {
            $pdo->prepare('DELETE FROM photos_sorties WHERE id = ?')->execute([$id]);
            @unlink(__DIR__ . '/photos_sorties/' . basename((string) $photo['fichier']));
            definir_message('succes', "Photo supprimée.");
        } else {
            definir_message('erreur', "Vous ne pouvez supprimer que vos propres photos.");
        }
    } else {
        $titre       = trim((string) ($_POST['titre'] ?? ''));
        $nom_affiche = trim((string) ($_POST['nom_affiche'] ?? '')) ?: null;
        $description = trim((string) ($_POST['description'] ?? '')) ?: null;
        $fichiers    = fichiers_multiples($_FILES['photos'] ?? ['name' => []]);

        if ($titre === '') {
            definir_message('erreur', "Donnez un titre à la photo.");
        } elseif (!$fichiers) {
            definir_message('erreur', "Sélectionnez au moins une photo.");
        } else {
            $reussis = 0;
            $erreurs = [];

            foreach ($fichiers as $fichier) {
                $resultat = enregistrer_fichier_envoye(
                    $fichier,
                    __DIR__ . '/photos_sorties',
                    'image',
                    TAILLE_MAX_PHOTO_ADHERENT,
                    "Photo trop lourde, ne pas dépasser 1000 Ko. Merci."
                );

                if ($resultat['erreur'] !== null) {
                    $erreurs[] = "« " . basename((string) $fichier['name']) . " » : {$resultat['erreur']}";
                    continue;
                }

                $pdo->prepare(
                    'INSERT INTO photos_sorties (album_id, titre, description, nom_affiche, fichier, depose_par)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $album_id,
                    $titre,
                    $description,
                    $nom_affiche,
                    $resultat['nom'],
                    $adherent['id'],
                ]);
                $reussis++;
            }

            if ($reussis > 0) {
                // Même confort que galerie-club.php : le titre reste inscrit
                // pour le dépôt suivant. Une clé par album, pour ne pas
                // mélanger le dernier titre de deux albums différents.
                $_SESSION['dernier_titre_album_' . $album_id] = $titre;
            }

            $parts = [];
            if ($reussis > 0) {
                $parts[] = "{$reussis} photo" . ($reussis > 1 ? 's' : '') . " ajoutée" . ($reussis > 1 ? 's' : '')
                    . " à l'album « {$album['nom']} ». Elle" . ($reussis > 1 ? 's' : '')
                    . " apparai" . ($reussis > 1 ? 'ssent' : 't') . " aussi sur la page Nos Sorties, ouverte à tous.";
            }
            array_push($parts, ...$erreurs);
            definir_message($erreurs ? 'erreur' : 'succes', implode(' ', $parts));
        }
    }

    header('Location: album.php?id=' . $album_id);
    exit;
}

$requete_photos = $pdo->prepare(
    'SELECT p.id, p.titre, p.description, p.nom_affiche, p.depose_par, p.cree_le, a.nom AS auteur
       FROM photos_sorties p
       LEFT JOIN adherents a ON a.id = p.depose_par
      WHERE p.album_id = ?
      ORDER BY p.cree_le DESC'
);
$requete_photos->execute([$album_id]);
$photos = $requete_photos->fetchAll();

debut_page("Album « {$album['nom']} »");
?>
<section class="gallery-hero">
  <div class="container">
    <h1><?= e($album['nom']) ?></h1>
    <p>Déposez vos photos de cette sortie — elles apparaissent aussi sur <a href="../nos-sorties.html">Nos Sorties</a>, ouvert à tous.</p>
  </div>
</section>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <details class="depot-bloc">
    <summary>Ajouter une photo</summary>
    <form method="post" enctype="multipart/form-data" class="form-card" style="margin-top:16px;">
      <?= champ_csrf() ?>
      <div class="field">
        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" required maxlength="190"
               value="<?= e($_SESSION['dernier_titre_album_' . $album_id] ?? '') ?>">
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
          elles partagent alors le même titre et la même note. Réservé aux sorties avec
          peu de photos — pour un album fourni, préférez Google Drive.
        </p>
        <p class="form-avertissement" data-avertissement-taille hidden></p>
      </div>
      <button type="submit" class="btn btn-primary">Envoyer les photos</button>
    </form>
  </details>

  <?php if ($photos): ?>
    <div class="diaporama-bar">
      <button type="button" class="diaporama-trigger" data-start-diaporama>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
        Diaporama
      </button>
    </div>
  <?php endif; ?>

  <?php if (!$photos): ?>
    <div class="empty-state" style="margin-top:28px;">
      <p>Aucune photo pour l'instant. Soyez le premier à en déposer une !</p>
    </div>
  <?php else: ?>
    <div class="photo-grid">
      <?php foreach ($photos as $photo): ?>
        <?php $type = 'sortie_album'; include __DIR__ . '/inc/photo-carte.php'; ?>
      <?php endforeach; ?>
    </div>
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
