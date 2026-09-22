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
//
// Pointait vers myfocal.online (bug trouvé le 10/09/2026, en creusant le
// signalement « aucun mail reçu » sur mot-de-passe-oublie.php) : ce réglage
// datait d'avant le 30/08/2026, quand myfocal.online était encore l'unique
// domaine en ligne — depuis, focalclub.fr est devenu le site de référence
// (voir plus haut, « Pièges déjà rencontrés »), et myfocal.online est un
// site de test qui peut prendre du retard sur les déploiements (constaté le
// 09/09/2026, figé depuis le 31/08/2026 à cette date). Tout lien envoyé par
// e-mail ou WhatsApp (nouvelle sortie, nouvel article de blog, ce lien de
// réinitialisation) pointait donc potentiellement vers une page absente ou
// périmée sur myfocal.online, en plus d'un signal de hameçonnage classique
// pour les filtres anti-spam (nom de marque « Focal Club Turballais » dans
// l'e-mail, lien vers un domaine sans rapport apparent).
const SITE_URL = 'https://focalclub.fr';

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
 * (focalclub.fr, voir plus bas — pas la vraie adresse de contact du club,
 * souvent une adresse Gmail), $expediteur restant utilisé comme Reply-To
 * pour que répondre au mail atterrisse bien sur la bonne adresse.
 *
 * **Ce domaine a changé le 10/09/2026** (choix explicite de l'utilisatrice,
 * qui a signalé ne plus recevoir aucun e-mail de réinitialisation sur
 * Gmail — ni en boîte de réception, ni en spam — alors qu'une adresse
 * Hostinger comme admin@focalclub.fr recevait tout normalement, la même
 * asymétrie inbox-Hostinger/rien-Gmail que pour les autres notifications
 * du site). Avant ce jour, `From:` utilisait `noreply@myfocal.online` :
 * réglage posé le 23/08/2026, quand `myfocal.online` était encore l'unique
 * domaine du site et que « Hostinger envoie mail() sous ce domaine »
 * suffisait à décrire l'infrastructure. Depuis la bascule du 30/08/2026,
 * qui a fait de `focalclub.fr` un second site Hostinger entièrement
 * séparé (voir CLAUDE.md, « Pièges déjà rencontrés »), cette hypothèse
 * n'a jamais été revérifiée. Un diagnostic DNS direct (10/09/2026, requêtes
 * `dig` depuis un workflow GitHub Actions, le sandbox ne pouvant pas
 * interroger le DNS public) a confirmé l'écart : `focalclub.fr` porte un
 * enregistrement SPF valide (`v=spf1 include:_spf.mail.hostinger.com
 * ~all`), un DMARC (`p=none`) et des MX Hostinger — tandis que
 * `myfocal.online` n'a **aucun** enregistrement SPF, MX ni DMARC. Un
 * `From:` sur un domaine sans SPF est exactement le signal qui pousse
 * Gmail à rejeter silencieusement un message (pas même en spam), tout en
 * laissant passer sans problème une remise locale vers une boîte du même
 * hébergeur (d'où « ça marche sur admin@focalclub.fr, jamais sur Gmail »).
 * `From:` pointe donc maintenant vers `noreply@focalclub.fr`, le domaine
 * réellement authentifié.
 *
 * **Ce correctif n'a pas suffi non plus (22/09/2026)** : même après
 * activation de DKIM pour focalclub.fr, un test réel via mail-tester.com
 * a montré que le message continuait d'être envoyé avec une **enveloppe
 * SMTP** (Return-Path, ce que SPF/DKIM/DMARC vérifient réellement — pas
 * l'en-tête `From:` visible) sur `noreply@srv1427.main-hosting.eu`, le
 * nom générique du serveur mutualisé Hostinger, jamais revu depuis. PHP
 * `mail()` ne fixe l'enveloppe sur le domaine voulu que si on la lui
 * passe explicitement (5ᵉ paramètre, `-f`) : sans lui, le serveur retombe
 * sur son adresse par défaut, quel que soit le `From:` affiché — d'où un
 * SPF qui authentifiait bien *quelque chose*, mais jamais `focalclub.fr`,
 * une signature DKIM jamais appliquée (Hostinger ne signe que ce qu'il
 * reconnaît comme envoyé pour un domaine du compte) et un DMARC en échec
 * (aucun des deux n'aligne avec le `From:` affiché). `-f` ajouté pour
 * forcer l'enveloppe sur `noreply@focalclub.fr`, un domaine réellement
 * configuré sur ce compte Hostinger (MX/SPF/DKIM déjà en place) — ce qui
 * devrait aligner SPF et laisser Hostinger signer en DKIM du même coup.
 */
function envoyer_mail(string $destinataire, string $expediteur, string $sujet, string $corps): void
{
    $entetes = "From: Focal Club Turballais <noreply@focalclub.fr>\r\n"
             . "Reply-To: {$expediteur}\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n";
    $sujet_encode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

    if (!@mail($destinataire, $sujet_encode, corps_html($corps), $entetes, '-f noreply@focalclub.fr')) {
        error_log("Espace adhérents — échec d'envoi de mail à {$destinataire} : {$sujet}");
    }
}

/*
 * Confirmation personnelle envoyée à l'adhérent qui vient d'ajouter un
 * contenu au site — document, sortie, article de blog, album de « Nos
 * Sorties » (choix explicite de l'utilisatrice, 18/09/2026 : « je veux
 * aussi recevoir un mail pour être sûr de ne pas avoir fait de sottise »).
 * Distincte de la notification envoyée à tous les adhérents (voir les
 * quatre appelants) même si l'auteur en fait déjà partie : un sujet et un
 * ton dédiés, pour qu'elle se reconnaisse immédiatement dans la boîte mail
 * plutôt que de se fondre parmi les notifications générales. Silencieuse
 * si l'adhérent connecté n'a pas d'adresse e-mail renseignée (le tout
 * premier compte du site, créé par installation.php, peut ne pas en avoir).
 *
 * L'adresse est relue en base par $adherent['id'], jamais prise dans
 * $adherent['email'] (piège trouvé le 21/09/2026 : cette clé vient de
 * $_SESSION['adherent'], posée une seule fois à la connexion — inc/auth.php
 * — et jamais rafraîchie ensuite ; le cookie de session durant 30 jours,
 * une adresse ajoutée ou corrigée après la dernière connexion restait
 * invisible ici jusqu'à une reconnexion, faisant échouer la confirmation en
 * silence sans rapport avec l'état réel du compte).
 */
function envoyer_confirmation_personnelle(PDO $pdo, array $adherent, string $sujet, string $corps): void
{
    $requete = $pdo->prepare('SELECT email FROM adherents WHERE id = ?');
    $requete->execute([$adherent['id']]);
    $email = $requete->fetchColumn();

    if (empty($email)) {
        return;
    }
    $expediteur = valeur_parametre($pdo, 'email') ?: 'cooky44.sl@gmail.com';
    envoyer_mail($email, $expediteur, $sujet, $corps);
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
