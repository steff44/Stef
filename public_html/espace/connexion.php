<?php
/*
 * Connexion à l'espace adhérents — page séparée de l'inscription
 * (inscription.php), conformément au dessin fourni par l'utilisateur
 * (choix explicite, 20/08/2026 : revient sur la version à deux onglets sur
 * une seule page du 18/08/2026, qui ne correspondait pas à la maquette).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

// Page dédiée à la seule connexion (choix explicite de l'utilisateur,
// 18/08/2026) : le tableau de bord vit exclusivement sur index.php, jamais
// ici. Un visiteur déjà connecté y est donc renvoyé directement, sans étape
// intermédiaire sur cette page.
if (adherent_connecte()) {
    header('Location: index.php');
    exit;
}

$erreur_connexion = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $erreur_connexion = tenter_connexion(
        trim((string) ($_POST['identifiant'] ?? '')),
        (string) ($_POST['mot_de_passe'] ?? '')
    );

    if ($erreur_connexion === null) {
        // Direction unique après connexion, quelle que soit la page d'où
        // l'on venait (choix explicite de l'utilisateur, 18/08/2026) : le
        // tableau de bord, pas la page qui a demandé la connexion.
        header('Location: index.php');
        exit;
    }
}

debut_page("Connexion", 'connexion');
titre_page("Espace adhérents", "Réservé aux membres du Focal Club Turballais.");
?>
<section class="section"><div class="container">
  <div class="form-card" style="max-width:560px;margin:0 auto;">
    <a class="lien-retour" href="../index.html"
       onclick="if (history.length > 1) { history.back(); return false; }">← Page précédente</a>

    <?php afficher_message(); ?>

    <?php if ($erreur_connexion !== null): ?>
      <div class="alerte alerte-erreur"><?= e($erreur_connexion) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= champ_csrf() ?>
      <div class="field">
        <label for="identifiant">Identifiant</label>
        <input type="text" id="identifiant" name="identifiant" required
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
      Mot de passe oublié ?
      <a href="../contact.html" style="text-decoration:underline;">Contactez un responsable du club</a>.
    </p>
    <p class="form-note">
      Pas encore de compte ?
      <a href="inscription.php" style="text-decoration:underline;">Créer un compte</a>.
    </p>
  </div>
</div></section>
<?php
fin_page();
