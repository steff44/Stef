<?php
/*
 * Deuxième étape de la réinitialisation en libre-service (voir
 * mot-de-passe-oublie.php) : la personne arrive ici depuis le lien reçu par
 * e-mail, avec un jeton dans l'URL. Cette page ne dépend jamais de l'état
 * de connexion du navigateur — l'autorité vient du jeton, pas de la
 * session — contrairement à connexion.php/inscription.php, qui redirigent
 * un visiteur déjà connecté.
 *
 * Mêmes règles de mot de passe qu'à l'inscription et dans l'Annuaire (10
 * caractères, une majuscule, un caractère spécial) : un changement ne doit
 * pas permettre de revenir à un mot de passe plus faible.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

$pdo   = base_de_donnees();
$jeton = (string) ($_GET['jeton'] ?? $_POST['jeton'] ?? '');

if ($jeton === '' || !preg_match('/^[a-f0-9]{64}$/', $jeton)) {
    page_erreur(
        "Lien invalide",
        "Ce lien de réinitialisation est invalide. <a href=\"mot-de-passe-oublie.php\">Demandez-en un nouveau</a>.",
        400
    );
}

$requete = $pdo->prepare(
    'SELECT id, identifiant FROM adherents
      WHERE jeton_reinitialisation = ? AND jeton_expire_le > NOW()
      LIMIT 1'
);
$requete->execute([$jeton]);
$adherent = $requete->fetch();

if (!$adherent) {
    page_erreur(
        "Lien expiré",
        "Ce lien de réinitialisation est invalide ou a expiré (il n'est valable qu'une heure). "
        . "<a href=\"mot-de-passe-oublie.php\">Demandez-en un nouveau</a>.",
        400
    );
}

$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $mot1 = (string) ($_POST['mot_de_passe'] ?? '');
    $mot2 = (string) ($_POST['confirmation'] ?? '');

    if (mb_strlen($mot1) < 10) {
        $erreurs[] = "Le mot de passe doit contenir au moins 10 caractères.";
    }
    if (!preg_match('/[A-Z]/', $mot1)) {
        $erreurs[] = "Le mot de passe doit contenir au moins une majuscule.";
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $mot1)) {
        $erreurs[] = "Le mot de passe doit contenir au moins un caractère spécial.";
    }
    if ($mot1 !== $mot2) {
        $erreurs[] = "Les deux mots de passe ne sont pas identiques.";
    }

    if (!$erreurs) {
        $pdo->prepare(
            'UPDATE adherents
                SET mot_de_passe = ?, jeton_reinitialisation = NULL, jeton_expire_le = NULL
              WHERE id = ?'
        )->execute([password_hash($mot1, PASSWORD_DEFAULT), $adherent['id']]);

        definir_message('succes', "Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.");
        header('Location: connexion.php');
        exit;
    }
}

debut_page("Nouveau mot de passe", 'connexion');
titre_page("Choisir un nouveau mot de passe", "Identifiant : " . $adherent['identifiant']);
?>
<section class="section"><div class="container">
  <div class="form-card" style="max-width:560px;margin:0 auto;">
    <?php foreach ($erreurs as $erreur): ?>
      <div class="alerte alerte-erreur"><?= e($erreur) ?></div>
    <?php endforeach; ?>

    <form method="post" autocomplete="off">
      <?= champ_csrf() ?>
      <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
      <div class="field">
        <label for="mot_de_passe">Nouveau mot de passe (10 caractères minimum, avec au moins une majuscule et un caractère spécial)</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="10"
               pattern="(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{10,}"
               title="Au moins 10 caractères, une majuscule et un caractère spécial" autofocus>
      </div>
      <div class="field">
        <label for="confirmation">Confirmer le mot de passe</label>
        <input type="password" id="confirmation" name="confirmation" required minlength="10"
               pattern="(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{10,}"
               title="Au moins 10 caractères, une majuscule et un caractère spécial">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Changer le mot de passe</button>
    </form>
  </div>
</div></section>
<?php
fin_page();
