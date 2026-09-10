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

// Anti-spam pour ce formulaire public — le champ piège suffit ici, sans
// délai minimum (choix explicite de l'utilisatrice, 11/09/2026, après
// diagnostic : contrairement à inscription.php, un formulaire à un seul
// champ portant autocomplete="username" est complété quasi instantanément
// par un navigateur/gestionnaire de mots de passe, ce qui déclenchait à
// tort le délai minimum et bloquait silencieusement l'envoi pour un
// adhérent parfaitement légitime).
//
// Le champ piège s'appelait à l'origine "site_web" — renommé le même jour
// après un second diagnostic : même après le retrait du délai ci-dessus,
// l'utilisatrice recevait toujours zéro e-mail depuis cette page (alors
// que le test direct via diag-reset-mail.php fonctionnait). "site_web"
// ("website" en anglais) est un nom que beaucoup de navigateurs/
// gestionnaires de mots de passe reconnaissent et préremplissent
// automatiquement — y compris sur un champ positionné hors écran
// (`position:absolute; left:-9999px`), qui n'est pas masqué au sens CSS
// (`display:none`) que ces outils vérifient généralement. Le champ piège
// se faisait donc probablement remplir tout seul, déclenchant à tort la
// même protection anti-spam que le délai retiré plus haut. Renommé en
// "ref_interne", un nom qui ne correspond à aucune catégorie
// d'autoremplissage connue.
$message_generique = "Si un identifiant ou une adresse e-mail correspond à un compte, un e-mail avec les instructions de réinitialisation vient d'être envoyé.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $piege_rempli = trim((string) ($_POST['ref_interne'] ?? '')) !== '';

    // Consigné pour confirmer définitivement si ce champ se fait encore
    // remplir automatiquement malgré le renommage ci-dessus.
    if ($piege_rempli) {
        error_log("Espace adhérents — réinitialisation bloquée par le champ piège (ref_interne rempli avec : " . trim((string) ($_POST['ref_interne'] ?? '')) . ").");
    }

    $saisie = trim((string) ($_POST['identifiant_ou_email'] ?? ''));

    if (!$piege_rempli && $saisie !== '') {
        $requete = $pdo->prepare(
            'SELECT id, identifiant, nom, email FROM adherents
              WHERE actif = 1 AND (identifiant = ? OR email = ?)
              LIMIT 1'
        );
        $requete->execute([$saisie, $saisie]);
        $adherent = $requete->fetch();

        // Consigné pour distinguer, dans les journaux, une saisie qui ne
        // correspond à aucun compte actif (faute de frappe, valeur
        // autoremplie erronée...) des deux autres cas déjà journalisés
        // ci-dessous.
        if (!$adherent) {
            error_log("Espace adhérents — réinitialisation demandée pour « {$saisie} », mais aucun compte actif ne correspond.");
        }

        // Consigné même en cas de succès de la recherche : un compte trouvé
        // mais sans e-mail renseigné (le tout premier compte, créé par
        // installation.php, où l'e-mail est facultatif — voir plus bas) ne
        // reçoit jamais de lien, silencieusement comme voulu (anti-
        // énumération), mais sans aucune trace nulle part sinon. Utile pour
        // diagnostiquer un « je n'ai rien reçu » qui ne serait pas un
        // problème d'envoi mais simplement une fiche adhérent incomplète.
        if ($adherent && !$adherent['email']) {
            error_log("Espace adhérents — réinitialisation demandée pour {$adherent['identifiant']} (id {$adherent['id']}), mais aucun e-mail n'est renseigné sur ce compte.");
        }

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
                . "**Pensez à vérifier aussi votre dossier de courriers indésirables (spams)** "
                . "si vous ne voyez pas cet e-mail arriver.\n\n"
                . "À bientôt,\nLe Focal Club Turballais"
            );
        }
    }

    definir_message('succes', $message_generique);
    header('Location: connexion.php');
    exit;
}

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
        <label for="ref_interne">Laissez ce champ vide</label>
        <input type="text" id="ref_interne" name="ref_interne" tabindex="-1" autocomplete="off">
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
