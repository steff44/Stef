<?php
/*
 * Blog du club — liste des articles, publique comme l'agenda
 * (espace/agenda.php) malgré son emplacement : exige_connexion() n'est
 * jamais appelée ici, seule la rédaction (formulaire plus bas) exige
 * exige_gestionnaire() (responsable ou éditeur, voir inc/auth.php).
 *
 * Présentation calquée sur la maquette fournie par l'utilisateur
 * (24/08/2026) : liste d'articles avec vignette, méta (date, auteur,
 * catégorie) et extrait, plus une colonne latérale « Articles récents » et
 * « Catégories ». Filtrage par catégorie (?categorie=ID) et pagination
 * (?page=N) en rechargement de page classique, sans JavaScript — même
 * philosophie que le calendrier de agenda.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/blog.php';
require_once __DIR__ . '/inc/televersement.php';
require_once __DIR__ . '/inc/mail.php';

$adherent = adherent_connecte();
$pdo      = base_de_donnees();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    exige_gestionnaire();

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
        $image       = null;
        $envoi_image = $_FILES['image'] ?? null;
        if ($envoi_image && ($envoi_image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $resultat_image = enregistrer_fichier_envoye($envoi_image, __DIR__ . '/photos_blog', 'image');
            if ($resultat_image['erreur'] !== null) {
                definir_message('erreur', $resultat_image['erreur']);
                header('Location: blog.php');
                exit;
            }
            $image = $resultat_image['nom'];
        }

        $pdo->prepare(
            'INSERT INTO articles_blog (titre, extrait, contenu, image, categorie_id, auteur_nom, depose_par)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $titre,
            $extrait !== '' ? $extrait : extrait_auto($contenu),
            $contenu,
            $image,
            $categorie_id,
            $auteur_nom !== '' ? $auteur_nom : $adherent['nom'],
            $adherent['id'],
        ]);
        $nouvel_id = (int) $pdo->lastInsertId();

        // Prévient tous les adhérents validés par e-mail (choix explicite de
        // l'utilisateur, 28/08/2026) — même principe que la notification de
        // nouvelle sortie (sorties-a-venir.php) : un e-mail qui échoue à
        // partir ne doit jamais faire échouer la publication de l'article
        // elle-même (envoyer_mail() échoue déjà silencieusement, voir
        // inc/mail.php).
        $extrait_notif = $extrait !== '' ? $extrait : extrait_auto($contenu);
        $lien_article   = SITE_URL . '/espace/blog-article.php?id=' . $nouvel_id;
        $expediteur     = valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com';

        $destinataires = $pdo->query(
            "SELECT nom, email FROM adherents WHERE valide = 1 AND actif = 1 AND email IS NOT NULL AND email <> ''"
        )->fetchAll();
        foreach ($destinataires as $destinataire) {
            envoyer_mail(
                $destinataire['email'],
                $expediteur,
                'Nouvel article sur le blog : ' . $titre,
                "Bonjour {$destinataire['nom']},\n\n"
                . "Un nouvel article vient d'être publié sur le blog du club :\n\n"
                . "**{$titre}**\n{$extrait_notif}\n\n"
                . "Lisez l'article complet ici :\n{$lien_article}\n\n"
                . "À bientôt,\nLe Focal Club Turballais"
            );
        }

        definir_message('succes', "Article publié. Un e-mail a été envoyé aux adhérents.");
    }

    header('Location: blog.php');
    exit;
}

$categories = categories_blog($pdo);

// Filtre par catégorie, facultatif.
$categorie_filtre = (int) ($_GET['categorie'] ?? 0);
if (!isset($categories[$categorie_filtre])) {
    $categorie_filtre = 0;
}

$conditions = [];
$parametres = [];
if ($categorie_filtre > 0) {
    $conditions[] = 'categorie_id = ?';
    $parametres[] = $categorie_filtre;
}
$ou = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$total = (int) (function () use ($pdo, $ou, $parametres) {
    $requete = $pdo->prepare("SELECT COUNT(*) FROM articles_blog{$ou}");
    $requete->execute($parametres);
    return $requete->fetchColumn();
})();

$par_page    = BLOG_ARTICLES_PAR_PAGE;
$nb_pages    = max(1, (int) ceil($total / $par_page));
$page        = max(1, min($nb_pages, (int) ($_GET['page'] ?? 1)));
$decalage    = ($page - 1) * $par_page;

$requete = $pdo->prepare(
    "SELECT * FROM articles_blog{$ou} ORDER BY cree_le DESC LIMIT {$par_page} OFFSET {$decalage}"
);
$requete->execute($parametres);
$articles = $requete->fetchAll();

$recents = $pdo->query(
    'SELECT id, titre FROM articles_blog ORDER BY cree_le DESC LIMIT ' . BLOG_ARTICLES_RECENTS
)->fetchAll();

debut_page("Blog du Club", 'blog');
titre_page("Le blog du Club", "Actualités, concours, expositions et vie du club.", true, true);
?>
<section class="section"><div class="container container-large">
  <?php afficher_message(); ?>

  <?php if (est_gestionnaire()): ?>
    <details class="depot-bloc">
      <summary>Ajouter un article</summary>
      <form method="post" enctype="multipart/form-data" class="form-card" style="margin-top:16px;max-width:640px;">
        <?= champ_csrf() ?>
        <div class="field">
          <label for="titre">Titre</label>
          <input type="text" id="titre" name="titre" required maxlength="190">
        </div>
        <div class="field">
          <label for="categorie_id">Catégorie</label>
          <select id="categorie_id" name="categorie_id" required>
            <option value="">— Choisir —</option>
            <?php foreach ($categories as $id_categorie => $nom_categorie): ?>
              <option value="<?= $id_categorie ?>"><?= e($nom_categorie) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="auteur_nom">Signé (facultatif)</label>
          <input type="text" id="auteur_nom" name="auteur_nom" maxlength="120"
                 placeholder="<?= e($adherent['nom']) ?>">
        </div>
        <div class="field">
          <label for="extrait">Résumé affiché dans la liste (facultatif — sinon calculé automatiquement)</label>
          <textarea id="extrait" name="extrait" rows="2" maxlength="400"></textarea>
        </div>
        <div class="field">
          <label for="contenu">Texte de l'article</label>
          <textarea id="contenu" name="contenu" rows="10" required></textarea>
          <p class="form-note">Laissez une ligne vide entre deux paragraphes. Entourez un mot de ** pour le mettre en gras.</p>
        </div>
        <div class="field">
          <label for="image">Photo de couverture (facultatif — JPEG, PNG, WebP ou GIF, <?= taille_lisible(TAILLE_MAX_OCTETS) ?> maximum)</label>
          <input type="file" id="image" name="image" accept="image/*"
                 data-taille-max="<?= TAILLE_MAX_OCTETS ?>"
                 data-taille-max-lisible="<?= e(taille_lisible(TAILLE_MAX_OCTETS)) ?>">
          <p class="form-avertissement" data-avertissement-taille hidden></p>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:16px;">Publier l'article</button>
      </form>
    </details>
  <?php endif; ?>

  <div class="blog-layout">
    <div class="blog-liste">
      <?php if ($categorie_filtre > 0): ?>
        <p class="blog-filtre-actif">
          Catégorie : <strong><?= e($categories[$categorie_filtre]) ?></strong>
          — <a href="blog.php">Voir tous les articles</a>
        </p>
      <?php endif; ?>

      <?php if (!$articles): ?>
        <div class="empty-state"><p>Aucun article pour le moment.</p></div>
      <?php else: ?>
        <?php foreach ($articles as $article): ?>
          <article class="blog-article-ligne">
            <h2><a href="blog-article.php?id=<?= (int) $article['id'] ?>"><?= e($article['titre']) ?></a></h2>
            <p class="blog-meta">
              <?= e(date_en_francais($article['cree_le'], false)) ?>
              <?php if ($article['auteur_nom']): ?> · par <?= e($article['auteur_nom']) ?><?php endif; ?>
              <?php if ($article['categorie_id'] && isset($categories[$article['categorie_id']])): ?>
                · Catégorie : <a href="blog.php?categorie=<?= (int) $article['categorie_id'] ?>"><?= e($categories[$article['categorie_id']]) ?></a>
              <?php endif; ?>
            </p>
            <div class="blog-article-corps">
              <p class="blog-extrait"><?= e((string) $article['extrait']) ?></p>
              <?php if ($article['image']): ?>
                <img class="blog-vignette" src="telecharger.php?type=blog&amp;id=<?= (int) $article['id'] ?><?= e(version_fichier(__DIR__ . '/photos_blog/' . $article['image'])) ?>"
                     alt="" loading="lazy">
              <?php endif; ?>
            </div>
            <a class="blog-lire" href="blog-article.php?id=<?= (int) $article['id'] ?>">Lire l'article »</a>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($nb_pages > 1): ?>
        <nav class="blog-pagination" aria-label="Pages d'articles">
          <?php if ($page > 1): ?>
            <a href="blog.php?page=<?= $page - 1 ?><?= $categorie_filtre ? '&categorie=' . $categorie_filtre : '' ?>">« Page précédente</a>
          <?php endif; ?>
          <?php if ($page < $nb_pages): ?>
            <a href="blog.php?page=<?= $page + 1 ?><?= $categorie_filtre ? '&categorie=' . $categorie_filtre : '' ?>">Page suivante »</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>

    <aside class="blog-sidebar">
      <div class="blog-widget">
        <h2>Articles récents</h2>
        <?php if (!$recents): ?>
          <p class="form-note" style="margin:0;">Aucun article pour le moment.</p>
        <?php else: ?>
          <ul>
            <?php foreach ($recents as $recent): ?>
              <li><a href="blog-article.php?id=<?= (int) $recent['id'] ?>"><?= e($recent['titre']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="blog-widget">
        <h2>Catégories</h2>
        <ul>
          <li><a href="blog.php"<?= $categorie_filtre === 0 ? ' aria-current="page"' : '' ?>>Tous les articles</a></li>
          <?php foreach ($categories as $id_categorie => $nom_categorie): ?>
            <li><a href="blog.php?categorie=<?= $id_categorie ?>"<?= $id_categorie === $categorie_filtre ? ' aria-current="page"' : '' ?>><?= e($nom_categorie) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>
</div></section>
<?php
fin_page();
