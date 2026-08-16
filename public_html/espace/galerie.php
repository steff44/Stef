<?php
/*
 * Galerie privée : photos visibles uniquement par les adhérents connectés.
 * Tout adhérent peut déposer ; seul l'auteur ou un responsable peut supprimer.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/televersement.php';

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
        $titre = trim((string) ($_POST['titre'] ?? ''));
        if ($titre === '') {
            definir_message('erreur', "Donnez un titre à la photo.");
        } else {
            $resultat = enregistrer_fichier_envoye($_FILES['photo'] ?? null, __DIR__ . '/photos', 'image');

            if ($resultat['erreur'] !== null) {
                definir_message('erreur', $resultat['erreur']);
            } else {
                $pdo->prepare(
                    'INSERT INTO photos_privees (titre, description, fichier, depose_par) VALUES (?, ?, ?, ?)'
                )->execute([
                    $titre,
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    $resultat['nom'],
                    $adherent['id'],
                ]);
                definir_message('succes', "Photo ajoutée à la galerie privée.");
            }
        }
    }

    // Redirection après envoi : évite qu'un rafraîchissement renvoie le formulaire.
    header('Location: galerie.php');
    exit;
}

$photos = $pdo->query(
    'SELECT p.id, p.titre, p.description, p.depose_par, p.cree_le, a.nom AS auteur
       FROM photos_privees p
       LEFT JOIN adherents a ON a.id = p.depose_par
      ORDER BY p.cree_le DESC'
)->fetchAll();

debut_page("Galerie privée", 'galerie');
titre_page("Galerie privée", "Ces photos ne sont visibles que par les adhérents connectés.");
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <details class="depot-bloc">
    <summary>Ajouter une photo</summary>
    <form method="post" enctype="multipart/form-data" class="form-card" style="margin-top:16px;">
      <?= champ_csrf() ?>
      <div class="field">
        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" required maxlength="190">
      </div>
      <div class="field">
        <label for="description">Description (facultatif)</label>
        <textarea id="description" name="description" rows="2"></textarea>
      </div>
      <div class="field">
        <label for="photo">Image (JPEG, PNG, WebP ou GIF — <?= taille_lisible(TAILLE_MAX_OCTETS) ?> maximum)</label>
        <input type="file" id="photo" name="photo" accept="image/*" required>
      </div>
      <button type="submit" class="btn btn-primary">Envoyer la photo</button>
    </form>
  </details>

  <?php if (!$photos): ?>
    <div class="empty-state" style="margin-top:28px;">
      <p>Aucune photo pour l'instant. Soyez le premier à en déposer une !</p>
    </div>
  <?php else: ?>
    <div class="photo-grid" style="margin-top:28px;">
      <?php foreach ($photos as $photo): ?>
        <figure class="photo-card">
          <img class="photo-privee" src="telecharger.php?type=photo&amp;id=<?= (int) $photo['id'] ?>"
               alt="<?= e($photo['titre']) ?>" loading="lazy">
          <figcaption class="photo-caption">
            <strong><?= e($photo['titre']) ?></strong>
            <?php if ($photo['description']): ?>
              <span class="photo-description"><?= e($photo['description']) ?></span>
            <?php endif; ?>
            <span class="photo-meta">
              <?= $photo['auteur'] ? e($photo['auteur']) : 'Adhérent retiré' ?>
              — <?= e(date_en_francais($photo['cree_le'], false)) ?>
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
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div></section>
<?php
fin_page();
