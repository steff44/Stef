<?php
/*
 * Documents du club, classés par rubrique et catégorie — tables
 * rubriques_documents / categories_documents (voir inc/documents_categories.php),
 * modifiables par un responsable depuis parametres.php (choix explicite de
 * l'utilisateur, 20/08/2026). Tous les adhérents consultent et recherchent ;
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
        $titre        = trim((string) ($_POST['titre'] ?? ''));
        $categorie_id = (int) ($_POST['categorie_id'] ?? 0);
        $categorie    = categorie_document($pdo, $categorie_id);

        if ($titre === '') {
            definir_message('erreur', "Donnez un titre au document.");
        } elseif ($categorie === null) {
            definir_message('erreur', "Choisissez une rubrique — créez-en une dans Réglages du site si aucune ne convient.");
        } else {
            $resultat = enregistrer_fichier_envoye($_FILES['document'] ?? null, __DIR__ . '/fichiers', 'document');

            if ($resultat['erreur'] !== null) {
                definir_message('erreur', $resultat['erreur']);
            } else {
                $pdo->prepare(
                    'INSERT INTO documents (titre, description, fichier, nom_origine, taille, categorie, categorie_id, depose_par)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $titre,
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    $resultat['nom'],
                    // Le nom d'origine sert seulement à proposer un joli nom au
                    // téléchargement ; il n'est jamais utilisé comme chemin.
                    basename((string) ($_FILES['document']['name'] ?? 'document')),
                    (int) ($_FILES['document']['size'] ?? 0),
                    // Colonne historique, gardée synchronisée pour qui
                    // consulterait la base directement — categorie_id fait
                    // foi pour l'affichage (voir inc/documents_categories.php).
                    $categorie['categorie_nom'],
                    $categorie_id,
                    $adherent['id'],
                ]);
                definir_message('succes', "Document ajouté dans « {$categorie['categorie_nom']} ».");
            }
        }
    }

    header('Location: documents.php');
    exit;
}

$rubriques = rubriques_documents($pdo);

$documents = $pdo->query(
    'SELECT d.id, d.titre, d.description, d.nom_origine, d.taille, d.categorie_id, d.cree_le, a.nom AS auteur
       FROM documents d
       LEFT JOIN adherents a ON a.id = d.depose_par
      ORDER BY d.titre'
)->fetchAll();

// Rangement par rubrique puis par catégorie, dans l'ordre de
// rubriques_documents() — pas celui, arbitraire, du résultat SQL — pour que
// la page présente toujours la même organisation. « Autres documents » ne
// recueille que des documents sans categorie_id valide (catégorie
// supprimée depuis leur dépôt, ou document déposé avant ce classement et
// dont l'ancienne valeur ne correspond plus à rien de connu).
$groupes = [];
$autres  = [];
foreach ($documents as $document) {
    $categorie_id = $document['categorie_id'] !== null ? (int) $document['categorie_id'] : null;
    $trouve       = false;
    if ($categorie_id !== null) {
        foreach ($rubriques as $rubrique_id => $rubrique) {
            if (isset($rubrique['categories'][$categorie_id])) {
                $groupes[$rubrique_id][$categorie_id][] = $document;
                $trouve = true;
                break;
            }
        }
    }
    if (!$trouve) {
        $autres[] = $document;
    }
}

debut_page("Documents", 'documents');
titre_page("Documents du club", "Comptes rendus, statuts, bulletins et ressources, réservés aux adhérents.");
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <div class="documents-intro">
    <?php if ($rubriques): ?>
      <p>
        Les documents du club sont classés en <?= count($rubriques) ?> rubriques, elles-mêmes
        divisées en catégories :
        <?php
          $descriptions = [];
          foreach ($rubriques as $rubrique) {
              $descriptions[] = '<strong>' . e($rubrique['nom']) . '</strong>'
                  . ($rubrique['categories'] ? ' (' . e(implode(', ', $rubrique['categories'])) . ')' : '');
          }
          echo implode(', ', $descriptions);
        ?>.
      </p>
      <p>
        Un responsable dépose chaque document et lui attribue sa catégorie au moment de
        l'envoi. Pour en retrouver un, parcourez les rubriques ci-dessous ou tapez son
        nom dans le champ de recherche.
        <?php if (est_administrateur()): ?>
          Les rubriques et catégories se renomment, s'ajoutent ou se suppriment depuis
          <a href="parametres.php">Réglages du site</a>.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p>
        Aucune rubrique n'est encore définie.
        <?php if (est_administrateur()): ?>
          Créez-en une depuis <a href="parametres.php">Réglages du site</a> avant de pouvoir
          déposer un document.
        <?php endif; ?>
      </p>
    <?php endif; ?>
  </div>

  <?php if (est_administrateur() && $rubriques): ?>
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
          <label for="categorie_id">Rubrique</label>
          <select id="categorie_id" name="categorie_id">
            <?php foreach ($rubriques as $rubrique): ?>
              <?php if (!$rubrique['categories']) continue; ?>
              <optgroup label="<?= e($rubrique['nom']) ?>">
                <?php foreach ($rubrique['categories'] as $categorie_id => $nom_categorie): ?>
                  <option value="<?= $categorie_id ?>"><?= e($nom_categorie) ?></option>
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

    <?php foreach ($rubriques as $rubrique_id => $rubrique): ?>
      <?php if (empty($groupes[$rubrique_id])) continue; ?>
      <div class="rubrique-documents">
        <h2><?= e($rubrique['nom']) ?></h2>
        <?php foreach ($rubrique['categories'] as $categorie_id => $nom_categorie): ?>
          <?php if (empty($groupes[$rubrique_id][$categorie_id])) continue; ?>
          <div class="sous-categorie-documents">
            <h3><?= e($nom_categorie) ?></h3>
            <ul class="liste-documents">
              <?php foreach ($groupes[$rubrique_id][$categorie_id] as $document): ?>
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
