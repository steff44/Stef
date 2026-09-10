<?php
/*
 * DIAGNOSTIC TEMPORAIRE — à supprimer une fois la cause trouvée (même
 * principe que les diagnostics déjà utilisés pour le favicon et Google
 * Drive). Réservé au responsable : reproduit exactement la recherche et
 * l'envoi de mot-de-passe-oublie.php, mais affiche le résultat à l'écran
 * au lieu de rester silencieux — pour savoir si la recherche trouve le
 * compte et si mail() réussit ou échoue, sans avoir à fouiller le journal
 * d'erreurs PHP (introuvable dans hPanel).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/mail.php';

exige_administrateur();

$pdo = base_de_donnees();
$resultat = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $saisie = trim((string) ($_POST['saisie'] ?? ''));

    $requete = $pdo->prepare(
        'SELECT id, identifiant, nom, email, actif FROM adherents
          WHERE identifiant = ? OR email = ?
          LIMIT 1'
    );
    $requete->execute([$saisie, $saisie]);
    $adherent = $requete->fetch();

    $resultat = [
        'saisie' => $saisie,
        'trouve' => (bool) $adherent,
    ];

    if ($adherent) {
        $resultat['identifiant'] = $adherent['identifiant'];
        $resultat['nom']         = $adherent['nom'];
        $resultat['email']       = $adherent['email'];
        $resultat['actif']       = (bool) $adherent['actif'];

        if ((int) $adherent['actif'] === 1 && $adherent['email']) {
            // Envoi d'un vrai e-mail de test, mais en appelant directement
            // mail() (pas envoyer_mail(), qui ne renvoie rien) pour
            // capturer le résultat exact.
            $entetes = "From: Focal Club Turballais <noreply@myfocal.online>\r\n"
                     . "Reply-To: noreply@myfocal.online\r\n"
                     . "Content-Type: text/html; charset=UTF-8\r\n";
            $sujet = '=?UTF-8?B?' . base64_encode('[Diagnostic] Test d\'envoi — Focal Club Turballais') . '?=';
            $corps = '<!DOCTYPE html><html><body>Ceci est un e-mail de test du diagnostic de réinitialisation.</body></html>';

            error_clear_last();
            $envoi_reussi = @mail($adherent['email'], $sujet, $corps, $entetes);
            $derniere_erreur = error_get_last();

            $resultat['envoi_reussi'] = $envoi_reussi;
            $resultat['derniere_erreur'] = $derniere_erreur['message'] ?? null;
        } else {
            $resultat['envoi_tente'] = false;
        }
    }
}

debut_page("Diagnostic réinitialisation", 'connexion');
titre_page("Diagnostic — réinitialisation du mot de passe", "Page temporaire, réservée aux responsables.");
?>
<section class="section"><div class="container">
  <div class="form-card" style="max-width:640px;margin:0 auto;">
    <p class="form-note" style="margin-bottom:20px;">
      Reproduit exactement la recherche faite par <code>mot-de-passe-oublie.php</code>
      (sans le filtre <code>actif = 1</code> ici, pour voir aussi un compte inactif)
      et tente un envoi réel par <code>mail()</code>, avec le résultat affiché ci-dessous.
    </p>

    <form method="post" autocomplete="off">
      <?= champ_csrf() ?>
      <div class="field">
        <label for="saisie">Identifiant ou e-mail à tester</label>
        <input type="text" id="saisie" name="saisie" required
               value="<?= e($resultat['saisie'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Tester</button>
    </form>

    <?php if ($resultat !== null): ?>
      <div class="alerte <?= $resultat['trouve'] ? 'alerte-succes' : 'alerte-erreur' ?>" style="margin-top:24px;">
        <?php if (!$resultat['trouve']): ?>
          <strong>Aucun compte trouvé</strong> pour « <?= e($resultat['saisie']) ?> ».
          La recherche compare exactement <code>identifiant = ?</code> ou <code>email = ?</code> —
          vérifiez l'orthographe exacte de votre identifiant ou de votre e-mail
          (espace en trop, majuscule/minuscule, faute de frappe...).
        <?php else: ?>
          <strong>Compte trouvé</strong> :
          identifiant « <?= e($resultat['identifiant']) ?> »,
          nom « <?= e($resultat['nom']) ?> »,
          e-mail « <?= e($resultat['email'] ?? '(aucun)') ?> »,
          actif : <?= $resultat['actif'] ? 'oui' : 'non' ?>.
          <?php if (isset($resultat['envoi_tente']) && !$resultat['envoi_tente']): ?>
            <br><strong>Aucun envoi tenté</strong> (compte inactif ou sans e-mail) —
            c'est exactement pour cette raison que <code>mot-de-passe-oublie.php</code>
            resterait silencieux avec ce compte.
          <?php elseif (isset($resultat['envoi_reussi'])): ?>
            <br><strong>Résultat de mail()</strong> :
            <?= $resultat['envoi_reussi'] ? '✅ succès (mail() a renvoyé vrai)' : '❌ échec (mail() a renvoyé faux)' ?>
            <?php if ($resultat['derniere_erreur']): ?>
              <br>Dernière erreur PHP : <code><?= e($resultat['derniere_erreur']) ?></code>
            <?php endif; ?>
            <?php if ($resultat['envoi_reussi']): ?>
              <br>Un e-mail de test vient d'être envoyé à <?= e($resultat['email']) ?> —
              vérifiez la boîte de réception (et les spams) dans les prochaines minutes.
              Si <code>mail()</code> a renvoyé vrai mais que rien n'arrive, le problème
              est du côté de la remise (spam, quota d'envoi Hostinger, filtrage du
              fournisseur de messagerie du destinataire) plutôt que du code du site.
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div></section>
<?php
fin_page();
