<?php
/*
 * Point d'accès en lecture seule à l'état de connexion, pour les pages
 * HTML statiques (voir js/main.js) : elles ne savent jamais, au moment du
 * déploiement, si le visiteur qui les charge est connecté. Sans ce
 * correctif, le menu « {pseudo} connecté » revenait afficher « Espace
 * Adhérent » dès qu'on quittait une page de l'espace adhérents pour une
 * page publique, alors que la session restait bien active.
 *
 * Volontairement limité à inc/auth.php (session uniquement, pas de base de
 * données) : rapide, et résistant à une panne de la base — dans ce cas, le
 * menu reste simplement affiché comme non connecté sur les pages statiques,
 * plutôt que de faire échouer leur affichage.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

header('Content-Type: application/json; charset=utf-8');
// Dépend de la session : jamais de cache partagé, jamais de cache du tout
// (l'état change à chaque connexion/déconnexion).
header('Cache-Control: private, no-store');

$adherent = adherent_connecte();

if ($adherent === null) {
    echo json_encode(['connecte' => false]);
    exit;
}

echo json_encode([
    'connecte'       => true,
    'identifiant'    => $adherent['identifiant'],
    'nom'            => $adherent['nom'],
    'administrateur' => (bool) $adherent['administrateur'],
    'editeur'        => (bool) ($adherent['editeur'] ?? false),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
