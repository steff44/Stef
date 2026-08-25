<?php
/*
 * Blog du club (espace/blog.php, espace/blog-article.php) : catégories
 * (table `categories_blog`) et mise en forme du texte des articles.
 */

declare(strict_types=1);

const BLOG_ARTICLES_PAR_PAGE   = 8;
const BLOG_ARTICLES_RECENTS    = 5;
const BLOG_LONGUEUR_EXTRAIT    = 220;

/* Catégories dans l'ordre d'affichage : [id => nom]. Même principe que
   categories_galerie(inc/galerie_categories.php) : une seule liste à plat. */
function categories_blog(PDO $pdo): array
{
    static $categories = null;
    if ($categories !== null) {
        return $categories;
    }

    $categories = [];
    foreach ($pdo->query('SELECT id, nom FROM categories_blog ORDER BY ordre, id')->fetchAll() as $ligne) {
        $categories[(int) $ligne['id']] = $ligne['nom'];
    }

    return $categories;
}

/*
 * Convertit le texte brut saisi dans le formulaire (voir blog.php) en HTML :
 * échappé d'abord (protège contre un titre ou un contenu contenant `<`/`>`),
 * puis **texte** devient du gras — même convention minimale que les e-mails
 * de notification (corps_html() dans inc/mail.php) — une adresse
 * http(s):// devient un lien cliquable (nouvel onglet), et une ligne vide
 * sépare deux paragraphes. Pas d'éditeur riche : cohérent avec un site sans
 * build ni dépendance JavaScript externe.
 */
function texte_riche_html(string $texte): string
{
    $echappe = htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
    $gras    = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $echappe);
    $lien    = preg_replace_callback(
        '/https?:\/\/[^\s<]+/i',
        static function (array $correspondance): string {
            $url = rtrim($correspondance[0], '.,;:!?');
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
        },
        $gras
    );

    $paragraphes = preg_split('/\n{2,}/', trim((string) $lien));
    $html        = '';
    foreach ($paragraphes as $paragraphe) {
        $paragraphe = trim($paragraphe);
        if ($paragraphe === '') {
            continue;
        }
        $html .= '<p>' . nl2br($paragraphe) . '</p>';
    }

    return $html;
}

/*
 * Extrait automatique d'un contenu (texte brut, pas encore transformé en
 * HTML) : utilisé quand l'auteur n'a pas saisi d'extrait à la main. Coupe au
 * dernier espace avant la limite pour ne jamais trancher un mot en deux.
 */
function extrait_auto(string $contenu, int $longueur = BLOG_LONGUEUR_EXTRAIT): string
{
    // Un seul paragraphe suffit à donner un aperçu ; au-delà, la coupure à
    // $longueur caractères prend le relais.
    $premier_paragraphe = trim((string) preg_split('/\n{2,}/', trim($contenu))[0]);
    // L'extrait s'affiche en texte brut (voir blog.php), sans passer par
    // texte_riche_html() : retirer les ** de mise en gras évite qu'elles
    // n'apparaissent littéralement dans le résumé.
    $sans_gras = preg_replace('/\*\*(.+?)\*\*/s', '$1', $premier_paragraphe);
    $texte = preg_replace('/\s+/', ' ', (string) $sans_gras);

    if (mb_strlen($texte) <= $longueur) {
        return $texte;
    }

    $coupe = mb_substr($texte, 0, $longueur);
    $dernier_espace = mb_strrpos($coupe, ' ');
    if ($dernier_espace !== false) {
        $coupe = mb_substr($coupe, 0, $dernier_espace);
    }

    return rtrim($coupe) . '…';
}
