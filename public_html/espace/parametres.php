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

exige_administrateur();
$pdo = base_de_donnees();

const CHAMPS = [
    'nom_lieu'            => 'Nom du lieu de réunion',
    'adresse_rue'         => 'Rue et numéro',
    'adresse_code_postal' => 'Code postal',
    'adresse_ville'       => 'Ville',
    'telephone'           => 'Téléphone',
    'email'               => 'E-mail',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

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

    <h2 style="font-family:var(--font-heading);font-size:1.2rem;margin:28px 0 18px;">Présentation</h2>
    <div class="field">
      <label for="presentation">Texte affiché en pied de page de chaque page du site</label>
      <textarea id="presentation" name="presentation" rows="4" required maxlength="500"><?= e($actuel['presentation'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%;">Enregistrer</button>
    <p class="form-note">Les visiteurs déjà sur le site verront le changement à leur prochaine visite, ou après quelques minutes si leur page reste ouverte.</p>
  </form>
</div></section>
<?php
fin_page();
