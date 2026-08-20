<?php
/*
 * Galerie du Club : photos déposées par les adhérents, classées par
 * catégorie (voir inc/galerie_club.php). Contrairement à la Galerie privée
 * (galerie.php), ces photos sont PUBLIQUES une fois en ligne — reprises sur
 * la page publique galerie.html (choix explicite de l'utilisateur,
 * 20/08/2026). Tout adhérent peut déposer ; seul l'auteur ou un responsable
 * peut supprimer.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/televersement.php';
require_once __DIR__ . '/inc/galerie_club.php';

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

        if ($titre === '') {
            definir_message('erreur', "Donnez un titre à la photo.");
        } elseif (!isset($categories[$categorie_id])) {
            definir_message('erreur', "Choisissez une catégorie — créez-en une dans Réglages du site si aucune ne convient.");
        } else {
            $resultat = enregistrer_fichier_envoye($_FILES['photo'] ?? null, __DIR__ . '/photos_club', 'image');

            if ($resultat['erreur'] !== null) {
                definir_message('erreur', $resultat['erreur']);
            } else {
                $pdo->prepare(
                    'INSERT INTO photos_club (titre, description, nom_affiche, fichier, categorie_id, depose_par)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $titre,
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    trim((string) ($_POST['nom_affiche'] ?? '')) ?: null,
                    $resultat['nom'],
                    $categorie_id,
                    $adherent['id'],
                ]);
                definir_message('succes', "Photo ajoutée à la Galerie du Club, dans « {$categories[$categorie_id]} ». Elle apparaît aussi sur la page Galerie, ouverte à tous.");
            }
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
titre_page("Galerie du Club", "Déposez vos photos et classez-les par catégorie — elles apparaissent aussi sur la page Galerie, ouverte à tous.");
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
          <input type="text" id="titre" name="titre" required maxlength="190">
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
          <label for="nom_affiche">Nom affiché (facultatif)</label>
          <input type="text" id="nom_affiche" name="nom_affiche" maxlength="120"
                 placeholder="<?= e($adherent['nom']) ?>">
        </div>
        <div class="field">
          <label for="description">Note (facultatif)</label>
          <textarea id="description" name="description" rows="2" placeholder="Un mot pour expliquer votre photo…"></textarea>
        </div>
        <div class="field">
          <label for="photo">Image (JPEG, PNG, WebP ou GIF — <?= taille_lisible(TAILLE_MAX_OCTETS) ?> maximum)</label>
          <input type="file" id="photo" name="photo" accept="image/*" required>
        </div>
        <button type="submit" class="btn btn-primary">Envoyer la photo</button>
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
      <div class="groupe-galerie-club">
        <h2><?= e($nom_categorie) ?></h2>
        <div class="photo-grid">
          <?php foreach ($groupes[$categorie_id] as $photo): ?>
            <?php include __DIR__ . '/inc/photo-club-carte.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if ($sans_categorie): ?>
      <div class="groupe-galerie-club">
        <h2>Sans catégorie</h2>
        <div class="photo-grid">
          <?php foreach ($sans_categorie as $photo): ?>
            <?php include __DIR__ . '/inc/photo-club-carte.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div></section>
<?php
fin_page();
