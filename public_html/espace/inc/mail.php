<?php
/*
 * Envoi des e-mails de notification liés à l'inscription (en attente,
 * validée) et à la modération (nouveau compte à valider).
 *
 * PHP mail() natif, sans dépendance externe (Composer/PHPMailer) : cohérent
 * avec le reste du projet, qui n'a ni build ni gestionnaire de paquets, et
 * Hostinger route mail() par son propre serveur sortant.
 */

declare(strict_types=1);

/* Lit une seule valeur de parametres_site (coordonnées du club). */
function valeur_parametre(PDO $pdo, string $cle): ?string
{
    $requete = $pdo->prepare('SELECT valeur FROM parametres_site WHERE cle = ?');
    $requete->execute([$cle]);
    $valeur = $requete->fetchColumn();
    return $valeur !== false ? (string) $valeur : null;
}

/*
 * Échoue silencieusement (juste consigné dans error_log) : un e-mail qui ne
 * part pas ne doit jamais empêcher une inscription ou une validation
 * d'aboutir — l'action en base a déjà réussi quand celle-ci est appelée.
 */
function envoyer_mail(string $destinataire, string $expediteur, string $sujet, string $corps): void
{
    $entetes = "From: Focal Club Turballais <{$expediteur}>\r\n"
             . "Reply-To: {$expediteur}\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    $sujet_encode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

    if (!@mail($destinataire, $sujet_encode, $corps, $entetes)) {
        error_log("Espace adhérents — échec d'envoi de mail à {$destinataire} : {$sujet}");
    }
}
