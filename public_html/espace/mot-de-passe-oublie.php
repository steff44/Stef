<?php
/*
 * Réinitialisation du mot de passe en libre-service (choix explicite de
 * l'utilisatrice, 11/09/2026 : « je voudrais qu'il puisse le faire
 * lui-même ») — jusqu'ici, « Mot de passe oublié ? » sur connexion.php
 * renvoyait vers le formulaire de contact : un responsable devait
 * régénérer un mot de passe provisoire depuis adherents.php et le
 * communiquer lui-même à la main. Cette page permet à l'adhérent de
 * choisir directement un nouveau mot de passe, sans intervention d'un
 * responsable.
 *
 * Un jeton aléatoire à usage unique (jeton_reinitialisation/jeton_expire_le
 * sur `adherents`) est envoyé par e-mail, valable une heure ; le lien mène
 * à nouveau-mot-de-passe.php, qui vérifie ce jeton avant d'accepter un
 * nouveau mot de passe.
 *
 * Le message affiché après envoi est TOUJOURS le même, que l'identifiant/
 * e-mail saisi corresponde à un compte ou non : révéler la différence
 * permettrait de deviner quels identifiants existent (même principe que
 * tenter_connexion(), qui ne dit jamais si c'est l'identifiant ou le mot de
 * passe qui est faux).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/mail.php';

if (adherent_connecte()) {
    header('Location: index.php');
    exit;
}

$pdo = base_de_donnees();

// Même anti-spam que inscription.php (champ piège + délai minimum), sans
// dépendance externe : ce formulaire public peut sinon servir à bombarder
// la boîte mail de n'importe quel adhérent de demandes de réinitialisation.
const DELAI_MIN_RESET_SECONDES = 3;

$message_generique = "Si un identifiant ou une adresse e-mail correspond à un compte, un e-mail avec les instructions de réinitialisation vient d'être envoyé.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $piege_rempli = trim((string) ($_POST['site_web'] ?? '')) !== '';
    $trop_rapide  = (time() - (int) ($_SESSION['reset_affiche_a'] ?? 0)) < DELAI_MIN_RESET_SECONDES;

    $saisie = trim((string) ($_POST['identifiant_ou_email'] ?? ''));

    if (!$piege_rempli && !$trop_rapide && $saisie !== '') {
        $requete = $pdo->prepare(
            'SELECT id, identifiant, nom, email FROM adherents
              WHERE actif = 1 AND (identifiant = ? OR email = ?)
              LIMIT 1'
        );
        $requete->execute([$saisie, $saisie]);
        $adherent = $requete->fetch();

        if ($adherent && $adherent['email']) {
            $jeton = bin2hex(random_bytes(32));
            $pdo->prepare(
                'UPDATE adherents
                    SET jeton_reinitialisation = ?, jeton_expire_le = NOW() + INTERVAL 1 HOUR
                  WHERE id = ?'
            )->execute([$jeton, $adherent['id']]);

            $email_club = valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com';
            $lien       = SITE_URL . '/espace/nouveau-mot-de-passe.php?jeton=' . $jeton;

            envoyer_mail(
                $adherent['email'],
                $email_club,
                "Réinitialisation de votre mot de passe — Focal Club Turballais",
                "Bonjour {$adherent['nom']},\n\n"
                . "Vous avez demandé la réinitialisation de votre mot de passe sur l'espace "
                . "adhérents du Focal Club Turballais (identifiant : {$adherent['identifiant']}).\n\n"
                . "Cliquez sur le lien ci-dessous pour choisir un nouveau mot de passe. "
                . "Il est valable une heure :\n" . $lien . "\n\n"
                . "**Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail** : "
                . "votre mot de passe actuel reste inchangé.\n\n"
                . "À bientôt,\nLe Focal Club Turballais"
            );
        }
    }

    definir_message('succes', $message_generique);
    header('Location: connexion.php');
    exit;
}

// Réamorce le délai anti-spam à chaque affichage du formulaire.
$_SESSION['reset_affiche_a'] = time();

debut_page("Mot de passe oublié", 'connexion');
titre_page("Mot de passe oublié", "Recevez un lien par e-mail pour en choisir un nouveau.");
?>
<section class="section"><div class="container">
  <div class="form-card" style="max-width:560px;margin:0 auto;">
    <a class="lien-retour" href="connexion.php"
       onclick="if (history.length > 1) { history.back(); return false; }">← Page précédente</a>

    <?php afficher_message(); ?>

    <form method="post" autocomplete="off">
      <?= champ_csrf() ?>
      <!-- Champ piège anti-spam : invisible et non focalisable (voir
           inscription.php pour le même principe). -->
      <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
        <label for="site_web">Laissez ce champ vide</label>
        <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
      </div>
      <div class="field">
        <label for="identifiant_ou_email">Identifiant ou e-mail</label>
        <input type="text" id="identifiant_ou_email" name="identifiant_ou_email" required
               autocomplete="username">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Recevoir le lien</button>
      <p class="form-note">
        Vous recevrez un e-mail avec un lien pour choisir un nouveau mot de passe,
        valable une heure.
      </p>
    </form>

    <p class="form-note" style="margin-top:18px;">
      <a href="connexion.php" style="text-decoration:underline;">Se connecter</a>.
    </p>
  </div>
</div></section>
<?php
fin_page();
