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
require_once __DIR__ . '/inc/galerie_club.php';

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

        if (in_array($action, ['ajouter_rubrique', 'renommer_rubrique', 'ajouter_categorie', 'renommer_categorie', 'ajouter_categorie_galerie', 'renommer_categorie_galerie'], true) && $nom === '') {
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
            $requete = $pdo->prepare('SELECT COUNT(*) FROM photos_club WHERE categorie_id = ?');
            $requete->execute([$id]);
            $nb_photos = (int) $requete->fetchColumn();

            if ($nb_photos > 0) {
                definir_message('erreur', "{$nb_photos} photo(s) sont classées dans cette catégorie — déplacez-les ou supprimez-les d'abord.");
            } else {
                $pdo->prepare('DELETE FROM categories_galerie WHERE id = ?')->execute([$id]);
                definir_message('succes', "Catégorie supprimée.");
            }
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

  <div class="form-card reglage-rubriques" style="max-width:640px;margin-top:32px;">
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

  <div class="form-card reglage-rubriques" style="max-width:640px;margin-top:32px;">
    <h2 style="font-family:var(--font-heading);font-size:1.2rem;margin:0 0 6px;">Catégories de la Galerie du Club</h2>
    <p class="form-note" style="margin-top:0;margin-bottom:20px;">
      Ces catégories organisent la Galerie du Club et les filtres de la page Galerie.
      Une catégorie contenant encore des photos ne peut pas être supprimée.
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
</div></section>
<?php
fin_page();
