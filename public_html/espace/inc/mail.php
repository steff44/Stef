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

// Adresse de base du site, pour bâtir des liens absolus dans un e-mail ou un
// message externe (WhatsApp) — un lien relatif n'aurait aucun sens en dehors
// d'une page du site.
const SITE_URL = 'https://myfocal.online';

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
             . "Content-Type: text/html; charset=UTF-8\r\n";
    $sujet_encode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

    if (!@mail($destinataire, $sujet_encode, corps_html($corps), $entetes)) {
        error_log("Espace adhérents — échec d'envoi de mail à {$destinataire} : {$sujet}");
    }
}

/*
 * $corps reste écrit en texte brut par les appelants (comme avant) — pas de
 * HTML à la main dans chaque message. Une seule convention Markdown minimale
 * est reconnue ici : **texte** devient du gras (choix explicite de
 * l'utilisateur, 23/08/2026, pour la mention « pensez à vérifier vos
 * spams »). Échapper AVANT de reposer les <strong> évite qu'un nom ou un
 * texte contenant `<`/`>` ne casse le rendu ou n'injecte du HTML.
 */
function corps_html(string $corps): string
{
    $echappe = htmlspecialchars($corps, ENT_QUOTES, 'UTF-8');
    $gras    = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $echappe);
    return '<!DOCTYPE html><html><body style="font-family:sans-serif;font-size:15px;line-height:1.6;color:#111111;">'
        . nl2br($gras)
        . '</body></html>';
}
