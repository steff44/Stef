<?php
/*
 * Documents du club : comptes rendus, statuts, bulletins d'inscription…
 * Tous les adhérents consultent ; seuls les responsables déposent et suppriment.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/televersement.php';

$adherent = exige_connexion();
$pdo      = base_de_donnees();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    exige_administrateur();

    if (($_POST['action'] ?? '') === 'supprimer') {
        $id      = (int) ($_POST['id'] ?? 0);
        $requete = $pdo->prepare('SELECT fichier FROM documents WHERE id = ?');
        $requete->execute([$id]);

        if ($document = $requete->fetch()) {
            $pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
            @unlink(__DIR__ . '/fichiers/' . basename((string) $document['fichier']));
            definir_message('succes', "Document supprimé.");
        }
    } else {
        $titre = trim((string) ($_POST['titre'] ?? ''));
        if ($titre === '') {
            definir_message('erreur', "Donnez un titre au document.");
        } else {
            $resultat = enregistrer_fichier_envoye($_FILES['document'] ?? null, __DIR__ . '/fichiers', 'document');

            if ($resultat['erreur'] !== null) {
                definir_message('erreur', $resultat['erreur']);
            } else {
                $pdo->prepare(
                    'INSERT INTO documents (titre, description, fichier, nom_origine, taille, depose_par)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $titre,
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    $resultat['nom'],
                    // Le nom d'origine sert seulement à proposer un joli nom au
                    // téléchargement ; il n'est jamais utilisé comme chemin.
                    basename((string) ($_FILES['document']['name'] ?? 'document')),
                    (int) ($_FILES['document']['size'] ?? 0),
                    $adherent['id'],
                ]);
                definir_message('succes', "Document ajouté.");
            }
        }
    }

    header('Location: documents.php');
    exit;
}

$documents = $pdo->query(
    'SELECT d.id, d.titre, d.description, d.nom_origine, d.taille, d.cree_le, a.nom AS auteur
       FROM documents d
       LEFT JOIN adherents a ON a.id = d.depose_par
      ORDER BY d.cree_le DESC'
)->fetchAll();

debut_page("Documents", 'documents');
titre_page("Documents du club", "Comptes rendus, statuts, bulletins et tarifs, réservés aux adhérents.");
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <?php if (est_administrateur()): ?>
    <details class="depot-bloc">
      <summary>Ajouter un document</summary>
      <form method="post" enctype="multipart/form-data" class="form-card" style="margin-top:16px;">
        <?= champ_csrf() ?>
        <div class="field">
          <label for="titre">Titre</label>
          <input type="text" id="titre" name="titre" required maxlength="190"
                 placeholder="Compte rendu de l'assemblée générale 2026">
        </div>
        <div class="field">
          <label for="description">Description (facultatif)</label>
          <textarea id="description" name="description" rows="2"></textarea>
        </div>
        <div class="field">
          <label for="document">Fichier (PDF, Word, Excel, OpenDocument, texte ou image — <?= taille_lisible(TAILLE_MAX_OCTETS) ?> maximum)</label>
          <input type="file" id="document" name="document" required>
        </div>
        <button type="submit" class="btn btn-primary">Déposer le document</button>
      </form>
    </details>
  <?php endif; ?>

  <?php if (!$documents): ?>
    <div class="empty-state" style="margin-top:28px;">
      <p>Aucun document pour l'instant.</p>
    </div>
  <?php else: ?>
    <ul class="liste-documents">
      <?php foreach ($documents as $document): ?>
        <li class="document-ligne">
          <div class="document-infos">
            <a class="document-titre" href="telecharger.php?type=document&amp;id=<?= (int) $document['id'] ?>">
              <?= e($document['titre']) ?>
            </a>
            <?php if ($document['description']): ?>
              <p class="document-description"><?= e($document['description']) ?></p>
            <?php endif; ?>
            <p class="document-meta">
              <?= e($document['nom_origine']) ?> — <?= e(taille_lisible((int) $document['taille'])) ?>
              — déposé le <?= e(date_en_francais($document['cree_le'], false)) ?>
              <?= $document['auteur'] ? ' par ' . e($document['auteur']) : '' ?>
            </p>
          </div>
          <div class="document-actions">
            <a class="btn btn-ghost" href="telecharger.php?type=document&amp;id=<?= (int) $document['id'] ?>">Télécharger</a>
            <?php if (est_administrateur()): ?>
              <form method="post" onsubmit="return confirm('Supprimer ce document ?');">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="id" value="<?= (int) $document['id'] ?>">
                <button type="submit" class="lien-danger">Supprimer</button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div></section>
<?php
fin_page();
