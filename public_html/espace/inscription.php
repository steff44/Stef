<?php
/*
 * Inscription à l'espace adhérents — page séparée de la connexion
 * (connexion.php), conformément au dessin fourni par l'utilisateur
 * (choix explicite, 20/08/2026 : revient sur la version à deux onglets sur
 * une seule page du 18/08/2026, qui ne correspondait pas à la maquette).
 *
 * Un compte créé ici démarre non validé (`valide = 0`) : un responsable ou
 * un éditeur doit le valider dans adherents.php avant que la personne
 * puisse se connecter (choix explicite de l'utilisateur, remis en place le
 * 23/08/2026 après un aller-retour le même jour — l'activation immédiate
 * testée entre-temps a laissé un adhérent se connecter sans validation,
 * ce qui n'était pas voulu). Deux e-mails accompagnent l'inscription : un
 * vers le club (à valider) et un vers la personne inscrite (en attente).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/mail.php';

if (adherent_connecte()) {
    header('Location: index.php');
    exit;
}

$pdo = base_de_donnees();

// Anti-spam pour ce formulaire public, sans dépendance externe (pas de
// CAPTCHA, cohérent avec le reste du site) : un robot soumet en général en
// bien moins de 3 secondes après avoir chargé la page.
const DELAI_MIN_INSCRIPTION_SECONDES = 3;

$erreurs = [];
$valeurs = [
    'pseudo' => '', 'nom' => '', 'prenom' => '', 'email' => '',
    'telephone' => '', 'adresse' => '', 'code_postal' => '', 'ville' => '', 'boitier' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    // Champ piège invisible (site_web) : un humain ne le voit ni ne le
    // remplit jamais, un robot qui remplit tous les champs si. Combiné à un
    // délai minimum depuis l'affichage du formulaire (inscription_affichee_a,
    // posé juste avant le rendu plus bas). Un robot pris au piège voit le
    // même message de succès qu'une vraie inscription, sans qu'aucun compte
    // ne soit créé ni aucun e-mail envoyé — pour ne pas l'inciter à s'adapter.
    $piege_rempli = trim((string) ($_POST['site_web'] ?? '')) !== '';
    $trop_rapide  = (time() - (int) ($_SESSION['inscription_affichee_a'] ?? 0)) < DELAI_MIN_INSCRIPTION_SECONDES;
    if ($piege_rempli || $trop_rapide) {
        definir_message('succes', "Votre inscription a bien été enregistrée. Elle est en attente de validation par un responsable du club — vous recevrez un e-mail dès qu'elle sera validée.");
        header('Location: connexion.php');
        exit;
    }

    foreach (array_keys($valeurs) as $champ) {
        $valeurs[$champ] = trim((string) ($_POST[$champ] ?? ''));
    }
    $mot1 = (string) ($_POST['mot_de_passe'] ?? '');
    $mot2 = (string) ($_POST['confirmation'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $valeurs['pseudo'])) {
        $erreurs[] = "Le pseudo doit faire 3 à 60 caractères, sans espace ni accent (lettres, chiffres, point, tiret).";
    }
    if ($valeurs['nom'] === '') {
        $erreurs[] = "Le nom est obligatoire.";
    }
    if ($valeurs['prenom'] === '') {
        $erreurs[] = "Le prénom est obligatoire.";
    }
    if ($valeurs['email'] === '' || !filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse e-mail est obligatoire et doit être valide.";
    }
    // Téléphone, adresse, code postal et ville obligatoires — choix
    // explicite de l'utilisateur, 28/08/2026 (auparavant tous facultatifs).
    if ($valeurs['telephone'] === '') {
        $erreurs[] = "Le téléphone est obligatoire.";
    }
    if ($valeurs['adresse'] === '') {
        $erreurs[] = "L'adresse est obligatoire.";
    }
    if ($valeurs['code_postal'] === '') {
        $erreurs[] = "Le code postal est obligatoire.";
    } elseif (!preg_match('/^[0-9]{4,10}$/', $valeurs['code_postal'])) {
        $erreurs[] = "Le code postal ne doit contenir que des chiffres.";
    }
    if ($valeurs['ville'] === '') {
        $erreurs[] = "La ville est obligatoire.";
    }
    // Nom du boîtier obligatoire — choix explicite de l'utilisateur,
    // 28/08/2026 (auparavant facultatif).
    if ($valeurs['boitier'] === '') {
        $erreurs[] = "Le nom du boîtier est obligatoire.";
    }
    if (mb_strlen($mot1) < 10) {
        $erreurs[] = "Le mot de passe doit contenir au moins 10 caractères.";
    }
    // Une majuscule et un caractère spécial — choix explicite de
    // l'utilisateur, 28/08/2026.
    if (!preg_match('/[A-Z]/', $mot1)) {
        $erreurs[] = "Le mot de passe doit contenir au moins une majuscule.";
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $mot1)) {
        $erreurs[] = "Le mot de passe doit contenir au moins un caractère spécial.";
    }
    if ($mot1 !== $mot2) {
        $erreurs[] = "Les deux mots de passe ne sont pas identiques.";
    }

    // Le pseudo doit être libre — vérifié à part de la contrainte UNIQUE
    // de la base, pour renvoyer un message clair plutôt qu'une erreur SQL.
    if (!$erreurs) {
        $existe = $pdo->prepare('SELECT 1 FROM adherents WHERE identifiant = ?');
        $existe->execute([$valeurs['pseudo']]);
        if ($existe->fetchColumn()) {
            $erreurs[] = "Ce pseudo est déjà pris, choisissez-en un autre.";
        }
    }

    if (!$erreurs) {
        $pdo->prepare(
            'INSERT INTO adherents
                (identifiant, nom, email, telephone, adresse, code_postal, ville, boitier, mot_de_passe, administrateur, actif, valide)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, 0)'
        )->execute([
            $valeurs['pseudo'],
            trim($valeurs['prenom'] . ' ' . $valeurs['nom']),
            $valeurs['email'],
            $valeurs['telephone'],
            $valeurs['adresse'],
            $valeurs['code_postal'],
            $valeurs['ville'],
            $valeurs['boitier'],
            password_hash($mot1, PASSWORD_DEFAULT),
        ]);

        // Adresse d'expédition/notification : celle du club (Réglages du
        // site), avec un repli si un responsable ne l'a pas encore
        // renseignée.
        $email_club = valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com';

        envoyer_mail(
            $email_club,
            $email_club,
            "Nouvelle inscription à valider : {$valeurs['prenom']} {$valeurs['nom']}",
            "Une nouvelle personne s'est inscrite sur le site du Focal Club Turballais.\n\n"
            . "Nom : {$valeurs['prenom']} {$valeurs['nom']}\n"
            . "Pseudo : {$valeurs['pseudo']}\n"
            . "E-mail : {$valeurs['email']}\n"
            . ($valeurs['telephone'] !== '' ? "Téléphone : {$valeurs['telephone']}\n" : '')
            . "\nPour valider son compte, connectez-vous à l'espace adhérents puis ouvrez "
            . "l'onglet Adhérents, et cliquez sur « Valider » sur sa ligne."
        );

        envoyer_mail(
            $valeurs['email'],
            $email_club,
            "Votre inscription au Focal Club Turballais est en attente de validation",
            "Bonjour {$valeurs['prenom']},\n\n"
            . "Votre inscription à l'espace adhérents du Focal Club Turballais a bien été "
            . "enregistrée. Elle est en attente de validation par un responsable du club : "
            . "vous recevrez un nouvel e-mail dès que votre compte sera activé, et pourrez "
            . "alors vous connecter avec le pseudo « {$valeurs['pseudo']} » et le "
            . "mot de passe que vous venez de choisir.\n\n"
            . "**Pensez à vérifier aussi votre dossier de courriers indésirables (spams)** "
            . "si vous ne voyez pas cet e-mail de validation arriver.\n\n"
            . "À bientôt,\nLe Focal Club Turballais"
        );

        definir_message('succes', "Votre inscription a bien été enregistrée. Elle est en attente de validation par un responsable du club — vous recevrez un e-mail dès qu'elle sera validée.");
        header('Location: connexion.php');
        exit;
    }
}

// Réamorce le délai anti-spam à chaque affichage du formulaire (premier
// chargement ou nouvel essai après une erreur de validation).
$_SESSION['inscription_affichee_a'] = time();

debut_page("Créer un compte", 'inscription');
titre_page("Rejoignez le club", "Créez votre compte pour accéder à l'espace adhérents.");
?>
<section class="section"><div class="container">
  <div class="form-card" style="max-width:560px;margin:0 auto;">
    <a class="lien-retour" href="../index.html"
       onclick="if (history.length > 1) { history.back(); return false; }">← Page précédente</a>

    <?php foreach ($erreurs as $erreur): ?>
      <div class="alerte alerte-erreur"><?= e($erreur) ?></div>
    <?php endforeach; ?>

    <form method="post" autocomplete="off">
      <?= champ_csrf() ?>
      <!-- Champ piège anti-spam : invisible et non focalisable, un humain
           ne le remplit jamais (voir le commentaire en haut du fichier). -->
      <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
        <label for="site_web">Laissez ce champ vide</label>
        <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="prenom">Prénom</label>
          <input type="text" id="prenom" name="prenom" required
                 value="<?= e($valeurs['prenom']) ?>">
        </div>
        <div class="field">
          <label for="nom">Nom</label>
          <input type="text" id="nom" name="nom" required
                 value="<?= e($valeurs['nom']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="pseudo">Pseudo (servira à vous connecter)</label>
        <input type="text" id="pseudo" name="pseudo" required
               value="<?= e($valeurs['pseudo']) ?>" placeholder="stephane">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" required
                 value="<?= e($valeurs['email']) ?>">
        </div>
        <div class="field">
          <label for="telephone">Téléphone</label>
          <input type="tel" id="telephone" name="telephone" required
                 value="<?= e($valeurs['telephone']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="adresse">Adresse</label>
        <input type="text" id="adresse" name="adresse" required
               value="<?= e($valeurs['adresse']) ?>" placeholder="12 rue de la Fontaine">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="code_postal">Code postal</label>
          <input type="text" id="code_postal" name="code_postal" inputmode="numeric" required
                 value="<?= e($valeurs['code_postal']) ?>">
        </div>
        <div class="field">
          <label for="ville">Ville</label>
          <input type="text" id="ville" name="ville" required
                 value="<?= e($valeurs['ville']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="boitier">Nom du boîtier</label>
        <input type="text" id="boitier" name="boitier" required
               value="<?= e($valeurs['boitier']) ?>" placeholder="Canon EOS R6">
      </div>
      <div class="field">
        <label for="mot_de_passe">Mot de passe (10 caractères minimum, avec au moins une majuscule et un caractère spécial)</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="10"
               pattern="(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{10,}"
               title="Au moins 10 caractères, une majuscule et un caractère spécial">
      </div>
      <div class="field">
        <label for="confirmation">Confirmer le mot de passe</label>
        <input type="password" id="confirmation" name="confirmation" required minlength="10"
               pattern="(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{10,}"
               title="Au moins 10 caractères, une majuscule et un caractère spécial">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Créer le compte</button>
      <p class="form-note">
        Après inscription, votre compte est en attente de validation par un responsable du
        club : vous recevrez un e-mail dès qu'il sera activé.
      </p>
    </form>

    <p class="form-note" style="margin-top:18px;">
      Déjà membre ?
      <a href="connexion.php" style="text-decoration:underline;">Se connecter</a>.
    </p>
  </div>
</div></section>
<?php
fin_page();
