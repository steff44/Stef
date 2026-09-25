<?php
/*
 * Journal des e-mails envoyés par le site — réservé au responsable (même
 * règle que statistiques.php). Ajouté le 25/09/2026 : les notifications
 * (nouveau document, nouvelle sortie…) n'arrivaient à aucun adhérent alors
 * que les tests d'un seul e-mail passaient, et le journal d'erreurs PHP est
 * introuvable dans hPanel. Chaque envoi est consigné par envoyer_mail()
 * (inc/mail.php) dans inc/.journal-mails.log ; cette page le relit, et
 * permet de tester la connexion SMTP ou d'envoyer un e-mail d'essai.
 * Le mot de passe SMTP n'est jamais affiché.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/mail.php';

$adherent = exige_administrateur();
$pdo = base_de_donnees();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'tester_connexion') {
        $smtp = config_smtp();
        if ($smtp === null) {
            definir_message('erreur', "Aucun identifiant SMTP dans config.local.php : les e-mails partent par mail(), le relais de Hostinger.");
        } else {
            require_once __DIR__ . '/inc/smtp.php';
            $session = new SessionSmtp($smtp['hote'], $smtp['port'], $smtp['utilisateur'], $smtp['mot_de_passe']);
            try {
                $session->ouvrir();
                $session->fermer();
                definir_message('succes', "Connexion SMTP réussie ({$smtp['hote']}:{$smtp['port']}, {$smtp['utilisateur']}).");
            } catch (Throwable $e) {
                definir_message('erreur', 'Connexion SMTP impossible : ' . $e->getMessage());
            }
        }
    } elseif ($action === 'envoyer_test') {
        $adresse = trim((string) ($_POST['adresse'] ?? ''));
        if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
            definir_message('erreur', 'Adresse e-mail invalide.');
        } else {
            $expediteur = valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com';
            envoyer_mail(
                $adresse,
                $expediteur,
                'Test d\'envoi — Focal Club Turballais',
                "Bonjour,\n\nCeci est un e-mail de test envoyé depuis le journal des e-mails du site.\n\nSi vous le recevez, l'envoi fonctionne vers cette adresse."
            );
            $lignes = lire_journal_mails(1);
            $resultat = $lignes ? $lignes[0]['resultat'] : 'inconnu';
            definir_message(str_starts_with($resultat, 'SMTP OK') ? 'succes' : 'erreur',
                "E-mail de test vers {$adresse} : {$resultat}.");
        }
    }
    header('Location: journal-mails.php');
    exit;
}

/* Les $nombre dernières lignes du journal, les plus récentes en premier. */
function lire_journal_mails(int $nombre): array
{
    $brut = is_file(JOURNAL_MAILS) ? (@file(JOURNAL_MAILS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
    $lignes = [];
    foreach (array_reverse(array_slice($brut, -$nombre)) as $ligne) {
        $champs = explode("\t", $ligne, 4);
        if (count($champs) === 4) {
            $lignes[] = ['date' => $champs[0], 'destinataire' => $champs[1], 'sujet' => $champs[2], 'resultat' => $champs[3]];
        }
    }
    return $lignes;
}

$smtp = config_smtp();
$nb_destinataires = (int) $pdo->query(
    "SELECT COUNT(*) FROM adherents WHERE valide = 1 AND actif = 1 AND email IS NOT NULL AND email <> ''"
)->fetchColumn();
$journal = lire_journal_mails(300);

debut_page('Journal des e-mails', 'journal-mails');
titre_page('Journal des e-mails', 'Chaque e-mail envoyé par le site, avec le résultat de l\'envoi.');
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <div class="form-card">
    <h2>État de l'envoi</h2>
    <?php if ($smtp !== null): ?>
      <p>SMTP authentifié configuré : <strong><?= e($smtp['utilisateur']) ?></strong> via <?= e($smtp['hote']) ?>:<?= (int) $smtp['port'] ?>.</p>
    <?php else: ?>
      <p><strong>Aucun SMTP configuré</strong> : les e-mails partent par mail() (relais de Hostinger, souvent retardés par Gmail, et limité à quelques envois d'affilée).</p>
      <?php
      // Diagnostic : quel fichier est lu, et quelles clés « smtp » il contient
      // (les noms seulement, jamais les valeurs).
      $chemin_config = __DIR__ . '/inc/config.local.php';
      $cles_smtp = [];
      if (is_file($chemin_config)) {
          $donnees_config = require $chemin_config;
          foreach ((array) $donnees_config as $cle => $valeur) {
              if (stripos((string) $cle, 'smtp') !== false) {
                  $cles_smtp[] = $cle . (trim((string) $valeur) === '' ? ' (vide)' : ' (renseignée)');
              }
          }
      }
      ?>
      <p class="form-note">Fichier lu : <code><?= e($chemin_config) ?></code><br>
        Réglages SMTP trouvés dedans : <?= $cles_smtp ? e(implode(', ', $cles_smtp)) : '<strong>aucun</strong>' ?>.<br>
        Attendus : <code>smtp_utilisateur</code> et <code>smtp_mot_de_passe</code>, tous deux renseignés.</p>
    <?php endif; ?>
    <p><?= $nb_destinataires ?> adhérent<?= $nb_destinataires > 1 ? 's' : '' ?> reçoi<?= $nb_destinataires > 1 ? 'vent' : 't' ?> les notifications (compte validé, actif, avec une adresse e-mail).</p>

    <form method="post" style="margin-top:16px;">
      <?= champ_csrf() ?>
      <input type="hidden" name="action" value="tester_connexion">
      <button type="submit" class="btn btn-ghost">Tester la connexion SMTP</button>
    </form>

    <form method="post" style="margin-top:24px;">
      <?= champ_csrf() ?>
      <input type="hidden" name="action" value="envoyer_test">
      <div class="field">
        <label for="adresse">Envoyer un e-mail de test à</label>
        <input type="email" id="adresse" name="adresse" required placeholder="adresse@exemple.fr">
      </div>
      <button type="submit" class="btn btn-primary">Envoyer le test</button>
    </form>
  </div>

  <div class="form-card" style="margin-top:32px;">
    <h2>Derniers envois</h2>
    <?php if (!$journal): ?>
      <p class="empty-state">Aucun envoi enregistré pour l'instant. Le journal se remplit au prochain e-mail envoyé par le site.</p>
    <?php else: ?>
      <p class="form-note">« SMTP OK » : le serveur de messagerie du club a accepté l'e-mail. S'il n'arrive pas, regarder dans les courriers indésirables du destinataire.</p>
      <div class="tableau-defilant"><table class="tableau-adherents">
        <thead><tr><th>Date</th><th>Destinataire</th><th>Sujet</th><th>Résultat</th></tr></thead>
        <tbody>
        <?php foreach ($journal as $ligne): ?>
          <tr>
            <td><?= e($ligne['date']) ?></td>
            <td><?= e($ligne['destinataire']) ?></td>
            <td><?= e($ligne['sujet']) ?></td>
            <td><?= e($ligne['resultat']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div></section>
<?php fin_page(); ?>
