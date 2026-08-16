<?php
/*
 * Déconnexion : vide la session puis renvoie à la page de connexion.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

// Avant de détruire la session, on efface la trace d'activité : sans cela, la
// personne resterait affichée comme « connectée » dans le tableau des
// responsables pendant encore un quart d'heure.
$adherent = adherent_connecte();
if ($adherent !== null) {
    $maj = base_de_donnees()->prepare('UPDATE adherents SET derniere_activite = NULL WHERE id = ?');
    $maj->execute([$adherent['id']]);
}

deconnecter();
demarrer_session();
$_SESSION['message'] = ['type' => 'succes', 'texte' => "Vous êtes déconnecté."];

header('Location: connexion.php');
exit;
