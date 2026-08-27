<?php
/*
 * Réglages du site public — réservé aux responsables.
 *
 * Modifie les coordonnées et le texte de présentation affichés sur les pages
 * publiques (accueil, contact, galerie, événements, le club) : ce sont les
 * seuls textes du site que l'espace adhérents permet de changer. Le reste du
 * site reste écrit en dur dans le HTML, comme documenté dans CLAUDE.md.
 *
 * Les pages publiques sont du HTML statique : les valeurs saisies ici ne s'y
 * affichent pas directement au chargement, mais sont récupérées par le
 * navigateur du visiteur via infos-club.php (voir js/main.js), qui vient
 * remplacer le texte déjà présent dans la page. Un délai de quelques minutes
 * est donc normal avant qu'une modification soit visible partout, à cause du
 * cache navigateur sur ce point d'accès (voir infos-club.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/documents_categories.php';
require_once __DIR__ . '/inc/galerie_categories.php';
require_once __DIR__ . '/inc/blog.php';
require_once __DIR__ . '/inc/albums.php';

exige_administrateur();
$pdo = base_de_donnees();

const CHAMPS = [
    'nom_lieu'            => 'Nom du lieu de réunion',
    'adresse_rue'         => 'Rue et numéro',
    'adresse_code_postal' => 'Code postal',
    'adresse_ville'       => 'Ville',
    'telephone'           => 'Téléphone',
    'email'               => 'E-mail',
    'horaires_creneau'    => 'Jour et créneau des réunions',
    'horaires_frequence'  => 'Fréquence',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? '');

    // Rubriques et catégories des documents (voir inc/documents_categories.php) :
    // un formulaire par action, sur le même principe que adherents.php. Le
    // gros formulaire des coordonnées du club, plus bas, ne porte pas de
    // champ « action » — c'est ce qui distingue les deux.
    if ($action !== '') {
        $id  = (int) ($_POST['id'] ?? 0);
        $nom = trim((string) ($_POST['nom'] ?? ''));

        if (in_array($action, ['ajouter_rubrique', 'renommer_rubrique', 'ajouter_categorie', 'renommer_categorie', 'ajouter_categorie_galerie', 'renommer_categorie_galerie', 'ajouter_categorie_blog', 'renommer_categorie_blog'], true) && $nom === '') {
            definir_message('erreur', "Le nom ne peut pas être vide.");
        } elseif ($action === 'ajouter_rubrique') {
            $ordre = (int) $pdo->query('SELECT COALESCE(MAX(ordre), -1) FROM rubriques_documents')->fetchColumn() + 1;
            $pdo->prepare('INSERT INTO rubriques_documents (nom, ordre) VALUES (?, ?)')->execute([$nom, $ordre]);
            definir_message('succes', "Rubrique « {$nom} » ajoutée.");

        } elseif ($action === 'renommer_rubrique') {
            $pdo->prepare('UPDATE rubriques_documents SET nom = ? WHERE id = ?')->execute([$nom, $id]);
            definir_message('succes', "Rubrique renommée en « {$nom} ».");

        } elseif ($action === 'supprimer_rubrique') {
            $requete = $pdo->prepare('SELECT COUNT(*) FROM categories_documents WHERE rubrique_id = ?');
            $requete->execute([$id]);
            $nb_categories = (int) $requete->fetchColumn();

            if ($nb_categories > 0) {
                definir_message('erreur', "Cette rubrique contient encore {$nb_categories} catégorie(s) — supprimez-les d'abord.");
            } else {
                $pdo->prepare('DELETE FROM rubriques_documents WHERE id = ?')->execute([$id]);
                definir_message('succes', "Rubrique supprimée.");
            }

        } elseif ($action === 'ajouter_categorie') {
            $rubrique_id = (int) ($_POST['rubrique_id'] ?? 0);
            $requete     = $pdo->prepare('SELECT COUNT(*) FROM rubriques_documents WHERE id = ?');
            $requete->execute([$rubrique_id]);

            if (!$requete->fetchColumn()) {
                definir_message('erreur', "Rubrique introuvable.");
            } else {
                $requete_ordre = $pdo->prepare('SELECT COALESCE(MAX(ordre), -1) FROM categories_documents WHERE rubrique_id = ?');
                $requete_ordre->execute([$rubrique_id]);
                $ordre = (int) $requete_ordre->fetchColumn() + 1;
                $pdo->prepare('INSERT INTO categories_documents (rubrique_id, nom, ordre) VALUES (?, ?, ?)')
                    ->execute([$rubrique_id, $nom, $ordre]);
                definir_message('succes', "Catégorie « {$nom} » ajoutée.");
            }

        } elseif ($action === 'renommer_categorie') {
            $pdo->prepare('UPDATE categories_documents SET nom = ? WHERE id = ?')->execute([$nom, $id]);
            definir_message('succes', "Catégorie renommée en « {$nom} ».");

        } elseif ($action === 'supprimer_categorie') {
            $requete = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE categorie_id = ?');
            $requete->execute([$id]);
            $nb_documents = (int) $requete->fetchColumn();

            if ($nb_documents > 0) {
                definir_message('erreur', "{$nb_documents} document(s) sont classés dans cette catégorie — déplacez-les ou supprimez-les d'abord.");
            } else {
                $pdo->prepare('DELETE FROM categories_documents WHERE id = ?')->execute([$id]);
                definir_message('succes', "Catégorie supprimée.");
            }

        } elseif ($action === 'ajouter_categorie_galerie') {
            $ordre = (int) $pdo->query('SELECT COALESCE(MAX(ordre), -1) FROM categories_galerie')->fetchColumn() + 1;
            $pdo->prepare('INSERT INTO categories_galerie (nom, ordre) VALUES (?, ?)')->execute([$nom, $ordre]);
            definir_message('succes', "Catégorie « {$nom} » ajoutée.");

        } elseif ($action === 'renommer_categorie_galerie') {
            $pdo->prepare('UPDATE categories_galerie SET nom = ? WHERE id = ?')->execute([$nom, $id]);
            definir_message('succes', "Catégorie renommée en « {$nom} ».");

        } elseif ($action === 'supprimer_categorie_galerie') {
            // Compte les deux galeries : une catégorie partagée par
            // galerie.php et galerie-club.php (voir inc/galerie_categories.php)
            // ne doit pas pouvoir disparaître tant que l'une des deux
            // l'utilise encore.
            $requete_club = $pdo->prepare('SELECT COUNT(*) FROM photos_club WHERE categorie_id = ?');
            $requete_club->execute([$id]);
            $requete_privee = $pdo->prepare('SELECT COUNT(*) FROM photos_privees WHERE categorie_id = ?');
            $requete_privee->execute([$id]);
            $nb_photos = (int) $requete_club->fetchColumn() + (int) $requete_privee->fetchColumn();

            if ($nb_photos > 0) {
                definir_message('erreur', "{$nb_photos} photo(s) sont classées dans cette catégorie — déplacez-les ou supprimez-les d'abord.");
            } else {
                $pdo->prepare('DELETE FROM categories_galerie WHERE id = ?')->execute([$id]);
                definir_message('succes', "Catégorie supprimée.");
            }

        } elseif ($action === 'ajouter_categorie_blog') {
            $ordre = (int) $pdo->query('SELECT COALESCE(MAX(ordre), -1) FROM categories_blog')->fetchColumn() + 1;
            $pdo->prepare('INSERT INTO categories_blog (nom, ordre) VALUES (?, ?)')->execute([$nom, $ordre]);
            definir_message('succes', "Catégorie « {$nom} » ajoutée.");

        } elseif ($action === 'renommer_categorie_blog') {
            $pdo->prepare('UPDATE categories_blog SET nom = ? WHERE id = ?')->execute([$nom, $id]);
            definir_message('succes', "Catégorie renommée en « {$nom} ».");

        } elseif ($action === 'supprimer_categorie_blog') {
            $requete = $pdo->prepare('SELECT COUNT(*) FROM articles_blog WHERE categorie_id = ?');
            $requete->execute([$id]);
            $nb_articles = (int) $requete->fetchColumn();

            if ($nb_articles > 0) {
                definir_message('erreur', "{$nb_articles} article(s) sont classés dans cette catégorie — déplacez-les ou supprimez-les d'abord.");
            } else {
                $pdo->prepare('DELETE FROM categories_blog WHERE id = ?')->execute([$id]);
                definir_message('succes', "Catégorie supprimée.");
            }

        // Albums de « Nos Sorties » : nom + type, et selon le type un
        // dossier Drive (type='drive') ou rien de plus, les photos étant
        // déposées directement par les adhérents (type='local' — choix
        // explicite de l'utilisateur, 27/08/2026, réservé aux sorties avec
        // peu de photos). L'identifiant de dossier Drive est validé ici pour
        // ne jamais laisser entrer autre chose que des caractères
        // d'identifiant Drive — infos-albums.php l'insère dans une clause
        // `q` envoyée à l'API Google.
        } elseif (in_array($action, ['ajouter_album', 'modifier_album'], true)) {
            $type = (string) ($_POST['type'] ?? 'drive') === 'local' ? 'local' : 'drive';

            // Basculer un album local (déjà des photos déposées ici) vers
            // Drive orphelinerait ces photos — ni affichables (l'album
            // deviendrait un dossier Drive vide), ni supprimables (la page
            // de dépôt refuse un album qui n'est plus de type local). Refusé
            // avec un message explicite, même principe que les catégories
            // encore utilisées plus haut dans ce fichier.
            $photos_orphelines = 0;
            if ($action === 'modifier_album' && $type === 'drive') {
                $requete = $pdo->prepare('SELECT COUNT(*) FROM photos_sorties WHERE album_id = ?');
                $requete->execute([$id]);
                $photos_orphelines = (int) $requete->fetchColumn();
            }

            if ($nom === '') {
                definir_message('erreur', "Le nom de l'album ne peut pas être vide.");
            } elseif ($photos_orphelines > 0) {
                definir_message('erreur', "Cet album contient encore {$photos_orphelines} photo(s) hébergée(s) sur ce site — supprimez-les d'abord pour basculer vers Google Drive.");
            } elseif ($type === 'local') {
                $dossier = '';
                if ($action === 'ajouter_album') {
                    $ordre = (int) $pdo->query('SELECT COALESCE(MAX(ordre), -1) FROM albums_sorties')->fetchColumn() + 1;
                    $pdo->prepare('INSERT INTO albums_sorties (nom, dossier_drive, type, ordre) VALUES (?, ?, ?, ?)')
                        ->execute([$nom, $dossier, $type, $ordre]);
                    definir_message('succes', "Album « {$nom} » ajouté.");
                } else {
                    $pdo->prepare('UPDATE albums_sorties SET nom = ?, type = ? WHERE id = ?')
                        ->execute([$nom, $type, $id]);
                    definir_message('succes', "Album « {$nom} » modifié.");
                }
            } else {
                $dossier = trim((string) ($_POST['dossier_drive'] ?? ''));
                // Colle aussi bien un identifiant seul qu'une URL de dossier
                // complète (drive.google.com/drive/folders/ID) : on en
                // extrait l'identifiant, plus simple que d'exiger un
                // copier-coller précis.
                if (preg_match('#/folders/([A-Za-z0-9_-]+)#', $dossier, $trouve) === 1) {
                    $dossier = $trouve[1];
                }

                if (preg_match('/^[A-Za-z0-9_-]+$/', $dossier) !== 1) {
                    definir_message('erreur', "Identifiant de dossier Google Drive invalide. Collez l'adresse du dossier, ou seulement la partie après « /folders/ ».");
                } elseif ($action === 'ajouter_album') {
                    $ordre = (int) $pdo->query('SELECT COALESCE(MAX(ordre), -1) FROM albums_sorties')->fetchColumn() + 1;
                    $pdo->prepare('INSERT INTO albums_sorties (nom, dossier_drive, type, ordre) VALUES (?, ?, ?, ?)')
                        ->execute([$nom, $dossier, $type, $ordre]);
                    definir_message('succes', "Album « {$nom} » ajouté.");
                } else {
                    $pdo->prepare('UPDATE albums_sorties SET nom = ?, dossier_drive = ?, type = ? WHERE id = ?')
                        ->execute([$nom, $dossier, $type, $id]);
                    definir_message('succes', "Album « {$nom} » modifié.");
                }
            }

        } elseif ($action === 'supprimer_album') {
            // Un album local a des photos sur cet hébergement : les fichiers
            // doivent être effacés du disque avant de supprimer la ligne,
            // sans quoi ils resteraient orphelins (la suppression en cascade
            // de photos_sorties ne touche que les lignes, jamais le disque).
            // Un album Drive n'a rien à effacer ici, les photos y restent.
            $requete = $pdo->prepare('SELECT type FROM albums_sorties WHERE id = ?');
            $requete->execute([$id]);
            $type_album = (string) $requete->fetchColumn();

            if ($type_album === 'local') {
                $fichiers = $pdo->prepare('SELECT fichier FROM photos_sorties WHERE album_id = ?');
                $fichiers->execute([$id]);
                foreach ($fichiers->fetchAll(PDO::FETCH_COLUMN) as $fichier) {
                    @unlink(__DIR__ . '/photos_sorties/' . basename((string) $fichier));
                }
            }

            $pdo->prepare('DELETE FROM albums_sorties WHERE id = ?')->execute([$id]);
            definir_message('succes', $type_album === 'local'
                ? "Album supprimé, photos effacées."
                : "Album supprimé. Les photos restent sur Google Drive.");
        }

        header('Location: parametres.php');
        exit;
    }

    $valeurs = [];
    foreach (CHAMPS as $cle => $libelle) {
        $valeurs[$cle] = trim((string) ($_POST[$cle] ?? ''));
    }
    $valeurs['presentation'] = trim((string) ($_POST['presentation'] ?? ''));

    $erreurs = [];
    foreach (CHAMPS as $cle => $libelle) {
        if ($valeurs[$cle] === '') {
            $erreurs[] = "« {$libelle} » ne peut pas être vide.";
        }
    }
    if ($valeurs['adresse_code_postal'] !== '' && !preg_match('/^\d{5}$/', $valeurs['adresse_code_postal'])) {
        $erreurs[] = "Le code postal doit contenir 5 chiffres.";
    }
    if ($valeurs['email'] !== '' && !filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse e-mail n'est pas valide.";
    }
    if ($valeurs['presentation'] === '') {
        $erreurs[] = "Le texte de présentation ne peut pas être vide.";
    }

    if ($erreurs) {
        foreach ($erreurs as $erreur) {
            definir_message('erreur', $erreur);
        }
    } else {
        // ON DUPLICATE KEY UPDATE : fonctionne que la ligne existe déjà
        // (cas normal, posée par la migration) ou non.
        $requete = $pdo->prepare(
            'INSERT INTO parametres_site (cle, valeur) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)'
        );
        foreach ($valeurs as $cle => $valeur) {
            $requete->execute([$cle, $valeur]);
        }
        definir_message('succes', "Les réglages ont été enregistrés. Ils apparaîtront sur le site public d'ici quelques minutes.");
    }

    header('Location: parametres.php');
    exit;
}

$requete = $pdo->query('SELECT cle, valeur FROM parametres_site');
$actuel  = [];
foreach ($requete->fetchAll() as $ligne) {
    $actuel[$ligne['cle']] = $ligne['valeur'];
}

$rubriques           = rubriques_documents($pdo);
$categories_galerie  = categories_galerie($pdo);
$categories_blog     = categories_blog($pdo);
$albums              = albums_sorties($pdo);

debut_page("Réglages du site", 'parametres');
titre_page(
    "Réglages du site public",
    "Ces textes apparaissent sur l'accueil, la page Contact et le pied de page de tout le site."
);
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <form method="post" class="form-card" style="max-width:640px;">
    <?= champ_csrf() ?>

    <h2 style="font-family:var(--font-heading);font-size:1.2rem;margin:0 0 18px;">Lieu de réunion</h2>
    <div class="field">
      <label for="nom_lieu">Nom du lieu</label>
      <input type="text" id="nom_lieu" name="nom_lieu" required maxlength="190"
             value="<?= e($actuel['nom_lieu'] ?? '') ?>">
    </div>
    <div class="field-row">
      <div class="field">
        <label for="adresse_rue">Rue et numéro</label>
        <input type="text" id="adresse_rue" name="adresse_rue" required maxlength="190"
               value="<?= e($actuel['adresse_rue'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="adresse_code_postal">Code postal</label>
        <input type="text" id="adresse_code_postal" name="adresse_code_postal" required
               inputmode="numeric" pattern="\d{5}" maxlength="5"
               value="<?= e($actuel['adresse_code_postal'] ?? '') ?>">
      </div>
    </div>
    <div class="field">
      <label for="adresse_ville">Ville</label>
      <input type="text" id="adresse_ville" name="adresse_ville" required maxlength="120"
             value="<?= e($actuel['adresse_ville'] ?? '') ?>">
    </div>

    <h2 style="font-family:var(--font-heading);font-size:1.2rem;margin:28px 0 18px;">Contact</h2>
    <div class="field-row">
      <div class="field">
        <label for="telephone">Téléphone</label>
        <input type="tel" id="telephone" name="telephone" required maxlength="30"
               value="<?= e($actuel['telephone'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" required maxlength="190"
               value="<?= e($actuel['email'] ?? '') ?>">
      </div>
    </div>

    <h2 style="font-family:var(--font-heading);font-size:1.2rem;margin:28px 0 18px;">Horaires</h2>
    <div class="field-row">
      <div class="field">
        <label for="horaires_creneau">Jour et créneau</label>
        <input type="text" id="horaires_creneau" name="horaires_creneau" required maxlength="120"
               placeholder="Jeudi 20h30-23h00" value="<?= e($actuel['horaires_creneau'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="horaires_frequence">Fréquence</label>
        <input type="text" id="horaires_frequence" name="horaires_frequence" required maxlength="120"
               placeholder="Réunions hebdomadaires" value="<?= e($actuel['horaires_frequence'] ?? '') ?>">
      </div>
    </div>

    <h2 style="font-family:var(--font-heading);font-size:1.2rem;margin:28px 0 18px;">Présentation</h2>
    <div class="field">
      <label for="presentation">Texte affiché en pied de page de chaque page du site</label>
      <textarea id="presentation" name="presentation" rows="4" required maxlength="500"><?= e($actuel['presentation'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%;">Enregistrer</button>
    <p class="form-note">Les visiteurs déjà sur le site verront le changement à leur prochaine visite, ou après quelques minutes si leur page reste ouverte.</p>
  </form>

  <div class="reglages-grid">
  <div class="form-card reglage-rubriques">
    <h2 style="font-family:var(--font-heading);font-size:1.2rem;margin:0 0 6px;">Rubriques des documents</h2>
    <p class="form-note" style="margin-top:0;margin-bottom:20px;">
      Ces rubriques et catégories organisent la page « Documents du club ». Une rubrique ou
      une catégorie contenant encore des documents ne peut pas être supprimée.
    </p>

    <?php foreach ($rubriques as $rubrique_id => $rubrique): ?>
      <div class="rubrique-reglage">
        <div class="reglage-ligne reglage-ligne--rubrique">
          <form method="post" class="reglage-forme-nom">
            <?= champ_csrf() ?>
            <input type="hidden" name="action" value="renommer_rubrique">
            <input type="hidden" name="id" value="<?= $rubrique_id ?>">
            <input type="text" name="nom" value="<?= e($rubrique['nom']) ?>" maxlength="120" required>
            <button type="submit" class="btn btn-ghost">Renommer</button>
          </form>
          <form method="post" onsubmit="return confirm('Supprimer la rubrique « <?= e(addslashes($rubrique['nom'])) ?> » ?');">
            <?= champ_csrf() ?>
            <input type="hidden" name="action" value="supprimer_rubrique">
            <input type="hidden" name="id" value="<?= $rubrique_id ?>">
            <button type="submit" class="lien-danger">Supprimer</button>
          </form>
        </div>

        <ul class="reglage-categories">
          <?php foreach ($rubrique['categories'] as $categorie_id => $nom_categorie): ?>
            <li class="reglage-ligne">
              <form method="post" class="reglage-forme-nom">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="renommer_categorie">
                <input type="hidden" name="id" value="<?= $categorie_id ?>">
                <input type="text" name="nom" value="<?= e($nom_categorie) ?>" maxlength="120" required>
                <button type="submit" class="btn btn-ghost">Renommer</button>
              </form>
              <form method="post" onsubmit="return confirm('Supprimer la catégorie « <?= e(addslashes($nom_categorie)) ?> » ?');">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="supprimer_categorie">
                <input type="hidden" name="id" value="<?= $categorie_id ?>">
                <button type="submit" class="lien-danger">Supprimer</button>
              </form>
            </li>
          <?php endforeach; ?>
          <li class="reglage-ligne">
            <form method="post" class="reglage-forme-nom">
              <?= champ_csrf() ?>
              <input type="hidden" name="action" value="ajouter_categorie">
              <input type="hidden" name="rubrique_id" value="<?= $rubrique_id ?>">
              <input type="text" name="nom" maxlength="120" required placeholder="Nouvelle catégorie">
              <button type="submit" class="btn btn-ghost">Ajouter</button>
            </form>
          </li>
        </ul>
      </div>
    <?php endforeach; ?>

    <form method="post" class="reglage-forme-nom reglage-forme-rubrique">
      <?= champ_csrf() ?>
      <input type="hidden" name="action" value="ajouter_rubrique">
      <input type="text" name="nom" maxlength="120" required placeholder="Nouvelle rubrique">
      <button type="submit" class="btn btn-primary">Ajouter une rubrique</button>
    </form>
  </div>

  <div class="reglages-col-droite">
  <div class="form-card reglage-rubriques">
    <h2 style="font-family:var(--font-heading);font-size:1.2rem;margin:0 0 6px;">Catégories des galeries</h2>
    <p class="form-note" style="margin-top:0;margin-bottom:20px;">
      Ces catégories organisent aussi bien la Galerie du Club que la Galerie privée,
      et les filtres de la page Galerie publique. Une catégorie contenant encore des
      photos (dans l'une ou l'autre galerie) ne peut pas être supprimée.
    </p>

    <ul class="reglage-categories" style="padding-left:0;">
      <?php foreach ($categories_galerie as $categorie_id => $nom_categorie): ?>
        <li class="reglage-ligne">
          <form method="post" class="reglage-forme-nom">
            <?= champ_csrf() ?>
            <input type="hidden" name="action" value="renommer_categorie_galerie">
            <input type="hidden" name="id" value="<?= $categorie_id ?>">
            <input type="text" name="nom" value="<?= e($nom_categorie) ?>" maxlength="120" required>
            <button type="submit" class="btn btn-ghost">Renommer</button>
          </form>
          <form method="post" onsubmit="return confirm('Supprimer la catégorie « <?= e(addslashes($nom_categorie)) ?> » ?');">
            <?= champ_csrf() ?>
            <input type="hidden" name="action" value="supprimer_categorie_galerie">
            <input type="hidden" name="id" value="<?= $categorie_id ?>">
            <button type="submit" class="lien-danger">Supprimer</button>
          </form>
        </li>
      <?php endforeach; ?>
      <li class="reglage-ligne">
        <form method="post" class="reglage-forme-nom">
          <?= champ_csrf() ?>
          <input type="hidden" name="action" value="ajouter_categorie_galerie">
          <input type="text" name="nom" maxlength="120" required placeholder="Nouvelle catégorie">
          <button type="submit" class="btn btn-ghost">Ajouter</button>
        </form>
      </li>
    </ul>
  </div>

  <div class="form-card reglage-rubriques">
    <h2 style="font-family:var(--font-heading);font-size:1.2rem;margin:0 0 6px;">Catégories du blog</h2>
    <p class="form-note" style="margin-top:0;margin-bottom:20px;">
      Ces catégories organisent la page « Blog du Club ». Une catégorie contenant encore
      des articles ne peut pas être supprimée.
    </p>

    <ul class="reglage-categories" style="padding-left:0;">
      <?php foreach ($categories_blog as $categorie_id => $nom_categorie): ?>
        <li class="reglage-ligne">
          <form method="post" class="reglage-forme-nom">
            <?= champ_csrf() ?>
            <input type="hidden" name="action" value="renommer_categorie_blog">
            <input type="hidden" name="id" value="<?= $categorie_id ?>">
            <input type="text" name="nom" value="<?= e($nom_categorie) ?>" maxlength="120" required>
            <button type="submit" class="btn btn-ghost">Renommer</button>
          </form>
          <form method="post" onsubmit="return confirm('Supprimer la catégorie « <?= e(addslashes($nom_categorie)) ?> » ?');">
            <?= champ_csrf() ?>
            <input type="hidden" name="action" value="supprimer_categorie_blog">
            <input type="hidden" name="id" value="<?= $categorie_id ?>">
            <button type="submit" class="lien-danger">Supprimer</button>
          </form>
        </li>
      <?php endforeach; ?>
      <li class="reglage-ligne">
        <form method="post" class="reglage-forme-nom">
          <?= champ_csrf() ?>
          <input type="hidden" name="action" value="ajouter_categorie_blog">
          <input type="text" name="nom" maxlength="120" required placeholder="Nouvelle catégorie">
          <button type="submit" class="btn btn-ghost">Ajouter</button>
        </form>
      </li>
    </ul>
  </div>
  </div>
  </div>

  <div class="form-card reglage-rubriques" style="margin-top:32px;">
    <h2 style="font-family:var(--font-heading);font-size:1.2rem;margin:0 0 6px;">Albums de « Nos Sorties »</h2>
    <p class="form-note" style="margin-top:0;margin-bottom:20px;">
      Chaque album correspond à une sortie du club et s'affiche sur la page publique
      « Nos Sorties ». Deux façons de l'alimenter : les photos restent sur <strong>Google
      Drive</strong> (indiquez l'adresse du dossier de la sortie, qui doit contenir un
      sous-dossier par adhérent et être partagé en « Tous les utilisateurs disposant du
      lien ») — à privilégier s'il y a beaucoup de photos — ou les adhérents les
      <strong>déposent directement sur ce site</strong>, pour une sortie avec peu de
      photos. Supprimer un album Drive ne supprime aucune photo sur Drive ; supprimer un
      album hébergé ici efface définitivement ses photos.
    </p>

    <?php foreach ($albums as $album_id => $album): ?>
      <div class="reglage-album">
        <form method="post" class="reglage-album-champs" data-album-type-forme>
          <?= champ_csrf() ?>
          <input type="hidden" name="action" value="modifier_album">
          <input type="hidden" name="id" value="<?= $album_id ?>">
          <label>Nom de l'album
            <input type="text" name="nom" value="<?= e($album['nom']) ?>" maxlength="120" required>
          </label>
          <div class="reglage-album-type" role="radiogroup">
            <label><input type="radio" name="type" value="drive" data-album-type-radio <?= $album['type'] === 'drive' ? 'checked' : '' ?>> Dossier Google Drive</label>
            <label><input type="radio" name="type" value="local" data-album-type-radio <?= $album['type'] === 'local' ? 'checked' : '' ?>> Hébergé sur ce site</label>
          </div>
          <label class="reglage-album-champ-drive" data-album-champ-drive>Dossier Google Drive
            <input type="text" name="dossier_drive" value="<?= e($album['dossier_drive']) ?>" maxlength="190">
          </label>
          <button type="submit" class="btn btn-ghost">Enregistrer</button>
        </form>
        <form method="post"
              onsubmit="return confirm('Supprimer l\'album « <?= e(addslashes($album['nom'])) ?> » ?<?= $album['type'] === 'local' ? ' Ses photos seront définitivement effacées.' : ' Les photos restent sur Google Drive.' ?>');">
          <?= champ_csrf() ?>
          <input type="hidden" name="action" value="supprimer_album">
          <input type="hidden" name="id" value="<?= $album_id ?>">
          <button type="submit" class="lien-danger">Supprimer</button>
        </form>
      </div>
    <?php endforeach; ?>

    <div class="reglage-album reglage-album--nouveau">
      <form method="post" class="reglage-album-champs" data-album-type-forme>
        <?= champ_csrf() ?>
        <input type="hidden" name="action" value="ajouter_album">
        <label>Nom de l'album
          <input type="text" name="nom" maxlength="120" required placeholder="Croisière Penbron">
        </label>
        <div class="reglage-album-type" role="radiogroup">
          <label><input type="radio" name="type" value="drive" data-album-type-radio checked> Dossier Google Drive</label>
          <label><input type="radio" name="type" value="local" data-album-type-radio> Hébergé sur ce site</label>
        </div>
        <label class="reglage-album-champ-drive" data-album-champ-drive>Dossier Google Drive
          <input type="text" name="dossier_drive" maxlength="190"
                 placeholder="https://drive.google.com/drive/folders/…">
        </label>
        <button type="submit" class="btn btn-primary">Ajouter cet album</button>
      </form>
    </div>
  </div>
</div></section>
<script>
  // Masque le champ « Dossier Google Drive » quand l'album est de type
  // « Hébergé sur ce site » — un album local n'en a pas besoin. Générique à
  // toutes les formes d'album de la page (existants + « ajouter »).
  document.querySelectorAll("[data-album-type-forme]").forEach(function (forme) {
    var champDrive = forme.querySelector("[data-album-champ-drive]");
    var radios      = forme.querySelectorAll("[data-album-type-radio]");
    function actualiser() {
      var local = forme.querySelector("[data-album-type-radio]:checked").value === "local";
      champDrive.hidden = local;
      champDrive.querySelector("input").required = !local;
    }
    radios.forEach(function (radio) { radio.addEventListener("change", actualiser); });
    actualiser();
  });
</script>
<?php
fin_page();
