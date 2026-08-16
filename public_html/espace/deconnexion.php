<?php
/*
 * Déconnexion : vide la session puis renvoie à la page de connexion.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

deconnecter();
demarrer_session();
definir_message_simple("Vous êtes déconnecté.");

header('Location: connexion.php');
exit;

/*
 * definir_message() vit dans page.php, qu'on ne charge pas ici (aucune page
 * n'est affichée). On refait donc le strict minimum.
 */
function definir_message_simple(string $texte): void
{
    $_SESSION['message'] = ['type' => 'succes', 'texte' => $texte];
}
