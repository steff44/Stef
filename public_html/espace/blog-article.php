<?php
/*
 * Un article du blog — publique comme blog.php ; modifier/supprimer exige
 * exige_gestionnaire() (responsable ou éditeur, voir inc/auth.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/blog.php';
require_once __DIR__ . '/inc/televersement.php';

$adherent = adherent_connecte();
$pdo      = base_de_donnees();
$id       = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    exige_gestionnaire();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'supprimer') {
        $requete = $pdo->prepare('SELECT image FROM articles_blog WHERE id = ?');
        $requete->execute([$id]);
        $article_supprime = $requete->fetch();

        $pdo->prepare('DELETE FROM articles_blog WHERE id = ?')->execute([$id]);
        if ($article_supprime && $article_supprime['image']) {
            @unlink(__DIR__ . '/photos_blog/' . basename((string) $article_supprime['image']));
        }
        definir_message('succes', "Article supprimé.");
        header('Location: blog.php');
        exit;

    } elseif ($action === 'modifier') {
        $titre        = trim((string) ($_POST['titre'] ?? ''));
        $categorie_id = (int) ($_POST['categorie_id'] ?? 0);
        $auteur_nom   = trim((string) ($_POST['auteur_nom'] ?? ''));
        $extrait      = trim((string) ($_POST['extrait'] ?? ''));
        $contenu      = trim((string) ($_POST['contenu'] ?? ''));

        $categories_valides = categories_blog($pdo);

        if ($titre === '' || $contenu === '') {
            definir_message('erreur', "Le titre et le texte de l'article sont obligatoires.");
        } elseif (!isset($categories_valides[$categorie_id])) {
            definir_message('erreur', "Choisissez une catégorie.");
        } else {
            $requete = $pdo->prepare('SELECT image FROM articles_blog WHERE id = ?');
            $requete->execute([$id]);
            $article_existant = $requete->fetch();

            if (!$article_existant) {
                definir_message('erreur', "Cet article n'existe plus.");
            } else {
                // L'image n'est remplacée que si un nouveau fichier est
                // envoyé ; sinon celle déjà en place est conservée.
                $image       = $article_existant['image'];
                $envoi_image = $_FILES['image'] ?? null;
                if ($envoi_image && ($envoi_image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $resultat_image = enregistrer_fichier_envoye($envoi_image, __DIR__ . '/photos_blog', 'image');
                    if ($resultat_image['erreur'] !== null) {
                        definir_message('erreur', $resultat_image['erreur']);
                        header('Location: blog-article.php?id=' . $id);
                        exit;
                    }
                    if ($image) {
                        @unlink(__DIR__ . '/photos_blog/' . basename((string) $image));
                    }
                    $image = $resultat_image['nom'];
                }

                $pdo->prepare(
                    'UPDATE articles_blog SET titre = ?, extrait = ?, contenu = ?, image = ?, categorie_id = ?, auteur_nom = ?
                     WHERE id = ?'
                )->execute([
                    $titre,
                    $extrait !== '' ? $extrait : extrait_auto($contenu),
                    $contenu,
                    $image,
                    $categorie_id,
                    $auteur_nom !== '' ? $auteur_nom : null,
                    $id,
                ]);
                definir_message('succes', "Article modifié.");
            }
        }
        header('Location: blog-article.php?id=' . $id);
        exit;
    }
}

$requete = $pdo->prepare('SELECT * FROM articles_blog WHERE id = ?');
$requete->execute([$id]);
$article = $requete->fetch();

if (!$article) {
    page_erreur("Article introuvable", "Cet article n'existe pas ou a été supprimé.", 404);
}

$categories = categories_blog($pdo);
$recents    = $pdo->query(
    'SELECT id, titre FROM articles_blog ORDER BY cree_le DESC LIMIT ' . BLOG_ARTICLES_RECENTS
)->fetchAll();

debut_page($article['titre'], 'blog');
titre_page($article['titre'], "", true);
?>
<section class="section"><div class="container container-large">
  <?php afficher_message(); ?>

  <p style="margin:-8px 0 24px;">
    <a class="lien-retour" href="blog.php"
       onclick="if (history.length > 1) { history.back(); return false; }">← Retour au blog</a>
  </p>

  <div class="blog-layout">
    <article class="blog-article-complet">
      <p class="blog-meta">
        <?= e(date_en_francais($article['cree_le'], false)) ?>
        <?php if ($article['auteur_nom']): ?> · par <?= e($article['auteur_nom']) ?><?php endif; ?>
        <?php if ($article['categorie_id'] && isset($categories[$article['categorie_id']])): ?>
          · Catégorie : <a href="blog.php?categorie=<?= (int) $article['categorie_id'] ?>"><?= e($categories[$article['categorie_id']]) ?></a>
        <?php endif; ?>
      </p>

      <?php if ($article['image']): ?>
        <img class="blog-image-article" src="telecharger.php?type=blog&amp;id=<?= (int) $article['id'] ?><?= e(version_fichier(__DIR__ . '/photos_blog/' . $article['image'])) ?>" alt="">
      <?php endif; ?>

      <div class="blog-contenu"><?= texte_riche_html($article['contenu']) ?></div>

      <?php if (est_gestionnaire()): ?>
        <div class="blog-actions-gestion">
          <details class="sortie-modifier">
            <summary class="btn btn-ghost">Modifier</summary>
            <form method="post" enctype="multipart/form-data" class="form-card" style="margin-top:16px;max-width:640px;">
              <?= champ_csrf() ?>
              <input type="hidden" name="action" value="modifier">
              <div class="field">
                <label for="titre">Titre</label>
                <input type="text" id="titre" name="titre" required maxlength="190" value="<?= e($article['titre']) ?>">
              </div>
              <div class="field">
                <label for="categorie_id">Catégorie</label>
                <select id="categorie_id" name="categorie_id" required>
                  <?php foreach ($categories as $id_categorie => $nom_categorie): ?>
                    <option value="<?= $id_categorie ?>" <?= $id_categorie === (int) $article['categorie_id'] ? 'selected' : '' ?>><?= e($nom_categorie) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="auteur_nom">Signé (facultatif)</label>
                <input type="text" id="auteur_nom" name="auteur_nom" maxlength="120" value="<?= e((string) $article['auteur_nom']) ?>">
              </div>
              <div class="field">
                <label for="extrait">Résumé affiché dans la liste (facultatif)</label>
                <textarea id="extrait" name="extrait" rows="2" maxlength="400"><?= e((string) $article['extrait']) ?></textarea>
              </div>
              <div class="field">
                <label for="contenu">Texte de l'article</label>
                <textarea id="contenu" name="contenu" rows="10" required><?= e($article['contenu']) ?></textarea>
                <p class="form-note">Laissez une ligne vide entre deux paragraphes. Entourez un mot de ** pour le mettre en gras.</p>
              </div>
              <div class="field">
                <label for="image">Photo de couverture (facultatif — <?= $article['image'] ? 'laissez vide pour garder la photo actuelle, ou remplacez-la' : 'JPEG, PNG, WebP ou GIF' ?>)</label>
                <input type="file" id="image" name="image" accept="image/*">
              </div>
              <button type="submit" class="btn btn-primary" style="margin-top:16px;">Enregistrer les modifications</button>
            </form>
          </details>
          <form method="post" onsubmit="return confirm('Supprimer cet article ?');">
            <?= champ_csrf() ?>
            <input type="hidden" name="action" value="supprimer">
            <button type="submit" class="lien-danger">Supprimer</button>
          </form>
        </div>
      <?php endif; ?>
    </article>

    <aside class="blog-sidebar">
      <div class="blog-widget">
        <h2>Articles récents</h2>
        <ul>
          <?php foreach ($recents as $recent): ?>
            <li><a href="blog-article.php?id=<?= (int) $recent['id'] ?>"<?= (int) $recent['id'] === (int) $article['id'] ? ' aria-current="page"' : '' ?>><?= e($recent['titre']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="blog-widget">
        <h2>Catégories</h2>
        <ul>
          <li><a href="blog.php">Tous les articles</a></li>
          <?php foreach ($categories as $id_categorie => $nom_categorie): ?>
            <li><a href="blog.php?categorie=<?= $id_categorie ?>"><?= e($nom_categorie) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>
</div></section>
<?php
fin_page();
