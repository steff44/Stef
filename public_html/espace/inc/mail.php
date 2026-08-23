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
 *
 * Le champ From utilise toujours une adresse du domaine du site
 * (myfocal.online), jamais l'adresse de contact réelle (souvent une
 * adresse Gmail) : Hostinger envoie mail() sous ce domaine, et un From qui
 * prétend venir d'une autre adresse (ex. Gmail) échoue à la vérification
 * DMARC du destinataire — Gmail rejette alors le message en silence, sans
 * même le déposer dans les spams. Piège constaté le 23/08/2026 : aucun mail
 * de notification reçu malgré une adresse correctement réglée dans
 * Réglages du site. $expediteur reste utilisé comme Reply-To, pour que
 * répondre au mail atterrisse bien sur la bonne adresse.
 */
function envoyer_mail(string $destinataire, string $expediteur, string $sujet, string $corps): void
{
    $entetes = "From: Focal Club Turballais <noreply@myfocal.online>\r\n"
             . "Reply-To: {$expediteur}\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";
    $sujet_encode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

    if (!@mail($destinataire, $sujet_encode, $corps, $entetes)) {
        error_log("Espace adhérents — échec d'envoi de mail à {$destinataire} : {$sujet}");
    }
}
