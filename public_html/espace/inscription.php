<?php
/*
 * Inscription à l'espace adhérents — page séparée de la connexion
 * (connexion.php), conformément au dessin fourni par l'utilisateur
 * (choix explicite, 20/08/2026 : revient sur la version à deux onglets sur
 * une seule page du 18/08/2026, qui ne correspondait pas à la maquette).
 *
 * Un compte créé ici est immédiatement utilisable (choix explicite de
 * l'utilisateur, 23/08/2026 — remplace le verrou de validation manuelle du
 * 18/08/2026 : un responsable ou un éditeur qui ne veut pas d'un compte le
 * supprime directement depuis adherents.php plutôt que de le laisser en
 * attente). Deux e-mails accompagnent l'inscription : un vers le club, pour
 * information, et un vers la personne inscrite, qui confirme qu'elle peut
 * se connecter dès maintenant.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/mail.php';

if (adherent_connecte()) {
    header('Location: index.php');
    exit;
}

$pdo = base_de_donnees();

$erreurs = [];
$valeurs = [
    'pseudo' => '', 'nom' => '', 'prenom' => '', 'email' => '',
    'telephone' => '', 'code_postal' => '', 'ville' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

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
    if ($valeurs['code_postal'] !== '' && !preg_match('/^[0-9]{4,10}$/', $valeurs['code_postal'])) {
        $erreurs[] = "Le code postal ne doit contenir que des chiffres.";
    }
    if (mb_strlen($mot1) < 10) {
        $erreurs[] = "Le mot de passe doit contenir au moins 10 caractères.";
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
                (identifiant, nom, email, telephone, code_postal, ville, mot_de_passe, administrateur, actif)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)'
        )->execute([
            $valeurs['pseudo'],
            trim($valeurs['prenom'] . ' ' . $valeurs['nom']),
            $valeurs['email'],
            $valeurs['telephone'] !== '' ? $valeurs['telephone'] : null,
            $valeurs['code_postal'] !== '' ? $valeurs['code_postal'] : null,
            $valeurs['ville'] !== '' ? $valeurs['ville'] : null,
            password_hash($mot1, PASSWORD_DEFAULT),
        ]);

        // Adresse d'expédition/notification : celle du club (Réglages du
        // site), avec un repli si un responsable ne l'a pas encore
        // renseignée.
        $email_club = valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com';

        envoyer_mail(
            $email_club,
            $email_club,
            "Nouvelle inscription : {$valeurs['prenom']} {$valeurs['nom']}",
            "Une nouvelle personne s'est inscrite sur le site du Focal Club Turballais.\n\n"
            . "Nom : {$valeurs['prenom']} {$valeurs['nom']}\n"
            . "Pseudo : {$valeurs['pseudo']}\n"
            . "E-mail : {$valeurs['email']}\n"
            . ($valeurs['telephone'] !== '' ? "Téléphone : {$valeurs['telephone']}\n" : '')
            . "\nSon compte est actif dès maintenant. Pour le retirer, connectez-vous à "
            . "l'espace adhérents, ouvrez l'onglet Adhérents, et supprimez sa ligne."
        );

        envoyer_mail(
            $valeurs['email'],
            $email_club,
            "Votre inscription au Focal Club Turballais est confirmée",
            "Bonjour {$valeurs['prenom']},\n\n"
            . "Votre inscription à l'espace adhérents du Focal Club Turballais a bien été "
            . "enregistrée. Vous pouvez dès à présent vous connecter avec le pseudo "
            . "« {$valeurs['pseudo']} » et le mot de passe que vous venez de choisir.\n\n"
            . "À bientôt,\nLe Focal Club Turballais"
        );

        definir_message('succes', "Votre inscription a bien été enregistrée. Vous pouvez vous connecter dès maintenant.");
        header('Location: connexion.php');
        exit;
    }
}

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
          <label for="telephone">Téléphone (facultatif)</label>
          <input type="tel" id="telephone" name="telephone"
                 value="<?= e($valeurs['telephone']) ?>">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="code_postal">Code postal (facultatif)</label>
          <input type="text" id="code_postal" name="code_postal" inputmode="numeric"
                 value="<?= e($valeurs['code_postal']) ?>">
        </div>
        <div class="field">
          <label for="ville">Ville (facultatif)</label>
          <input type="text" id="ville" name="ville"
                 value="<?= e($valeurs['ville']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="mot_de_passe">Mot de passe (10 caractères minimum)</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="10">
      </div>
      <div class="field">
        <label for="confirmation">Confirmer le mot de passe</label>
        <input type="password" id="confirmation" name="confirmation" required minlength="10">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Créer le compte</button>
      <p class="form-note">
        Vous pourrez vous connecter dès la création de votre compte, avec le pseudo et le
        mot de passe choisis ci-dessus.
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
