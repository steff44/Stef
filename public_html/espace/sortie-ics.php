<?php
/*
 * Sert le fichier .ics d'une sortie, pour l'ajouter à un agenda qui ne
 * propose pas de lien direct (Apple Calendar, Outlook de bureau,
 * Thunderbird...) — voir "Ajouter au calendrier" sur sorties-a-venir.php,
 * choix explicite de l'utilisatrice, 24/09/2026. Public, comme le reste de
 * l'agenda (agenda.php, sorties-a-venir.php) : aucune connexion requise.
 *
 * Script autonome (même principe que telecharger.php) : ne prend qu'un
 * identifiant en base, jamais de contenu depuis l'URL.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/agenda.php';
require_once __DIR__ . '/inc/mail.php'; // SITE_URL

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Sortie introuvable.');
}

$pdo     = base_de_donnees();
$requete = $pdo->prepare('SELECT id, titre, categorie, debut, fin, lieu, description FROM sorties WHERE id = ?');
$requete->execute([$id]);
$sortie = $requete->fetch();

if (!$sortie) {
    http_response_code(404);
    exit('Sortie introuvable.');
}

$lien_retour = SITE_URL . '/espace/sorties-a-venir.php#sortie-' . (int) $sortie['id'];
$contenu     = ics_evenement_sortie($sortie, $lien_retour);

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Length: ' . strlen($contenu));
header(sprintf(
    "Content-Disposition: attachment; filename*=UTF-8''%s",
    rawurlencode('sortie-' . $sortie['id'] . '.ics')
));
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

echo $contenu;
