# Contexte projet — site du Focal Club Turballais

Ce fichier est chargé automatiquement au démarrage d'une session Claude Code.
Il résume l'état du projet pour repartir sans avoir à tout réexpliquer.

## Le projet

Site vitrine statique (HTML/CSS/JS, sans framework ni build) pour le **Focal
Club Turballais**, club photo associatif de La Turballe (44).

- **En ligne :** https://focalclub.fr — **adresse de référence du site**
  (voir « Déploiement » et « Pièges déjà rencontrés » plus bas pour
  l'historique de la panne du 01/09/2026 et son diagnostic). Espace
  adhérents sur https://focalclub.fr/espace/connexion.php.
- **`myfocal.online`** (et `www.myfocal.online`) est un **second site
  Hostinger indépendant**, choisi par l'utilisatrice comme **site de
  test** (01/09/2026) : une branche pas encore fusionnée peut y être
  déployée à la demande (`deploy-test.yml`, voir « Déploiement ») pour
  l'essayer en conditions réelles sans toucher au site en ligne. **Une
  fois la branche fusionnée sur `main`, en revanche, les deux sites sont
  remis au même niveau automatiquement** (choix explicite de
  l'utilisatrice, 01/09/2026, même jour — `deploy.yml` déploie désormais
  vers les deux) : `myfocal.online` n'est donc « en avance » sur
  `focalclub.fr` que le temps d'un test, jamais durablement.
- **Branche de référence :** `main` — c'est elle, et elle seule, qui met le
  site en ligne. Le travail se fait sur une branche `claude/**`, se relit sur
  la préversion (voir « Déploiement »), puis se fusionne sur `main`.

## Structure

```
public_html/          ← racine du site, déployée telle quelle
  index.html          ← accueil : hero photo, cartes, galerie, CTA
  galerie.html        ← galerie publique : vraies photos de la Galerie du Club
  nos-sorties.html    ← Nos Sorties : albums Google Drive, un album par sortie (voir plus bas)
  expo-2026.html      ← redirection vers nos-sorties.html (ne pas supprimer)
  evenements.html     ← redirection vers espace/agenda.php (ne pas supprimer)
  membres.html        ← redirection vers espace/le-club.php (ne pas supprimer)
  contact.html
  mentions-legales.html ← mentions légales (éditeur, hébergeur, droit d'auteur/droit à l'image)
  confidentialite.html  ← politique de confidentialité (RGPD)
  connexion.html      ← redirection vers espace/connexion.php (ne pas supprimer)
  infos-club.php      ← API publique en lecture seule (coordonnées du club, voir plus bas)
  infos-galerie-club.php ← API publique en lecture seule (photos de la Galerie du Club, voir plus bas)
  infos-albums.php    ← API publique en lecture seule (albums Google Drive par sortie, voir plus bas)
  infos-prochaine-sortie.php ← API publique en lecture seule (bandeau de l'accueil, voir plus bas)
  espace/             ← ESPACE ADHÉRENTS en PHP + MySQL (voir plus bas)
  css/style.css       ← tout le style, variables CSS en haut du fichier
  js/data.js          ← DONNÉES : adhérents + leurs photos
  js/main.js          ← rendu des galeries, lightbox, menu mobile
  images/
```

## Choix de design déjà actés

- Palette sombre bleu nuit (`#0f172a`) + dégradé rose → violet → bleu
  (`--accent-gradient`), repris de focalclub.fr.
- **Police : Comic Sans MS** partout (choix explicite de l'utilisateur, comme
  sur focalclub.fr). Pas de Google Fonts — police système avec repli.
  **Sur téléphone (≤ 760px), Comic Sans est remplacée par la police système**
  (`-apple-system, "Segoe UI", Roboto, ...` — choix explicite de
  l'utilisateur, 22/08/2026, Comic Sans en italique étant difficile à lire
  sur petit écran) : reste Comic Sans sur ordinateur. Un seul endroit à
  modifier, `--font-heading`/`--font-body` redéfinies dans un bloc
  `@media (max-width: 760px) { :root { ... } }` en haut de `style.css` —
  tout le reste du CSS lit déjà ces variables, rien d'autre à toucher.
- Ordre du menu (imposé par l'utilisateur, à ne pas réordonner) :
  Accueil, Galerie, **Nos Sorties**, **Blog**, Agenda, Le Club, Nous Contacter,
  Espace Adhérent. « Nos Sorties » (`nos-sorties.html`, les albums Google Drive
  groupées par adhérent — voir plus bas) a été ajouté le 24/08/2026 entre
  Galerie et Agenda ; « Blog » (`espace/blog.php`, voir plus bas) a été
  ajouté le 25/08/2026, **entre Nos Sorties et Agenda** — l'utilisateur avait
  demandé « entre Galerie et Agenda », mais cette page occupait déjà cette
  place depuis la veille : question posée explicitement à l'utilisateur, qui
  a choisi cette position plutôt que juste après Galerie. Ce sont les deux
  seuls changements de cet ordre depuis le début du projet.
  **Agenda et Espace Adhérent sont tous deux des menus déroulants**
  (`.nav-dropdown`, voir juste en dessous) ; Galerie, Nos Sorties, Blog, Le
  Club et Nous Contacter sont des liens simples. Le menu est écrit en dur
  dans chaque page statique (`index.html`, `galerie.html`, `nos-sorties.html`,
  `contact.html`) **et** dans `espace/inc/page.php` (`debut_page()`, avec un
  préfixe `../`) : y ajouter une entrée demande donc **cinq** modifications
  identiques, plus la liste « Liens rapides » du pied de page des pages
  statiques (absente de `page.php`, dont le pied de page est minimal).
  - Agenda → deux sous-pages, ajoutées le 18/08/2026 : « Agenda des
    sorties » (`espace/agenda.php`, le calendrier — page publique malgré son
    emplacement) et « Sorties à venir » (`espace/sorties-a-venir.php`, les
    listes, inscriptions, et le formulaire d'ajout pour un responsable).
    Cliquer sur une sortie dans le calendrier renvoie sur sa carte dans
    Sorties à venir (ancre `#sortie-{id}`). Une sortie peut avoir une photo
    (facultative), recadrée en carré 400×400 à l'envoi
    (`redimensionner_en_carre()` dans `inc/televersement.php`), publique via
    `telecharger.php?type=sortie`. Un responsable peut aussi **modifier** une
    sortie existante (22/08/2026) : bouton « Modifier » à côté de
    « Supprimer » sur chaque carte à venir, qui déplie (`<details
    class="sortie-modifier">`) le même formulaire que « Ajouter une sortie »,
    prérempli avec les valeurs actuelles. La photo n'est remplacée que si un
    nouveau fichier est envoyé ; sinon celle déjà en place est conservée
    (l'action `modifier`, dans `sorties-a-venir.php`, relit la photo actuelle
    avant d'écraser la ligne). Uniquement sur les sorties à venir, pas sur
    les sorties passées (qui n'ont aucune action).
    **Créer une sortie prévient les adhérents par e-mail, et propose un
    partage WhatsApp** (choix explicite de l'utilisateur, 27/08/2026) :
    dès qu'un responsable ou un éditeur ajoute une sortie (`action=creer`),
    un e-mail est envoyé à tous les adhérents `valide=1 actif=1` ayant une
    adresse renseignée (`envoyer_mail()`, un e-mail par adhérent, échoue
    silencieusement comme les autres notifications du site — voir
    `inc/mail.php`). Un envoi automatique dans le **groupe** WhatsApp du
    club n'est techniquement pas possible depuis cet hébergement : aucune
    API (officielle ou non) ne permet de poster dans un groupe WhatsApp
    existant sans risquer de faire bannir un vrai numéro, et l'exécuter
    exigerait un processus Node persistant, absent d'un hébergement
    mutualisé PHP. À la place, chaque carte « à venir » porte un bouton
    « Partager sur WhatsApp » (responsable/éditeur seulement, à côté de
    Modifier) : un lien `https://wa.me/?text=...` (« click-to-chat »,
    aucune clé ni compte à configurer) qui ouvre WhatsApp avec le message
    déjà rédigé (titre, date, lieu, précisions, lien vers la sortie) — il
    ne reste qu'à choisir le groupe Focal Club Turballais et cliquer
    Envoyer. `SITE_URL` (`inc/mail.php`, `https://myfocal.online`) bâtit le
    lien absolu vers la sortie, utilisé par les deux canaux.
    **Les adresses http(s):// tapées dans les « Précisions » d'une sortie
    sont cliquables** (choix explicite de l'utilisateur, 27/08/2026, capture
    d'écran à l'appui) : `texte_avec_liens_html()` (`inc/page.php`) reprend
    la même détection de lien que `texte_riche_html()` (`inc/blog.php` — même
    expression régulière, nouvel onglet) mais sans le gras `**...**` ni le
    découpage en paragraphes, pour un texte court affiché tel quel plutôt
    qu'un article long. `.sortie-description a` (`css/style.css`) rend le
    lien reconnaissable — même piège déjà rencontré et corrigé sur le blog
    (un `<a>` se fond sinon dans le texte, `a { color: inherit; }` étant la
    règle générale du site).
  - Blog → `espace/blog.php` (voir « Blog du Club » plus bas), ajouté le
    25/08/2026.
  - Le Club → `espace/le-club.php` (voir « Le Club » plus bas) : **réservé
    aux adhérents connectés depuis le 18/08/2026** — ce n'est plus la liste
    publique des adhérents. `membres.html` est désormais une redirection
    (même principe qu'`evenements.html`).
  - Nous Contacter → `contact.html`. Depuis le 22/08/2026, cette page porte
    aussi une section **« Inscription »** (choix explicite de l'utilisateur),
    entre le formulaire de contact et la FAQ : accroche + bouton
    « Créer mon compte » vers `espace/inscription.php`, le vrai formulaire.
    Pas de duplication du formulaire lui-même : `contact.html` reste une
    page statique, incapable de générer le jeton anti-CSRF qu'exige
    `espace/inscription.php` — seule une page PHP le peut. Reprend le style
    `.cta-section` déjà utilisé sur `index.html`. Le grand titre en haut de
    page tient sur deux lignes, « Nous contacter » puis « S'inscrire »
    (`<br>` volontaire dans le `<h1>`, même principe que le logo). Le lien
    « Nous Contacter » du menu principal reprend le même traitement
    (`.nav-deux-lignes`, choix explicite de l'utilisateur) — présent en dur
    dans `index.html`, `galerie.html`, `contact.html` et
    `espace/inc/page.php` (pas de rendu centralisé pour les pages
    statiques) ; seul le lien du menu change, pas les occurrences dans le
    pied de page ou le bouton « Nous Contacter » de l'accueil, qui restent
    sur une ligne. La section Inscription est placée juste sous le bandeau
    d'en-tête (avant le formulaire de contact). Cette page a connu plusieurs
    allers-retours de mise en page le 22 et le 23/08/2026 (choix explicite
    de l'utilisateur à chaque fois) ; **l'état final** (23/08/2026, second
    changement de la journée) est : le bandeau d'en-tête « Nous contacter
    S'inscrire » est une **bande pleine largeur** (`.cta-section
    cta-section--reduit`, le même dégradé que le CTA de `index.html`, moitié
    moins haut que d'habitude), titre sur **une seule ligne** (pas de `<br>`
    ni d'accroche « Contact » séparée, contrairement au lien du menu qui
    reste sur deux lignes, voir plus bas) ; la section Inscription juste en
    dessous est au contraire un **pavé** — une `.form-card` centrée, même
    style que la carte « Envoyez-nous un message » plus bas sur la page,
    hauteur réduite (`padding: 21px 32px` en ligne, plutôt que les 32px par
    défaut de `.form-card`). `.cta-section--reduit` (créée le 22/08/2026)
    s'applique donc aujourd'hui au bandeau du haut, pas à la section
    Inscription — ne pas réduire `.cta-section` elle-même, partagée avec
    `index.html`. `.gallery-header--reduit` (créée le 22/08/2026 pour cette
    même page, plus utilisée ici depuis ce changement) est réutilisée par
    `titre_page($reduit = true)` pour Agenda des sorties et Le Club — voir
    plus bas.
- **« Agenda » et « Espace Adhérent » sont des menus déroulants**
  (`.nav-dropdown` dans `css/style.css` + comportement dans `js/main.js` :
  clic pour ouvrir/fermer, clic extérieur, Échap, accordéon en dessous de
  760px — générique, fonctionne pour n'importe quel nombre de
  `.nav-dropdown` sur la page). Sur les pages statiques, « Espace Adhérent »
  contient « Connexion » et « S'inscrire » (toutes deux vers `espace/`) — le
  HTML statique ne connaît jamais l'état de connexion. Dans
  `espace/inc/page.php` (`debut_page()`), ce même menu déroulant remplace
  l'ancienne barre d'onglets sous l'en-tête : non connecté, il propose
  Connexion et S'inscrire ; connecté, **le libellé devient
  « {pseudo} connecté »**. Depuis le 18/08/2026, ce libellé n'est plus le
  bouton qui ouvrit/ferme le menu : c'est un `<a class="nav-dropdown-label">`
  qui renvoie **directement** vers `index.php` (Tableau de bord) au clic ; la
  flèche à côté (`.nav-dropdown-trigger.nav-dropdown-trigger--icone`, seule à
  porter `aria-expanded`) reste le bouton qui ouvre/ferme le sous-menu.
  Celui-ci affiche « Bonjour {nom} » puis, juste en dessous, **« Se
  déconnecter »** (choix explicite de l'utilisateur : la déconnexion doit
  être la première action visible, pas la dernière), puis Tableau de bord,
  Galerie privée, Galerie du Club, Documents, Agenda des sorties, Sorties à venir, Annuaire,
  Le Club (+ Adhérents pour un responsable ou un éditeur, Réglages du site
  pour un responsable seulement). Les classes
  `.espace-barre`/`.espace-onglets` ont été supprimées avec cette bascule
  (17/08/2026) — ne pas les réintroduire.
- **`espace/connexion.php` et `espace/index.php` sont deux pages
  strictement séparées** (choix explicite de l'utilisateur, 18/08/2026,
  après une première tentative — testée puis abandonnée le même jour — qui
  faisait cohabiter un encart « Vous êtes déjà connecté » sur la page de
  connexion) : `connexion.php` ne sert **que** la connexion/inscription,
  jamais le tableau de bord. Un visiteur déjà connecté qui y arrive est
  renvoyé **directement et silencieusement** vers `index.php`, sans étape
  intermédiaire ni bouton à cliquer. Après une connexion réussie, direction
  unique également : toujours `index.php`, quelle que soit la page qui a
  déclenché la demande de connexion (`exige_connexion()` ne mémorise plus de
  page de retour). La page porte une flèche **« ← Page précédente »**
  (`.lien-retour`, en haut de la carte, visible seulement quand le
  formulaire s'affiche puisqu'un visiteur connecté ne voit jamais cette
  page), qui utilise `history.back()` avec un repli statique vers
  `../index.html` si l'historique est vide (JS désactivé, arrivée directe).
  `afficher_message()` y affiche aussi « Adhérents seulement. » quand on est
  renvoyé ici depuis `le-club.php` sans être connecté.
- **Le titre du logo est sur deux lignes** depuis le 18/08/2026 : « Focal
  Club » puis « Turballais » centré dessous (`<span class="logo-text">Focal
  Club<br>Turballais</span>`, à côté de `.logo-mark`). La coupure est un
  `<br>` volontaire, pas un retour à la ligne automatique — sans ça, le point
  de coupure dépendrait de la largeur disponible.
- **Le logo utilise la vraie image fournie par l'utilisateur**, déposée dans
  `images/logo.png` (600×600, fond blanc, appareil photo + « FOCAL CLUB
  TURBALLAIS » en bleu, texte compris dans l'image). Plusieurs étapes le
  23/08/2026 : un premier essai a recréé l'icône en SVG (`.logo-mark`)
  faute d'accès au fichier — coller une image dans la conversation ne
  dépose rien d'exploitable dans ce sandbox (vérifié : `/mnt/attach`, le
  dossier réservé aux pièces jointes, restait vide). L'utilisateur a
  ensuite déposé le fichier directement dans le dépôt GitHub (Upload
  files, avec un contournement par URL directe
  `github.com/<repo>/upload/<branche>/<dossier>` — le bouton « Add file »
  n'apparaissait pas dans son interface), ce qui a permis de récupérer le
  vrai fichier. Un premier essai l'a utilisé tel quel (icône + texte en un
  seul `<img>`), mais l'utilisateur a ensuite redemandé le texte sur deux
  lignes « comme avant » et un logo un peu plus grand : `images/logo.png`
  reste le fichier source, mais **`images/logo-icone.png`** (recadrée avec
  Pillow — bbox de l'appareil photo repérée par détection des pixels non
  blancs, marge de 20px, complétée en carré sur fond blanc — vérifiée par
  rendu Chromium local avant publication) n'en garde que l'appareil photo,
  sans le texte. Le texte redevient du HTML (`.logo-text`, « Focal
  Club<br>Turballais », comme avant le 23/08/2026) — plus net et plus
  facile à agrandir qu'un texte figé dans une image. `.logo-mark`
  (l'`<img>` de l'icône recadrée, 72px, carrée avec coins arrondis) et
  `.logo-text` sont redevenus deux éléments distincts, comme à l'origine,
  simplement avec la vraie icône à la place du dessin SVG recréé. Texte
  du logo (`.logo`) à `1.85rem`. S'applique partout où `.logo` est utilisé
  (en-tête et pied de page de chaque page).
- **Le favicon (icône d'onglet) reprend aussi `images/logo-icone.png`**
  depuis le 31/08/2026 (choix explicite de l'utilisateur) — remplace
  l'ancien favicon provisoire, une simple pastille violette en SVG intégré
  (`data:image/svg+xml,...`, posée faute d'image réelle avant que le vrai
  logo ne soit récupéré). `<link rel="icon" type="image/png"
  href="images/logo-icone.png">` (`../images/logo-icone.png` depuis
  `espace/inc/page.php`), dans les 11 endroits où la balise favicon
  apparaît — une par page statique, plus `page.php` pour tout l'espace
  adhérents. Compatible avec la CSP posée le même jour (voir « Sécurité »
  plus bas) sans rien y changer : le favicon est désormais un fichier de
  même origine, que `img-src 'self'` couvre déjà. **Porte aussi un suffixe
  `?v=` de cache-busting**, comme `style.css` — signalé par l'utilisatrice
  le jour même (« j'ai l'impression que la pastille violette est au-dessus
  du logo ») : contrairement à l'ancienne pastille en `data:` URI, jamais
  mise en cache séparément puisque intégrée dans le HTML lui-même,
  `logo-icone.png` est un vrai fichier soumis au même cache Hostinger
  d'une semaine que les autres fichiers statiques (voir « Hostinger sert le
  CSS avec... » plus bas) — sans ce suffixe, les navigateurs ayant déjà
  visité le site gardaient l'ancienne icône en cache jusqu'à expiration.
- **Un vrai `favicon.ico` à la racine du site**, ajouté le 31/08/2026 (même
  jour), en plus de la balise `<link rel="icon">` — le cache-busting seul
  n'a pas suffi : l'utilisatrice a testé sur trois navigateurs différents
  (Chrome, Firefox, Chromium), à chaque fois en navigation privée (donc
  sans aucun cache), et n'a toujours vu aucune icône. Le site n'avait
  jamais eu de `favicon.ico` — beaucoup de navigateurs interrogent cette
  adresse par convention, en parallèle de la balise `<link>`, et son
  absence pure et simple (plutôt qu'une balise mal réglée) explique mieux
  qu'aucune icône ne s'affiche, dans aucun navigateur, même sans cache.
  Généré avec Pillow à partir de `images/logo-icone.png` (déjà utilisée
  pour `.logo-mark` et le favicon PNG) : quatre résolutions incluses
  (16×16, 32×32, 48×48, 64×64) dans un seul fichier `.ico` multi-tailles,
  format standard reconnu par tous les navigateurs. Vérifié hors ligne :
  fichier ICO valide (4 icônes intégrées, contenu non vide aux extrema
  RGBA), servi en 200 avec `Content-Type: image/vnd.microsoft.icon`.

**Toujours aucune icône après ce dernier essai** — l'utilisatrice a rouvert
`focalclub.fr` (toujours sans cache) et confirmé n'en voir aucune,
signalant que le déploiement du `favicon.ico` avait pourtant réussi
(vérifié dans les journaux du workflow : le fichier apparaît bien dans la
liste `rsync`, transféré comme les autres). Suspect retenu : le type MIME
du fichier. `X-Content-Type-Options: nosniff`, ajouté la veille (voir plus
bas), empêche un navigateur de deviner le type d'une ressource — si
l'hébergement sert `.ico`/`.png` avec un `Content-Type` incorrect ou
absent (piège classique sur certains hébergements mutualisés, plus fréquent
pour `.ico` que pour les types les plus courants), le navigateur refuse
alors de l'utiliser comme icône tout en continuant de l'afficher
normalement en navigation directe — chemin de rendu différent, ce qui
correspond exactement aux symptômes observés (le lien direct fonctionnait,
jamais l'icône). Avant `nosniff`, l'ancien favicon en `data:` URI
n'avait jamais ce problème, puisqu'il ne dépend d'aucune négociation de
type avec le serveur. `public_html/.htaccess` porte désormais
`AddType image/vnd.microsoft.icon .ico` et `AddType image/png .png`
(bloc `<IfModule mod_mime.c>`, avant les en-têtes de sécurité) pour forcer
le bon type quel que soit le réglage par défaut de l'hébergeur. Non
vérifiable hors ligne (comme les `.htaccess` en général) — à confirmer en
ligne après déploiement.

**Cause réelle trouvée, sans rapport avec `nosniff`** — l'utilisatrice a
inspecté l'onglet Réseau des outils de développement (à ma demande, faute
de pouvoir inspecter `focalclub.fr` moi-même) : la requête vers
`images/logo-icone.png` répondait `200 OK`, servie depuis le **CDN de
Hostinger** (`Server: hcdn`, `X-Hcdn-Cache-Status: HIT`), mais avec
`Content-Type: image/webp` — pas `image/png`. Le CDN de Hostinger
convertit automatiquement les images PNG/JPEG en WebP à la volée pour
économiser de la bande passante, en gardant la même URL. Un `<img>`
classique (le logo dans l'en-tête, `.logo-mark`) s'en accommode sans
problème — le navigateur affiche l'image quel que soit le format réel des
octets reçus. Mais un favicon déclaré `type="image/png"` qui reçoit du
WebP à la place se fait rejeter par le navigateur (type déclaré et type
réellement reçu ne correspondent pas), tout en continuant de s'afficher
normalement en navigation directe — exactement les symptômes observés.
`nosniff` n'était donc pas seul en cause : la conversion silencieuse par
le CDN en était la vraie source, `nosniff` ne faisant qu'empêcher le
navigateur de deviner malgré tout le bon type. **Corrigé en pointant le
favicon vers `favicon.ico` plutôt que vers `images/logo-icone.png`** dans
les 11 mêmes endroits — un fichier `.ico` n'est pas un format candidat à
la conversion WebP automatique des CDN (ce n'est pas un format photo), il
échappe donc à ce problème par construction.

**Toujours aucune icône après ce correctif non plus** — l'utilisatrice a
de nouveau répondu « non » après ce déploiement. Cette fois, plutôt que de
continuer à déduire la cause depuis des captures d'écran fournies par
l'utilisatrice, un workflow GitHub Actions temporaire
(`diag-favicon.yml`, `workflow_dispatch` uniquement, supprimé une fois la
cause trouvée — même principe que les points d'accès de diagnostic
temporaires déjà utilisés pour Google Drive) a interrogé directement les
deux domaines depuis l'extérieur (le sandbox ne peut atteindre ni l'un ni
l'autre). Résultat sans appel :
- `myfocal.online/favicon.ico` → `200 OK`, `Content-Type:
  image/vnd.microsoft.icon`, `Last-Modified` daté du dernier déploiement
  (31/08/2026 17:17) — **le correctif fonctionne parfaitement sur ce
  domaine**.
- `focalclub.fr/favicon.ico` → **`404`**, avec un `Last-Modified` d'avril
  2025 — bien avant tout ce chantier. `focalclub.fr/index.html` sert
  encore l'ancienne pastille violette en SVG et `style.css?v=202608271516`
  (une version d'avant le 31/08) ; aucun des en-têtes de sécurité HTTP
  ajoutés le 31/08 (CSP, HSTS, `X-Content-Type-Options`…) n'apparaît dans
  ses réponses, alors qu'ils sont bien présents sur `myfocal.online`.

**`focalclub.fr` ne reçoit donc pas les déploiements automatiques** — il
sert une copie figée d'avant le 30/08/2026, malgré la bascule faite dans
hPanel ce jour-là. Ce n'est pas un problème de cache CDN (les requêtes de
diagnostic étaient cache-bustées) : l'origine elle-même diffère. Corrige
donc une affirmation faite plus haut dans ce fichier et dans « Pièges déjà
rencontrés » (« les deux domaines pointent vers le même `~/public_html/`
») — **à vérifier par l'utilisatrice dans hPanel** : le document root de
`focalclub.fr` doit être configuré pour pointer vers le **même**
`~/public_html/` que `myfocal.online`, pas vers une copie séparée figée au
moment de la bascule. En attendant cette vérification, `myfocal.online`
reste le domaine fiable pour vérifier qu'un déploiement a bien pris effet.
- **Les libellés du menu principal sont alignés sur une même ligne
  médiane** depuis le 23/08/2026 (choix explicite de l'utilisateur) :
  `.nav-links` porte désormais `align-items: center`. Avant ce changement,
  les liens à une seule ligne (Accueil, Galerie, Le Club…) restaient plaqués
  en haut de la barre (comportement par défaut d'un conteneur flex) tandis
  que les menus déroulants (`.nav-dropdown`, déjà centrés sur eux-mêmes via
  leur propre `align-items: center`) semblaient plus bas — d'où un menu à
  l'air désaligné, surtout à côté du lien « Nous Contacter / S'inscrire »
  sur deux lignes. En dessous de 760px, où le menu devient une colonne
  dépliante, `align-items: stretch` est réappliqué explicitement (sans quoi
  les liens rétréciraient à la largeur de leur texte au lieu de rester
  cliquables sur toute la largeur).
- **Bandeaux de titre réduits** (choix explicite de l'utilisateur,
  23/08/2026, `.gallery-hero` réduite trois fois au total, la dernière le
  25/08/2026) : celui de `galerie.html` (`.gallery-hero`, propre à cette
  page — titre, sous-titre et les pastilles de filtre par thème, qu'elle
  regroupe sous le même bandeau) part de 64px/40px, réduit d'un tiers le
  23/08/2026 (→ 43px/27px), d'un tiers à nouveau le 25/08/2026
  (→ 29px/18px), puis de moitié le même jour, dans un second passage
  (→ 15px/9px, `margin-top` des pastilles 32px → 21px → 10px pour suivre
  la même proportion à chaque étape). Celui d'Agenda des sorties, du Club,
  du Blog du Club, de Sorties à venir et du tableau de bord (`titre_page()`
  dans `espace/inc/page.php`, partagé par toutes les pages de l'espace
  adhérents) est réduit de moitié sur ces cinq pages, via un 4ᵉ paramètre
  `$reduit` qui ajoute `.gallery-header--reduit` (`espace/blog.php`,
  `sorties-a-venir.php` et `index.php` le passent à `true` depuis le
  25/08/2026 ; `blog-article.php` et `agenda.php`, non cités par
  l'utilisateur, gardent le bandeau standard) — les autres pages de
  l'espace (Documents, Galerie privée, Annuaire, Réglages du site…)
  gardent aussi le bandeau standard. Sur l'accueil, `.cta-section`
  (« Prêt à capturer l'ordinaire ? ») porte désormais la même classe
  `.cta-section--reduit` déjà utilisée sur `contact.html` (choix explicite
  de l'utilisateur, 25/08/2026) — moitié moins haute (88px → 44px de
  padding), sans nouvelle règle CSS.
- **Le rectangle de « Une communauté de passionnés » (`.about-visual`,
  accueil) affiche une vraie photo** depuis le 25/08/2026 (choix explicite
  de l'utilisateur) — auparavant un dégradé de repli décoratif, faute de
  photo fournie. Même principe que `.hero-full` : un `background: url(...)
  center / cover no-repeat` posé sur le bloc en CSS, pas un `<img>` — rien
  à changer dans le HTML (`<div class="about-visual" aria-hidden="true">`
  reste vide). Fichier `images/communaute.jpg` : l'utilisateur a d'abord
  tenté de coller la photo directement dans la conversation, ce qui ne
  dépose rien d'exploitable dans ce sandbox (même piège que pour le logo,
  23/08/2026 — voir plus bas) ; elle l'a donc déposée sur GitHub
  (`github.com/<repo>/upload/main/public_html/images`), qui a conservé le
  nom de fichier d'origine de l'appareil photo (avec espaces) — renommé en
  `communaute.jpg` par un `git mv` avant intégration, plus propre et
  cohérent avec les autres noms d'images du site (`logo.png`,
  `accueil-pleine-largeur.jpg`…).
- **`js/main.js` et `js/data.js` sont versionnés comme `style.css`** (voir
  plus bas « Hostinger sert... ») : `?v=AAAAMMJJHHmm` en dur sur les pages
  statiques, `lien_js()` (calqué sur `lien_css()`) sur les pages PHP. Sans
  ça, un navigateur qui a déjà visité le site garde l'ancien script sept
  jours et ne voit aucun changement de comportement (menu déroulant resté
  inerte au clic, constaté le 17/08/2026 — c'est ce qui a révélé l'oubli).
- Hero plein écran : photo d'un photographe en fond (`images/hero-photographer.jpg`,
  Unsplash, la même que focalclub.fr) sous un voile dégradé, titre en overlay.
- **Œil pour afficher/masquer un mot de passe** (choix explicite de
  l'utilisateur, 19/08/2026) : posé automatiquement par `js/main.js` sur
  tout `input[type="password"]` de la page (connexion, inscription,
  installation, changement de mot de passe dans l'annuaire) — générique,
  jamais besoin de le poser à la main sur un nouveau champ. Le script
  enveloppe le champ dans `.champ-mot-de-passe` et y ajoute un bouton
  `.bouton-oeil` qui bascule `type="password"`/`type="text"`.
- **Boutons flottants « page précédente » / « section précédente » / « retour
  en haut » / « aller en bas »** (choix explicite de l'utilisateur,
  20/08/2026, complété le 23/08/2026 puis le 25/08/2026) : posés
  automatiquement par `js/main.js` (`.retour-nav`, bas-droite de l'écran)
  sur toute page comptant au moins une `<section>` — générique, s'applique
  donc à toute page publique ou de l'espace adhérents (y compris les pages
  réservées aux responsables) sans rien ajouter à la main, y compris pour
  une page future. « Page précédente » (`history.back()`, même principe que
  `.lien-retour` sur `connexion.php`/`inscription.php` mais flottant et
  générique à toute page), « retour en haut » et « aller en bas »
  (`window.scrollTo({ top: document.body.scrollHeight })`, ajouté le
  25/08/2026 comme pendant de « retour en haut ») s'affichent dès qu'il y a
  au moins une section ; « section précédente » ne s'affiche qu'à partir de
  deux `<section>` (rien à survoler avec une seule) — seuil qui, avant le
  23/08/2026, empêchait aussi « retour en haut » d'apparaître sur une page
  à une seule section mais longue, comme `espace/documents.php`. Le bouton
  « section précédente » remonte au début de la `<section>` précédente
  (pas seulement en haut de la section actuelle) ; « retour en haut »/
  « aller en bas » vont toujours tout en haut/tout en bas de la page,
  quelle que soit la section affichée. **Tous sont toujours visibles dès le
  chargement de la page** (choix explicite de l'utilisateur, 23/08/2026,
  second changement de la journée à leur sujet — ils n'apparaissaient
  auparavant qu'après un défilement d'au moins 60 % de la hauteur de
  l'écran, ce qui les rendait difficiles à trouver). Ordre dans le HTML —
  page précédente, section précédente (si présente), retour en haut, aller
  en bas — donc, visuellement (`.retour-nav` en colonne flex), le bouton
  « aller en bas » est le dernier posé, le plus proche du coin de l'écran,
  juste sous « retour en haut ».
- **Pied de page réduit de moitié, « Liens rapides » sur deux colonnes**
  (choix explicite de l'utilisateur, 25/08/2026, sur toutes les pages) :
  `.site-footer` (padding 48px/32px → 24px/16px) et `.footer-bottom`
  (`margin-top`/`padding-top` 40px/24px → 20px/12px) vivent dans le CSS
  partagé, donc ce changement s'applique d'un coup à toutes les pages —
  rien à modifier dans chaque HTML de pied de page, dupliqué mais identique
  d'une page à l'autre. La liste « Liens rapides » (8 liens, empilée en une
  seule colonne, nettement plus haute que les colonnes Contact/logo à côté)
  passe en deux colonnes via `columns: 2` sur le `<ul>` (pas une grille à
  nombre de lignes fixé en dur) — se répartit automatiquement même si un
  lien s'ajoute plus tard ; `break-inside: avoid` sur chaque `<li>`
  l'empêche de se couper au milieu entre les deux colonnes.

## Modifier le contenu

- **Photos :** il n'y a plus de photos de démonstration depuis le 20/08/2026
  (choix explicite de l'utilisateur — `CLUB_DATA.membres` est un tableau vide
  dans `public_html/js/data.js`, à ne pas repeupler avec des données
  fictives). Les vraies photos viennent des adhérents, via la Galerie du Club
  (`espace/galerie-club.php`) — voir plus bas. `CLUB_DATA.themes` reste la
  liste des filtres affichés sur la page Galerie, même sans aucune photo dans
  une catégorie pour l'instant.
- **Agenda :** une seule page, `espace/agenda.php` — voir plus bas.

## Espace adhérents (`public_html/espace/`)

Vraie authentification en **PHP + MySQL** sur Hostinger (choix explicite de
l'utilisateur). Quatre rubriques une fois connecté : galerie privée, documents,
agenda des sorties avec inscriptions, annuaire. Trois rôles : adhérent,
**éditeur** (`editeur = 1`, nouveau rôle du 23/08/2026 — voir plus bas) et
responsable (`administrateur = 1`).

**Le rôle Éditeur** (choix explicite de l'utilisateur, 23/08/2026) a
exactement les mêmes droits qu'un responsable sur trois pages — gérer les
comptes (`adherents.php`, y compris nommer/retirer un rôle et supprimer un
compte), déposer/supprimer des documents (`documents.php`) et gérer l'agenda
(créer/modifier/supprimer une sortie depuis `sorties-a-venir.php`) — mais
**pas d'accès aux réglages du site** (`parametres.php` reste réservé à
`exige_administrateur()`, jamais à `exige_gestionnaire()`) ni aux privilèges
de modération des galeries (voir toutes les photos privées, supprimer la
photo d'un autre adhérent), non demandés pour ce rôle. `est_gestionnaire()`
(`inc/auth.php`) renvoie vrai pour un responsable ou un éditeur, et
`exige_gestionnaire()` protège les pages/actions partagées par les deux
rôles — même schéma que `est_administrateur()`/`exige_administrateur()`,
restés inchangés et toujours réservés au seul responsable. Le rôle se coche
à la création d'un compte (case « Éditeur », `adherents.php`) ou se
bascule ensuite via les cases à cocher carrées (voir plus bas).

**L'Agenda est unique et public** (`espace/agenda.php`), choix explicite de
l'utilisateur (17/08/2026). Il vivait au départ en double : une page publique
démonstrative (`evenements.html`, alimentée par `js/data.js`) et la vraie
page privée connectée à la table `sorties`. Piège rencontré avec ce
doublon : une sortie ajoutée dans le vrai agenda semblait « ne pas
apparaître » (on regardait la démo), et changer de page donnait l'impression
d'être déconnecté (la page démo ne sait jamais afficher « {pseudo}
connecté »). Le doublon a été supprimé : `evenements.html` est désormais une
redirection vers `espace/agenda.php` (même principe que `connexion.html`),
et cette page n'exige plus de connexion pour être **consultée** —
`exige_connexion()` n'est appelée qu'au moment de s'inscrire/se désinscrire
à une sortie (`exige_gestionnaire()` reste nécessaire pour en créer, modifier
ou supprimer une, y compris le formulaire « Ajouter une sortie » lui-même,
invisible si on n'est ni responsable ni éditeur — rôle éditeur ajouté le
23/08/2026). Un visiteur non connecté voit les
sorties et qui y participe, avec un lien « Se connecter pour participer » à
la place du bouton d'inscription. Ne pas réintroduire de calendrier public
séparé.

`espace/agenda.php` affiche aussi un **calendrier**, en vue **mois, semaine
ou année** (choix explicite de l'utilisateur, 22/08/2026 — la vue semaine
manquait, puis l'utilisateur a signalé avoir aussi oublié la vue année dans
la foulée du même message), connecté aux mêmes lignes de la table `sorties`
que les listes juste en dessous (aucune donnée séparée) — trois onglets
`.agenda-cal-onglet` en haut du calendrier, navigation par
`?vue=mois&mois=AAAA-MM`, `?vue=semaine&semaine=AAAA-MM-JJ` (le lundi de la
semaine affichée — un paramètre pointant sur un autre jour est ramené à son
lundi) ou `?vue=annee&annee=AAAA`, en rechargement de page classique, sans
JavaScript. Basculer d'un onglet à l'autre préserve le contexte plutôt que
de revenir à aujourd'hui (une date « ancre » commune aux trois vues) : depuis
la vue mois, « Semaine » ouvre la semaine du 1er du mois affiché et
« Année » l'année de ce mois ; depuis la vue semaine, « Mois » ouvre le mois
du lundi affiché ; depuis la vue année, « Mois »/« Semaine » ouvrent
janvier de cette année. La vue année affiche douze mini-mois compacts
(`.agenda-cal-mini-mois`, grille `grille_mois()` réutilisée depuis
`inc/agenda.php` pour la vue mois comme pour chaque mini-mois) : un point
sous un jour signale au moins un événement ce jour-là (sans distinction de
catégorie, faute de place), le nom du mois est un lien direct vers sa vue
mois. Les **vacances scolaires** (zone B, académie de Nantes, dates
officielles 2026-2027 — vérifiées en ligne, pas inventées) sont affichées en
fond teinté sur les jours concernés (classe `.agenda-cal-jour.vacances` en
vue mois/semaine, `.agenda-cal-mini-jour.vacances` en vue année) et
récapitulées dans un bandeau au-dessus du calendrier pour toute vacance qui
chevauche la période affichée (mois, semaine ou année entière) —
`VACANCES_SCOLAIRES`, `vacances_du_jour()` et `vacances_chevauchant()` dans
`inc/agenda.php`, à côté de `CATEGORIES_SORTIES` ; à compléter à la même
source lors d'une prochaine année scolaire. Chaque sortie a une
**catégorie** (colonne
`categorie` sur `sorties`, ajoutée via `COLONNES_SORTIES_ATTENDUES` dans
`migration.php`) parmi celles listées dans `CATEGORIES_SORTIES` en haut de
`agenda.php` — actuellement Sortie photo / Cours / Réunion, choisie à la
création par un responsable, affichée en pastille colorée dans le
calendrier et en badge sur chaque carte. Ajouter une catégorie se fait en
un seul endroit : la constante `CATEGORIES_SORTIES` (le mapping couleur
`classe_categorie()` a un cas par défaut, donc une catégorie oubliée dans
ce mapping retombe simplement sur le style « sortie » plutôt que de
planter).

**La réunion hebdomadaire du club est semée automatiquement dans
`sorties`** (choix explicite de l'utilisateur, 22/08/2026) : une sortie
catégorie « Réunion » par jeudi de 20h30 à 23h00 (l'heure de fin va dans
`description`, faute de colonne dédiée), au Foyer des Vignes, du 10/09/2026
au 30/06/2027, sauf les jeudis qui tombent pendant une vacance scolaire
(`VACANCES_SCOLAIRES`). Semée une seule fois par `appliquer_migrations()`
(constantes `REUNION_HEBDOMADAIRE_*`, `inc/migration.php`) si aucune sortie
« Réunion hebdomadaire » n'existe déjà — jamais réintroduite si un
responsable supprime ensuite tout ou partie de la série, même principe que
les autres semis `PAR_DEFAUT` de ce fichier. Chaque séance reste ensuite une
sortie ordinaire, modifiable ou supprimable une par une depuis
`sorties-a-venir.php` comme n'importe quelle autre.

**« Galerie » existe en trois versions**, sans confondre les deux publiques
avec la privée : `galerie.html` (page publique, ouverte à tous) d'un côté,
`galerie.php` (**Galerie privée**, table `photos_privees`) de l'autre —
chaque adhérent n'y voit **que ses propres photos** depuis le 21/08/2026
(choix explicite de l'utilisateur, revient sur un premier comportement où
tout adhérent connecté voyait les photos de tout le monde) ; un responsable
continue de tout voir, pour la modération, même logique que la suppression
(déjà réservée à l'auteur ou à un responsable). La restriction porte aussi
sur `telecharger.php?type=photo` lui-même (vérifie `depose_par`), pas
seulement sur la liste affichée — sans quoi un identifiant deviné dans
l'URL aurait donné accès au fichier d'un autre adhérent malgré tout. Et
depuis le 20/08/2026, `galerie-club.php`
(**Galerie du Club**, choix explicite de l'utilisateur) : un adhérent y
dépose une photo, la classe dans une catégorie (portrait, paysage…), et
cette photo devient **publique**, reprise automatiquement sur `galerie.html`
(voir plus bas). Depuis le 21/08/2026, `galerie.php` a les mêmes
possibilités que `galerie-club.php` (catégorie, nom affiché) — voir plus
bas, dans le paragraphe sur `galerie-club.php`, pour le détail partagé par
les deux. Le lien « Galerie » du menu principal pointe **toujours**
vers `galerie.html`, connecté ou non (choix explicite de l'utilisateur,
19/08/2026, qui revient sur un choix inverse du 17/08/2026) : la galerie
privée et la Galerie du Club ne s'ouvrent que depuis « Le Club »
(`espace/le-club.php`) ou le menu déroulant « {pseudo} connecté »
(`$onglets` de `espace/inc/page.php`). Avant ce changement, cliquer sur
« Galerie » depuis une page de l'espace adhérents ouvrait la galerie privée
— piège reconnu par l'utilisateur comme non voulu, à ne pas réintroduire.

**Le tableau de bord (`espace/index.php`) n'affiche plus de carte
Annuaire** depuis le 21/08/2026 (choix explicite de l'utilisateur) : la
carte Annuaire (`.feature-card`) a été remplacée par une carte **Galerie du
Club** (nombre de photos partagées, lien vers `galerie-club.php`) —
changement volontairement limité à cette page : le lien Annuaire reste
présent et fonctionnel dans le menu déroulant « {pseudo} connecté »
(`$onglets` de `espace/inc/page.php`) et sur `annuaire.php` lui-même, ne
pas les retirer sans qu'on le redemande explicitement.

**« Le Club » (`espace/le-club.php`)** est réservé aux adhérents connectés
depuis le 18/08/2026 (choix explicite de l'utilisateur) : `exige_connexion()`
protège la page, avec un message dédié — « Adhérents seulement. » posé par
`definir_message()` avant le renvoi vers `connexion.php`, plutôt que le
silence habituel de `exige_connexion()` — pour qu'un visiteur non connecté
comprenne pourquoi il atterrit sur la page de connexion. Trois cartes
(`.cards-grid`/`.feature-card`, même présentation que le tableau de bord) :
**Documents du Club** → `documents.php`, **Galerie Privée** → `galerie.php`
et **Galerie du Club** → `galerie-club.php` (ajoutée le 20/08/2026).
L'ancienne liste publique des adhérents qui vivait ici (`membres.html`) a
été entièrement effacée.

**`galerie-club.php` : les adhérents déposent des photos qui deviennent
publiques**, classées par catégorie (choix explicite de l'utilisateur,
20/08/2026). Contrairement à `galerie.php` (Galerie privée, jamais visible
hors connexion), ces photos sont reprises sur la page publique
`galerie.html`. Catégories à plat (pas de rubriques, contrairement aux
documents) dans la table `categories_galerie`, **partagée avec `galerie.php`
depuis le 21/08/2026** (choix explicite de l'utilisateur — mêmes catégories
pour les deux galeries, une seule liste à gérer) — gérées par un responsable
depuis `parametres.php` (`ajouter_categorie_galerie` / `renommer_...` /
`supprimer_...`, refusé si l'une ou l'autre galerie contient encore des
photos dans cette catégorie) — semées une seule fois avec
`CATEGORIES_GALERIE_PAR_DEFAUT` (`inc/migration.php`, reprend les catégories
de la rubrique « Thèmes photographiques » des documents — sauf « Macro »,
volontairement écrit « Macro / Proxi » pour correspondre exactement au thème
déjà utilisé dans `CLUB_DATA.themes`, `js/main.js` ajoutant automatiquement
aux filtres toute catégorie de photo qui ne s'y trouve pas déjà : les deux
libellés faisaient double emploi sur la page Galerie publique jusqu'au
21/08/2026, corrigé par un renommage ponctuel — `UPDATE ... WHERE nom =
'Macro'` — dans `appliquer_migrations()`, qui ne perd aucune photo déjà
classée puisque seul le nom change, jamais l'identifiant).

**Deux cadres d'avertissement au-dessus de « Ajouter une photo »**, choix
explicite de l'utilisateur, 01/09/2026 : le premier rappelle que les
photos doivent être au format JPEG et ne pas dépasser
`taille_lisible(TAILLE_MAX_PHOTO_ADHERENT)` (valeur dynamique, jamais
« 1000 Ko » écrit en dur, pour rester juste si la limite change) ; le
second renvoie vers trois fiches d'aide au redimensionnement —
Fiche_Export_darktable_1000Ko, Fiche_Export_Lightroom_1000Ko et
Fiche_Export_XnConvert — à déposer par un responsable dans les Documents
du Club. Nouvelle classe CSS `.alerte-avertissement` (fond ambré, même
famille que `.alerte-succes`/`.alerte-erreur` déjà utilisées pour les
messages de `afficher_message()`), avec un lien en `--accent-3` souligné
— même correctif que `.blog-contenu a`/`.sortie-description a` pour
qu'un lien à l'intérieur ne se fonde pas dans le texte coloré. L'ordre
demandé (limite de taille en premier, fiches d'aide ensuite) est
respecté dans le HTML.

**Chacune des trois fiches a son propre lien** (choix explicite de
l'utilisateur, 01/09/2026, même jour, en remplacement du lien unique
« voir les fichiers » du premier essai) — un problème concret s'est posé
pour les construire : `telecharger.php?type=document&id=…` exige
l'identifiant en base du document, que ce cadre ne peut pas connaître à
l'avance (les fiches n'existent pas encore, un responsable doit encore
les déposer, et leur identifiant dépendra de l'ordre et du moment du
dépôt). Plutôt qu'un lien de téléchargement direct, chaque fiche pointe
vers `documents.php?recherche=NOM_DE_LA_FICHE` : `documents.php` relit
ce paramètre côté serveur pour pré-remplir le champ de recherche
existant (`value=` sur `#recherche-documents`), et son script inline
applique désormais le même filtre au chargement de la page si ce champ
n'est pas vide (fonction `appliquerRecherche()`, extraite de l'ancien
gestionnaire d'évènement `input` pour être appelable aussi bien au
chargement qu'à la frappe) — la page s'ouvre donc directement filtrée
sur la bonne fiche, sans jamais coder en dur un identifiant fragile.
Cette approche reste robuste même si les fiches sont déposées dans un
ordre différent de celui du texte, ou redéposées plus tard. Vérifié hors
ligne (page HTML isolée reproduisant exactement le script de
`documents.php`, Playwright) : avec le champ pré-rempli, seul le
document dont le titre correspond reste visible au chargement, les
autres sont masqués.

**Les pastilles de filtre de la page publique et celles de la Galerie du
Club affichaient deux listes de catégories différentes** (signalé par
l'utilisateur le 26/08/2026, capture d'écran à l'appui : « Marais salants »,
« Sport » ou « Voyage / Reportage » n'apparaissaient que sur la page
publique, et « Street » — le seul mot anglais du site — n'apparaissait que
sur la Galerie du Club). Cause : deux listes de thèmes maintenues à la
main, jamais resynchronisées depuis l'introduction de la table
`categories_galerie` — `CLUB_DATA.themes` (`js/data.js`), une liste figée
datant d'avant cette table, contre `CATEGORIES_GALERIE_PAR_DEFAUT`
(`inc/migration.php`), la vraie liste qui organise les photos et qu'un
responsable édite depuis `parametres.php`. `CLUB_DATA.themes` reprend
maintenant exactement `CATEGORIES_GALERIE_PAR_DEFAUT` (huit thèmes) —
c'est la table `categories_galerie`, éditable, qui fait foi ; les thèmes
qui n'existaient que dans l'ancienne liste (Marais salants, Sport, Voyage /
Reportage, la variante « Abstrait / Créatif ») ont disparu des pastilles
par défaut, mais un responsable peut les recréer à tout moment depuis
Réglages du site s'il les veut de retour — ils réapparaîtraient alors
identiques sur les deux pages. « Street » est renommé « Photo de rue »,
comme dans l'ancienne liste : à la fois dans `CATEGORIES_GALERIE_PAR_DEFAUT`
(pour une prochaine installation) et via un renommage ponctuel dans
`appliquer_migrations()` — même principe que le correctif « Macro » juste
au-dessus — pour la base déjà en ligne, qui contient déjà une catégorie
« Street ». Le témoin `categories_galerie_v2` de `signature_schema()` est
passé à `v3` : sans ce changement, une base déjà migrée aurait ignoré ce
correctif, le considérant à tort déjà à jour.

**Ce correctif du 26/08/2026 n'était qu'une synchronisation ponctuelle, pas
un lien permanent** — le piège s'est reproduit le 27/08/2026, signalé de
nouveau par l'utilisateur (capture d'écran à l'appui) : elle avait renommé
« Architecture » et « Créatif » en « Voyage » et « Sport » depuis Réglages
du site, et la Galerie du Club (`galerie-club.php`, rendue côté serveur,
lit `categories_galerie` à chaque affichage) montrait bien les nouveaux
noms — mais la page publique `galerie.html` gardait « Architecture » et
« Créatif » indéfiniment. Cause exacte : `CLUB_DATA.themes` (`js/data.js`)
est une liste **figée au moment du déploiement** — la synchroniser une fois
avec `CATEGORIES_GALERIE_PAR_DEFAUT` (comme le 26/08/2026) ne fait que
repousser le problème à la prochaine fois qu'un responsable modifie une
catégorie, puisque rien ne relie plus jamais cette liste à la table après
coup. **Corrigé cette fois en supprimant la dépendance à une liste figée** :
`infos-galerie-club.php` renvoie désormais `{photos: [...], categories:
[...]}` plutôt qu'un simple tableau de photos — `categories` vient de
`categories_galerie($pdo)` (`inc/galerie_categories.php`, requise
directement depuis ce point d'accès autonome), la même fonction déjà
utilisée par `galerie-club.php` et `parametres.php`. Côté page publique,
`js/main.js` peint d'abord les pastilles à partir de `CLUB_DATA.themes`
(inchangé, pour un premier affichage instantané et un repli hors ligne/
préversion GitHub Pages), puis les **reconstruit entièrement**
(`rebuildThemeFilters()`, vide `filtersRoot` et rejoue `addThemeFilter()`
sur la vraie liste) dès que `infos-galerie-club.php` répond — au lieu de
seulement *ajouter* les catégories des photos trouvées par-dessus la liste
figée, comme avant, ce qui pouvait ajouter une catégorie manquante mais ne
retirait jamais une catégorie obsolète. Toute catégorie encore vide (sans
aucune photo) continue d'apparaître, puisque la liste vient de la table et
non des photos elles-mêmes — l'exigence documentée plus bas
(« CLUB_DATA.themes reste la liste des filtres... même sans aucune photo »)
tient donc toujours, simplement portée par la vraie table plutôt qu'un
tableau JS. Le même correctif profite à la sélection de photos récentes de
l'accueil (`[data-highlights]`), seule autre consommatrice de ce point
d'accès, adaptée à la nouvelle forme de réponse. Testé hors ligne (copie de
test connectée à SQLite au lieu de MySQL, seul point d'accès autonome de la
racine encore jamais exercé par le banc d'essai jusqu'ici) : renommage de
catégorie simulé en base, pastilles de la page publique reconstruites sans
« Architecture »/« Créatif » ni perdre « Voyage »/« Sport », filtre par
catégorie et sélection de l'accueil toujours fonctionnels, suite de 39
tests du blog rejouée sans régression.

`inc/galerie_categories.php` (renommé le 21/08/2026, s'appelait
`inc/galerie_club.php` avant que `galerie.php` ne partage aussi ses
catégories) porte la seule fonction `categories_galerie($pdo)`. Tout
adhérent peut déposer (titre, catégorie, nom affiché facultatif — pour
signer autrement que son identifiant de connexion —, note facultative) —
les photos sont plafonnées à 1000 Ko (`TAILLE_MAX_PHOTO_ADHERENT` dans
`inc/televersement.php`, choix explicite de l'utilisateur, 21/08/2026 —
relevé de 600 Ko à 1000 Ko le 26/08/2026 : plus strict que le plafond
général de 8 Mo, qui reste appliqué aux documents et aux photos de sortie),
message dédié — « Photo trop lourde, ne pas dépasser 1000 Ko. Merci. » —
passé en 5ᵉ argument à `enregistrer_fichier_envoye()` ;
seul l'auteur ou un responsable peut supprimer sa photo — même règle,
et même formulaire de dépôt, dans `galerie.php` (colonnes `categorie_id` et
`nom_affiche` ajoutées à `photos_privees` le 21/08/2026, exactement comme
`photos_club`). La catégorie n'est pas répétée sur chaque photo : elle est
déjà donnée par le titre du groupe. Les deux galeries partagent le même
partiel de carte, `inc/photo-carte.php` (remplace l'ancien
`inc/photo-club-carte.php`, propre à la Galerie du Club), paramétré par
`$type` (`'photo'` ou `'galerie_club'`, le type attendu par
`telecharger.php`) — même principe que `inc/document-ligne.php`. Fichiers
dans `espace/photos_club/` (fermé par `.htaccess`), servis par
`telecharger.php?type=galerie_club` — **public**, comme `type=sortie`,
contrairement à `type=photo`/`document` qui exigent une connexion.

**Cartes de photo uniformes, légende en incrustation, agrandissement au
clic** (choix explicite de l'utilisateur, 21/08/2026 — les vignettes
paraissaient de tailles inégales avec l'ancien « masonry » en colonnes CSS
et une légende en bloc sous la photo, à hauteur variable selon la longueur
du titre). `.photo-grid` est une vraie grille (`display:grid`, cases toutes
identiques) plutôt qu'un masonry ; chaque `.photo-card` a un ratio fixe
(4:3) avec la légende **incrustée** en dégradé sombre par-dessus la photo
(`.photo-caption`, `position:absolute`), pas un bloc séparé qui grandirait
avec le texte. Le titre et le nom/date sont tronqués en CSS
(`text-overflow: ellipsis`, une seule ligne) — jamais dans les données : le
texte complet (description comprise) part dans des attributs `data-titre` /
`data-description` / `data-meta` / `data-image` sur chaque carte
(`inc/photo-carte.php`), lus intégralement par l'agrandissement au clic.
Un « effet de relief » habille les cartes et la boîte d'agrandissement
(ombre portée à deux niveaux, accentuée au survol/zoom léger). Pour
`galerie.php` et `galerie-club.php` (pages PHP sans le système de
diaporama de la page publique), un bloc générique dans `js/main.js`
détecte toute page portant des `.photo-card[data-titre]` et le marqueur
`[data-lightbox]` (ajouté dans le HTML de ces deux pages), et branche
l'agrandissement avec navigation précédente/suivante — un clic sur le
bouton Supprimer, en incrustation dans le coin de la carte
(`.photo-supprimer`), n'ouvre jamais l'agrandissement. La page publique
`galerie.html` garde son propre système, plus riche (diaporama automatique)
et non concerné puisque ses cartes ne portent pas ces attributs — seul le
CSS partagé (`.photo-card`/`.photo-frame`/`.photo-caption`) s'applique aux
deux, ce qui suffit à leur donner le même habillage.

**La page publique `galerie.html` (et la sélection sur l'accueil) affichent
les vraies photos de la Galerie du Club.** `infos-galerie-club.php` (à la
racine, hors de `espace/` — même principe qu'`infos-club.php` : public par
conception, connexion autonome à la base, jamais de dépendance à une page de
l'espace déjà visitée) renvoie en JSON les photos de `photos_club` avec leur
catégorie et leur auteur affiché, des plus récentes aux plus anciennes.
`js/main.js` les récupère sur les deux pages : sur l'accueil (`[data-highlights]`,
les 8 plus récentes), la section reste masquée (`hidden` posé en dur dans
`index.html`) tant qu'aucune photo n'est encore disponible ; sur la page
Galerie, la grille et les filtres par thème restent vides jusqu'à ce que
l'appel réussisse, puis se complètent sans à-coup. Dans les deux cas, l'appel
échoue silencieusement sinon (hors ligne, ou préversion GitHub Pages, qui ne
peut pas exécuter PHP). Il n'y a plus de photos de démonstration depuis le
20/08/2026 (`CLUB_DATA.membres` vide dans `js/data.js`, à ne pas repeupler) ;
`photoBackground()` dans `main.js` sait toujours afficher un dégradé de
couleur si jamais une photo sans champ `image` réapparaissait. Toute
catégorie de photo inconnue de `CLUB_DATA.themes` s'ajoute automatiquement
aux filtres.

**Les photos Google Drive vivent sur `nos-sorties.html` (« Nos Sorties »),
un album par sortie**, entre Galerie et Blog dans le menu. Historique en
trois temps :
- 23–24/08/2026 : simple section « Photos Google Drive » de `galerie.html`,
  alimentée par `infos-galerie-drive.php` — section et point d'accès
  **supprimés**, ne pas les réintroduire.
- 24/08/2026 : page dédiée `expo-2026.html` (« Expo 2026 »), un seul dossier
  Drive figé dans `config.local.php`, photos groupées par adhérent.
- 27/08/2026 (choix explicite de l'utilisateur) : devient **« Nos Sorties »**
  (`nos-sorties.html`), qui regroupe **un album par sortie** — Expo 2026,
  Croisière Penbron, Fête de la mer… L'objectif énoncé était d'« éviter des
  pages trop longues » : chaque sortie a maintenant son album plutôt que de
  tout empiler sur une page unique. L'utilisatrice avait pensé à des
  « catégories » dans la page Expo 2026 ; la proposition retenue va plus
  loin — **les albums se créent depuis Réglages du site**, pas dans le code,
  donc une nouvelle sortie ne demande plus aucun déploiement.
  `expo-2026.html` est conservée en **redirection** vers `nos-sorties.html`
  (même principe qu'`evenements.html`/`membres.html`), et le libellé de menu
  « Expo 2026 » devient « Nos Sorties » (le nom a été choisi explicitement
  par l'utilisatrice parmi trois propositions). Le principe de fond ne change
  pas : les photos restent sur Google Drive plutôt que sur l'hébergement
  Hostinger, pour ne pas l'encombrer.

**Un album = une ligne de la table `albums_sorties`** (`nom`,
`dossier_drive`, `ordre` — voir `schema.sql` et `inc/albums.php`, qui porte
la seule fonction `albums_sorties($pdo)`, même forme que
`categories_galerie()`). Les albums suivants sont créés à la main depuis
`parametres.php`, pavé « Albums de "Nos Sorties" » en pleine largeur sous la
grille des catégories — contrairement aux catégories (un seul champ nom),
un album porte deux champs, d'où un bloc empilé `.reglage-album` plutôt
qu'une ligne `.reglage-forme-nom`. Le champ « Dossier Google Drive » accepte
aussi bien un identifiant seul qu'une **URL de dossier complète**
(l'identifiant en est extrait par expression régulière) ; il est validé
(`[A-Za-z0-9_-]+`) avant tout enregistrement, puisqu'il finit dans la clause
`q` envoyée à l'API Google. Supprimer un album ne retire que son entrée du
site, jamais une photo sur Drive — d'où l'absence de garde-fou
« album non vide », contrairement aux catégories.

**Le tout premier album, « Expo 2026 », est repris automatiquement** —
revirement le jour même (27/08/2026) : l'utilisatrice avait d'abord dit
qu'elle le recréerait elle-même depuis Réglages, puis a demandé l'inverse
quelques échanges plus tard. `appliquer_migrations()` (`inc/migration.php`)
lit directement `config.local.php` (le seul endroit du fichier de migration
à le faire — les autres migrations ne touchent qu'à des colonnes) et, si
`albums_sorties` est encore vide, reprend l'ancien réglage unique
`google_drive_dossier_id` (celui de l'ex-page « Expo 2026 ») pour en faire
le premier album, ordre 0. Ne se joue qu'**une seule fois** — dès qu'un
album existe, qu'il s'appelle « Expo 2026 » ou autrement, plus aucune
réintroduction, même si l'utilisatrice le supprime ensuite. Le témoin
`albums_sorties_v1` de `signature_schema()` est passé à `v2` pour que cette
reprise s'applique aussi à la base déjà en ligne, migrée une première fois
la veille sous l'ancienne signature. Sur une base neuve sans
`config.local.php` (ou sans ce réglage dedans), rien ne se passe — pas
d'erreur, juste aucun album créé, comme avant.

`infos-albums.php` (à la racine, même principe d'autonomie
qu'`infos-club.php` : connexion PDO propre, jamais `base_de_donnees()`)
remplace `infos-expo-2026.php` (supprimé). Deux modes :
- **sans paramètre** — la liste des albums : `{albums:[{id, nom, vignette,
  dossiers}]}`. Volontairement économe : un appel pour lister les adhérents
  de l'album, puis au plus `REQUETES_MAX_COUVERTURE` (4) appels pour trouver
  **une seule** photo de couverture (`collecter_images_drive()` prend un
  paramètre `$images_voulues` qui l'arrête dès qu'il en a assez). Faire la
  collecte complète de chaque album ici rendrait la page d'accueil des
  albums très lente et userait le quota gratuit pour rien. Un album mal
  partagé ou vide reste affiché, `vignette: null` (dégradé de repli côté
  page), plutôt que de disparaître.
- **`?album=ID`** — le contenu d'un album : `{nom, dossiers:[{nom, vignette,
  photos:[{titre, image, image_grande}]}]}`, exactement la structure que
  servait `infos-expo-2026.php`. Chaque sous-dossier direct de l'album est
  un adhérent (un fichier posé directement à la racine est ignoré, il n'a pas
  de nom d'adhérent à afficher) ; `collecter_images_drive()` est appelée une
  fois par adhérent, avec toute sa robustesse déjà éprouvée (voir plus bas).
  Un adhérent sans aucune photo est **absent de la liste** plutôt que d'y
  figurer avec une carte vide.

Cache disque 15 minutes, **un fichier par mode** :
`espace/inc/.cache-albums-liste.json` et `.cache-albums-album-{id}.json`
(non versionnés, `.gitignore` couvre `.cache-albums-*.json`). En cas d'échec
du **premier** appel (quota dépassé, clé invalide, panne), le dernier
résultat connu est resservi — même expiré — plutôt que de vider la page.

Côté page, `js/main.js` détecte `[data-expo-page]` et gère **trois** vues
dans la même page, sans navigation : la grille des albums
(`[data-expo-albums]`), au clic celle des dossiers d'adhérents
(`[data-expo-vue-dossiers]`, bouton « ← Retour aux albums »), au clic celle
de ses photos (`[data-expo-vue-photos]`, bouton « ← Retour aux dossiers »).
Le contenu d'un album déjà ouvert est gardé en mémoire (`albumsCharges`) :
y revenir ne redemande rien au serveur. Le titre et l'accroche du bandeau
(`[data-expo-titre]`/`[data-expo-accroche]`) prennent le nom de l'album
quand on y entre, et sont restaurés au retour. `construireCarte()` sert
aussi bien aux albums qu'aux dossiers — seules la vignette, la légende et
l'action changent. Les cartes de photo passent par `buildPhotoCard()`, donc
l'agrandissement au clic (lightbox partagée) fonctionne comme ailleurs.

**Un seul réglage reste dans `espace/inc/config.local.php`** (jamais
commité) : `google_drive_cle_api`. C'est un secret, il n'a rien à faire en
base ni dans une interface web. L'ancien `google_drive_dossier_id`, unique et
figé, **a disparu** avec la page Expo 2026 — s'il traîne encore dans le
`config.local.php` en ligne, il est simplement ignoré. Clé vide ou aucun
album créé : la page affiche « Aucun album pour le moment. », sans erreur
(même message en cas d'échec de l'appel : hors ligne, ou préversion GitHub
Pages qui ne peut pas exécuter PHP).
**Chaque dossier Drive d'album doit être partagé « Accessible à tous les
utilisateurs disposant du lien »** : une clé API seule (sans OAuth) ne
peut lire que des fichiers Drive publics, jamais un dossier resté privé.
La requête à `files.list` inclut `supportsAllDrives`/
`includeItemsFromAllDrives` (sans quoi un dossier vivant dans un Drive
partagé — « Shared Drive » — resterait invisible même bien partagé).

Testé hors ligne (27/08/2026) avec un **faux annuaire Drive** injecté dans
la copie de test (`patch-infos-albums.php` remplace `recuperer_url()`, le
domaine googleapis.com étant bloqué depuis ce sandbox) : liste des albums
(couvertures, comptes de participants, album vide sans vignette), contenu
d'un album, exploration des sous-dossiers, tri naturel des photos,
navigation aux trois niveaux et retours, lightbox, redirection de
`expo-2026.html`, et le cycle complet ajouter/renommer/supprimer un album
depuis Réglages (URL Drive collée entière, identifiant invalide refusé).
Aucun débordement à 390px sur les trois niveaux ni sur `parametres.php` ;
suite de 39 tests du blog rejouée sans régression.

**La reprise automatique du premier album** a été testée séparément, trois
scénarios avec le même faux annuaire Drive : témoin de schéma repositionné à
la main sur l'ancienne signature (`albums_sorties_v1`) avec `albums_sorties`
vide et `google_drive_dossier_id` renseigné → album « Expo 2026 » créé avec
sa couverture et ses dossiers d'adhérents corrects ; même scénario mais avec
un album déjà présent (simule une base où l'utilisatrice aurait déjà créé un
album entre-temps) → aucun doublon, la migration se contente de ne rien
faire ; base neuve sans `config.local.php` du tout → aucune erreur dans les
journaux PHP, page « Aucun album pour le moment. » comme attendu. Suite de
39 tests du blog rejouée une dernière fois sans régression.

**Un album peut aussi être hébergé directement sur ce site, sans Google
Drive** (choix explicite de l'utilisateur, 27/08/2026, même jour : « je
voudrais aussi pouvoir intégrer de nouveaux albums qui seraient hébergés sur
mon hébergement Hostinger… je ne veux pas mettre beaucoup de photos par
album hébergé sur le site, si il y a beaucoup de photos ce sera par le
cloud »). `albums_sorties` porte donc une colonne `type` (`'drive'` par
défaut, ou `'local'`, migration ALTER + `signature_schema()` passé à
`albums_sorties_v3`) — un album Drive fonctionne exactement comme avant ;
un album local n'a pas de dossier Drive (`dossier_drive` reste une chaîne
vide) et ses photos vivent dans une nouvelle table `photos_sorties` (`id`,
`album_id`, `titre`, `description`, `nom_affiche`, `fichier`, `depose_par`,
`cree_le` — mêmes colonnes que `photos_club`, `ON DELETE CASCADE` sur
`album_id`), déposées par les adhérents eux-mêmes depuis une nouvelle page,
`espace/album.php?id=…`.

`espace/album.php` reprend le formulaire de dépôt de `galerie-club.php`
(sélection multiple/glissé-déposé, `TAILLE_MAX_PHOTO_ADHERENT` — 1000 Ko,
même plafond que la Galerie du Club et la Galerie privée, cohérent avec la
consigne « pas beaucoup de photos »), mais sans catégorie ni filtre par
thème — un album suffit à classer les photos, une seule liste de la plus
récente à la plus ancienne. Titre gardé en session d'un dépôt à l'autre
(une clé par album, `dernier_titre_album_{id}`, pour ne pas mélanger deux
albums). Suppression d'une photo : son auteur ou un responsable seulement
— jamais un éditeur, même règle de modération que `galerie-club.php`
(`inc/photo-carte.php`, partagé tel quel, porte déjà cette restriction).
Fichiers dans `espace/photos_sorties/` (fermé par `.htaccess`, comme
`photos_club/`), servis par `telecharger.php?type=sortie_album` —
**public**, comme `type=sortie`/`galerie_club`/`blog`.

Dans `parametres.php`, le pavé « Albums de "Nos Sorties" » porte maintenant
deux cases radio (Dossier Google Drive / Hébergé sur ce site) sur chaque
formulaire d'album — `js/main.js` (inline, propre à cette page) masque le
champ « Dossier Google Drive » quand « Hébergé sur ce site » est coché,
sans quoi il resterait affiché et requis pour un album qui n'en a pas
besoin. Supprimer un album local efface d'abord ses fichiers sur le disque
(la suppression en cascade de `photos_sorties` ne touche que les lignes,
jamais le disque) ; supprimer un album Drive ne touche à rien, comme avant.
**Rebasculer un album local vers Drive est refusé tant qu'il contient
encore des photos** — un aller-retour orphelinerait ces photos (ni
affichables, l'album deviendrait un dossier Drive vide, ni supprimables,
`album.php` refuse un album qui n'est plus de type local) : message
explicite, même principe que les catégories encore utilisées un peu plus
haut dans cette page. L'inverse (Drive vers local) reste toujours permis,
un album Drive n'ayant jamais de photo à perdre ici.

`infos-albums.php` traite les deux types côte à côte, en gardant la même
forme de réponse pour ne rien changer au JavaScript de `nos-sorties.html` :
un album local ajoute simplement `"type": "local"` à son entrée, et ses
« dossiers » sont les photos de `photos_sorties` groupées par adhérent
(`depose_par`), exactement comme un dossier Drive contiendrait les photos
d'un adhérent. Contrairement aux albums Drive, un album local n'est
**jamais mis en cache et ne dépend jamais de la clé API Google** — une
simple requête SQL, sans quota à ménager — pour qu'une photo tout juste
déposée apparaisse aussitôt sur la page publique, y compris en mode liste
où le bloc mis en cache (protégeant le quota Drive) est ré-actualisé pour
ses seules entrées locales avant chaque réponse. Cette ré-actualisation lit
le type **actuel** de l'album (table `albums_sorties`), jamais celui figé
dans le cache : un album peut avoir changé de type depuis l'écriture du
cache (voir le refus ci-dessus pour l'unique sens qui resterait dangereux).
Sans clé API configurée, la liste des albums n'est plus vidée comme avant
l'introduction des albums locaux : seuls les albums Drive retombent sur une
carte sans vignette, les albums locaux s'affichent normalement.

`js/main.js` (bloc Nos Sorties) affiche un lien « Déposer des photos »
(`[data-expo-deposer]`, dans `.expo-barre` du niveau 2 — les dossiers d'un
album ouvert) uniquement quand l'album ouvert est de type `local`, vers
`espace/album.php?id=…`.

Testé hors ligne (27/08/2026, même jour) avec un vrai serveur PHP intégré
branché sur SQLite : création d'un album local depuis Réglages, dépôt
multiple, affichage immédiat en liste et en détail (y compris avec un cache
liste déjà écrit), suppression d'une photo par son auteur, refus de
suppression par un autre adhérent non responsable, suppression de l'album
(fichiers + lignes effacés), refus de bascule vers Drive tant que des
photos restent, bascule Drive → local acceptée, page `album.php` refusant
un identifiant d'album Drive (404), et liste des albums non vidée par
l'absence de clé API Google.

**Le diaporama se lance depuis la photo agrandie** (choix explicite de
l'utilisateur, 24/08/2026) : un bouton `.lightbox-diaporama` (▶ / ⏸) dans
la lightbox elle-même, à côté de Fermer. Il réutilise
`startDiaporama()`/`stopDiaporama()`, déjà en place pour le bouton
« Lancer le diaporama » en haut de `galerie.html` (conservé) ; ces deux
fonctions reflètent maintenant l'état sur le bouton
(`reglerBoutonDiaporama()`), donc le diaporama s'affiche bien comme
arrêté quand on clique sur une flèche ou qu'on ferme la lightbox.
Générique : présent dans le HTML de la lightbox d'`index.html`,
`galerie.html`, `nos-sorties.html` et (depuis le 26/08/2026, voir plus bas)
`espace/galerie-club.php` ; `js/main.js` ne câble rien si le bouton est
absent, ce qui laisse `espace/galerie.php` sans ce bouton (pas de
diaporama sur la Galerie privée, hors scope de ce changement).

**La photo agrandie s'affiche ENTIÈRE, jamais recadrée** (24/08/2026,
signalé par l'utilisateur : les photos verticales — celles d'Annie sur
Expo 2026 — étaient coupées en haut et en bas). Le cadre de la lightbox
posait un fond CSS `center / cover` dans une boîte au format 4/3 fixe :
`cover` remplit la boîte en rognant tout ce qui dépasse, donc toute photo
qui n'était pas exactement en 4/3 perdait une partie de l'image.
`poserPhotoAgrandie()` (`js/main.js`) insère désormais une **vraie
`<img class="lightbox-image">`** dans `.lightbox-frame`, plutôt qu'un fond :
le cadre épouse alors la photo, donc bordure, arrondi et ombre entourent
l'image elle-même — avec un simple `background-size: contain`, ils auraient
dessiné un rectangle 4/3 avec des bandes vides autour d'une photo
verticale. La taille est bornée par `max-width: 100%` (900px via
`.lightbox-content`) et `max-height: 70vh`, ce qui laisse la place à la
légende quelle que soit la hauteur de l'écran. `.lightbox-frame--degrade`
reprend l'ancien cadre 4/3 pour le seul cas d'une photo **sans** fichier
image (dégradé de repli de `photoGradient()`, qui n'a aucune dimension
propre à donner au cadre).

**Plafond de l'agrandissement : 1920px sur la plus grande dimension**
(choix explicite de l'utilisateur, 24/08/2026 — `.lightbox-content`
plafonnait à 900px, ce qui bridait la photo bien en deçà sur un grand
écran). Trois réglages qui vont ensemble, à ne pas changer isolément :
`.lightbox-content { max-width: 1920px }`, `.lightbox-image
{ max-height: min(70vh, 1920px) }` (70vh garde la légende visible ; le
1920 ne joue qu'au-delà d'un écran de ~2740px de haut), et une marge
latérale de 80px sur `.lightbox` **à partir de 761px** de large, sans
quoi une photo large passerait sous les flèches précédente/suivante. En
dessous de 761px, les flèches restent volontairement en surimpression sur
les bords de la photo : réserver 2×68px sur un écran de 390px la
réduirait beaucoup trop — c'est le comportement du site depuis toujours.
Côté source, `infos-albums.php` renvoie **deux** adresses par photo :
`image` en `sz=w1000` pour la vignette de la grille, et `image_grande` en
`sz=w1920` pour l'agrandissement — servir 1920 partout alourdirait la
grille, et servir 1000 partout rendrait la photo agrandie floue une fois
étirée. `poserPhotoAgrandie()` prend `photo.imageGrande || photo.image` :
le repli couvre les photos hébergées sur le serveur du club (Galerie du
Club, galeries de l'espace adhérents), qui n'ont qu'une seule taille.

**Le nom du dossier (adhérent) n'apparaît pas dans la légende de la photo
agrandie**, seulement sur la vignette (choix explicite de l'utilisateur,
25/08/2026) : `renderLightbox()` affiche `photo.membreNom` en légende
(`.lightbox-meta`) sur toutes les galeries — pertinent ailleurs, où il
porte le nom de l'auteur de la photo (Galerie du Club, accueil), mais pas
sur Expo 2026, où ce même champ porte simplement le nom du dossier déjà
répété sur chaque photo qu'il contient. Les objets photo construits par
`ouvrirDossier()` portent donc un champ dédié, `masquerNomAgrandi: true`,
lu uniquement par `renderLightbox()` pour omettre `membreNom` de la
légende — `membreNom` lui-même reste inchangé (toujours utilisé pour la
vignette via `buildPhotoCard()`, et pour retrouver la bonne photo dans le
tableau au clic). Les autres galeries ne posent jamais ce champ, donc leur
légende agrandie continue d'afficher l'auteur comme avant.

**Le titre de chaque photo (nom du fichier envoyé par l'adhérent, ex.
« mgl_1_1_62x33 ») ne s'affiche plus sur sa vignette, dans la vue « photos
d'un dossier »** (choix explicite de l'utilisateur, 25/08/2026, revient
sur une confusion avec le point précédent : le premier réglage portait sur
la photo agrandie, celui-ci sur la vignette elle-même). Contrairement aux
autres galeries, où le titre est saisi à la main et sert de légende utile
(Galerie du Club, galerie privée), celui d'Expo 2026 n'est que le nom de
fichier brut envoyé par l'adhérent — illisible en vignette. `buildPhotoCard()`
prend un 6ᵉ paramètre facultatif, `masquerTitreVignette` : à `true`, le
`<span class="title">` n'est simplement pas ajouté au HTML de la carte (le
`<span class="meta">`, qui affiche le nom du dossier, reste inchangé).
Seul l'appel de `ouvrirDossier()` (page Expo 2026) le passe à `true` ; les
deux autres appelants (`buildPhotoCard`, accueil et `galerie.html`) ne le
passent pas, donc leur titre reste affiché comme avant.

**Le même titre est aussi masqué dans la photo agrandie** (choix explicite
de l'utilisateur, 25/08/2026, même jour — revient sur la dernière phrase du
paragraphe précédent, qui excluait la lightbox à tort). `renderLightbox()`
lit un second champ dédié, `masquerTitreAgrandi`, posé lui aussi sur les
objets photo d'Expo 2026 dans `ouvrirDossier()` (à côté de
`masquerNomAgrandi`) : à `true`, `.lightbox-title` reste vide plutôt que
d'afficher le nom de fichier brut. Sur Expo 2026, la photo agrandie n'a
donc plus aucune légende (titre et nom de dossier tous deux masqués) —
seule l'image compte. Les autres galeries ne posent jamais ce champ, donc
leur titre continue d'apparaître normalement dans la lightbox.

La fonction est partagée par les **deux**
systèmes d'agrandissement du site — celui des pages publiques
(`renderLightbox()`) et celui des galeries de l'espace adhérents
(`afficher()`, cartes `.photo-card[data-titre]`) — donc les deux ont été
corrigés d'un coup ; vérifié au rendu Chromium en vertical, carré,
panoramique et 4/3, sur ordinateur comme sur téléphone.
**Le recadrage reste volontaire sur les vignettes** (`.photo-card`, voir
`buildPhotoCard()` et le choix du 21/08/2026) : c'est lui qui leur donne
des tailles uniformes. Ne pas « corriger » les vignettes en croyant
prolonger ce correctif.

**`[hidden]` est forcé à `display: none !important`** en tête de
`css/style.css` (24/08/2026). Sans ça, masquer un élément dont une classe
impose un `display` ne fait rien : une règle de classe (`.photo-grid {
display: grid }`) l'emporte toujours sur le `display: none` que le
navigateur donne par défaut à `[hidden]`. Constaté sur la page Expo 2026,
où la grille des dossiers restait affichée sous celle des photos après un
clic. Beaucoup de sections du site sont masquées ainsi en attendant leurs
données JSON — vérifié après coup que l'accueil et la page Galerie
s'affichent toujours normalement. Le site ne pilote jamais l'affichage par
`element.style.display`, donc rien ne peut entrer en conflit avec ce
`!important`.

**Les sous-dossiers sont explorés** (24/08/2026) : chaque adhérent range
souvent ses photos dans un sous-dossier (`Expo FOCAL 2026 / {Prénom} /
1920 / …`), donc se limiter aux images posées directement dans son dossier
ne remonterait rien pour lui. L'API Google ne sait pas répondre « et tout
ce qu'il y a en dessous » : `collecter_images_drive()` descend niveau par
niveau, en
groupant les dossiers d'un même niveau dans un seul appel
(`'a' in parents or 'b' in parents …`, par lots de `PARENTS_PAR_REQUETE`,
8 dossiers). Le parcours est borné par `PROFONDEUR_MAX` (5) et
`REQUETES_MAX` (40) — une arborescence profonde ne doit ni user le quota
gratuit ni faire attendre la page — et retient les dossiers déjà vus, sans
quoi deux raccourcis Drive pointant l'un vers l'autre boucleraient à
l'infini. Seul l'échec du **premier** appel fait replier sur le cache ; un
échec plus tard garde les photos déjà récoltées plutôt que de tout perdre.
La fonction reçoit son « interrogeur » en argument (`callable`) au lieu
d'appeler l'API en dur : c'est ce qui permet de tester tout ce parcours
hors ligne avec un faux annuaire Drive, le domaine du site étant bloqué
depuis ce sandbox. L'identifiant de dossier est validé (`[A-Za-z0-9_-]+`)
avant d'entrer dans la clause `q`.

**Un seul dossier inaccessible dans un lot fait échouer tout le lot**
(piège découvert et corrigé le 24/08/2026, distinct de celui décrit plus
bas) : une requête groupée (`'a' in parents or 'b' in parents or …`) où
**un seul** des identifiants n'est pas lisible par la clé API renvoie
« The user does not have sufficient permissions for this file » pour
**toute** la requête — pas seulement pour le dossier fautif. Constaté avec
le dossier « Logo Focal Club », resté sans partage individuel alors que
les 16 autres sous-dossiers d'adhérents, eux, étaient bien accessibles :
sans retraitement, cela aurait fait disparaître les photos de tous les
adhérents à cause d'un seul dossier oublié. (Depuis le passage à la page
Expo 2026, un dossier d'adhérent est de toute façon interrogé seul, donc
son échec ne coûte que sa propre carte — mais la relance individuelle
reste indispensable pour les sous-dossiers *à l'intérieur* du dossier d'un
adhérent, interrogés eux en lot.) Quand un lot échoue et que ce
n'est pas le tout premier appel, `collecter_images_drive()` relance donc
chaque dossier du lot **individuellement** (`construire_requete_dossier()`
avec un seul identifiant) : les dossiers accessibles remontent leurs
photos normalement, et seul celui réellement bloqué est ignoré en
silence — d'où `PARENTS_PAR_REQUETE` volontairement modeste (8, pas 20) et
`REQUETES_MAX` généreux (40) pour laisser de la marge à ces relances.
Couvert par un test hors ligne dédié qui simule ce comportement exact de
l'API (un lot contenant un identifiant bloqué échoue en bloc, chaque
identifiant relancé seul réussit ou échoue pour de bon).

**`file_get_contents` + `stream_context_create('timeout')` peut rester
bloqué plusieurs minutes au lieu d'échouer** (constaté le 24/08/2026,
piège distinct des deux précédents) : en diagnostiquant ce qui précède, un
appel resté bloqué plus de deux minutes — largement au-delà du délai de
6 secondes configuré — a fait perdre du temps avant qu'on comprenne que
le délai de `stream_context_create` ne s'applique pas toujours de façon
fiable sur les flux HTTPS (limitation connue de PHP, variable selon la
version/plateforme). `recuperer_url()` utilise désormais cURL quand il est
disponible (`CURLOPT_TIMEOUT`/`CURLOPT_CONNECTTIMEOUT`, bien plus fiables
sur ce point), avec repli sur `file_get_contents` sinon.

**Piège rencontré le 24/08/2026** : après la première configuration par
l'utilisateur, la page (alors une section de `galerie.html`) restait vide.
Diagnostic via un point d'accès
temporaire (`?diag=...`, retiré une fois la cause trouvée — le domaine du
site étant bloqué depuis ce sandbox, un appel direct à l'API Google
`files.get` sur l'identifiant du dossier configuré a montré une erreur
« File not found », alors que la clé API et l'identifiant de dossier
étaient corrects. Le dossier n'était en réalité **pas encore partagé
publiquement** malgré l'étape 5 suivie : à vérifier en premier lieu si la
page Expo 2026 reste vide — rouvrir le dossier sur drive.google.com, bouton de
partage, « Accès général » doit afficher « Tous les utilisateurs disposant
du lien » (pas « Restreint »). Si le dossier est bien partagé mais que des
fichiers individuels à l'intérieur avaient été mis en ligne *avant* ce
changement, ils peuvent avoir gardé leur propre restriction : les
sélectionner tous dans Drive et les partager explicitement au même réglage
résout ce cas.

**Les outils Google Drive de Claude ne savent pas régler ce partage**
(constaté le 24/08/2026, à ne pas repromettre) : `share_file` exige une
adresse e-mail précise, et ne peut donc pas poser un partage « tous les
utilisateurs disposant du lien » (`type: anyone`) ; `update_file` ne
touche qu'au titre et au dossier parent. Cette étape reste à faire à la
main par l'utilisateur. En revanche `get_file_permissions`,
`get_file_metadata` et `search_files` permettent de **diagnostiquer**
beaucoup plus vite qu'en déployant un point d'accès temporaire : lire les
permissions réelles d'un dossier (un partage public y apparaît en
`type: anyone`) et vérifier ce qu'il contient vraiment. C'est ainsi qu'on
a découvert, le 24/08/2026, que le dossier configuré ne contenait
**aucune photo** — seulement une arborescence de sous-dossiers vides au
nom de chaque adhérent, plus deux fichiers Word/Excel : même
parfaitement partagé, la galerie serait restée vide. Réflexe à garder :
vérifier d'abord *qu'il y a des photos*, avant de chercher pourquoi elles
ne s'affichent pas.

Pour activer cette section, un responsable doit (une seule fois, sur
[console.cloud.google.com](https://console.cloud.google.com)) :
1. Créer un projet Google Cloud (gratuit) — bouton en haut de la page,
   nom libre (ex. « Focal Club site »).
2. Dans ce projet, menu ☰ → *API et services* → *Bibliothèque*, chercher
   « Google Drive API » et cliquer *Activer*.
3. *API et services* → *Identifiants* → *Créer des identifiants* → *Clé
   API*. La clé générée est `google_drive_cle_api`.
4. Cliquer sur la clé pour la restreindre (recommandé, pas obligatoire) :
   *Restrictions relatives à l'API* → limiter à « Google Drive API »
   uniquement — une clé qui fuiterait ne donnerait alors accès à rien
   d'autre.
5. Sur [drive.google.com](https://drive.google.com), créer ou choisir le
   dossier à afficher, clic droit → *Partager* → *Accès général* →
   « Tous les utilisateurs disposant du lien » (rôle *Lecteur*).
6. Dans l'adresse du dossier ouvert
   (`drive.google.com/drive/folders/CET_IDENTIFIANT`), copier
   `CET_IDENTIFIANT` : c'est `google_drive_dossier_id`.
7. Sur le serveur (hPanel → Gestionnaire de fichiers), ouvrir
   `public_html/espace/inc/config.local.php` et y coller les deux valeurs.

**La Galerie du Club (`espace/galerie-club.php`) reprend la présentation de
la page publique `galerie.html`** (choix explicite de l'utilisateur,
26/08/2026 — clarifié par deux questions, l'utilisateur ayant d'abord
demandé « identique à la page galerie » sans préciser laquelle : la
Galerie privée, déjà quasi identique en présentation, ou la page publique,
plus riche). `titre_page()` (le bandeau simple partagé par le reste de
l'espace) est remplacé par un vrai `<section class="gallery-hero">`
(titre, sous-titre, pastilles de filtre par thème) — même classes CSS que
`galerie.html`, aucune nouvelle règle nécessaire. Contrairement à la page
publique (qui charge ses photos par `fetch` depuis `infos-galerie-club.php`
et reconstruit ses cartes en JavaScript), les photos ici sont déjà rendues
côté serveur, groupées par catégorie dans des `<div class="groupe-galerie
data-categorie-id="…">` : une pastille de filtre ne fait donc qu'afficher/
masquer ces blocs déjà présents dans le DOM (`[hidden]`, voir plus bas),
sans nouvel appel réseau. Un bouton **Diaporama** (même icône et bouton
`.diaporama-trigger`/`.lightbox-diaporama` que la page publique) est posé
juste au-dessus de la grille de photos. La Galerie privée (`galerie.php`)
n'a reçu aucun de ces trois éléments (bandeau, filtres, diaporama) — elle
garde son bandeau `titre_page()` simple, hors du périmètre demandé.

Pour porter le diaporama sur une page dont les cartes sont rendues côté
serveur, le bloc générique de `js/main.js` qui gérait jusque-là
l'agrandissement au clic sur `espace/galerie.php` et `galerie-club.php`
(fonctions `afficher()`/`ouvrir()`/`fermer()` propres, séparées du système
des pages publiques) a été réécrit pour réutiliser directement
`openLightbox()`/`renderLightbox()`/`startDiaporama()`, déjà en place pour
`index.html`/`galerie.html`/`nos-sorties.html` — une carte de l'espace
(`espace/inc/photo-carte.php`) porte déjà `data-titre`/`data-description`/
`data-meta`/`data-image` ; ces attributs sont recomposés en objet `photo`
compatible (`meta` devient `membreNom`, `theme` reste vide) plutôt que de
dupliquer la logique d'ouverture/fermeture/navigation. Bénéfice
secondaire : ce partage a aussi corrigé un double-câblage invisible
jusque-là (les deux systèmes posaient chacun leurs propres écouteurs sur
les mêmes boutons Fermer/Précédent/Suivant de la lightbox, l'un d'eux
levant une exception JS silencieuse à chaque clic, sans conséquence
visible puisque l'autre système gérait correctement l'affichage). Seules
les cartes **visibles** (`carte.offsetParent !== null`) comptent pour la
navigation et le diaporama : un groupe masqué par le filtre de thème ne
doit apparaître ni dans le diaporama ni dans les flèches précédent/
suivant.

**Le bouton Diaporama de `galerie.html` est passé de « Lancer le
diaporama » à « Diaporama », et déplacé juste au-dessus de la grille de
photos** (choix explicite de l'utilisateur, 26/08/2026 — il vivait jusque-là
dans `.gallery-hero`, loin au-dessus, sous les pastilles de filtre). Un
nouveau conteneur `.diaporama-bar` (centré, `margin-bottom: 24px`) enveloppe
le bouton dans le `<section class="section">` qui contient `.photo-grid`,
juste avant elle — même bouton `.diaporama-trigger`/`data-start-diaporama`,
seul son emplacement et son texte changent. `espace/galerie-club.php`
reprend ce même `.diaporama-bar` (voir plus haut).

**Le titre reste inscrit d'une photo à l'autre, dans la Galerie privée et
la Galerie du Club** (choix explicite de l'utilisateur, 26/08/2026 —
pratique pour déposer une série de photos sous le même titre sans le
retaper à chaque fois). Le dernier titre envoyé avec succès est gardé en
session (`$_SESSION['dernier_titre_galerie_privee']` /
`$_SESSION['dernier_titre_galerie_club']`, deux clés séparées puisque ce
sont deux formulaires distincts) et pré-rempli dans le champ `value=` à
l'affichage suivant du formulaire. Seul le titre est concerné — catégorie,
nom affiché et note repartent à vide à chaque fois, comme avant. Rien n'est
mémorisé en cas d'échec (mauvais format de fichier, catégorie invalide) :
seul un dépôt réussi met à jour la session, pour ne jamais réafficher un
titre qui aurait échoué à la place d'un titre valide précédent.

**Le bouton Diaporama de la lightbox est passé d'une simple flèche ronde
en haut à droite (à côté de Fermer) à un vrai bouton centré juste
au-dessus de la photo, avec le texte « Diaporama »** (choix explicite de
l'utilisateur, 26/08/2026, capture d'écran à l'appui). `.lightbox-diaporama`
porte désormais aussi la classe `.diaporama-trigger` (même icône, même
style que le bouton hors de la lightbox) et vit dans le HTML comme premier
enfant de `.lightbox-content`, avant `.lightbox-frame` — son
`text-align: center` centre le bouton sans CSS supplémentaire. Il n'est
plus `position: absolute` : un simple `margin: 0 0 16px` le sépare de la
photo. `reglerBoutonDiaporama()` (`js/main.js`) ne remplace plus le
contenu du bouton par un simple caractère (`▶`/`⏸`, perdu à chaque
diaporama puisque l'icône+texte harmonisés avec `.diaporama-trigger`
auraient disparu au premier `textContent =`) : il bascule maintenant
`innerHTML` entre deux constantes, une icône « lecture » + « Diaporama »
et une icône « pause » + « Pause », tout en gardant la classe
`.is-playing` (fond en dégradé) pour l'état actif. Générique, comme avant :
présent dans le HTML de la lightbox d'`index.html`, `galerie.html`,
`nos-sorties.html` et `espace/galerie-club.php`.

**Dépôt de plusieurs photos à la fois, par sélection multiple ou
glissé-déposé, dans la Galerie privée et la Galerie du Club** (choix
explicite de l'utilisateur, 26/08/2026 — « comme dans Documents du club »).
Même principe que `documents.php` : le champ fichier devient
`<input type="file" name="photos[]" multiple>`, qui accepte nativement le
glissé-déposé de plusieurs fichiers sans une ligne de JavaScript — aucune
zone de dépôt à construire, c'est un comportement natif du navigateur sur
tout `<input type="file" multiple>`. `fichiers_multiples()`
(`inc/televersement.php`, déjà utilisée par `documents.php`) éclate
`$_FILES['photos']` en une liste, un appel à `enregistrer_fichier_envoye()`
par photo ; contrairement aux documents (dont le titre reprend le nom de
fichier), le titre reste un champ saisi à la main, **partagé par toutes les
photos du dépôt** — de même pour la catégorie, le nom affiché et la note,
chacun choisi une seule fois pour tout le lot (labels du formulaire
complétés en conséquence : « s'applique à toutes les photos déposées
ici »). Un fichier refusé (mauvais format, plus de 1000 Ko) n'empêche pas
les autres d'être déposés — le message résume les deux, sur le même
principe que `documents.php` (« 2 photos ajoutées… » suivi de l'erreur du
fichier refusé, avec accord au singulier/pluriel selon le nombre de photos
réussies). Le titre gardé en session (voir plus haut) l'est aussi après un
dépôt multiple.

**Le glissé-déposé ratait souvent sa cible, et rien ne prévenait avant
l'envoi qu'une photo dépassait la taille maximale** (deux corrections,
choix explicite de l'utilisateur, 26/08/2026, en plus du relevé de la
limite à 1000 Ko ci-dessus) :
- Un `<input type="file">` nu n'accepte un glissé-déposé que si le fichier
  atterrit exactement sur son petit bouton natif « Choisir des fichiers » —
  un dépôt n'importe où ailleurs dans le champ (le label, le texte d'aide,
  le padding autour) était ignoré, ce qui donnait l'impression que le
  glissé-déposé ne fonctionnait pas du tout. `js/main.js` (bloc « Dépôt de
  fichiers ») élargit désormais la zone de dépôt effective au `.field`
  entier qui contient l'input, pour tout `<input type="file">` de la
  page (générique, comme l'œil du mot de passe) : les fichiers lâchés y
  sont réaffectés à `champ.files` via `DataTransfer`, puis un évènement
  « change » est déclenché pour que le reste du script (avertissement de
  taille ci-dessous) réagisse comme à une sélection normale. Repère visuel
  pendant le survol (`.field--survole`, contour en tirets). Un écouteur
  global `dragover`/`drop` — armé seulement quand le glissé transporte
  effectivement des fichiers (`dataTransfer.types` contient `"Files"`, pour
  ne jamais gêner un glissé de texte ordinaire ailleurs sur la page) —
  empêche aussi le navigateur de remplacer toute la page par l'image si le
  dépôt rate malgré tout la zone.
- Avertissement immédiat, avant tout envoi au serveur, si une photo choisie
  ou glissée dépasse la taille maximale : `<input data-taille-max="…"
  data-taille-max-lisible="…">` (posé par `galerie.php` et
  `galerie-club.php`, valeurs lues depuis `TAILLE_MAX_PHOTO_ADHERENT` côté
  PHP pour rester synchronisées) et un `<p data-avertissement-taille
  hidden>` juste après, que `js/main.js` remplit et affiche au moment de la
  sélection (`change`, déclenché aussi bien par un choix au clic que par un
  glissé-déposé) — nomme la ou les photos en cause. Confort seulement : la
  vérification qui fait foi reste côté serveur (`enregistrer_fichier_envoye()`),
  inchangée.

**Cet avertissement s'affiche en surimpression, directement sur l'endroit où
le dépôt a été tenté, et couvre désormais tout point de dépôt du site, pas
seulement les deux galeries** (choix explicite de l'utilisateur, 26/08/2026
— « je veux qu'un avertissement arrive en surimpression sur le même endroit
que celui où j'ai tenté de charger la photo ou le fichier »). `.form-avertissement`
(`css/style.css`) est passé d'un simple texte rouge sous le champ à un vrai
calque : `position: absolute; inset: 0;` par-dessus tout le `.field` (label,
champ et texte d'aide compris), fond rouge sombre semi-opaque, texte centré,
`pointer-events: none` — pour qu'un clic ou un glissé-déposé continue de
traverser le calque et d'atteindre le vrai `<input>` en dessous, permettant de
réessayer immédiatement sans d'abord faire disparaître l'avertissement. `.field`
porte maintenant `position: relative` (base commune à tous les champs, sans
effet sur ceux qui n'ont pas ce calque) pour servir de repère à `inset: 0`.
Piège rencontré en le construisant : `<p>` porte par défaut une marge
navigateur (`margin-block: 1em`, ~13,6px à cette taille de police) que
`inset: 0` ne neutralise pas — le calque se réduisait donc vers l'intérieur
et ne couvrait que la ligne du champ, laissant le label et le texte d'aide
dépasser dessous. Corrigé par un simple `margin: 0` sur `.form-avertissement`,
vérifié par comparaison de `getBoundingClientRect()` entre le calque et
`.field` (désormais identiques) puis par capture d'écran.

Le même trio d'attributs (`data-taille-max`, `data-taille-max-lisible`,
`<p class="form-avertissement" data-avertissement-taille hidden>`), déjà
posé sur `galerie.php`/`galerie-club.php`, a été étendu aux cinq points de
dépôt qui en étaient encore dépourvus — `js/main.js` n'a nécessité aucun
changement, son bloc « Dépôt de fichiers » étant déjà générique à tout
`input[type="file"]` portant ces attributs :
- `espace/documents.php` (`#documents`, plusieurs fichiers à la fois,
  `TAILLE_MAX_OCTETS`, 8 Mo) ;
- `espace/blog.php` (`#image`, photo de couverture à l'ajout d'un article,
  `TAILLE_MAX_OCTETS`) ;
- `espace/blog-article.php` (`#image`, même champ à la modification) ;
- `espace/sorties-a-venir.php`, sur les **deux** formulaires photo : celui
  d'ajout d'une sortie (`#photo`) et celui de modification, répété une fois
  par sortie affichée (`#photo-{id}`, dans `<details class="sortie-modifier">`).

Vérifié hors ligne (PHP+SQLite intégré, Playwright) sur les sept points de
dépôt : avertissement masqué avant sélection, affiché et couvrant exactement
le `.field` après un fichier trop lourd, `pointer-events: none` confirmé,
retrait de l'avertissement après un nouveau choix valide, et un glissé-déposé
simulé (`elementFromPoint` sur le centre du calque résout bien sur l'`<input>`
en dessous) qui aboutit normalement malgré l'avertissement affiché par-dessus.
Suite de 39 tests du blog rejouée sans régression sur une base neuve ; aucun
débordement horizontal à 390px sur les quatre pages modifiées.

**Cet avertissement porte une croix pour le fermer** (choix explicite de
l'utilisateur, 26/08/2026, même jour) : un bouton `.form-avertissement-fermer`
(cercle semi-transparent, `×`) en haut à droite du calque. Contrairement au
reste de l'avertissement (`pointer-events: none`, pour laisser passer clics et
glissé-déposé jusqu'au champ en dessous), la croix reprend `pointer-events:
auto` — c'est le seul élément cliquable du calque, et cliquer dessus se
contente de masquer l'avertissement (`avertissement.hidden = true`) sans
toucher au fichier déjà sélectionné dans le champ. `js/main.js` construit le
texte et la croix une seule fois par champ (un `<span>` pour le texte, un
`<button>` pour la croix, tous deux ajoutés au `<p class="form-avertissement">`
vide posé par chaque page) : seul le texte du `<span>` est mis à jour à
chaque sélection, la croix reste en place. Générique comme le reste du
dispositif — aucune page n'a eu besoin d'être modifiée, seuls `js/main.js` et
`css/style.css` ont changé. Vérifié hors ligne : la croix est cliquable
malgré le calque parent en `pointer-events: none`, la fermeture ne vide pas
le champ fichier, une nouvelle sélection trop lourde derrière rouvre bien
l'avertissement avec une croix de nouveau fonctionnelle, aucun chevauchement
avec le texte à 390px de large, suite de 39 tests du blog rejouée sans
régression.

**L'accueil affiche un bandeau dépliant « Prochaine sortie / réunion »**
(choix explicite de l'utilisateur, 23/08/2026), posé **sur la photo du grand
hero**, tout en haut (premier élément à l'intérieur de `.hero-full .container`,
avant `.hero-content` — pas dans une section séparée au-dessus, comme lors
d'un premier essai le même jour, revenu en arrière car pas assez « sur la
photo »). Fond semi-transparent flouté (`.bandeau-sortie--sur-photo`, même
traitement que l'en-tête collant) plutôt que le fond uni utilisé ailleurs,
pour rester lisible sur l'image. Même principe que les autres points d'accès
publics :
`infos-prochaine-sortie.php` (à la racine, hors de `espace/`) renvoie en
JSON la prochaine ligne de `sorties` dont `debut >= NOW()` (`{}` si aucune),
avec la date au format ISO (`debut_iso`) pour un parsing JavaScript fiable.
Le bandeau lui-même est un `<details class="bandeau-sortie">` **natif** —
pas de JavaScript pour l'ouverture/fermeture, seulement pour remplir le
texte une fois l'appel réussi (`js/main.js`) : le résumé (« Prochaine
sortie » ou « Prochaine réunion » selon la catégorie, titre, date/heure en
français via `toLocaleDateString('fr-FR', …)`) est toujours visible ; le
lieu, la description et un lien vers Sorties à venir n'apparaissent qu'une
fois déplié. Reste masqué (`hidden` posé en dur dans `index.html`) tant
qu'aucune sortie à venir n'est chargée ou que l'appel échoue (hors ligne,
préversion GitHub Pages qui ne peut pas exécuter PHP) — même philosophie
que la section Galerie de l'accueil, juste au-dessus.

**Le fond d'une vignette (`.photo-frame`) est posé via un attribut `style="…"`
construit en JavaScript (`buildPhotoCard()`), pas via `element.style` :**
piège rencontré le 20/08/2026 — `photoBackground()` entourait l'URL de
guillemets doubles (`url("…")`), qui refermaient prématurément l'attribut
`style="…"` lui-même délimité par des guillemets doubles, vidant la
vignette de toute photo réelle (le titre et la fiche s'affichaient quand même,
seul le fond disparaissait). Corrigé en guillemets simples autour de l'URL.
Tout texte inséré via `innerHTML` dans une carte de photo (titre, nom
affiché, catégorie) passe par `echapperHtml()` — un titre ou un nom contenant
`&`, `<` ou `"` (saisi par un adhérent) casserait sinon le HTML généré.

**Blog du Club (`espace/blog.php`, `espace/blog-article.php`)**, ajouté le
25/08/2026 à la demande explicite de l'utilisateur, qui a fourni une capture
d'écran du blog d'un club voisin (imageinperigny.fr) comme référence de
présentation : liste d'articles (titre, méta date/auteur/catégorie, extrait
+ vignette) à gauche, colonne latérale « Articles récents » (5 derniers
titres) et « Catégories » à droite. **Page publique comme l'agenda**,
malgré son emplacement dans `espace/` : `exige_connexion()` n'est jamais
appelée pour lire, seule la rédaction (formulaire « Ajouter un article »,
Modifier, Supprimer) exige `exige_gestionnaire()` — l'utilisateur en sera
la seule rédactrice, mais le rôle éditeur en profite aussi, par cohérence
avec le reste du site (documents, agenda, galerie du club).

Deux tables : `categories_blog` (liste à plat, comme `categories_galerie`,
gérée par un responsable depuis `parametres.php` — ajouter/renommer/
supprimer, refusé si des articles restent classés dedans) et
`articles_blog` (`titre`, `extrait`, `contenu`, `image` — photo de
couverture facultative —, `categorie_id`, `auteur_nom`, `depose_par`,
`cree_le`). Neuf catégories semées par défaut (`CATEGORIES_BLOG_PAR_DEFAUT`,
`inc/migration.php`), reprises du blog de référence : À la une, Club photo,
Concours, Culture photographique, Exposition, Festival photo, Livre photo,
Stage, Travail d'auteur.

**Pas d'éditeur de texte riche** (cohérent avec un site sans build ni
dépendance JavaScript externe) : le contenu se saisit en texte brut dans le
formulaire, et `texte_riche_html()` (`inc/blog.php`) le transforme à
l'affichage — échappé d'abord, puis `**texte**` devient `<strong>`, une
adresse `http(s)://` collée dans le texte devient un lien cliquable
(nouvel onglet, `rel="noopener noreferrer"`), puis une ligne vide sépare
deux paragraphes (`<p>`). Même principe minimal que `corps_html()` dans
`inc/mail.php` pour les e-mails de notification, réécrit séparément ici
(paragraphes en plus, pas seulement `nl2br`) plutôt que partagé, les deux
usages étant assez différents (e-mail vs longue page HTML). Le champ
« Résumé » (extrait) reste du texte brut affiché sans mise en forme (`e()`
simple, pas de gras ni de lien) — laissé vide, il est calculé
automatiquement par `extrait_auto()` : premier paragraphe du contenu,
`**` retirées (elles n'ont sinon aucun sens hors mise en forme), coupé à
220 caractères au dernier espace pour ne jamais trancher un mot.

**Le lien détecté restait invisible** (piège signalé par l'utilisateur le
25/08/2026, corrigé le jour même) : la règle CSS générale du site
(`a { color: inherit; text-decoration: none; }`) fait qu'un lien inséré
dans le corps d'un article se fondait dans le texte environnant — présent
dans le HTML (`<a href="...">`, donc bien cliquable), mais rien ne le
distinguait visuellement, ce qui le faisait paraître non cliquable.
`.blog-contenu a` (couleur `--accent-3`, soulignée, `--accent` au survol —
même palette que `.blog-meta a`/`.blog-lire`) rend maintenant ce lien
reconnaissable, en plus d'être fonctionnel.

La photo de couverture suit le même principe que la Galerie du Club :
aucun recadrage serveur, stockée dans `espace/photos_blog/` (fermé par
`.htaccess`, comme `photos_club/`), servie par `telecharger.php?type=blog`
— **public**, comme `type=sortie`/`type=galerie_club`, contrairement à
`type=photo`/`document` qui exigent une connexion. À la modification d'un
article, la photo n'est remplacée que si un nouveau fichier est envoyé
(même logique que la photo de sortie dans `sorties-a-venir.php`) ; à la
suppression, le fichier est retiré du disque.

**Remplacer une photo de couverture restait invisible côté navigateur**
(piège signalé par l'utilisateur le 25/08/2026, corrigé le jour même) :
`telecharger.php` renvoie `Cache-Control: private, max-age=600` pour tout
type de fichier, y compris `blog`/`sortie` — et l'URL
(`telecharger.php?type=blog&id={id}`) ne change jamais quand la photo est
remplacée, puisqu'elle est bâtie sur l'identifiant de l'article, pas sur le
fichier. Le navigateur continuait donc d'afficher l'ancienne image jusqu'à
expiration du cache. `version_fichier()` (`inc/page.php`, même principe que
`lien_css()`/`lien_js()`) ajoute un suffixe `&v={filemtime}` à cette URL
partout où elle est rendue (`blog.php`, `blog-article.php`,
`sorties-a-venir.php` — seuls endroits où un fichier peut être remplacé
sans changer d'identifiant) : la version change dès que le fichier change,
donc le navigateur redemande l'image. Vérifié avec le même banc d'essai
PHP+SQLite que le reste du blog : publier une photo, la remplacer, l'URL
change bien et sert la nouvelle image octet pour octet.

Pagination (8 articles par page, `BLOG_ARTICLES_PAR_PAGE`) et filtre par
catégorie (`?categorie=ID`) en paramètres d'URL classiques, sans
JavaScript — même philosophie que le calendrier d'`agenda.php`. Le filtre
ne s'applique qu'à la liste principale : la colonne « Articles récents »
reste volontairement globale (comme sur le blog de référence), donc un
article y reste visible même en dehors de la catégorie affichée.

**Le widget « Catégories » commence toujours par « Tous les articles »**
(choix explicite de l'utilisateur, 25/08/2026), lien statique vers
`blog.php` sans filtre, marqué `aria-current="page"` quand aucune
catégorie n'est active. Ce n'est **pas** une ligne de `categories_blog` —
l'utilisateur a demandé « une nouvelle catégorie » mais une vraie catégorie
aurait exigé de reclasser chaque article dedans, ce qui contredit
justement l'idée de « voir tout les articles » ; c'est donc un simple lien
de navigation ajouté en tête de liste (dans `blog.php` **et**
`blog-article.php`, qui partagent la même sidebar), pas une entrée
gérable depuis `parametres.php`.

**Carte « Blog » sur le tableau de bord** (`espace/index.php`) : ajoutée
juste après « Galerie du Club », avant les cartes réservées aux
gestionnaires (Adhérents, Réglages du site) — un contenu public comme les
autres cartes de cette première rangée, donc pas conditionné par
`est_gestionnaire()`.

**Publier un article prévient tous les adhérents par e-mail** (choix
explicite de l'utilisateur, 28/08/2026 : « je veux que quand un nouvel
article est ajouté au Blog un mail soit envoyé aux adhérents »), même
principe que la notification de nouvelle sortie
(`sorties-a-venir.php`, voir plus haut) : dès qu'un responsable ou un
éditeur publie un article (`action` implicite du formulaire de
`espace/blog.php`), un e-mail est envoyé à tous les adhérents `valide=1
actif=1` ayant une adresse renseignée (`envoyer_mail()`, un e-mail par
adhérent, échoue silencieusement comme les autres notifications du site —
voir `inc/mail.php`). Le message reprend le titre et l'extrait de
l'article (celui saisi, ou `extrait_auto()` si laissé vide — la même
valeur que celle enregistrée en base) et un lien direct vers
`blog-article.php?id=…` (`SITE_URL`, `inc/mail.php`). Le message de
confirmation affiché au responsable/éditeur (« Article publié. Un e-mail a
été envoyé aux adhérents. ») reprend le même principe que celui de
sorties-a-venir.php.

Testé de bout en bout (28/08/2026) avec le même banc d'essai PHP+SQLite que
le reste du blog : connexion, publication d'un article, exactement les
adhérents `valide=1 actif=1` avec e-mail renseigné notifiés (vérifié sur un
jeu de cinq comptes couvrant chaque cas d'exclusion — sans e-mail, inactif,
en attente de validation), sujet et lien de l'e-mail corrects. Deux pièges
rencontrés en construisant ce banc d'essai, sans rapport avec la fonction
elle-même :
- **Un piège déjà documenté (`UNIX_TIMESTAMP(NULL)`, voir plus bas) a été
  réintroduit par erreur** dans le simulateur SQLite de ce test — corrigé
  en distinguant explicitement « aucun argument » (l'instant présent) d'
  « argument `NULL` explicite » (`NULL`), plutôt que de tester `=== null`
  sur un paramètre par défaut, qui ne peut pas faire cette distinction.
- **Le témoin `espace/inc/.schema-a-jour` (voir plus bas, « Les migrations
  sont automatiques ») avait survécu à la suppression de la base SQLite**
  entre deux essais : `appliquer_migrations()` le trouvait à jour et ne
  rejouait donc aucune migration sur la base pourtant neuve et vide, ce qui
  laissait `categories_blog` sans aucune catégorie. Un banc d'essai qui
  recrée la base doit donc aussi supprimer ce témoin, sans quoi il faut
  penser à le faire à la main à chaque fois.
- **Une vraie découverte, dans `inc/schema.sql` cette fois** (pas propre au
  banc d'essai) : quatre commentaires SQL contenaient un point-virgule en
  plein milieu de ligne (ex. « `catégorie ; \`categorie\` (VARCHAR) est
  l'ancien` »). `installation.php` découpe `schema.sql` sur les
  points-virgules avant d'exécuter chaque instruction (`explode(';',
  $sql)`) — un point-virgule à l'intérieur d'un commentaire, s'il n'est pas
  suivi de la fin de la ligne, casse ce découpage et produit un fragment
  invalide (`\`categorie\` (VARCHAR) est l'ancien...`), qui fait échouer
  toute la création des tables. Resté invisible jusqu'ici parce que
  `installation.php` ne s'est joué qu'une seule fois en production, avant
  l'ajout de ces commentaires — un point-virgule en fin de ligne de
  commentaire (juste avant le retour à la ligne) ne pose lui aucun
  problème, seul un point-virgule suivi d'autre texte sur la même ligne est
  dangereux. Corrigé en remplaçant les quatre points-virgules fautifs par
  un tiret cadratin — purement une correction de commentaire, aucune
  colonne ni donnée n'est concernée, sans risque pour le site déjà en
  ligne (dont l'installation est verrouillée) mais qui aurait bloqué toute
  réinstallation neuve.

Testé de bout en bout (25/08/2026) avec un vrai serveur PHP intégré
(`php -S`) branché sur SQLite plutôt que MySQL — même principe que le banc
d'essai déjà utilisé pour l'authentification, complété ici par deux détails
propres à ce test : les instructions SQL embarquées dans `migration.php`/
`schema.sql` (`ENGINE=InnoDB…`, `INT AUTO_INCREMENT PRIMARY KEY`,
`UNIQUE KEY nom (...)`, `INSERT IGNORE`) sont converties à la volée en leur
équivalent SQLite pour cette copie de test seulement, jamais dans le dépôt ;
et `NOW()`/`UNIX_TIMESTAMP()`, utilisées par `auth.php` mais absentes de
SQLite, sont ajoutées via `PDO::sqliteCreateFunction()` — piège rencontré
au passage : `UNIX_TIMESTAMP(NULL)` doit renvoyer `NULL` (comme MySQL), pas
l'instant présent, sans quoi `signaler_presence()` déconnecte
immédiatement toute session fraîchement ouverte (un `deconnecte_le` NULL en
base est alors lu comme une coupure à l'instant même). Une fois ce piège de
test corrigé, connexion, publication (avec photo), affichage (gras,
paragraphes), modification, filtre par catégorie, pagination, CSRF, accès
réservé aux gestionnaires, suppression (fichier compris) et gestion des
catégories (ajout, renommage, suppression refusée si utilisée) passent
tous ; vérifié aussi au rendu Chromium, connecté et anonyme.

**`documents.php` range les documents par rubrique et catégorie, éditables
par un responsable** (choix explicite de l'utilisateur, 20/08/2026 — la
liste de départ était Débuter la photo, Ateliers techniques du club,
Thèmes photographiques et Administration du club, mais un responsable peut
désormais la renommer et la compléter sans intervention sur le dépôt : ce
n'est plus une constante figée dans le code, comme au tout début de cette
fonctionnalité le même jour). Deux tables, `rubriques_documents` et
`categories_documents` (voir `schema.sql`), semées une seule fois avec la
classification de départ par `RUBRIQUES_DOCUMENTS_PAR_DEFAUT`
(`inc/migration.php`) — semis qui ne se rejoue jamais une fois qu'une
rubrique existe, pour ne pas réintroduire une rubrique qu'un responsable
aurait supprimée. `inc/documents_categories.php` porte les deux fonctions
de lecture, `rubriques_documents($pdo)` (toute la classification, mise en
cache pour la durée de la page) et `categorie_document($pdo, $id)` (rubrique
et libellés d'une catégorie, pour valider un `categorie_id` posté). La
gestion (renommer/ajouter/supprimer une rubrique ou une catégorie) vit dans
`parametres.php`, sous le grand formulaire des coordonnées du club — un
formulaire par action, sur le même principe que `adherents.php`.

**Ces sections de gestion passent sur deux colonnes dès que la largeur
le permet** (choix explicite de l'utilisateur, 25/08/2026) : les trois
pavés « Rubriques des documents », « Catégories des galeries » et
« Catégories du blog » (chacun un `.form-card.reglage-rubriques`) sont
englobés dans un `.reglages-grid` (`display: grid;
grid-template-columns: repeat(auto-fit, minmax(400px, 1fr))`) plutôt que
chacun forcé à `max-width:640px` et empilé verticalement — deux colonnes
sur un écran d'ordinateur (le conteneur fait 1180px), une seule en
dessous de ~832px de large (l'auto-fit repasse alors chaque pavé en
pleine largeur, sans media query dédiée). Les pavés ne sont pas de même
hauteur (« Rubriques des documents » est le plus grand des trois) :
`align-items: start` les laisse chacun à sa propre hauteur plutôt que de
les étirer, au prix d'un peu d'espace vide sous le pavé le plus court de
chaque ligne — accepté comme un compromis simple plutôt qu'une mise en
page façon « masonry ». Seul `parametres.php` est concerné ; le grand
formulaire des coordonnées du club au-dessus (Lieu de réunion, Contact,
Horaires, Présentation) reste un formulaire unique, non scindé en
plusieurs pavés, donc hors de propos pour cette mise en colonnes.

**« Catégories du blog » doit rester juste sous « Catégories des
galeries », sans espace entre les deux** (choix explicite de l'utilisateur,
25/08/2026, deux essais le même jour) : un premier essai a placé le pavé
dans la bonne colonne via `grid-column: 2`, mais la hauteur de chaque
« rangée » de `.reglages-grid` est dictée par son plus grand pavé — ici
« Rubriques des documents », de loin le plus haut des trois — ce qui
laissait un grand vide entre « Catégories des galeries » et « Catégories
du blog » plutôt qu'un enchaînement direct ; l'utilisateur l'a signalé
(« j'avais demandé en dessous, juste en dessous »). Les deux pavés sont
donc regroupés dans un conteneur indépendant, `.reglages-col-droite`
(`display: flex; flex-direction: column; gap: 32px`), que `.reglages-grid`
traite comme un second bloc à côté de « Rubriques des documents » — sa
propre hauteur ne dépend alors que du contenu des deux pavés qu'il
contient, plus d'un calage sur la hauteur du bloc voisin. Cette
construction n'a plus besoin de media query dédiée (contrairement au
premier essai) : `.reglages-col-droite` suit la même bascule 1/2 colonnes
que « Rubriques des documents » via `.reglages-grid`, et en dessous du
seuil (~832px de grille, container `.container` moins 48px de padding),
les deux pavés qu'il contient restent simplement empilés dans l'ordre du
HTML, comme n'importe quel autre contenu en une seule colonne.

Supprimer
une rubrique qui contient encore des catégories, ou une catégorie qui
contient encore des documents, est refusé avec un message explicite plutôt
que de les orpheliner ou de les faire disparaître silencieusement ; renommer
est en revanche toujours permis (les documents suivent, puisqu'ils ne
référencent qu'un identifiant). Sur `documents`, `categorie_id` (entier,
référence vers `categories_documents`) fait foi pour l'affichage ;
`categorie` (texte libre) est l'ancien classement, gardé tel quel pour ne
perdre aucune donnée mais plus lu par le code — une bascule automatique dans
`appliquer_migrations()` retrouve, pour chaque document déposé avant ce
changement, la catégorie de même nom et lui pose son `categorie_id`. Un
document dont l'ancienne catégorie ne correspond plus à rien de connu (ou
qui n'a jamais eu de `categorie_id` valide) atterrit dans un groupe « Autres
documents » en bas de page plutôt que de disparaître. Le formulaire de dépôt
accepte **plusieurs fichiers à la fois** (`<input type="file" name="documents[]"
multiple>`, choix explicite de l'utilisateur, 21/08/2026) : chacun devient un
document séparé, dans la même rubrique et avec la même description
facultative choisies une seule fois pour tout le lot. Il n'y a plus de champ
Titre — **le titre de chaque document reprend le nom de son fichier, sans
l'extension** (`pathinfo($nom, PATHINFO_FILENAME)`), ce qui n'aurait plus de
sens à saisir à la main dès qu'on dépose plusieurs fichiers d'un coup.
`fichiers_multiples()` (`inc/televersement.php`) remet à plat la structure
que PHP donne à un champ multiple (`$_FILES['documents']['name'][i]`, etc.)
en une liste de fichiers, un par un, pour appeler `enregistrer_fichier_envoye()`
une fois par fichier ; un fichier refusé (mauvais format, trop lourd) n'empêche
pas les autres d'être déposés — le message affiché résume les deux
(« 3 documents ajoutés… » suivi de l'erreur du fichier refusé). Le formulaire
choisit la catégorie par `categorie_id` (menu `<select>` avec un
`<optgroup>` par rubrique) ; la page regroupe les documents par rubrique
puis catégorie, dans l'ordre de `rubriques_documents()` (pas celui de la
requête SQL, reconstruit depuis la base donc toujours à jour même après un
renommage). **Depuis le 23/08/2026** (choix explicite de l'utilisateur, qui
a fourni une capture de la structure réelle de ses rubriques/catégories
comme référence), la page ne montre plus de texte d'introduction expliquant
la classification : à la place, dès qu'au moins une rubrique existe, un
**sommaire cliquable** (`.documents-index`, juste après le champ de
recherche remonté en tout premier) liste toutes les rubriques et leurs
catégories en pastilles, chacune pointant via une ancre (`#categorie-{id}`)
vers sa section plus bas sur la page. Contrairement à avant, **toutes** les
rubriques et catégories s'affichent désormais dans la liste détaillée en
dessous, même sans aucun document — une catégorie vide montre juste
« Aucun document pour l'instant dans cette catégorie. » (`.categorie-vide`)
plutôt que de disparaître entièrement : le sommaire ne doit jamais pointer
vers une ancre absente. Le champ de recherche (affiché dès qu'au moins une
rubrique existe, avant le sommaire) filtre les lignes par titre en
JavaScript pur, `data-titre` sur chaque `<li class="document-ligne">`, sans
aller-retour serveur — script inline en bas de `documents.php`, sur le même
principe que l'ancien `.auth-tabs` de `connexion.php` (page-spécifique, pas
dans `main.js`) ; il masque aussi une catégorie entière (donc sa pastille
« vide ») si aucune de ses lignes ne correspond à la recherche. Le HTML de
chaque ligne est factorisé dans `inc/document-ligne.php`, inclus une fois
par sous-catégorie non vide.

**Connexion et inscription vivent sur deux pages séparées**,
`connexion.php` et `inscription.php` (choix explicite de l'utilisateur,
20/08/2026, d'après sa maquette Word — revient sur une tentative du
18/08/2026 qui les avait réunies en deux onglets sur une seule page ; cette
version à onglets a été codée puis constatée comme ne correspondant pas au
dessin, donc défaite le jour même de sa découverte). `connexion.php` ne
contient que le formulaire de connexion, avec un lien « Créer un compte » en
bas vers `inscription.php` ; `inscription.php` ne contient que le formulaire
d'inscription (plus la logique d'INSERT et les e-mails, ancien contenu du
bloc `formulaire === 'inscription'` de `connexion.php`), avec un lien « Se
connecter » en bas vers `connexion.php`. Chaque page redirige silencieusement
vers `index.php` si l'utilisateur est déjà connecté. Le menu déroulant
« Espace Adhérent » (pages statiques et `espace/inc/page.php`) pointe
« Connexion » vers `connexion.php` et « S'inscrire » vers `inscription.php`
directement — plus de paramètre `?onglet=`. N'importe qui peut créer un
compte (prénom, nom, pseudo, e-mail **obligatoire**, téléphone/code
postal/ville facultatifs, mot de passe) ; le pseudo devient `identifiant`
(unicité vérifiée avant l'INSERT, message dédié plutôt qu'une erreur SQL
brute).

**Un compte auto-inscrit doit être validé par un responsable ou un éditeur
avant de pouvoir se connecter** (choix explicite de l'utilisateur, remis en
place le 23/08/2026 après un aller-retour le même jour : une version
« activation immédiate » testée entre-temps a laissé un adhérent se
connecter sans validation, ce qui n'était pas voulu — revenu au
comportement du 18/08/2026). `inscription.php` force `valide = 0` à
l'INSERT ; `tenter_connexion()` (`inc/auth.php`) refuse la connexion tant
que `valide = 0`, avec un message dédié — « Votre inscription est en
attente de validation par un responsable. » — qui ne compte pas comme un
échec dans le compteur de blocage (le mot de passe était correct). Dans
`adherents.php`, un bouton **Valider** (action `valider`, réservée à
`est_gestionnaire()` comme le reste de la page) active le compte et
prévient la personne par e-mail ; il ne s'affiche que sur un compte non
validé (badge ambre « en attente de validation » sur sa ligne, comptes non
validés remontés en tête de liste — `ORDER BY valide ASC, actif DESC,
nom`). Pas d'action inverse (« invalider ») : un compte déjà validé qu'on
ne veut plus se retire avec **Supprimer** plutôt que d'être renvoyé en
attente, ce qui n'aurait pas de sens.

**Un bouton « Supprimer » sur chaque ligne** (action `supprimer`, choix
explicite de l'utilisateur, 23/08/2026) permet de rejeter une inscription
en attente ou de retirer un compte déjà actif. La suppression est
**définitive** (confirmation JavaScript avant envoi) : la ligne
`adherents` est retirée avec `DELETE`, mais les photos et documents déjà
déposés par la personne restent — leur colonne `depose_par` passe à `NULL`
(clés étrangères `ON DELETE SET NULL` dans `schema.sql`, déjà en place) ;
seules ses inscriptions à des sorties disparaissent avec elle (`ON DELETE
CASCADE`). Un adhérent ne peut pas se supprimer lui-même (même garde-fou
que les autres actions de cette page), et **supprimer ou retirer le rôle
responsable du dernier responsable actif restant est refusé** —
`dernier_responsable_actif()` dans `adherents.php`, pour ne pas se
retrouver sans personne capable d'ouvrir `parametres.php` ; cette garde ne
s'applique jamais à une *promotion* (nommer quelqu'un responsable ou
éditeur reste toujours possible).

Trois e-mails accompagnent ce cycle, envoyés par `envoyer_mail()`
(`inc/mail.php`, `mail()` natif de PHP — pas de PHPMailer/Composer, cohérent
avec un projet sans build ; un échec d'envoi est seulement consigné dans
`error_log`, il ne doit jamais faire échouer l'inscription ou la
validation) :
1. à l'inscription, vers l'e-mail du club (`parametres_site.email`, réglable
   dans `parametres.php`) — nouvelle inscription à valider ;
2. à l'inscription, vers la personne inscrite — confirmation que son compte
   est enregistré et en attente, **avec un rappel en gras** de vérifier
   aussi son dossier de spams si l'e-mail de validation à venir n'arrive
   pas (choix explicite de l'utilisateur, 23/08/2026) ;
3. à la validation (action `valider` dans `adherents.php`), vers la
   personne — reprend **textuellement** le message affiché à l'écran au
   responsable/éditeur qui valide (`$message_validation`, une seule
   variable utilisée à la fois pour `definir_message()` et dans le corps de
   l'e-mail, choix explicite de l'utilisateur, 23/08/2026 — une seule
   formulation à tenir à jour), plus le même rappel spams en gras.

**Les e-mails de `envoyer_mail()` sont envoyés en HTML** (`Content-Type:
text/html`), pas en texte brut comme avant le 23/08/2026 — seul moyen
d'afficher du gras. Les appelants continuent d'écrire un corps en texte
brut avec des retours à la ligne normaux (`\n`) : `corps_html()`
(`inc/mail.php`) échappe l'ensemble (`htmlspecialchars`, avant toute autre
transformation — protège contre l'injection si un champ dynamique comme un
nom contenait `<`/`>`), convertit une convention Markdown minimale
`**texte**` en `<strong>texte</strong>`, puis les retours à la ligne en
`<br>` (`nl2br`). Pour mettre un passage en gras dans un futur e-mail, il
suffit de l'entourer de `**` dans le texte passé à `envoyer_mail()` — rien
d'autre à changer.

**Nommer/retirer un rôle (responsable ou éditeur) se fait via une case à
cocher carrée avec une croix**, pas un lien texte (choix explicite de
l'utilisateur, 23/08/2026 — remplace les anciens liens « Nommer/Retirer
responsable ») : `.case-role` dans `adherents.php`, un `<button
type="submit">` carré (20×20px) posant `.case-role-actif` quand le rôle est
actif — fond en dégradé et croix blanche visible — et vide sinon (croix
transparente). Deux cases indépendantes par ligne (`.case-role-ligne`, une
pour Responsable, une pour Éditeur), chacune dans son propre `<form>` qui
POST l'action correspondante (`basculer_admin`/`basculer_editeur`) au clic
— aucun JavaScript nécessaire, même principe que les autres actions de
cette page.

**Un petit texte sous le tableau rappelle le rôle de chaque niveau**
(choix explicite de l'utilisateur, 28/08/2026) : Adhérent, Éditeur,
Responsable — reprend `.form-note` (texte discret déjà utilisé ailleurs
sur le site, aucune nouvelle règle CSS) plutôt que de dupliquer les cases
à cocher du formulaire de création un peu plus haut sur la page, qui ne
sont visibles qu'en dépliant « Créer un compte adhérent ».

**Le champ `From:` de ces e-mails est toujours `noreply@myfocal.online`,
jamais l'adresse de contact du club** (piège rencontré et corrigé le
23/08/2026 : aucun e-mail de notification n'arrivait, même pas en spam).
Hostinger envoie `mail()` sous le domaine `myfocal.online` ; un `From:` qui
prétend venir d'une autre adresse (l'e-mail du club, une adresse Gmail dans
ce cas) échoue à la vérification DMARC du destinataire — Gmail rejette
alors le message en silence, avant même de le classer en spam. L'adresse de
contact réelle (`parametres_site.email`) reste utilisée en `Reply-To`, pour
que répondre au mail atterrisse au bon endroit.

`code_postal` et `ville` sont des colonnes ajoutées à `adherents` (voir
`schema.sql` et `COLONNES_ATTENDUES` dans `migration.php`) ; rien d'autre ne
les affiche pour l'instant (l'annuaire ne montre encore que
nom/identifiant/contact).

**Téléphone, adresse, code postal, ville et nom du boîtier sont tous
obligatoires à l'inscription** (choix explicite de l'utilisateur,
28/08/2026, en deux temps le même jour : téléphone et boîtier avaient
d'abord été laissés facultatifs, avant que l'utilisatrice ne demande
explicitement qu'ils le deviennent aussi — « le téléphone et le type de
boîtier ne sont pas facultatifs »). `adresse` et `boitier` (nouvelles
colonnes) et `code_postal`/`ville`/`telephone` (déjà existantes, voir
juste au-dessus) sont désormais tous exigés par `inscription.php` avant
l'INSERT — seul l'e-mail restait déjà obligatoire depuis le début.
Comme pour les autres champs obligatoires du site, la contrainte est
posée côté formulaire (validation PHP + attribut `required`), pas en
`NOT NULL` en base : un compte déjà existant avant ce changement n'a pas
ces informations, et les colonnes doivent rester nullables pour ne pas le
casser.

**Le mot de passe (inscription et changement depuis l'Annuaire) doit
contenir une majuscule et un caractère spécial**, en plus des 10
caractères déjà exigés (choix explicite de l'utilisateur, 28/08/2026).
Appliqué aux deux seuls endroits où un adhérent choisit lui-même son mot
de passe — `inscription.php` et le formulaire « Changer mon mot de passe »
de `annuaire.php` — pour qu'un changement ne permette pas de revenir à un
mot de passe plus faible que celui exigé à la création du compte. Même
regex des deux côtés (`/[A-Z]/` et `/[^a-zA-Z0-9]/`, PHP) et attribut HTML
`pattern="(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{10,}"` côté client, pour un retour
immédiat dans le navigateur avant même l'envoi du formulaire. Le mot de
passe **provisoire** généré par un responsable/éditeur depuis
`adherents.php` (`mot_de_passe_provisoire()`) n'est pas concerné — il n'est
pas choisi par l'adhérent, et reste volontairement sans caractère ambigu
(0/O, 1/l/I) pour rester facile à recopier à la main.

**Export Excel des adhérents, réservé au responsable** (choix explicite de
l'utilisateur, 28/08/2026, « je veux qu'à partir des inscriptions tu
fasses un fichier excel avec toutes ces informations disponible pour
l'administrateur ») : bouton « Télécharger la liste des adhérents (Excel) »
en haut de `adherents.php`, visible seulement pour `est_administrateur()`
(jamais un éditeur — cohérent avec `parametres.php`, le seul autre endroit
du site réservé au seul rôle responsable). `espace/export-adherents.php`
construit le fichier et le sert en téléchargement (`Content-Disposition:
attachment`, pas de page HTML) : identifiant, nom, e-mail, téléphone,
adresse, code postal, ville, nom du boîtier, rôle, statut, validé, date
d'inscription, dernière connexion — un adhérent par ligne.

**Le fichier `.xlsx` est généré sans aucune dépendance externe**
(`espace/inc/xlsx.php`, `generer_xlsx()`) — même philosophie que
`inc/mail.php` (`mail()` natif plutôt que PHPMailer) : pas de Composer, pas
de PHPSpreadsheet. Un `.xlsx` n'est qu'une archive zip de fichiers XML
(format OOXML/SpreadsheetML) ; `ZipArchive` (extension PHP standard,
présente sur l'hébergement mutualisé Hostinger) suffit à la construire à
la main — six fichiers minimum (`[Content_Types].xml`, `_rels/.rels`,
`xl/workbook.xml`, `xl/_rels/workbook.xml.rels`, `xl/styles.xml`,
`xl/worksheets/sheet1.xml`), cellules en texte brut (`t="inlineStr"`, le
texte écrit directement dans la cellule plutôt que dans une table de
chaînes partagées à gérer séparément) — largement suffisant pour un export
de données tabulaire, pas un vrai éditeur de classeur. `texte_xml()`
échappe les entités XML et retire les caractères de contrôle interdits en
XML 1.0 qu'un champ saisi à la main pourrait contenir. Validé hors ligne
(28/08/2026) avec `openpyxl` (Python) : ouverture du fichier généré,
lecture cellule par cellule, contenu exact retrouvé y compris avec des
caractères spéciaux, accents, guillemets, esperluettes et chevrons dans les
données (`&`, `<`, `>`, `"`) et un caractère de contrôle injecté
volontairement (bien supprimé) — `LibreOffice --headless --convert-to`
s'est révélé cassé dans ce sandbox (échoue aussi sur un fichier généré par
openpyxl lui-même, donc sans rapport avec ce générateur) et n'a pas pu
servir de second recours.

**Le fichier Excel est mis en forme** (choix explicite de l'utilisateur,
28/08/2026, même jour : « je veux que tu formates le fichier Excel de
façon à ce qu'il soit plus lisible avec en titre FOCAL CLUB TURBALLAIS »).
`generer_xlsx()` prend désormais un titre et un sous-titre, affichés sur
un bandeau bleu nuit (`#0F172A`, la couleur d'accent du site — voir
`css/style.css`) fusionné sur toute la largeur du tableau : « FOCAL CLUB
TURBALLAIS » en grand (18pt, blanc, gras) puis « Liste des adhérents —
Export du {date du jour} » juste en dessous, en italique plus clair. La
ligne d'en-tête des colonnes reprend le même bandeau (gras, blanc), et les
lignes de données sont zébrées (une claire sur deux, `#F1F5F9`) pour rester
lisibles sur un grand tableau — `export-adherents.php` passe un tableau de
largeurs de colonnes (en caractères) à `generer_xlsx()`, sans quoi Excel
retombe sur sa largeur par défaut, trop étroite pour la plupart des champs.
La ligne d'en-tête reste figée à l'écran quand on fait défiler le tableau
(`<pane>` gelé sous la ligne 4). **« Inscrit le » et « Dernière
connexion » sont affichées en date courte** (`26-06-2026`, `date('d-m-Y',
...)`) plutôt que la formulation longue en français utilisée ailleurs sur
le site (`date_en_francais()`) — demandé explicitement pour ces deux
colonnes seulement. Revalidé hors ligne avec `openpyxl` après ce
changement : bandeau, couleurs, tailles de police, largeurs de colonnes,
figeage de la ligne d'en-tête et dates raccourcies tous corrects sur un
export réel généré par la page (deux adhérents, dont un jamais connecté).

**Le bandeau s'affichait en points gris avec du texte blanc au lieu du bleu
nuit attendu** (signalé par l'utilisateur le 28/08/2026, même jour) : piège
classique du format OOXML — Excel réserve l'**index 1** de la liste des
fonds (`<fills>`) au motif intégré « gray125 » (quadrillage gris à 12,5 %)
et l'affiche à cet index **quelle que soit la définition réellement
écrite**, même un fond uni bleu nuit comme ici. Le premier essai plaçait le
fond bleu nuit du bandeau justement à cet index 1 (juste après l'index 0,
réservé lui à « aucun fond » — respecté dès le départ), pensant qu'il
suffisait de le déclarer soi-même ; seul le texte (couleur de police)
suivait la vraie définition, d'où des lettres blanches lisibles sur un
quadrillage gris plutôt que sur le bleu nuit voulu. Corrigé en insérant
explicitement `<fill><patternFill patternType="gray125"/></fill>` à
l'index 1 (jamais réutilisé par aucun style) et en décalant le fond bleu
nuit et le fond gris clair du zébrage aux index 2 et 3 — `cellXfs` mis à
jour en conséquence (`fillId="2"`/`fillId="3"`). Revalidé avec `openpyxl` :
`fill_type`/`fgColor` du bandeau et des lignes zébrées lisent bien
`FF0F172A`/`FFF1F5F9`, et l'ordre des fonds dans `xl/styles.xml` confirmé
par inspection XML directe (index 1 bien du `gray125` inutilisé).

**Toutes les cellules sont centrées et entourées d'une bordure fine, et la
colonne « Dernière connexion » élargie** (choix explicite de l'utilisateur,
28/08/2026, même jour : « la colonne M est un peu petite, je voudrais que
tous les textes soient centrés et que chaque case ait un entourage »).
`generer_xlsx()` porte désormais une seconde bordure dans `<borders>` (fine,
`#94A3B8` — un gris-bleu discret cohérent avec la palette du site plutôt
qu'un noir dur) appliquée aux cinq formats de cellule
(`cellXfs`, `borderId="1"`), et l'alignement `horizontal="center"
vertical="center"` — déjà présent sur le bandeau — est étendu aux deux
styles de données (normal et zébré), qui n'avaient jusque-là aucun
alignement explicite. `export-adherents.php` porte la largeur de la
dernière colonne (M, « Dernière connexion ») de 16 à 20 caractères.
Revalidé avec `openpyxl` : alignement centré et bordure fine sur toutes les
cellules testées (bandeau, en-tête, données normales et zébrées), largeurs
de colonnes inchangées ailleurs.

```
espace/
  connexion.php  deconnexion.php  inscription.php  index.php    ← tableau de bord
  galerie.php    galerie-club.php documents.php     agenda.php   annuaire.php
  blog.php       blog-article.php ← Blog du Club, page publique (voir plus haut)
  album.php          ← dépôt de photos pour un album « Nos Sorties » hébergé sur ce site (type=local, voir plus haut)
  adherents.php      ← gestion des comptes, responsables et éditeurs
  export-adherents.php ← liste des adhérents en .xlsx, responsables uniquement (voir plus haut)
  parametres.php     ← coordonnées du club affichées sur le site public, responsables uniquement
  installation.php   ← à jouer UNE fois, se verrouille ensuite tout seul
  telecharger.php    ← seule porte d'accès aux fichiers privés (+ types publics : sortie, galerie_club, blog, sortie_album)
  statut-connexion.php ← état de connexion en JSON, pour js/main.js sur les pages statiques
  inc/               ← code interne, fermé par .htaccess
    config.local.php ← À CRÉER À LA MAIN SUR LE SERVEUR, jamais dans Git
    config.example.php  db.php  auth.php  page.php  televersement.php
    mail.php  blog.php  albums.php  xlsx.php  schema.sql
  photos/  photos_club/  photos_blog/  photos_sorties/  fichiers/  ← dépôts, fermés par .htaccess
```

Points à ne pas casser :

- **`config.local.php` ne doit JAMAIS être commité** : le dépôt est public. Il
  se crée dans le Gestionnaire de fichiers hPanel et survit aux déploiements
  (rsync sans `--delete`).
- Le schéma des tables est dans `inc/schema.sql`, joué par `installation.php`.
- Les fichiers déposés sont renommés aléatoirement et leur type est déduit du
  **contenu** (`mime_content_type` + `getimagesize`), jamais du nom : c'est ce
  qui empêche de déposer un `.php` déguisé en `.png`.
- `telecharger.php` ne prend jamais de nom de fichier dans l'URL, seulement un
  identifiant en base — pas de remontée de dossier possible.
- Toute page réservée commence par `exige_connexion()` (ou
  `exige_administrateur()`), et tout formulaire porte `champ_csrf()` +
  `verifier_csrf()`.
- Les pages PHP **ne tournent pas sur la préversion GitHub Pages** (statique
  uniquement). L'espace ne se teste qu'en ligne sur Hostinger.
- Pour tester la logique hors ligne : copier `espace/` ailleurs, remplacer le
  DSN de `db.php` par SQLite et adapter `schema.sql`. C'est ainsi qu'ont été
  validés connexion, CSRF, blocage après échecs, rôles et dépôts de fichiers.
- **Le cookie de session dure 30 jours** (`DUREE_SESSION_SECONDES`,
  `inc/auth.php`), pas « jusqu'à la fermeture du navigateur »
  (`lifetime = 0`, réglage d'origine) — piège signalé par l'utilisatrice
  le 30/08/2026 : sur mobile, quitter le navigateur pour une autre
  application (ex. Facebook) pousse souvent le système à décharger le
  navigateur de la mémoire ; à son retour au premier plan, il redémarre
  l'onglet et traite ça comme une nouvelle « session navigateur » — un
  cookie `lifetime = 0` disparaît alors, déconnectant l'adhérent sans
  qu'il ait rien demandé. `ini_set('session.gc_maxlifetime', ...)` est
  posé juste avant `session_start()` pour que le nettoyage automatique de
  PHP côté serveur (souvent bien plus court par défaut chez l'hébergeur)
  ne supprime pas le fichier de session avant l'expiration du cookie.
  « Se déconnecter » (`deconnecter()`) continue de fonctionner
  immédiatement : il efface le cookie explicitement, sans rapport avec sa
  durée de vie par défaut. Revalidé hors ligne (PHP+SQLite) : connexion
  (cookie avec une vraie date d'expiration, `Max-Age=2592000`), accès au
  tableau de bord, puis déconnexion explicite (`Max-Age=0`) et accès
  refusé ensuite.
- **Présence et déconnexion à distance** (onglet Adhérents) : deux colonnes sur
  `adherents`. `derniere_activite` est rafraîchie à chaque page réservée par
  `signaler_presence()` ; « en ligne » = activité de moins de
  `DELAI_PRESENCE_MINUTES` (15). `deconnecte_le` est posée par un responsable :
  à sa requête suivante, toute session ouverte **avant ou pendant** cette
  seconde est fermée. Une connexion réussie remet `deconnecte_le` à NULL, sans
  quoi une reconnexion dans la même seconde serait éjectée à tort. La
  comparaison des instants passe toujours par l'horloge de MySQL
  (`UNIX_TIMESTAMP`), jamais par celle de PHP : deux fuseaux différents
  fausseraient tout.
- **Les migrations sont automatiques** (`inc/migration.php`, appelé depuis
  `db.php`). C'est imposé par le contexte : `installation.php` se verrouille au
  premier compte, et du code neuf qui interroge une colonne absente casse la
  connexion — donc l'accès à toute page de migration. Chaque ajout de colonne
  se déclare dans `COLONNES_ATTENDUES` **et** dans `schema.sql`. La table
  `parametres_site` (coordonnées du club) est créée et semée de la même
  façon, via `PARAMETRES_PAR_DEFAUT` — les valeurs par défaut y reprennent
  exactement ce qui était déjà écrit en dur dans le HTML, pour qu'une base
  tout juste migrée n'affiche rien de différent tant que personne n'y touche.
  Un témoin `inc/.schema-a-jour` évite de réinterroger la base à chaque
  requête.
- **Contenu dynamique sur les pages publiques statiques** (adresse, horaires,
  téléphone, e-mail, texte de présentation en pied de page) : un responsable les modifie
  dans `espace/parametres.php`, table `parametres_site` (clé/valeur). Les
  pages publiques restent du HTML statique — rien n'y est régénéré. C'est le
  navigateur du visiteur qui, via `js/main.js`, interroge `infos-club.php` (à
  la racine, **volontairement hors de `espace/`** : ce point d'accès est
  public par conception, sans connexion requise, puisqu'il ne renvoie que des
  coordonnées déjà visibles de tous) et vient remplacer le texte des éléments
  portant `data-contenu="clé"`. Le HTML garde toujours le texte réel en dur :
  c'est le repli si l'appel échoue (hors ligne, ou **préversion GitHub Pages,
  qui ne peut pas exécuter PHP** — vérifié : la préversion continue d'afficher
  normalement le texte figé dans le commit). `infos-club.php` ne réutilise pas
  `base_de_donnees()` de `db.php` : en cas de panne celle-ci affiche une page
  d'erreur HTML via `page_erreur()`, inadaptée à un point d'accès JSON — il
  ouvre donc sa propre connexion, et appelle lui-même `appliquer_migrations()`
  pour fonctionner dès le premier déploiement, sans dépendre d'une visite
  préalable d'une page de l'espace.
- Les `.htaccess` ne peuvent pas se tester ainsi (le serveur PHP intégré les
  ignore). Vérifiés en ligne le 16/08/2026 : `inc/`, `photos/` et `fichiers/`
  répondent bien **403**, et une page réservée renvoie **302** vers la
  connexion.
- **Le menu « {pseudo} connecté » sur les pages publiques statiques** — même
  principe que le point précédent, bug constaté le 19/08/2026 : une page
  statique est figée au moment du déploiement, donc rendue une fois pour
  toutes avec le menu « Espace Adhérent » non connecté. En arrivant sur
  `index.html` (ou toute autre page statique) après avoir navigué dans
  l'espace adhérents connecté, le menu semblait revenir à l'état déconnecté
  alors que la session, elle, restait active. Corrigé par le même mécanisme
  qu'`infos-club.php` : `espace/statut-connexion.php` (session uniquement via
  `inc/auth.php`, jamais de base de données — résistant à une panne, comme
  `infos-club.php`) renvoie l'état de connexion en JSON ; `js/main.js` l'
  interroge sur toute page dont le menu « Espace Adhérent » n'a pas déjà été
  rendu connecté côté serveur (repéré par l'absence de
  `.nav-dropdown-label`), et reconstruit alors le libellé et le sous-menu à
  l'identique de `page.php` (Bonjour {nom}, Se déconnecter, Tableau de bord,
  Galerie privée, Galerie du Club, Documents, Agenda des sorties, Sorties à venir, Annuaire,
  Le Club, + Adhérents pour un responsable ou un éditeur, Réglages du site
  pour un responsable seulement). Sur les pages
  déjà connectées côté serveur (`espace/*.php`), ce script ne fait rien —
  il ne fait que compléter ce que PHP n'a pas pu savoir sur les pages
  statiques.

## Sécurité

Deux durcissements demandés explicitement par l'utilisatrice, 31/08/2026
(« comment sécuriser mon site » puis « fais les deux points ») :

**En-têtes de sécurité HTTP** (`public_html/.htaccess`, nouveau — la racine
du site n'avait pas encore de `.htaccess`, seuls `inc/`, `photos/` et les
dossiers de dépôt en avaient un, pour interdire l'accès) :
`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
`Strict-Transport-Security` (le site est entièrement en HTTPS, voir plus
haut), et une politique de sécurité du contenu (CSP). La CSP n'autorise que
les ressources du site lui-même, plus les deux exceptions réellement
utilisées ailleurs dans le code — les vignettes Google Drive
(`drive.google.com`, voir « Nos Sorties » plus haut) et le favicon en
`data:` URI (`index.html`) — vérifiées par une recherche exhaustive de
toute référence externe (`https://`, `<script src>`, `<link
rel="stylesheet">`, `<iframe>`, `fetch()`) dans le dépôt avant d'écrire la
règle, pour ne rien casser. `'unsafe-inline'` reste nécessaire pour
`script-src`/`style-src` : le site n'a pas de build, il utilise des
scripts et des attributs `style=""` en ligne un peu partout. Pas testable
avec le serveur PHP intégré (voir plus bas, « Les `.htaccess` ne peuvent
pas se tester ainsi ») — à vérifier en ligne après déploiement.

**Anti-spam sur l'inscription** (`espace/inscription.php`, seul point
d'écriture public du site sans protection — le formulaire de contact,
`contact.html`, n'a lui aucun traitement serveur : c'est un formulaire
`mailto:`, qui ouvre directement le client de messagerie du visiteur, donc
rien à protéger côté serveur). Sans dépendance externe (pas de CAPTCHA,
cohérent avec le reste du site) : un champ piège invisible (`site_web`,
qu'un humain ne voit ni ne remplit jamais, hors de portée du clavier via
`tabindex="-1"`) et un délai minimum de 3 secondes entre l'affichage du
formulaire et son envoi (`DELAI_MIN_INSCRIPTION_SECONDES`, comparé à
`$_SESSION['inscription_affichee_a']`, reposé à chaque affichage du
formulaire y compris après une erreur de validation) — un robot remplit et
envoie en général en un instant. Les deux déclenchent exactement le même
comportement qu'une vraie inscription réussie (même message de succès,
même redirection), sans qu'aucun compte ne soit créé ni aucun e-mail
envoyé : un robot pris au piège n'a aucun moyen de savoir qu'il a échoué,
donc aucune raison de s'adapter. Testé hors ligne (PHP+SQLite) : champ
piège rempli, soumission en moins de 3 secondes, et inscription légitime
après un délai de 4 secondes — seule la troisième crée effectivement un
compte, les trois renvoient le même code 302 vers `connexion.php`.

## Déploiement

Automatique via GitHub Actions (`.github/workflows/deploy.yml`) : tout push sur
`main` touchant `public_html/**` synchronise **les deux sites** par SSH/rsync
— `focalclub.fr` d'abord, puis `myfocal.online` (deux étapes du même job,
ajoutée le 01/09/2026, choix explicite de l'utilisatrice : « il faut qu'après
le test et la mise à jour de focalclub.fr les deux sites soient au même
niveau »). **Jamais de `--delete`** — aucun fichier n'est supprimé côté
serveur.

Les 6 secrets (`HOSTINGER_HOST`, `HOSTINGER_PORT`, `HOSTINGER_USER`,
`HOSTINGER_SSH_KEY`, `HOSTINGER_TARGET_DIR`, `HOSTINGER_TEST_TARGET_DIR`)
sont **déjà configurés** dans Settings → Secrets and variables → Actions.
Voir le README pour le détail.

Le workflow peut aussi être lancé à la main : onglet Actions → « Déploiement
Hostinger » → Run workflow.

### Tester une branche avant fusion (myfocal.online)

`.github/workflows/deploy-test.yml` (ajouté le 01/09/2026), **manuel
uniquement** (jamais déclenché par un push) : déploie `public_html/` vers
`myfocal.online`, pour tester une branche **pas encore fusionnée** sur
`main` (y compris l'espace adhérents PHP+MySQL, impossible à essayer sur
la préversion GitHub Pages ci-dessous) sans jamais toucher au site en
ligne. Réutilise les 4 mêmes secrets `HOSTINGER_HOST`/`PORT`/`USER`/
`SSH_KEY` que le déploiement principal (même compte Hostinger), plus
`HOSTINGER_TEST_TARGET_DIR` = `/home/u912253694/public_html/` (le dossier
de `myfocal.online`, confirmé par tout l'historique de déploiement
d'avant le 01/09/2026 — voir « Pièges déjà rencontrés » plus bas pour le
détail des deux dossiers séparés ; même secret que la seconde étape de
`deploy.yml` ci-dessus). Se lance depuis l'onglet Actions → « Déploiement
test (myfocal.online) » → Run workflow → en choisissant la branche à
tester dans le sélecteur. Le cycle complet est donc : tester la branche
sur `myfocal.online` via ce workflow → si concluant, fusionner sur `main`
comme d'habitude → `deploy.yml` remet alors automatiquement les deux
sites au même niveau.

### Préversion avant publication

`.github/workflows/preview.yml` publie `public_html/` sur GitHub Pages à
chaque poussée d'une branche `claude/**`, pour **relire le rendu avant de
fusionner** :

- **Adresse :** https://steff44.github.io/Stef/
- Une seule préversion à la fois : c'est toujours la branche poussée le plus
  récemment qui s'affiche.
- Elle ne touche jamais au site en ligne — Hostinger ne part que de `main`.
- Elle est protégée de l'indexation (`robots.txt` + balise `noindex` ajoutée à
  la volée). Ces protections sont injectées **dans l'archive publiée
  uniquement**, jamais dans les fichiers du dépôt : ne pas les y recopier.
- Peut aussi être lancée à la main : Actions → « Préversion GitHub Pages » →
  Run workflow.

GitHub Pages est **déjà activé** (Settings → Pages → Source = GitHub Actions).
C'est un réglage à faire une seule fois, et seul le propriétaire du dépôt le
peut : le jeton des workflows n'en a pas le droit (« Resource not accessible by
integration »). Si la préversion échoue un jour à cette étape, c'est là qu'il
faut regarder.

## Pièges déjà rencontrés (ne pas refaire)

- **Le sandbox Claude Code ne peut pas faire de SSH** (port 22/65002 bloqué,
  seul le HTTPS sortant passe, et via une liste de domaines autorisés).
  Tout ce qui touche au serveur doit passer par un workflow GitHub Actions.
- **`focalclub.fr` et la plupart des domaines externes sont bloqués** depuis le
  sandbox. Pour les inspecter : workflow GitHub Actions temporaire + Playwright,
  résultats renvoyés en base64 dans les logs (les artefacts GitHub sont sur un
  domaine bloqué, donc inutilisables ici).
- **`steff44.github.io` est bloqué depuis le sandbox** : impossible d'aller
  regarder la préversion soi-même. Pour vérifier qu'elle est bien en ligne,
  lire les logs du workflow (« Reported success! » à l'étape « Mise en ligne
  de la préversion »). C'est à l'utilisateur d'ouvrir l'adresse.
- **`myfocalclub.online` N'EXISTE PAS** — le DNS répond `NXDOMAIN`. Ce fichier
  et le README l'ont longtemps annoncé comme l'adresse du site : c'était faux.
  Ne pas réintroduire ce domaine.
- **Le compte Hostinger (`u912253694`) héberge plusieurs sites**, chacun
  avec son **propre dossier `public_html`** — pas un simple réglage DNS
  par-dessus un dossier partagé, contrairement à ce qui avait longtemps été
  supposé dans ce fichier. Jusqu'au 30/08/2026, `focalclub.fr` (et `.eu`)
  affichait un site Hostinger Horizons sans rapport, qui renvoyait une page
  « 200 » pour *n'importe quelle* adresse — un faux 404 (vérifié le
  16/08/2026 en interrogeant chaque domaine ; ne surtout pas en avoir
  conclu à une fuite de nos fichiers, ils n'y étaient pas). **`focalclub.fr`
  a été basculé le 30/08/2026** (choix explicite de l'utilisateur, action
  faite de sa main dans hPanel — hors de portée du sandbox, voir plus haut
  « ne peut pas faire de SSH ») : l'ancien contenu Hostinger Horizons a
  disparu, remplacé par une copie de notre site à ce moment-là — mais
  **cette bascule a créé un second site hPanel indépendant** (« Sites
  web » dans hPanel affiche `focalclub.fr` et `myfocal.online` comme deux
  entrées séparées, chacune avec son propre tableau de bord, sa propre
  page « Accès SSH », son propre dossier), pas un alias du site existant.
  `.eu` n'a pas été concerné par ce changement, toujours à vérifier au cas
  par cas avant d'affirmer quoi que ce soit sur son contenu.
  **`focalclub.fr` est devenu l'adresse de référence du site** dans cette
  documentation (choix explicite de l'utilisateur, 30/08/2026, même jour)
  — toujours citée en premier en haut de ce fichier.

  **Un diagnostic du 01/09/2026 (voir la section favicon plus haut) a
  montré que les deux sites ne recevaient PAS les mêmes déploiements** :
  un appel direct depuis un workflow GitHub Actions (le sandbox ne peut
  atteindre ni l'un ni l'autre) a trouvé `focalclub.fr` figé sur une copie
  d'avant le 30/08/2026 — favicon absent (404, `Last-Modified` d'avril
  2025), aucun des en-têtes de sécurité HTTP du 31/08/2026, ancienne
  feuille de style — alors que `myfocal.online` reflétait exactement le
  dernier déploiement. **Cause identifiée avec l'utilisatrice, via des
  captures d'écran de hPanel** : les deux sites partagent le même compte
  (`u912253694`), les mêmes identifiants SSH (même IP, port, utilisateur,
  et même clé déjà autorisée sur les deux — vérifié dans « Avancé → Accès
  SSH » de chaque site), mais ont chacun leur **propre dossier
  `public_html`**, à des chemins distincts (confirmé par deux ouvertures
  côte à côte du gestionnaire de fichiers hPanel, donnant deux URLs
  différentes, l'une avec `favicon.ico`, l'autre sans). Le chemin exact de
  `focalclub.fr` (trouvé via « Fichiers → Comptes FTP », champ
  « Répertoire ») est `/home/u912253694/domains/focalclub.fr/public_html/`
  — alors que `HOSTINGER_TARGET_DIR` pointait jusque-là vers
  `/home/u912253694/public_html/` (le dossier du compte par défaut, celui
  de `myfocal.online`).

  **Décision de l'utilisatrice (01/09/2026)** : plutôt que de faire
  pointer les deux domaines vers le même dossier, elle a choisi de garder
  les deux sites séparés et de **rediriger le déploiement automatique vers
  `focalclub.fr`** — `myfocal.online` devient un **site de test
  indépendant**, modifiable à la main sans jamais affecter le site en
  ligne. Correctif : mise à jour du secret GitHub `HOSTINGER_TARGET_DIR`
  vers `/home/u912253694/domains/focalclub.fr/public_html/` (seul secret
  changé — host/port/utilisateur/clé SSH restent identiques, le compte
  étant le même). Un déploiement de test (`workflow_dispatch`) suivi d'un
  nouveau diagnostic externe a confirmé le succès :
  `focalclub.fr/favicon.ico` répond désormais `200`, avec le bon
  `Content-Type` et un `Last-Modified` correspondant exactement à ce
  déploiement de test. **`focalclub.fr` est donc maintenant à jour et
  reçoit les futurs déploiements automatiques ; `myfocal.online` n'en
  reçoit plus depuis cette date** — ne pas le remettre en cible sans
  qu'on le redemande explicitement, et ne pas s'étonner qu'il diverge
  progressivement du contenu déployé.
- Dans `css/style.css`, les chemins d'images sont relatifs à `css/`, donc
  `url("../images/...")`.
- **Hostinger sert le CSS avec `cache-control: max-age=604800`** — sept jours.
  Sans précaution, une modification de style reste invisible une semaine pour
  qui a déjà ouvert le site : le navigateur ne redemande simplement pas le
  fichier. D'où le suffixe `?v=` sur chaque appel à `style.css` :
  - **pages PHP** : automatique, `lien_css()` dans `inc/page.php` utilise
    `filemtime()`, il n'y a rien à penser ;
  - **pages HTML statiques** : `?v=AAAAMMJJ` écrit en dur. **À incrémenter à
    la main dès qu'on touche à `style.css`**, sinon les visiteurs habituels ne
    verront pas le changement. En cas de second changement le même jour, la
    date seule ne suffit plus à faire changer l'adresse : passer à
    `AAAAMMJJHHmm` (heure UTC) le temps de cette journée-là suffit à
    redevenir unique.
  Constaté le 16/08/2026 : le quadrillage du tableau des adhérents était bien
  déployé, mais invisible côté navigateur pour cette raison.
- **Le bouton du menu mobile (`.nav-toggle`) n'affichait qu'une seule barre
  au lieu de trois** (signalé par l'utilisateur le 25/08/2026 — « deux
  points et un trait », pas franchement un hamburger). Cause : les trois
  barres (`span`, `span::before`, `span::after`) partageaient la même règle
  `left: 10px; right: 10px;`, pensée comme relative au bouton (42px de
  large). Mais `span` porte lui-même `position: absolute`, ce qui en fait le
  bloc de référence de ses **propres** pseudo-éléments — pas le bouton.
  `span::before`/`::after` se retrouvaient donc positionnés par rapport à un
  bloc large d'environ 20px (la largeur du `span`), où `10px` de chaque côté
  ne laisse plus aucune largeur : deux barres bien présentes dans le DOM,
  mais réduites à 0px, invisibles — seule la barre du milieu (le `span`
  lui-même, positionné par rapport au bouton) restait visible. Corrigé en
  donnant à `span::before`/`::after` un `left: 0; right: 0;` (relatifs au
  `span`, donc pleine largeur de celui-ci) au lieu de `10px`/`10px`. Un seul
  bouton, réutilisé tel quel sur toutes les pages statiques et sur
  `espace/inc/page.php` : corrigé partout d'un coup, vérifié par capture
  d'écran (fermé : trois barres ; ouvert : croix) sur `index.html`.
- **Audit mobile complet du 25/08/2026** (demandé explicitement par
  l'utilisateur : « vérifie que tout s'affiche bien sur mobile ») — deux
  problèmes trouvés et corrigés par capture d'écran + mesure directe de
  `document.documentElement.scrollWidth` à 390px de large (Playwright),
  sur toutes les pages publiques et de l'espace adhérents :
  1. **Message « Aucun(e) … pour le moment » chevauchant les boutons
     flottants** (`.retour-nav`, bas-droite) sur mobile — visible sur
     `galerie.html`, `nos-sorties.html` et le même motif `.empty-state` dans
     `espace/` (Sorties à venir, Galerie privée/du Club, Documents, Blog) :
     le texte centré, presque aussi large que le conteneur, passait sous la
     colonne de boutons quand il tombait en bas d'écran. Corrigé par
     `padding-right: 64px` sur `.empty-state, .gallery-empty`, dans le
     `@media (max-width: 640px)` existant (où `.retour-nav` bascule en
     bas-droite).
  2. **`espace/parametres.php` débordait horizontalement sur téléphone
     étroit** (page rendue défilable de force, bouton « Renommer »
     chevauchant le lien « Supprimer » d'à côté), dans les trois pavés de
     gestion (Rubriques des documents, Catégories des galeries, Catégories
     du blog) : quatre causes empilées, trouvées une à une par mesure des
     tailles réelles (pas seulement par capture d'écran, insuffisant pour
     distinguer un vrai débordement d'un chevauchement de boîtes) :
     - `.reglages-grid` imposait `minmax(400px, 1fr)` à chaque colonne —
       sur un écran de moins de 400px de large (une fois le padding du
       conteneur retiré), ce minimum dépassait la largeur disponible.
       Corrigé en `minmax(min(400px, 100%), 1fr)` : la piste ne dépasse
       jamais la largeur réellement disponible.
     - `.reglage-forme-nom input[type="text"]` n'avait pas `min-width: 0` —
       dans une ligne flex, un champ texte a par défaut un `min-width`
       implicite égal à la taille de son contenu, qui ignore `flex: 1` et
       l'empêche de rétrécir sous cette taille.
     - En dessous de 520px, le bouton « Renommer » (largeur fixée par son
       texte, incompressible) chevauchait visuellement « Supprimer » juste
       à côté : `@media (max-width: 520px) { .reglage-ligne { flex-direction:
       column } }` fait passer chaque ligne en colonne, un formulaire par
       ligne, sans plus aucun calcul de largeur à faire cohabiter deux
       éléments côte à côte.
     - Une fois empilé, `.reglage-forme-nom` (lui-même une ligne flex
       champ+bouton) débordait quand même de sa colonne : `align-items:
       stretch` du parent ne suffit pas à contraindre un enfant qui est
       lui-même un conteneur flex avec son propre minimum de contenu — il
       lui faut son propre `width: 100%; min-width: 0;` (ajouté dans le
       même `@media (max-width: 520px)`).
     - Même symptôme ensuite sur les catégories (`<li class="reglage-ligne">`
       dans `<ul class="reglage-categories">`, en grille) : l'équivalent
       CSS Grid du même piège — un item de grille garde `justify-self:
       stretch` sauf que sa taille minimale automatique (basée sur son
       contenu) l'emporte quand même. `min-width: 0` posé sur la règle de
       base `.reglage-ligne` (hors media query, donc valable à toute
       largeur) referme définitivement ce chapitre.
  Vérifié : `document.documentElement.scrollWidth === window.innerWidth`
  à 390px sur les 15 pages publiques/de l'espace testées (plus de
  débordement nulle part), capture d'écran de `parametres.php` en entier
  (les trois pavés s'empilent proprement), menu mobile toujours
  fonctionnel, et suite de 39 tests du blog rejouée sans régression sur un
  jeu de données neuf.

## Mentions légales et politique de confidentialité

**`mentions-legales.html` et `confidentialite.html`** (choix explicite de
l'utilisateur, 26/08/2026) sont deux pages statiques indépendantes, sur le
modèle de `contact.html` (même en-tête, même pied de page, bandeau
`.cta-section cta-section--reduit` réduit, contenu en prose dans
`.blog-contenu` réutilisé tel quel — aucune nouvelle règle CSS n'a été
nécessaire). Coordonnées de l'association fournies par l'utilisateur :
Focal Club Turballais, RNA W443010828, 10 rue de la Fontaine, 44420 La
Turballe. L'e-mail et le téléphone de contact reprennent les mêmes
`data-contenu="email"`/`data-contenu="telephone"` déjà utilisés ailleurs sur
le site : ces deux pages restent donc à jour automatiquement si un
responsable change les coordonnées du club depuis `parametres.php`, sans
qu'il soit besoin d'y toucher.

**Le directeur de la publication n'est pas nommé** : la page renvoie
génériquement au « président en exercice » plutôt que d'inventer un nom —
à compléter par l'utilisateur si elle souhaite y faire figurer un nom
précis. **L'hébergeur (Hostinger International Ltd., 61 Lordou Vironos
Street, 6023 Larnaca, Chypre)** est l'adresse habituellement publiée par
Hostinger sur ce type de mentions légales, mais n'a pas été vérifiée en
direct depuis ce dépôt (domaine externe, hors de la liste des domaines
autorisés depuis ce sandbox) : à confirmer auprès des mentions légales
officielles de Hostinger avant publication si l'utilisateur veut être
certaine qu'elle n'a pas changé.

`mentions-legales.html` couvre : éditeur du site, directeur de publication,
hébergement, propriété intellectuelle, **droit d'auteur et droit à l'image
des photographies** (spécifique à un club photo : chaque photo appartient à
son auteur-adhérent qui en autorise la publication dans le cadre du club ;
toute personne reconnaissable sur une photo peut en demander le retrait),
liens hypertextes, droit applicable.

`confidentialite.html` couvre les obligations RGPD : responsable du
traitement, données collectées (formulaire de contact, inscription à
l'espace adhérents, dépôts de photos/documents, cookie de session),
finalités et base légale, destinataires, durée de conservation, sécurité
(mots de passe hachés, HTTPS), cookies (session uniquement, dispensé de
consentement), droits RGPD et adresse de la CNIL, renvoi vers la rubrique
droit à l'image des mentions légales.

Liens ajoutés dans la liste « Liens rapides » du pied de page des quatre
pages statiques principales (`index.html`, `galerie.html`,
`nos-sorties.html`, `contact.html`) — même liste déjà documentée plus haut,
donc pas de nouvelle CSS. Le pied de page minimal des pages de l'espace
adhérents (`espace/inc/page.php`, volontairement réduit à la ligne de
copyright — voir plus haut) reçoit en plus une courte ligne « Mentions
légales · Confidentialité » avec une couleur de lien explicite
(`--accent-3`, soulignée) : sans elle, ces liens se seraient fondus dans le
texte gris de `.footer-bottom` (même piège que celui déjà rencontré et
corrigé sur les liens du blog, voir plus haut) puisque `.footer-bottom`
n'a pas de couleur de lien dédiée à la différence de `.footer-links`.

## Conventions

- Tout le contenu visible est en **français**.
- Ne pas inventer de fonctionnalité qui n'existe pas (pas de faux formulaire de
  connexion, pas de faux blog) — préférer une page « bientôt disponible ».
