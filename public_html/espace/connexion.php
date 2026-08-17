<?php
/*
 * Page de connexion à l'espace adhérents.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

// Déjà connecté : inutile de redemander.
if (adherent_connecte()) {
    header('Location: index.php');
    exit;
}

$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $erreur = tenter_connexion(
        trim((string) ($_POST['identifiant'] ?? '')),
        (string) ($_POST['mot_de_passe'] ?? '')
    );

    if ($erreur === null) {
        // Retour à la page demandée avant la connexion, si elle est bien
        // interne à l'espace (on ne suit jamais une adresse extérieure).
        $retour = $_SESSION['retour'] ?? 'index.php';
        unset($_SESSION['retour']);
        if (!preg_match('#^[a-z0-9_-]+\.php(\?[^\s]*)?$#i', (string) $retour)) {
            $retour = 'index.php';
        }
        header('Location: ' . $retour);
        exit;
    }
}

debut_page("Se connecter", 'connexion');
titre_page("Espace adhérents", "Réservé aux membres du Focal Club Turballais.");
?>
<section class="section"><div class="container">
  <div class="form-card" style="max-width:440px;margin:0 auto;">
    <?php afficher_message(); ?>
    <?php if ($erreur !== null): ?>
      <div class="alerte alerte-erreur"><?= e($erreur) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= champ_csrf() ?>
      <div class="field">
        <label for="identifiant">Identifiant</label>
        <input type="text" id="identifiant" name="identifiant" required autofocus
               autocomplete="username" value="<?= e($_POST['identifiant'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" required
               autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Se connecter</button>
    </form>

    <p class="form-note" style="margin-top:18px;">
      Mot de passe oublié ou pas encore de compte ?
      <a href="../contact.html" style="text-decoration:underline;">Contactez un responsable du club</a>.
    </p>
  </div>
</div></section>
<?php
fin_page();
