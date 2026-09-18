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
require_once __DIR__ . '/inc/mail.php';

/*
 * Prévient tous les adhérents validés par e-mail qu'un ou plusieurs
 * documents ont été déposés dans « Documents du Club » (choix explicite de
 * l'utilisatrice, 14/09/2026) — même principe que les notifications déjà en
 * place pour une nouvelle sortie, un nouvel article de blog ou un nouvel
 * album de « Nos Sorties » : un e-mail par adhérent valide=1 actif=1 avec
 * une adresse renseignée, échoue silencieusement (voir envoyer_mail(),
 * inc/mail.php). Un seul e-mail par dépôt, même si plusieurs fichiers ont
 * été envoyés d'un coup — jamais un e-mail par fichier.
 */
function notifier_nouveaux_documents(PDO $pdo, string $categorie_nom, array $titres): void
{
    $expediteur = valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com';
    $lien       = SITE_URL . '/espace/documents.php';
    $liste      = implode("\n", array_map(static fn($titre) => "- {$titre}", $titres));
    $pluriel    = count($titres) > 1;

    $destinataires = $pdo->query(
        "SELECT nom, email FROM adherents WHERE valide = 1 AND actif = 1 AND email IS NOT NULL AND email <> ''"
    )->fetchAll();
    foreach ($destinataires as $destinataire) {
        envoyer_mail(
            $destinataire['email'],
            $expediteur,
            ($pluriel ? 'Nouveaux documents' : 'Nouveau document') . ' : ' . $categorie_nom,
            "Bonjour {$destinataire['nom']},\n\n"
            . ($pluriel ? "De nouveaux documents ont été ajoutés" : "Un nouveau document a été ajouté")
            . " dans « {$categorie_nom} » :\n\n"
            . "{$liste}\n\n"
            . "Retrouvez-les ici :\n{$lien}\n\n"
            . "À bientôt,\nLe Focal Club Turballais"
        );
    }
}

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
            $titres_reussis = [];

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
                $titres_reussis[] = $titre !== '' ? $titre : $nom_origine;
            }

            if ($titres_reussis !== []) {
                notifier_nouveaux_documents($pdo, $categorie['categorie_nom'], $titres_reussis);

                $liste_confirmation = implode("\n", array_map(static fn($titre) => "- {$titre}", $titres_reussis));
                envoyer_confirmation_personnelle(
                    $pdo,
                    $adherent,
                    'Confirmation de votre dépôt : ' . $categorie['categorie_nom'],
                    "Bonjour {$adherent['nom']},\n\n"
                    . "Ceci confirme votre dépôt dans « {$categorie['categorie_nom']} » :\n\n"
                    . "{$liste_confirmation}\n\n"
                    . "Si vous constatez une erreur, vous pouvez le corriger ou le supprimer ici :\n"
                    . SITE_URL . "/espace/documents.php\n\n"
                    . "À bientôt,\nLe Focal Club Turballais"
                );
            }

            $parts = [];
            if ($reussis > 0) {
                $parts[] = "{$reussis} document" . ($reussis > 1 ? 's' : '')
                    . " ajouté" . ($reussis > 1 ? 's' : '') . " dans « {$categorie['categorie_nom']} ». Un e-mail a été envoyé aux adhérents.";
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

// Uniquement les catégories qui contiennent déjà au moins un document
// (choix explicite de l'utilisatrice, 08/09/2026, pour la lisibilité de la
// page) — le formulaire de dépôt continue lui de lister $rubriques en
// entier (voir plus bas) : il faut bien pouvoir choisir une catégorie
// encore vide pour y déposer un premier document. Une rubrique dont
// aucune catégorie n'a de document disparaît entièrement, sommaire compris.
$rubriques_peuplees = [];
foreach ($rubriques as $rubrique_id => $rubrique) {
    $categories_peuplees = [];
    foreach ($rubrique['categories'] as $categorie_id => $nom_categorie) {
        if (!empty($groupes[$rubrique_id][$categorie_id])) {
            $categories_peuplees[$categorie_id] = $nom_categorie;
        }
    }
    if ($categories_peuplees) {
        $rubriques_peuplees[$rubrique_id] = ['nom' => $rubrique['nom'], 'categories' => $categories_peuplees];
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
          <span class="label-comme">Rubrique et catégorie</span>
          <?php
            // Palette cyclique — une couleur par rubrique, réutilisée dès
            // que le nombre de rubriques dépasse la palette. Choisie parmi
            // les teintes déjà présentes ailleurs sur le site (accents,
            // catégories de sorties), pour rester dans la même famille.
            $palette_rubriques = ['#ec4899', '#0ea5e9', '#f59e0b', '#22c55e', '#a855f7', '#0e7490'];
            $index_couleur     = 0;
          ?>
          <div class="choix-rubrique-groupe">
            <?php foreach ($rubriques as $rubrique): ?>
              <?php if (!$rubrique['categories']) continue; ?>
              <?php $couleur_rubrique = $palette_rubriques[$index_couleur % count($palette_rubriques)]; $index_couleur++; ?>
              <div class="choix-rubrique-bloc" style="--rubrique-couleur: <?= e($couleur_rubrique) ?>;">
                <h3 class="choix-rubrique-titre"><?= e($rubrique['nom']) ?></h3>
                <div class="choix-rubrique-categories">
                  <?php foreach ($rubrique['categories'] as $categorie_id => $nom_categorie): ?>
                    <label class="choix-rubrique-categorie">
                      <input type="radio" name="categorie_id" value="<?= $categorie_id ?>" required>
                      <span><?= e($nom_categorie) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
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
    <?php if (!$rubriques_peuplees && !$autres): ?>
      <p class="empty-state">Aucun document n'a encore été déposé.</p>
    <?php else: ?>
      <div class="field documents-recherche">
        <label for="recherche-documents">Rechercher un document par son nom</label>
        <input type="search" id="recherche-documents" placeholder="Ex. : compte rendu, tarifs, portrait…"
               value="<?= e($_GET['recherche'] ?? '') ?>">
      </div>
      <?php
        // Résultats d'une recherche : liste à plat, juste sous le champ,
        // sans les intitulés de rubrique/catégorie (choix explicite de
        // l'utilisatrice, 01/09/2026 — ces intitulés ne servent plus qu'au
        // sommaire ci-dessous, pour naviguer hors recherche). Rempli en
        // JavaScript par déplacement des <li> correspondants, remis à leur
        // place quand le champ est vidé — voir le script en bas de page.
      ?>
      <ul id="resultats-recherche" class="liste-documents" hidden></ul>
      <div id="documents-recherche-vide" class="empty-state" hidden><p>Aucun document ne correspond à cette recherche.</p></div>

      <?php
        // Sommaire cliquable : seules les catégories peuplées apparaissent
        // (voir $rubriques_peuplees ci-dessus, choix explicite de
        // l'utilisatrice, 08/09/2026, pour la lisibilité — revient sur le
        // choix du 01/09/2026 qui gardait toutes les catégories visibles
        // même vides).
      ?>
      <nav class="documents-index" aria-label="Sommaire des documents">
        <?php foreach ($rubriques_peuplees as $rubrique_id => $rubrique): ?>
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

      <?php foreach ($rubriques_peuplees as $rubrique_id => $rubrique): ?>
        <div class="rubrique-documents">
          <h2><?= e($rubrique['nom']) ?></h2>
          <?php foreach ($rubrique['categories'] as $categorie_id => $nom_categorie): ?>
            <div class="sous-categorie-documents" id="categorie-<?= $categorie_id ?>">
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
  <?php endif; ?>
</div></section>
<script>
(function () {
  var champ = document.getElementById("recherche-documents");
  if (!champ) return;

  var lignes      = document.querySelectorAll(".document-ligne");
  var resultats   = document.getElementById("resultats-recherche");
  var sommaire    = document.querySelector(".documents-index");
  var rubriques   = document.querySelectorAll(".rubrique-documents");
  var messageVide = document.getElementById("documents-recherche-vide");

  // Chaque ligne garde le souvenir de son parent d'origine, pour l'y
  // remettre quand la recherche est vidée — plutôt qu'un nouveau rendu
  // complet, un simple déplacement du même élément dans le DOM (aucune
  // duplication, les actions Télécharger/Supprimer restent
  // fonctionnelles). `lignes` est une NodeList statique, figée dans
  // l'ordre du document au chargement de la page : remettre chaque ligne
  // en dernier enfant de son parent, dans cet ordre-là, reconstruit
  // exactement l'ordre d'origine (chaque `<ul class="liste-documents">`
  // ne contient jamais que des lignes de document, rien d'autre).
  lignes.forEach(function (ligne) {
    ligne._parent = ligne.parentElement;
  });

  // Recherche insensible aux accents (choix explicite de l'utilisatrice,
  // 08/09/2026) : "ecran" doit trouver "écran". normalize("NFD") décompose
  // chaque lettre accentuée en lettre de base + accent séparé, que la
  // plage Unicode U+0300–U+036F (les diacritiques combinants) retire ensuite.
  function normaliser(texte) {
    return texte.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
  }

  function appliquerRecherche() {
    var recherche = normaliser(champ.value.trim());
    var enRecherche = recherche !== "";
    var trouve = 0;

    lignes.forEach(function (ligne) {
      var titre = normaliser(ligne.dataset.titre || "");
      if (enRecherche && titre.indexOf(recherche) !== -1) {
        resultats.appendChild(ligne);
        trouve++;
      } else {
        // Remet la ligne à sa place d'origine dès qu'elle ne correspond
        // plus à la recherche en cours — pas seulement quand le champ est
        // entièrement vidé (piège corrigé le 08/09/2026 : affiner une
        // recherche laissait sinon les résultats d'une frappe précédente
        // affichés en plus des nouveaux, jamais retirés de #resultats-recherche).
        ligne._parent.appendChild(ligne);
      }
    });

    sommaire.hidden = enRecherche;
    rubriques.forEach(function (rubrique) { rubrique.hidden = enRecherche; });
    resultats.hidden = !enRecherche || trouve === 0;
    messageVide.hidden = !(enRecherche && trouve === 0);
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
