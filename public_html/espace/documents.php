<?php
/*
 * Documents du club, classés par rubrique (voir RUBRIQUES_DOCUMENTS dans
 * inc/documents_categories.php — seul endroit à modifier pour ajouter une
 * rubrique ou une catégorie). Tous les adhérents consultent et recherchent ;
 * seuls les responsables déposent, classent et suppriment.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/televersement.php';
require_once __DIR__ . '/inc/documents_categories.php';

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
        $titre     = trim((string) ($_POST['titre'] ?? ''));
        $categorie = (string) ($_POST['categorie'] ?? '');
        if (!in_array($categorie, categories_documents_a_plat(), true)) {
            $categorie = categories_documents_a_plat()[0];
        }

        if ($titre === '') {
            definir_message('erreur', "Donnez un titre au document.");
        } else {
            $resultat = enregistrer_fichier_envoye($_FILES['document'] ?? null, __DIR__ . '/fichiers', 'document');

            if ($resultat['erreur'] !== null) {
                definir_message('erreur', $resultat['erreur']);
            } else {
                $pdo->prepare(
                    'INSERT INTO documents (titre, description, fichier, nom_origine, taille, categorie, depose_par)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $titre,
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    $resultat['nom'],
                    // Le nom d'origine sert seulement à proposer un joli nom au
                    // téléchargement ; il n'est jamais utilisé comme chemin.
                    basename((string) ($_FILES['document']['name'] ?? 'document')),
                    (int) ($_FILES['document']['size'] ?? 0),
                    $categorie,
                    $adherent['id'],
                ]);
                definir_message('succes', "Document ajouté dans « {$categorie} ».");
            }
        }
    }

    header('Location: documents.php');
    exit;
}

$documents = $pdo->query(
    'SELECT d.id, d.titre, d.description, d.nom_origine, d.taille, d.categorie, d.cree_le, a.nom AS auteur
       FROM documents d
       LEFT JOIN adherents a ON a.id = d.depose_par
      ORDER BY d.titre'
)->fetchAll();

// Rangement par rubrique puis par catégorie, dans l'ordre de
// RUBRIQUES_DOCUMENTS — pas celui, arbitraire, du résultat SQL — pour que la
// page présente toujours la même organisation. « Autres documents » ne
// recueille que des documents dont la catégorie ne correspond plus à aucune
// rubrique connue (RUBRIQUES_DOCUMENTS modifié après leur dépôt) : ça ne
// devrait normalement jamais arriver.
$groupes = [];
$autres  = [];
foreach ($documents as $document) {
    $rubrique = rubrique_de_categorie($document['categorie']);
    if ($rubrique === null) {
        $autres[] = $document;
    } else {
        $groupes[$rubrique][$document['categorie']][] = $document;
    }
}

debut_page("Documents", 'documents');
titre_page("Documents du club", "Comptes rendus, statuts, bulletins et ressources, réservés aux adhérents.");
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <div class="documents-intro">
    <p>
      Les documents du club sont classés en quatre grandes rubriques, elles-mêmes
      divisées en catégories : <strong>Débuter la photo</strong> (bases techniques,
      premiers réglages, guides simplifiés pour les seniors),
      <strong>Ateliers techniques du club</strong> (lumière, exposition, composition,
      matériel, post-traitement), <strong>Thèmes photographiques</strong> (portrait,
      paysage, macro, nature, street, architecture, noir &amp; blanc, créatif) et
      <strong>Administration du club</strong> (planning, comptes rendus, documents
      internes).
    </p>
    <p>
      Un responsable dépose chaque document et lui attribue sa rubrique au moment de
      l'envoi. Pour en retrouver un, parcourez les rubriques ci-dessous ou tapez son
      nom dans le champ de recherche.
    </p>
  </div>

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
          <label for="categorie">Rubrique</label>
          <select id="categorie" name="categorie">
            <?php foreach (RUBRIQUES_DOCUMENTS as $rubrique => $categories): ?>
              <optgroup label="<?= e($rubrique) ?>">
                <?php foreach ($categories as $categorie): ?>
                  <option value="<?= e($categorie) ?>"><?= e($categorie) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
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
    <div class="field documents-recherche">
      <label for="recherche-documents">Rechercher un document par son nom</label>
      <input type="search" id="recherche-documents" placeholder="Ex. : compte rendu, tarifs, portrait…">
    </div>
    <div id="documents-recherche-vide" class="empty-state" hidden><p>Aucun document ne correspond à cette recherche.</p></div>

    <?php foreach (RUBRIQUES_DOCUMENTS as $rubrique => $categories): ?>
      <?php if (empty($groupes[$rubrique])) continue; ?>
      <div class="rubrique-documents">
        <h2><?= e($rubrique) ?></h2>
        <?php foreach ($categories as $categorie): ?>
          <?php if (empty($groupes[$rubrique][$categorie])) continue; ?>
          <div class="sous-categorie-documents">
            <h3><?= e($categorie) ?></h3>
            <ul class="liste-documents">
              <?php foreach ($groupes[$rubrique][$categorie] as $document): ?>
                <?php include __DIR__ . '/inc/document-ligne.php'; ?>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <?php if ($autres): ?>
      <div class="rubrique-documents">
        <h2>Autres documents</h2>
        <ul class="liste-documents">
          <?php foreach ($autres as $document): ?>
            <?php include __DIR__ . '/inc/document-ligne.php'; ?>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div></section>
<script>
(function () {
  var champ = document.getElementById("recherche-documents");
  if (!champ) return;

  var lignes    = document.querySelectorAll(".document-ligne");
  var groupes   = document.querySelectorAll(".sous-categorie-documents, .rubrique-documents");
  var messageVide = document.getElementById("documents-recherche-vide");

  champ.addEventListener("input", function () {
    var recherche = champ.value.trim().toLowerCase();

    lignes.forEach(function (ligne) {
      var titre = (ligne.dataset.titre || "").toLowerCase();
      ligne.hidden = recherche !== "" && titre.indexOf(recherche) === -1;
    });

    groupes.forEach(function (groupe) {
      var visibles = groupe.querySelectorAll(".document-ligne:not([hidden])");
      groupe.hidden = recherche !== "" && visibles.length === 0;
    });

    var toutMasque = document.querySelectorAll(".document-ligne:not([hidden])").length === 0;
    messageVide.hidden = !(recherche !== "" && toutMasque);
  });
})();
</script>
<?php
fin_page();
