<?php
/*
 * Connexion ET inscription à l'espace adhérents, sur une seule page à deux
 * onglets (choix explicite de l'utilisateur, 18/08/2026 : les deux
 * formulaires cohabitent plutôt que de vivre sur deux pages séparées).
 * inscription.php n'est plus qu'une redirection vers ici, comme
 * evenements.html/connexion.html/membres.html en HTML statique.
 *
 * Un compte créé ici démarre non validé (`valide = 0`) : un responsable doit
 * cocher « Validé » dans adherents.php avant que la personne puisse se
 * connecter. Trois e-mails accompagnent le cycle : à l'inscription, un vers
 * le club (à valider) et un vers la personne inscrite (en attente) ; à la
 * validation, un vers la personne (compte validé).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/mail.php';

// Page dédiée à la seule connexion/inscription (choix explicite de
// l'utilisateur, 18/08/2026) : le tableau de bord vit exclusivement sur
// index.php, jamais ici. Un visiteur déjà connecté y est donc renvoyé
// directement, sans étape intermédiaire sur cette page.
if (adherent_connecte()) {
    header('Location: index.php');
    exit;
}

$pdo = base_de_donnees();

// Onglet actif : par défaut Connexion, sauf lien direct vers l'inscription
// (?onglet=inscription, utilisé par inscription.php et le menu) ou après une
// erreur/un succès qui doit rester sur son propre onglet.
$onglet = ($_GET['onglet'] ?? '') === 'inscription' ? 'inscription' : 'connexion';

$erreur_connexion   = null;
$erreurs_inscription = [];
$valeurs_inscription = [
    'pseudo' => '', 'nom' => '', 'prenom' => '', 'email' => '',
    'telephone' => '', 'code_postal' => '', 'ville' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $formulaire = (string) ($_POST['formulaire'] ?? '');

    if ($formulaire === 'inscription') {
        $onglet = 'inscription';

        foreach (array_keys($valeurs_inscription) as $champ) {
            $valeurs_inscription[$champ] = trim((string) ($_POST[$champ] ?? ''));
        }
        $mot1 = (string) ($_POST['mot_de_passe'] ?? '');
        $mot2 = (string) ($_POST['confirmation'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $valeurs_inscription['pseudo'])) {
            $erreurs_inscription[] = "Le pseudo doit faire 3 à 60 caractères, sans espace ni accent (lettres, chiffres, point, tiret).";
        }
        if ($valeurs_inscription['nom'] === '') {
            $erreurs_inscription[] = "Le nom est obligatoire.";
        }
        if ($valeurs_inscription['prenom'] === '') {
            $erreurs_inscription[] = "Le prénom est obligatoire.";
        }
        if ($valeurs_inscription['email'] === '' || !filter_var($valeurs_inscription['email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs_inscription[] = "L'adresse e-mail est obligatoire et doit être valide.";
        }
        if ($valeurs_inscription['code_postal'] !== '' && !preg_match('/^[0-9]{4,10}$/', $valeurs_inscription['code_postal'])) {
            $erreurs_inscription[] = "Le code postal ne doit contenir que des chiffres.";
        }
        if (mb_strlen($mot1) < 10) {
            $erreurs_inscription[] = "Le mot de passe doit contenir au moins 10 caractères.";
        }
        if ($mot1 !== $mot2) {
            $erreurs_inscription[] = "Les deux mots de passe ne sont pas identiques.";
        }

        // Le pseudo doit être libre — vérifié à part de la contrainte UNIQUE
        // de la base, pour renvoyer un message clair plutôt qu'une erreur SQL.
        if (!$erreurs_inscription) {
            $existe = $pdo->prepare('SELECT 1 FROM adherents WHERE identifiant = ?');
            $existe->execute([$valeurs_inscription['pseudo']]);
            if ($existe->fetchColumn()) {
                $erreurs_inscription[] = "Ce pseudo est déjà pris, choisissez-en un autre.";
            }
        }

        if (!$erreurs_inscription) {
            $pdo->prepare(
                'INSERT INTO adherents
                    (identifiant, nom, email, telephone, code_postal, ville, mot_de_passe, administrateur, actif, valide)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, 0)'
            )->execute([
                $valeurs_inscription['pseudo'],
                trim($valeurs_inscription['prenom'] . ' ' . $valeurs_inscription['nom']),
                $valeurs_inscription['email'],
                $valeurs_inscription['telephone'] !== '' ? $valeurs_inscription['telephone'] : null,
                $valeurs_inscription['code_postal'] !== '' ? $valeurs_inscription['code_postal'] : null,
                $valeurs_inscription['ville'] !== '' ? $valeurs_inscription['ville'] : null,
                password_hash($mot1, PASSWORD_DEFAULT),
            ]);

            // Adresse d'expédition/notification : celle du club (Réglages du
            // site), avec un repli si un responsable ne l'a pas encore
            // renseignée.
            $email_club = valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com';

            envoyer_mail(
                $email_club,
                $email_club,
                "Nouvelle inscription à valider : {$valeurs_inscription['prenom']} {$valeurs_inscription['nom']}",
                "Une nouvelle personne s'est inscrite sur le site du Focal Club Turballais.\n\n"
                . "Nom : {$valeurs_inscription['prenom']} {$valeurs_inscription['nom']}\n"
                . "Pseudo : {$valeurs_inscription['pseudo']}\n"
                . "E-mail : {$valeurs_inscription['email']}\n"
                . ($valeurs_inscription['telephone'] !== '' ? "Téléphone : {$valeurs_inscription['telephone']}\n" : '')
                . "\nPour valider son compte, connectez-vous à l'espace adhérents puis ouvrez "
                . "l'onglet Adhérents, et cochez « Validé » sur sa ligne."
            );

            envoyer_mail(
                $valeurs_inscription['email'],
                $email_club,
                "Votre inscription au Focal Club Turballais est en attente de validation",
                "Bonjour {$valeurs_inscription['prenom']},\n\n"
                . "Votre inscription à l'espace adhérents du Focal Club Turballais a bien été "
                . "enregistrée. Elle est en attente de validation par un responsable du club : "
                . "vous recevrez un nouvel e-mail dès que votre compte sera activé, et pourrez "
                . "alors vous connecter avec le pseudo « {$valeurs_inscription['pseudo']} » et le "
                . "mot de passe que vous venez de choisir.\n\n"
                . "À bientôt,\nLe Focal Club Turballais"
            );

            definir_message('succes', "Votre inscription a bien été enregistrée. Elle est en attente de validation par un responsable du club — vous recevrez un e-mail dès qu'elle sera validée.");
            header('Location: connexion.php?onglet=inscription');
            exit;
        }

    } else {
        $erreur_connexion = tenter_connexion(
            trim((string) ($_POST['identifiant'] ?? '')),
            (string) ($_POST['mot_de_passe'] ?? '')
        );

        if ($erreur_connexion === null) {
            // Direction unique après connexion, quelle que soit la page
            // d'où l'on venait (choix explicite de l'utilisateur,
            // 18/08/2026) : le tableau de bord, pas la page qui a demandé
            // la connexion.
            header('Location: index.php');
            exit;
        }
    }
}

debut_page("Connexion / Inscription", 'connexion');
titre_page("Espace adhérents", "Réservé aux membres du Focal Club Turballais.");
?>
<section class="section"><div class="container">
  <div class="form-card" style="max-width:560px;margin:0 auto;">
    <a class="lien-retour" href="../index.html"
       onclick="if (history.length > 1) { history.back(); return false; }">← Page précédente</a>

    <?php afficher_message(); ?>

    <div class="auth-tabs">
      <button type="button" class="auth-tab<?= $onglet === 'connexion' ? ' is-active' : '' ?>" data-onglet="connexion">Connexion</button>
      <button type="button" class="auth-tab<?= $onglet === 'inscription' ? ' is-active' : '' ?>" data-onglet="inscription">Inscription</button>
    </div>

      <div class="auth-panel" data-panel="connexion"<?= $onglet !== 'connexion' ? ' hidden' : '' ?>>
        <?php if ($erreur_connexion !== null): ?>
          <div class="alerte alerte-erreur"><?= e($erreur_connexion) ?></div>
        <?php endif; ?>

        <form method="post">
          <?= champ_csrf() ?>
          <input type="hidden" name="formulaire" value="connexion">
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
      </div>

      <div class="auth-panel" data-panel="inscription"<?= $onglet !== 'inscription' ? ' hidden' : '' ?>>
        <?php foreach ($erreurs_inscription as $erreur): ?>
          <div class="alerte alerte-erreur"><?= e($erreur) ?></div>
        <?php endforeach; ?>

        <form method="post" autocomplete="off">
          <?= champ_csrf() ?>
          <input type="hidden" name="formulaire" value="inscription">
          <div class="field-row">
            <div class="field">
              <label for="prenom">Prénom</label>
              <input type="text" id="prenom" name="prenom" required
                     value="<?= e($valeurs_inscription['prenom']) ?>">
            </div>
            <div class="field">
              <label for="nom">Nom</label>
              <input type="text" id="nom" name="nom" required
                     value="<?= e($valeurs_inscription['nom']) ?>">
            </div>
          </div>
          <div class="field">
            <label for="pseudo">Pseudo (servira à vous connecter)</label>
            <input type="text" id="pseudo" name="pseudo" required
                   value="<?= e($valeurs_inscription['pseudo']) ?>" placeholder="stephane">
          </div>
          <div class="field-row">
            <div class="field">
              <label for="email">E-mail</label>
              <input type="email" id="email" name="email" required
                     value="<?= e($valeurs_inscription['email']) ?>">
            </div>
            <div class="field">
              <label for="telephone">Téléphone (facultatif)</label>
              <input type="tel" id="telephone" name="telephone"
                     value="<?= e($valeurs_inscription['telephone']) ?>">
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="code_postal">Code postal (facultatif)</label>
              <input type="text" id="code_postal" name="code_postal" inputmode="numeric"
                     value="<?= e($valeurs_inscription['code_postal']) ?>">
            </div>
            <div class="field">
              <label for="ville">Ville (facultatif)</label>
              <input type="text" id="ville" name="ville"
                     value="<?= e($valeurs_inscription['ville']) ?>">
            </div>
          </div>
          <div class="field">
            <label for="mot_de_passe_inscription">Mot de passe (10 caractères minimum)</label>
            <input type="password" id="mot_de_passe_inscription" name="mot_de_passe" required minlength="10">
          </div>
          <div class="field">
            <label for="confirmation">Confirmer le mot de passe</label>
            <input type="password" id="confirmation" name="confirmation" required minlength="10">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;">S'inscrire</button>
          <p class="form-note">
            Après inscription, votre compte est en attente de validation par un responsable du
            club : vous recevrez un e-mail dès qu'il sera activé.
          </p>
        </form>
      </div>
  </div>
</div></section>
<script>
(function () {
  var onglets = document.querySelectorAll(".auth-tab");
  var panneaux = document.querySelectorAll(".auth-panel");
  onglets.forEach(function (onglet) {
    onglet.addEventListener("click", function () {
      onglets.forEach(function (o) { o.classList.toggle("is-active", o === onglet); });
      panneaux.forEach(function (p) { p.hidden = p.dataset.panel !== onglet.dataset.onglet; });
    });
  });
})();
</script>
<?php
fin_page();
