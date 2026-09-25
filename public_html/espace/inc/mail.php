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
 * (aucun des deux n'aligne avec le `From:` affiché).
 *
 * **Un premier essai avec `-f noreply@focalclub.fr` a empiré la
 * situation (22/09/2026, même jour)** : après déploiement confirmé, plus
 * aucun e-mail n'arrivait nulle part — pas même à mail-tester.com, qui
 * recevait pourtant les envois d'avant ce correctif (avec un mauvais
 * score, mais reçus). `mail()` continuait de renvoyer vrai (le message
 * est accepté par le serveur local), donc l'échec se produit plus loin,
 * entre l'acceptation locale et la relève sortante de Hostinger — sans
 * jamais remonter d'erreur PHP. Cause la plus probable : beaucoup
 * d'hébergements mutualisés (Hostinger compris) n'acceptent une adresse
 * `-f` en enveloppe que si elle correspond à une **vraie boîte mail
 * existante** sur le compte, pour empêcher un script d'usurper n'importe
 * quelle adresse du domaine — `noreply@focalclub.fr` n'a jamais été créée
 * comme boîte réelle dans hPanel, seulement utilisée comme adresse
 * d'affichage dans l'en-tête `From:`. Le relais accepte donc le message en
 * local (d'où `mail()` qui renvoie vrai) puis l'abandonne silencieusement
 * en sortie faute d'enveloppe légitime. `admin@focalclub.fr`, en
 * revanche, est une **vraie boîte** du compte, déjà confirmée
 * fonctionnelle (10/09/2026, « je reçois les avertissements de création
 * et aussi les demandes de réinitialisation de mot de passe » — voir plus
 * haut). L'enveloppe passe donc à `-f admin@focalclub.fr` ; le `From:`
 * affiché reste `noreply@focalclub.fr` (purement cosmétique, DMARC vérifie
 * un alignement de **domaine**, pas d'adresse exacte, entre l'enveloppe et
 * le `From:` — les deux restent sur `focalclub.fr`).
 *
 * **Ce correctif n'a pas non plus résolu le fond (23/09/2026)** : un
 * dépôt réel de document a fini par recevoir sa notification — mais avec
 * un rapport mail-tester.com montrant SPF toujours authentifié pour
 * `noreply@srv1427.main-hosting.eu`, jamais pour `focalclub.fr`, quelle
 * que soit l'adresse passée en `-f`. Cause trouvée : Hostinger fait
 * passer tout le trafic `mail()` PHP par un relais mutualisé
 * (MailChannels, `dog.cedar.relay.mailchannels.net`, visible dans le
 * rapport) qui **ignore purement et simplement le `-f`** et impose
 * toujours l'identité du serveur partagé — `-f` n'a donc jamais eu
 * d'effet, ni avec `noreply@` ni avec `admin@`. Les e-mails de
 * diagnostic envoyés pendant toute cette investigation ont fini par
 * arriver, mais en rafale le lendemain (horodatages 08:18, 15:16, 20:31
 * la veille puis 06:06 le matin suivant, tous reçus dans la même
 * demi-heure) : Gmail ne les rejetait donc pas, il les mettait en
 * attente plusieurs heures — comportement classique face à un expéditeur
 * sans réputation établie, cohérent avec l'absence de DKIM du message.
 *
 * **Corrigé en sortant complètement de `mail()`** : `envoyer_mail()`
 * tente désormais un envoi en **SMTP authentifié** (`inc/smtp.php`,
 * client minimal écrit à la main) avec une vraie boîte du domaine —
 * `noreply@focalclub.fr`, créée le 23/09/2026 — dès que
 * `espace/inc/config.local.php` porte `smtp_utilisateur`/
 * `smtp_mot_de_passe` (voir `config.example.php`). Une connexion SMTP
 * authentifiée échappe au relais MailChannels : Hostinger traite alors
 * le message comme un vrai e-mail du compte, DKIM compris, sans le délai
 * de mise en attente observé plus haut. `mail()` (avec `-f
 * admin@focalclub.fr`, inchangé) reste le repli automatique si la
 * configuration SMTP est absente ou si l'envoi SMTP échoue — jamais de
 * régression pour une installation qui n'aurait pas encore cette boîte.
 */
/*
 * Correctifs du 25/09/2026 — les notifications n'arrivaient à AUCUN
 * adhérent alors que les tests d'un seul e-mail passaient :
 * - une seule connexion SMTP pour toute la page (session_smtp()), au lieu
 *   d'une connexion + authentification par adhérent ;
 * - coupe-circuit : si le SMTP échoue deux fois de suite, les envois
 *   suivants de la page passent directement par mail(), sans réessayer
 *   (sinon 10 s d'attente par adhérent et PHP coupait la page au bout de
 *   30 s, après 2 ou 3 envois seulement) ;
 * - message complet (Date, Message-ID, To, MIME-Version, corps en base64) :
 *   sans Date/Message-ID, plusieurs fournisseurs classent en spam ou
 *   refusent ; le base64 évite aussi les lignes de plus de 998 caractères
 *   interdites en SMTP (longue description d'une sortie) ;
 * - chaque envoi est consigné dans inc/.journal-mails.log, lisible par le
 *   responsable sur espace/journal-mails.php (le journal d'erreurs PHP est
 *   introuvable dans hPanel).
 */
function envoyer_mail(string $destinataire, string $expediteur, string $sujet, string $corps): void
{
    // Chaque envoi repart avec son propre délai d'exécution : une
    // notification à tous les adhérents ne doit pas être coupée en route par
    // la limite de 30 s de PHP, ni par un visiteur qui ferme la page.
    @set_time_limit(60);
    ignore_user_abort(true);

    // Jamais de retour à la ligne dans un en-tête (injection d'en-têtes).
    $destinataire = trim(str_replace(["\r", "\n"], '', $destinataire));
    $expediteur   = trim(str_replace(["\r", "\n"], '', $expediteur));

    $sujet_encode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';
    $corps_base64 = rtrim(chunk_split(base64_encode(corps_html($corps)), 76, "\r\n"));
    $entetes = "From: Focal Club Turballais <noreply@focalclub.fr>\r\n"
             . "Reply-To: {$expediteur}\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: base64\r\n";

    $session = session_smtp();
    if ($session !== null) {
        $message = 'Date: ' . date('r') . "\r\n"
                 . 'Message-ID: <' . bin2hex(random_bytes(16)) . "@focalclub.fr>\r\n"
                 . "To: <{$destinataire}>\r\n"
                 . "Subject: {$sujet_encode}\r\n"
                 . $entetes
                 . "\r\n"
                 . $corps_base64;
        $deja_ouverte = $session->est_ouverte();
        try {
            $session->envoyer($destinataire, $message);
            session_smtp_resultat(true);
            journal_mail($destinataire, $sujet, 'SMTP OK');
            return;
        } catch (ErreurConnexionSmtp $e) {
            // Serveur injoignable ou authentification refusée : inutile
            // d'insister pour les adhérents suivants.
            session_smtp_resultat(false, true);
            journal_mail($destinataire, $sujet, 'SMTP ÉCHEC (connexion) : ' . $e->getMessage());
        } catch (Throwable $e) {
            $erreur = $e;
            // Une connexion restée ouverte a pu être coupée par le serveur
            // entre deux envois : un second essai sur une connexion neuve.
            if ($deja_ouverte) {
                try {
                    $session->envoyer($destinataire, $message);
                    session_smtp_resultat(true);
                    journal_mail($destinataire, $sujet, 'SMTP OK (2e essai)');
                    return;
                } catch (ErreurConnexionSmtp $e2) {
                    session_smtp_resultat(false, true);
                    $erreur = $e2;
                } catch (Throwable $e2) {
                    $erreur = $e2;
                }
            }
            session_smtp_resultat(false);
            journal_mail($destinataire, $sujet, 'SMTP ÉCHEC : ' . $erreur->getMessage());
        }
        // Repli sur mail() ci-dessous plutôt que de perdre l'e-mail.
    }

    $ok = @mail($destinataire, $sujet_encode, $corps_base64, $entetes, '-f admin@focalclub.fr');
    journal_mail($destinataire, $sujet, $ok ? 'mail() accepté (relais Hostinger, peut être retardé)' : 'mail() ÉCHEC');
}

/*
 * La session SMTP de la page, ouverte au premier envoi et fermée en fin de
 * page. null si les identifiants SMTP manquent, ou si le SMTP a déjà échoué
 * deux fois de suite dans cette page (coupe-circuit, voir envoyer_mail()).
 */
function session_smtp(): ?SessionSmtp
{
    $etat = &etat_smtp();
    if ($etat['coupe']) {
        return null;
    }
    if ($etat['session'] === null) {
        $smtp = config_smtp();
        if ($smtp === null) {
            return null;
        }
        require_once __DIR__ . '/smtp.php';
        $etat['session'] = new SessionSmtp($smtp['hote'], $smtp['port'], $smtp['utilisateur'], $smtp['mot_de_passe']);
        $session = $etat['session'];
        register_shutdown_function(static function () use ($session): void {
            $session->fermer();
        });
    }
    return $etat['session'];
}

/* Coupe le SMTP pour le reste de la page après un échec de connexion, ou
   après deux échecs d'envoi de suite (un refus isolé ne concerne souvent
   qu'une adresse et ne doit pas priver les autres adhérents du SMTP). */
function session_smtp_resultat(bool $reussi, bool $echec_connexion = false): void
{
    $etat = &etat_smtp();
    $etat['echecs'] = $reussi ? 0 : $etat['echecs'] + 1;
    if ($echec_connexion || $etat['echecs'] >= 2) {
        $etat['coupe'] = true;
    }
}

function &etat_smtp(): array
{
    static $etat = ['session' => null, 'echecs' => 0, 'coupe' => false];
    return $etat;
}

/*
 * Une ligne par envoi dans inc/.journal-mails.log (dossier fermé par
 * .htaccess), gardé à ~1000 lignes pour ne pas grossir indéfiniment ni
 * conserver trop longtemps les adresses des adhérents.
 */
const JOURNAL_MAILS = __DIR__ . '/.journal-mails.log';

function journal_mail(string $destinataire, string $sujet, string $resultat): void
{
    $ligne = date('Y-m-d H:i:s') . "\t{$destinataire}\t"
           . str_replace(["\t", "\r", "\n"], ' ', $sujet) . "\t"
           . str_replace(["\t", "\r", "\n"], ' ', $resultat) . "\n";
    @file_put_contents(JOURNAL_MAILS, $ligne, FILE_APPEND | LOCK_EX);

    if (@filesize(JOURNAL_MAILS) > 300000) {
        $lignes = @file(JOURNAL_MAILS) ?: [];
        @file_put_contents(JOURNAL_MAILS, implode('', array_slice($lignes, -1000)), LOCK_EX);
    }
}

/*
 * Lit les identifiants SMTP dans config.local.php — null si absents, pour
 * laisser envoyer_mail() se replier silencieusement sur mail(). Mise en
 * cache pour la durée de la requête (plusieurs e-mails peuvent être
 * envoyés dans une même page, ex. la notification générale à tous les
 * adhérents).
 */
function config_smtp(): ?array
{
    static $config = false; // faux témoin « pas encore lu », distinct de null

    if ($config === false) {
        $chemin  = __DIR__ . '/config.local.php';
        $donnees = is_file($chemin) ? (require $chemin) : [];
        $utilisateur  = trim((string) ($donnees['smtp_utilisateur'] ?? ''));
        $mot_de_passe = (string) ($donnees['smtp_mot_de_passe'] ?? '');

        $config = ($utilisateur !== '' && $mot_de_passe !== '')
            ? [
                'hote'         => (string) ($donnees['smtp_hote'] ?? 'smtp.hostinger.com'),
                'port'         => (int) ($donnees['smtp_port'] ?? 587),
                'utilisateur'  => $utilisateur,
                'mot_de_passe' => $mot_de_passe,
            ]
            : null;
    }

    return $config;
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
