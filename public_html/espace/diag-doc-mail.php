<?php
/*
 * Diagnostic temporaire : l'utilisatrice ne reçoit ni la notification
 * générale ni la confirmation personnelle après un dépôt réel dans
 * « Documents du Club » (documents.php), alors que le même mécanisme
 * (envoyer_confirmation_personnelle()) a été confirmé fonctionnel la veille
 * via diag-confirmation.php (supprimé). Même principe : réservé au
 * responsable connecté, montre l'état réel plutôt que de deviner.
 *
 * À supprimer une fois la cause confirmée.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/mail.php';

$adherent = exige_administrateur();
$pdo      = base_de_donnees();

header('Content-Type: text/plain; charset=UTF-8');

echo "=== Diagnostic notification/confirmation — Documents du club ===\n\n";

echo "1) Les 5 derniers documents déposés (confirme si un dépôt récent a bien été enregistré en base) :\n";
$recents = $pdo->query(
    "SELECT d.id, d.titre, d.categorie, d.cree_le, a.nom AS auteur, a.email AS auteur_email
     FROM documents d
     LEFT JOIN adherents a ON a.id = d.depose_par
     ORDER BY d.id DESC LIMIT 5"
)->fetchAll();
if (!$recents) {
    echo "   AUCUN document en base — la table est vide.\n\n";
} else {
    foreach ($recents as $d) {
        echo "   #{$d['id']} « {$d['titre']} » ({$d['categorie']}) — déposé le {$d['cree_le']} par "
            . ($d['auteur'] ?? '(adhérent retiré)') . ' <' . ($d['auteur_email'] ?: 'sans e-mail') . ">\n";
    }
    echo "\n";
}

echo "2) Adhérents qui recevraient la notification générale (valide=1 actif=1 email renseigné) :\n";
$destinataires = $pdo->query(
    "SELECT id, nom, email FROM adherents WHERE valide = 1 AND actif = 1 AND email IS NOT NULL AND email <> ''"
)->fetchAll();
if (!$destinataires) {
    echo "   AUCUN — personne ne recevrait la notification générale (c'est pourquoi rien n'arrive).\n\n";
} else {
    foreach ($destinataires as $d) {
        $vous = ((int) $d['id'] === (int) $adherent['id']) ? '  <-- vous' : '';
        echo "   #{$d['id']} {$d['nom']} <{$d['email']}>{$vous}\n";
    }
    echo "\n";
}

echo "3) Votre compte (id={$adherent['id']}), tel qu'il est réellement en base :\n";
$requete = $pdo->prepare('SELECT nom, email, valide, actif FROM adherents WHERE id = ?');
$requete->execute([$adherent['id']]);
$moi = $requete->fetch();
if (!$moi) {
    echo "   INTROUVABLE — anomalie grave.\n\n";
} else {
    echo "   nom    : {$moi['nom']}\n";
    echo "   email  : " . ($moi['email'] !== '' && $moi['email'] !== null ? "'{$moi['email']}'" : '(vide — AUCUN e-mail ne peut partir)') . "\n";
    echo "   valide : {$moi['valide']}\n";
    echo "   actif  : {$moi['actif']}\n\n";
}

if (empty($moi['email'])) {
    echo "ARRÊT : aucune adresse en base pour votre compte, donc aucun test d'envoi possible.\n";
    exit;
}

echo "4) Envoi de test — UNIQUEMENT à vous, avec la vraie fonction envoyer_confirmation_personnelle() (celle appelée par documents.php après un dépôt réussi) — pas de mass-mailing aux autres adhérents ici :\n";
envoyer_confirmation_personnelle(
    $pdo,
    $adherent,
    'DIAGNOSTIC — Confirmation de votre dépôt (ignorez cet e-mail)',
    "Test de diagnostic envoyé à " . date('d/m/Y H:i:s') . " directement depuis diag-doc-mail.php."
);
echo "   Appelée sans erreur PHP.\n\n";

echo "5) Envoi de test n°2 — mail() natif appelé directement (contourne toute logique du site) :\n";
$entetes_test = "From: Focal Club Turballais <noreply@focalclub.fr>\r\n"
              . "Reply-To: " . (valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com') . "\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n";
$sujet_test   = '=?UTF-8?B?' . base64_encode('DIAGNOSTIC — mail() direct') . '?=';
$corps_test   = corps_html("Envoyé directement par mail(), sans passer par envoyer_mail(), à " . date('d/m/Y H:i:s') . ".");
$resultat_2   = @mail($moi['email'], $sujet_test, $corps_test, $entetes_test);
echo "   Résultat : " . ($resultat_2 ? 'VRAI (le serveur a accepté le message)' : 'FAUX (échec immédiat, voir error_log)') . "\n\n";

echo "=== Fin — vérifiez votre boîte de réception ET vos spams pour les e-mails DIAGNOSTIC ===\n";
echo "Si le point 1 ne montre AUCUN document récent (aujourd'hui) : le dépôt lui-même échoue avant même d'essayer d'envoyer un e-mail — cherchez un message d'erreur affiché sur documents.php au moment du dépôt (catégorie non choisie, fichier refusé...).\n";
echo "Si un document récent existe en base mais que les DEUX tests ci-dessus n'arrivent pas non plus : problème de délivrabilité, sans rapport avec ce code.\n";
echo "Si les deux tests arrivent mais que le VRAI dépôt n'a rien envoyé : très étrange, à investiguer plus loin (comparer les deux chemins de code).\n";
