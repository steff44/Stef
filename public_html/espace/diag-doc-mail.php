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

/*
 * DKIM activé le 22/09/2026, mais toujours aucun e-mail reçu sur Gmail —
 * malgré SPF+DKIM+DMARC tous corrects en DNS. Publier un enregistrement
 * DKIM ne garantit pas que le serveur SIGNE réellement les messages
 * envoyés par mail() avec la clé correspondante (l'activation dans hPanel
 * peut ne concerner que les boîtes mail hébergées, pas le trafic PHP du
 * site). Pour trancher sans deviner, ?destinataire=... permet d'envoyer
 * ce test vers une adresse externe de diagnostic (ex. mail-tester.com),
 * qui rapporte precisement si le message reçu est bien signé DKIM ou
 * non, et pourquoi Gmail pourrait le rejeter. Reste reservé au
 * responsable connecté (exige_administrateur() ci-dessus) : personne
 * d'autre ne peut faire envoyer ce site vers une adresse de son choix.
 */
$destinataire_test = trim((string) ($_GET['destinataire'] ?? ''));
$cible = filter_var($destinataire_test, FILTER_VALIDATE_EMAIL) ? $destinataire_test : $moi['email'];
echo "--- Destinataire des tests ci-dessous : {$cible} "
    . ($cible === $moi['email'] ? '(votre adresse — par défaut)' : '(adresse fournie via ?destinataire=)')
    . " ---\n\n";

echo "4) Envoi de test — avec la vraie fonction envoyer_confirmation_personnelle() (celle appelée par documents.php après un dépôt réussi) :\n";
if ($cible === $moi['email']) {
    envoyer_confirmation_personnelle(
        $pdo,
        $adherent,
        'DIAGNOSTIC — Confirmation de votre dépôt (ignorez cet e-mail)',
        "Test de diagnostic envoyé à " . date('d/m/Y H:i:s') . " directement depuis diag-doc-mail.php."
    );
} else {
    // Même expéditeur/en-têtes que envoyer_mail(), mais vers l'adresse de
    // diagnostic choisie plutôt que celle de l'adhérent connecté.
    envoyer_mail(
        $cible,
        valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com',
        'DIAGNOSTIC — Confirmation de votre dépôt (ignorez cet e-mail)',
        "Test de diagnostic envoyé à " . date('d/m/Y H:i:s') . " directement depuis diag-doc-mail.php."
    );
}
echo "   Appelée sans erreur PHP.\n\n";

echo "5) Envoi de test n°2 — mail() natif appelé directement (contourne toute logique du site) :\n";
$entetes_test = "From: Focal Club Turballais <noreply@focalclub.fr>\r\n"
              . "Reply-To: " . (valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com') . "\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n";
$sujet_test   = '=?UTF-8?B?' . base64_encode('DIAGNOSTIC — mail() direct') . '?=';
$corps_test   = corps_html("Envoyé directement par mail(), sans passer par envoyer_mail(), à " . date('d/m/Y H:i:s') . ".");
$resultat_2   = @mail($cible, $sujet_test, $corps_test, $entetes_test);
echo "   Résultat : " . ($resultat_2 ? 'VRAI (le serveur a accepté le message)' : 'FAUX (échec immédiat, voir error_log)') . "\n\n";

echo "=== Fin ===\n";
if ($cible === $moi['email']) {
    echo "Vérifiez votre boîte de réception ET vos spams pour les e-mails DIAGNOSTIC.\n";
    echo "Si le point 1 ne montre AUCUN document récent (aujourd'hui) : le dépôt lui-même échoue avant même d'essayer d'envoyer un e-mail.\n";
    echo "Si un document récent existe en base mais que les DEUX tests ci-dessus n'arrivent pas non plus : problème de délivrabilité, sans rapport avec ce code.\n\n";
    echo "POUR ALLER PLUS LOIN — obtenir un rapport technique précis (DKIM signé ou non, score anti-spam, raison exacte du rejet) :\n";
    echo "1. Ouvrez https://www.mail-tester.com/ dans un nouvel onglet — il affiche une adresse e-mail temporaire unique (ex. test-xxxxx@mail-tester.com).\n";
    echo "2. Recopiez-la et rechargez cette page en ajoutant ?destinataire=CETTE_ADRESSE à la fin de l'URL.\n";
    echo "3. Retournez sur mail-tester.com et cliquez 'Then check your score' : le rapport dira noir sur blanc si SPF/DKIM/DMARC sont bien appliqués à ce message précis, et donnera la cause exacte si ce n'est pas le cas.\n";
} else {
    echo "Retournez sur mail-tester.com et cliquez 'Then check your score' pour voir le rapport technique complet de ce message.\n";
}
