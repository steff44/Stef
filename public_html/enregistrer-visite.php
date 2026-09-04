<?php
/*
 * Point d'accès PUBLIC, en écriture seule, qui enregistre une visite pour
 * les statistiques de fréquentation (espace/statistiques.php, réservée au
 * responsable). Appelé en arrière-plan par js/main.js sur chaque page
 * (fetch avec keepalive, sans attendre la réponse) — jamais synchrone,
 * jamais bloquant pour l'affichage de la page.
 *
 * Volontairement autonome, même principe qu'infos-club.php : sa propre
 * connexion PDO plutôt que espace/inc/db.php (qui affiche une page d'erreur
 * HTML en cas de panne, inadapté ici), et ses propres fonctions plutôt que
 * de dépendre d'un fichier de espace/inc/.
 *
 * Aucune adresse IP complète n'est jamais stockée ni transmise au service de
 * géolocalisation : anonymiser_ip() met à zéro le dernier octet (IPv4) ou les
 * 80 derniers bits (IPv6) avant tout usage — voir schema.sql pour le détail.
 */

declare(strict_types=1);

header('Cache-Control: no-store');

$page = isset($_GET['page']) ? substr((string) $_GET['page'], 0, 190) : '/';
if ($page === '') {
    $page = '/';
}

$chemin = __DIR__ . '/espace/inc/config.local.php';
if (!is_file($chemin)) {
    http_response_code(204);
    exit;
}

$config = require $chemin;

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['hote'], $config['base']),
        $config['utilisateur'],
        $config['mot_de_passe'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Assure que la table existe, sans dépendre d'une visite préalable d'une
    // page de l'espace adhérents — même principe qu'infos-club.php.
    require_once __DIR__ . '/espace/inc/migration.php';
    appliquer_migrations($pdo);

    $referent = referent_visite();
    $ip       = anonymiser_ip(ip_visiteur());

    $pays  = null;
    $ville = null;
    if ($ip !== null) {
        // Réutilise la dernière géolocalisation connue pour cette même
        // adresse anonymisée plutôt que d'interroger le service tiers à
        // chaque visite : beaucoup plus rapide (la plupart des visiteurs
        // reviennent depuis le même réseau), et limite naturellement les
        // appels sortants en cas d'abus de ce point d'accès.
        $recent = $pdo->prepare(
            'SELECT pays, ville FROM visites WHERE ip = ? AND pays IS NOT NULL ORDER BY id DESC LIMIT 1'
        );
        $recent->execute([$ip]);
        $ligne = $recent->fetch();
        if ($ligne) {
            $pays  = $ligne['pays'];
            $ville = $ligne['ville'];
        } else {
            [$pays, $ville] = geolocaliser($ip);
        }
    }

    $pdo->prepare('INSERT INTO visites (page, referent, ip, pays, ville) VALUES (?, ?, ?, ?, ?)')
        ->execute([$page, $referent, $ip, $pays, $ville]);

    // Purge des visites de plus de 13 mois (durée usuelle recommandée par la
    // CNIL pour une mesure d'audience, voir confidentialite.html) — pas de
    // cron sur cet hébergement, donc déclenchée aléatoirement (1 requête sur
    // 200 environ) plutôt qu'à chaque visite, pour rester sans effet notable
    // sur le temps de réponse.
    if (random_int(1, 200) === 1) {
        $pdo->exec('DELETE FROM visites WHERE cree_le < NOW() - INTERVAL 13 MONTH');
    }
} catch (PDOException $e) {
    error_log('enregistrer-visite.php : ' . $e->getMessage());
}

http_response_code(204);
exit;

/* IP du visiteur : privilégie X-Forwarded-For (posé par un éventuel proxy/CDN
   devant le serveur — Hostinger fait transiter le site par son propre CDN,
   « hcdn »), avec repli sur REMOTE_ADDR si l'en-tête est absent ou invalide. */
function ip_visiteur(): string
{
    $entete = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($entete !== '') {
        $premiere = trim(explode(',', $entete)[0]);
        if (filter_var($premiere, FILTER_VALIDATE_IP) !== false) {
            return $premiere;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/* Met à zéro le dernier octet (IPv4) ou les 80 derniers bits (IPv6) — assez
   précis pour une géolocalisation pays/ville, jamais assez pour identifier
   un visiteur précis. Renvoie null si l'adresse est vide ou invalide. */
function anonymiser_ip(string $ip): ?string
{
    if ($ip === '') {
        return null;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        $parties = explode('.', $ip);
        if (count($parties) === 4) {
            $parties[3] = '0';
            return implode('.', $parties);
        }
        return null;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        $binaire = @inet_pton($ip);
        if ($binaire !== false && strlen($binaire) === 16) {
            $masque = str_repeat("\xff", 6) . str_repeat("\x00", 10);
            $anonyme = @inet_ntop($binaire & $masque);
            return $anonyme !== false ? $anonyme : null;
        }
    }
    return null;
}

/* Domaine à l'origine du clic (moteur de recherche, réseau social, autre
   site) — null pour une navigation directe, sans referrer, ou interne au
   site (une page du site vers une autre). */
function referent_visite(): ?string
{
    $entete = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($entete === '') {
        return null;
    }
    $hote = parse_url($entete, PHP_URL_HOST);
    if (!is_string($hote) || $hote === '') {
        return null;
    }
    $hote = strtolower($hote);
    $hote = (string) preg_replace('/^www\./', '', $hote);

    $nos_domaines = ['focalclub.fr', 'myfocal.online', 'steff44.github.io', 'localhost', '127.0.0.1'];
    if (in_array($hote, $nos_domaines, true)) {
        return null;
    }
    return substr($hote, 0, 190);
}

/* Pays/ville d'une adresse IP (déjà anonymisée) via le service gratuit
   ipapi.co — aucune clé requise pour ce volume de trafic. Renvoie
   [null, null] au moindre souci (service injoignable, quota dépassé,
   adresse non résolue) : la visite est comptée quand même. */
function geolocaliser(string $ip_anonyme): array
{
    $reponse = recuperer_url('https://ipapi.co/' . urlencode($ip_anonyme) . '/json/', 2);
    if ($reponse === false || $reponse === '') {
        return [null, null];
    }
    $donnees = json_decode($reponse, true);
    if (!is_array($donnees) || isset($donnees['error'])) {
        return [null, null];
    }
    $pays  = isset($donnees['country_name']) ? trim((string) $donnees['country_name']) : '';
    $ville = isset($donnees['city']) ? trim((string) $donnees['city']) : '';
    return [$pays !== '' ? substr($pays, 0, 80) : null, $ville !== '' ? substr($ville, 0, 120) : null];
}

function recuperer_url(string $url, int $delai_secondes): string|false
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $delai_secondes,
            CURLOPT_CONNECTTIMEOUT => $delai_secondes,
        ]);
        $brut = curl_exec($ch);
        curl_close($ch);
        return $brut === false ? false : $brut;
    }

    $contexte = stream_context_create(['http' => ['timeout' => $delai_secondes, 'ignore_errors' => true]]);
    return @file_get_contents($url, false, $contexte);
}
