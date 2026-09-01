<?php
/*
 * Documents du club, classés par rubrique et catégorie — tables
 * rubriques_documents / categories_documents (voir inc/documents_categories.php),
 * modifiables par un responsable depuis parametres.php (choix explicite de
 * l'utilisateur, 20/08/2026). Tous les adhérents consultent et recherchent ;
 * seuls les responsables et les éditeurs (est_gestionnaire(), 23/08/2026)
 * déposent, classent et suppriment.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/televersement.php';
require_once __DIR__ . '/inc/documents_categories.php';

$adherent = exige_connexion();
$pdo      = base_de_donnees();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    exige_gestionnaire();

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
        // Le titre de chaque document est repris du nom de son fichier, sans
        // l'extension (choix explicite de l'utilisateur, 21/08/2026) : avec
        // plusieurs fichiers déposés d'un coup, un seul champ de titre saisi
        // à la main n'aurait plus de sens.
        $categorie_id = (int) ($_POST['categorie_id'] ?? 0);
        $categorie    = categorie_document($pdo, $categorie_id);
        $description  = trim((string) ($_POST['description'] ?? '')) ?: null;
        $fichiers     = fichiers_multiples($_FILES['documents'] ?? ['name' => []]);

        if ($categorie === null) {
            definir_message('erreur', "Choisissez une rubrique — créez-en une dans Réglages du site si aucune ne convient.");
        } elseif (!$fichiers) {
            definir_message('erreur', "Sélectionnez au moins un fichier.");
        } else {
            $reussis = 0;
            $erreurs = [];

            foreach ($fichiers as $fichier) {
                $resultat = enregistrer_fichier_envoye($fichier, __DIR__ . '/fichiers', 'document');
                $nom_origine = basename((string) $fichier['name']);

                if ($resultat['erreur'] !== null) {
                    $erreurs[] = "« {$nom_origine} » : {$resultat['erreur']}";
                    continue;
                }

                $titre = pathinfo($nom_origine, PATHINFO_FILENAME);
                $pdo->prepare(
                    'INSERT INTO documents (titre, description, fichier, nom_origine, taille, categorie, categorie_id, depose_par)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $titre !== '' ? $titre : $nom_origine,
                    $description,
                    $resultat['nom'],
                    // Le nom d'origine sert seulement à proposer un joli nom au
                    // téléchargement ; il n'est jamais utilisé comme chemin.
                    $nom_origine,
                    (int) $fichier['size'],
                    // Colonne historique, gardée synchronisée pour qui
                    // consulterait la base directement — categorie_id fait
                    // foi pour l'affichage (voir inc/documents_categories.php).
                    $categorie['categorie_nom'],
                    $categorie_id,
                    $adherent['id'],
                ]);
                $reussis++;
            }

            $parts = [];
            if ($reussis > 0) {
                $parts[] = "{$reussis} document" . ($reussis > 1 ? 's' : '')
                    . " ajouté" . ($reussis > 1 ? 's' : '') . " dans « {$categorie['categorie_nom']} ».";
            }
            array_push($parts, ...$erreurs);
            definir_message($erreurs ? 'erreur' : 'succes', implode(' ', $parts));
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

  <?php if (!$rubriques): ?>
    <p style="color:var(--text-muted);">
      Aucune rubrique n'est encore définie.
      <?php if (est_administrateur()): ?>
        Créez-en une depuis <a href="parametres.php">Réglages du site</a> avant de pouvoir
        déposer un document.
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <?php if (est_gestionnaire() && $rubriques): ?>
    <details class="depot-bloc">
      <summary>Ajouter un document</summary>
      <form method="post" enctype="multipart/form-data" class="form-card" style="margin-top:16px;">
        <?= champ_csrf() ?>
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
          <label for="description">Description (facultatif, s'applique à tous les fichiers déposés ici)</label>
          <textarea id="description" name="description" rows="2"></textarea>
        </div>
        <div class="field">
          <label for="documents">Fichiers (PDF, Word, Excel, OpenDocument, texte ou image — <?= taille_lisible(TAILLE_MAX_OCTETS) ?> maximum chacun)</label>
          <input type="file" id="documents" name="documents[]" multiple required
                 data-taille-max="<?= TAILLE_MAX_OCTETS ?>"
                 data-taille-max-lisible="<?= e(taille_lisible(TAILLE_MAX_OCTETS)) ?>">
          <p class="form-note">
            Plusieurs fichiers peuvent être sélectionnés d'un coup : le titre de chaque
            document reprend alors le nom de son fichier, sans l'extension.
          </p>
          <p class="form-avertissement" data-avertissement-taille hidden></p>
        </div>
        <button type="submit" class="btn btn-primary">Déposer</button>
      </form>
    </details>
  <?php endif; ?>

  <?php if ($rubriques): ?>
    <div class="field documents-recherche">
      <label for="recherche-documents">Rechercher un document par son nom</label>
      <input type="search" id="recherche-documents" placeholder="Ex. : compte rendu, tarifs, portrait…"
             value="<?= e($_GET['recherche'] ?? '') ?>">
    </div>
    <div id="documents-recherche-vide" class="empty-state" hidden><p>Aucun document ne correspond à cette recherche.</p></div>

    <?php
      // Sommaire cliquable : chaque catégorie (le niveau « final » d'une
      // rubrique) renvoie directement vers sa section plus bas sur la page —
      // toujours affichée, même sans document pour l'instant, pour que le
      // sommaire ne pointe jamais dans le vide.
    ?>
    <nav class="documents-index" aria-label="Sommaire des documents">
      <?php foreach ($rubriques as $rubrique_id => $rubrique): ?>
        <?php if (!$rubrique['categories']) continue; ?>
        <div class="documents-index-rubrique">
          <h2><?= e($rubrique['nom']) ?></h2>
          <ul class="documents-index-categories">
            <?php foreach ($rubrique['categories'] as $categorie_id => $nom_categorie): ?>
              <li><a href="#categorie-<?= $categorie_id ?>"><?= e($nom_categorie) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </nav>

    <?php foreach ($rubriques as $rubrique_id => $rubrique): ?>
      <?php if (!$rubrique['categories']) continue; ?>
      <div class="rubrique-documents">
        <h2><?= e($rubrique['nom']) ?></h2>
        <?php foreach ($rubrique['categories'] as $categorie_id => $nom_categorie): ?>
          <div class="sous-categorie-documents" id="categorie-<?= $categorie_id ?>">
            <h3><?= e($nom_categorie) ?></h3>
            <?php if (empty($groupes[$rubrique_id][$categorie_id])): ?>
              <p class="categorie-vide">Aucun document pour l'instant dans cette catégorie.</p>
            <?php else: ?>
              <ul class="liste-documents">
                <?php foreach ($groupes[$rubrique_id][$categorie_id] as $document): ?>
                  <?php include __DIR__ . '/inc/document-ligne.php'; ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
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

  function appliquerRecherche() {
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
  }

  champ.addEventListener("input", appliquerRecherche);

  // Un lien externe (ex. depuis la Galerie du Club) peut arriver avec
  // ?recherche=... déjà rempli côté serveur dans value="" : on applique le
  // filtre une première fois au chargement pour aller droit au document.
  if (champ.value.trim() !== "") {
    appliquerRecherche();
  }
})();
</script>
<?php
fin_page();
