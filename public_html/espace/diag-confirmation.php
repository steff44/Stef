<?php
/*
 * Diagnostic temporaire pour la confirmation personnelle par e-mail, qui
 * reste non reçue malgré le correctif du 21/09/2026 (adresse relue en base
 * plutôt que depuis la session — voir CLAUDE.md). Même principe que les
 * diagnostics déjà utilisés pour la réinitialisation de mot de passe
 * (diag-reset-mail.php, supprimé une fois la cause confirmée) : réservé au
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

echo "=== Diagnostic confirmation personnelle par e-mail ===\n\n";

echo "1) Ce que la SESSION connaît (\$_SESSION['adherent'], mis à jour uniquement à la connexion) :\n";
echo "   id    : {$adherent['id']}\n";
echo "   email : " . (array_key_exists('email', $adherent) && $adherent['email'] !== '' && $adherent['email'] !== null
        ? "'{$adherent['email']}'" : '(vide ou absente)') . "\n\n";

$requete = $pdo->prepare('SELECT email FROM adherents WHERE id = ?');
$requete->execute([$adherent['id']]);
$email_bdd = $requete->fetchColumn();

echo "2) Ce que la BASE DE DONNÉES contient réellement pour ce compte (id={$adherent['id']}) :\n";
echo "   email : " . (!empty($email_bdd) ? "'{$email_bdd}'" : '(vide ou NULL — AUCUN e-mail ne peut partir)') . "\n\n";

$expediteur = valeur_parametre($pdo, 'email');
echo "3) Reply-To utilisé (parametres_site.email) : " . ($expediteur ?: '(non réglé, repli sur cooky44.sl@gmail.com)') . "\n\n";

if (empty($email_bdd)) {
    echo "ARRÊT : aucune adresse en base pour ce compte, donc aucun test d'envoi possible.\n";
    echo "Ajoutez une adresse depuis l'Annuaire, puis rechargez cette page.\n";
    exit;
}

echo "4) Envoi de test n°1 — mail() natif appelé directement ici (contourne toute logique du site) :\n";
$entetes_test = "From: Focal Club Turballais <noreply@focalclub.fr>\r\n"
              . "Reply-To: " . ($expediteur ?: 'cooky44.sl@gmail.com') . "\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n";
$sujet_test   = '=?UTF-8?B?' . base64_encode('Diagnostic 1/2 — mail() direct') . '?=';
$corps_test   = corps_html("Envoyé directement par mail(), sans passer par envoyer_mail() ni "
    . "envoyer_confirmation_personnelle(), à " . date('d/m/Y H:i:s') . ".");
$resultat_1   = @mail($email_bdd, $sujet_test, $corps_test, $entetes_test);
echo "   Résultat : " . ($resultat_1 ? 'VRAI (le serveur a accepté le message)' : 'FAUX (échec immédiat, voir error_log)') . "\n\n";

echo "5) Envoi de test n°2 — via la vraie fonction envoyer_confirmation_personnelle() (celle utilisée par documents.php, sorties-a-venir.php, blog.php, parametres.php) :\n";
envoyer_confirmation_personnelle(
    $pdo,
    $adherent,
    'Diagnostic 2/2 — envoyer_confirmation_personnelle()',
    "Envoyé par la fonction réellement utilisée en production, à " . date('d/m/Y H:i:s') . "."
);
echo "   Appelée sans erreur PHP.\n\n";

echo "=== Fin — vérifiez votre boîte de réception ET vos spams pour les DEUX e-mails de test ===\n";
echo "Si AUCUN des deux n'arrive : problème de délivrabilité (comme le cas SPF/Gmail déjà rencontré), sans rapport avec le code de confirmation.\n";
echo "Si SEULEMENT le test 1 arrive : la fonction envoyer_confirmation_personnelle() a un problème propre à elle.\n";
echo "Si LES DEUX arrivent : le correctif fonctionne — le souci d'origine était probablement un test fait avant la fin du déploiement.\n";
