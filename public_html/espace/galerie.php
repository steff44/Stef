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
                    __DIR__ . '/photos',
                    'image',
                    TAILLE_MAX_PHOTO_ADHERENT,
                    "Photo trop lourde, ne pas dépasser 600 Ko. Merci."
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
                    $categorie_id,
                    $adherent['id'],
                ]);
                $reussis++;
            }

            if ($reussis > 0) {
                // Le titre reste inscrit pour le dépôt suivant (choix
                // explicite de l'utilisateur, 26/08/2026) — pratique pour
                // déposer une série sous le même titre sans le retaper.
                $_SESSION['dernier_titre_galerie_privee'] = $titre;
            }

            $parts = [];
            if ($reussis > 0) {
                $parts[] = "{$reussis} photo" . ($reussis > 1 ? 's' : '') . " ajoutée" . ($reussis > 1 ? 's' : '')
                    . " à la galerie privée, dans « {$categories[$categorie_id]} ».";
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
        'SELECT p.id, p.titre, p.description, p.nom_affiche, p.categorie_id, p.depose_par, p.cree_le, a.nom AS auteur
           FROM photos_privees p
           LEFT JOIN adherents a ON a.id = p.depose_par
          ORDER BY p.cree_le DESC'
    )->fetchAll();
} else {
    $requete_photos = $pdo->prepare(
        'SELECT p.id, p.titre, p.description, p.nom_affiche, p.categorie_id, p.depose_par, p.cree_le, a.nom AS auteur
           FROM photos_privees p
           LEFT JOIN adherents a ON a.id = p.depose_par
          WHERE p.depose_par = ?
          ORDER BY p.cree_le DESC'
    );
    $requete_photos->execute([$adherent['id']]);
    $photos = $requete_photos->fetchAll();
}

// Même rangement par catégorie que galerie-club.php — voir ce fichier pour
// le détail du raisonnement (ordre stable, « Sans catégorie » en repli).
$groupes        = [];
$sans_categorie = [];
foreach ($photos as $photo) {
    $categorie_id = $photo['categorie_id'] !== null ? (int) $photo['categorie_id'] : null;
    if ($categorie_id !== null && isset($categories[$categorie_id])) {
        $groupes[$categorie_id][] = $photo;
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
          <input type="file" id="photo" name="photos[]" accept="image/*" multiple required>
          <p class="form-note">
            Plusieurs photos peuvent être sélectionnées ou glissées-déposées d'un coup :
            elles partagent alors le même titre, la même catégorie et la même note.
          </p>
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
