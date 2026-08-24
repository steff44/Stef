# Contexte projet — site du Focal Club Turballais

Ce fichier est chargé automatiquement au démarrage d'une session Claude Code.
Il résume l'état du projet pour repartir sans avoir à tout réexpliquer.

## Le projet

Site vitrine statique (HTML/CSS/JS, sans framework ni build) pour le **Focal
Club Turballais**, club photo associatif de La Turballe (44).

- **En ligne :** https://myfocal.online (et `www.myfocal.online`) — espace
  adhérents sur https://myfocal.online/espace/connexion.php
- **Source de vérité du design :** https://focalclub.fr — un autre site du même
  club, généré avec Hostinger Horizons (React compilé, pas de source lisible).
  L'utilisateur veut que notre site s'en rapproche visuellement.
- **Branche de référence :** `main` — c'est elle, et elle seule, qui met le
  site en ligne. Le travail se fait sur une branche `claude/**`, se relit sur
  la préversion (voir « Déploiement »), puis se fusionne sur `main`.

## Structure

```
public_html/          ← racine du site, déployée telle quelle
  index.html          ← accueil : hero photo, cartes, galerie, CTA
  galerie.html        ← galerie publique : photos de démonstration + vraies photos de la Galerie du Club
  evenements.html     ← redirection vers espace/agenda.php (ne pas supprimer)
  membres.html        ← redirection vers espace/le-club.php (ne pas supprimer)
  contact.html
  connexion.html      ← redirection vers espace/connexion.php (ne pas supprimer)
  infos-club.php      ← API publique en lecture seule (coordonnées du club, voir plus bas)
  infos-galerie-club.php ← API publique en lecture seule (photos de la Galerie du Club, voir plus bas)
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
  Accueil, Galerie, Agenda, Le Club, Nous Contacter, Espace Adhérent.
  **Agenda et Espace Adhérent sont tous deux des menus déroulants**
  (`.nav-dropdown`, voir juste en dessous) ; Le Club et Nous Contacter sont
  des liens simples.
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
  23/08/2026) : celui de `galerie.html` (`.gallery-hero`, propre à cette
  page) est réduit d'un tiers (64px/40px → 43px/27px). Celui d'Agenda des
  sorties et du Club (`titre_page()` dans `espace/inc/page.php`, partagé par
  toutes les pages de l'espace adhérents) est réduit de moitié sur ces deux
  pages seulement, via un 4ᵉ paramètre `$reduit` qui ajoute
  `.gallery-header--reduit` — les autres pages de l'espace (Documents,
  Galerie privée, Annuaire, Réglages du site…) gardent le bandeau standard,
  n'ayant pas été citées par l'utilisateur.
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
  en haut »** (choix explicite de l'utilisateur, 20/08/2026, complété le
  23/08/2026) : posés automatiquement par `js/main.js` (`.retour-nav`,
  bas-droite de l'écran) sur toute page comptant au moins une `<section>` —
  générique, s'applique donc à toute page publique ou de l'espace
  adhérents (y compris les pages réservées aux responsables) sans rien
  ajouter à la main, y compris pour une page future. « Page précédente »
  (`history.back()`, même principe que `.lien-retour` sur
  `connexion.php`/`inscription.php` mais flottant et générique à toute
  page) et « retour en haut » s'affichent dès qu'il y a au moins une
  section ; « section précédente » ne s'affiche qu'à partir de deux
  `<section>` (rien à survoler avec une seule) — seuil qui, avant le
  23/08/2026, empêchait aussi « retour en haut » d'apparaître sur une page
  à une seule section mais longue, comme `espace/documents.php`. Le bouton
  « section précédente » remonte au début de la `<section>` précédente
  (pas seulement en haut de la section actuelle) ; « retour en haut » va
  toujours en haut de la page. **Les trois sont toujours visibles dès le
  chargement de la page** (choix explicite de l'utilisateur, 23/08/2026,
  second changement de la journée à leur sujet — ils n'apparaissaient
  auparavant qu'après un défilement d'au moins 60 % de la hauteur de
  l'écran, ce qui les rendait difficiles à trouver).

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
`inc/galerie_categories.php` (renommé le 21/08/2026, s'appelait
`inc/galerie_club.php` avant que `galerie.php` ne partage aussi ses
catégories) porte la seule fonction `categories_galerie($pdo)`. Tout
adhérent peut déposer (titre, catégorie, nom affiché facultatif — pour
signer autrement que son identifiant de connexion —, note facultative) —
les photos sont plafonnées à 600 Ko (`TAILLE_MAX_PHOTO_ADHERENT` dans
`inc/televersement.php`, choix explicite de l'utilisateur, 21/08/2026 : plus
strict que le plafond général de 8 Mo, qui reste appliqué aux documents et
aux photos de sortie), message dédié — « Photo trop lourde, ne pas dépasser
600 Ko. Merci. » — passé en 5ᵉ argument à `enregistrer_fichier_envoye()` ;
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

**`galerie.html` a aussi une section « Photos Google Drive »**, séparée de
la Galerie du Club (choix explicite de l'utilisateur, 23/08/2026 — des
photos conservées sur Google Drive plutôt que déposées sur l'hébergement
Hostinger, pour ne pas l'encombrer). `infos-galerie-drive.php` (à la racine,
même principe d'autonomie qu'`infos-club.php`) interroge l'API Google
Drive (`files.list`, clé API sans OAuth) pour lister les images d'un
dossier Drive donné, et renvoie pour chacune son titre (nom du fichier sans
extension) et l'adresse de sa vignette publique
(`https://drive.google.com/thumbnail?id={id}&sz=w1000` — sert l'image
directement depuis Google, jamais copiée sur le serveur). Résultat mis en
cache sur disque 15 minutes (`espace/inc/.cache-galerie-drive.json`, non
versionné) pour ne pas user le quota gratuit de l'API à chaque visite ; en
cas d'échec de l'appel (quota dépassé, clé invalide, panne), le dernier
résultat connu est resservi plutôt que de faire disparaître la section.
`js/main.js` peuple `[data-drive-gallery]` et affiche la section
(`[data-drive-gallery-section]`, masquée par défaut comme les autres
sections chargées en JSON de cette page) dès que la liste n'est pas vide ;
réutilise `buildPhotoCard()`, donc l'agrandissement au clic (lightbox)
fonctionne aussi sur ces photos. Deux réglages dans
`espace/inc/config.local.php` (jamais commités, voir plus bas) :
`google_drive_cle_api` et `google_drive_dossier_id` — vides par défaut
(`config.example.php`), ce qui désactive simplement la section, sans
erreur. **Le dossier Drive doit être partagé « Accessible à tous les
utilisateurs disposant du lien »** : une clé API seule (sans OAuth) ne
peut lire que des fichiers Drive publics, jamais un dossier resté privé.
La requête à `files.list` inclut `supportsAllDrives`/
`includeItemsFromAllDrives` (sans quoi un dossier vivant dans un Drive
partagé — « Shared Drive » — resterait invisible même bien partagé).

**Les sous-dossiers sont explorés** (24/08/2026) : le dossier du club range
ses photos par adhérent (`Expo FOCAL 2026 / {Prénom} / 1920 / …`), donc se
limiter aux images posées directement dans le dossier racine ne remonterait
jamais rien. L'API Google ne sait pas répondre « et tout ce qu'il y a en
dessous » : `collecter_images_drive()` descend niveau par niveau, en
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
adhérents à cause d'un seul dossier oublié. Quand un lot échoue et que ce
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
l'utilisateur, la section restait vide. Diagnostic via un point d'accès
temporaire (`?diag=...`, retiré une fois la cause trouvée — le domaine du
site étant bloqué depuis ce sandbox, un appel direct à l'API Google
`files.get` sur l'identifiant du dossier configuré a montré une erreur
« File not found », alors que la clé API et l'identifiant de dossier
étaient corrects. Le dossier n'était en réalité **pas encore partagé
publiquement** malgré l'étape 5 suivie : à vérifier en premier lieu en cas
de section vide — rouvrir le dossier sur drive.google.com, bouton de
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
formulaire par action, sur le même principe que `adherents.php`. Supprimer
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

```
espace/
  connexion.php  deconnexion.php  inscription.php  index.php    ← tableau de bord
  galerie.php    galerie-club.php documents.php     agenda.php   annuaire.php
  adherents.php      ← gestion des comptes, responsables et éditeurs
  parametres.php     ← coordonnées du club affichées sur le site public, responsables uniquement
  installation.php   ← à jouer UNE fois, se verrouille ensuite tout seul
  telecharger.php    ← seule porte d'accès aux fichiers privés (+ types publics : sortie, galerie_club)
  statut-connexion.php ← état de connexion en JSON, pour js/main.js sur les pages statiques
  inc/               ← code interne, fermé par .htaccess
    config.local.php ← À CRÉER À LA MAIN SUR LE SERVEUR, jamais dans Git
    config.example.php  db.php  auth.php  page.php  televersement.php
    mail.php  schema.sql
  photos/  photos_club/  fichiers/  ← dépôts, fermés par .htaccess
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

## Déploiement

Automatique via GitHub Actions (`.github/workflows/deploy.yml`) : tout push sur
`main` touchant `public_html/**` synchronise le site par SSH/rsync.
**Jamais de `--delete`** — aucun fichier n'est supprimé côté serveur.

Les 5 secrets (`HOSTINGER_HOST`, `HOSTINGER_PORT`, `HOSTINGER_USER`,
`HOSTINGER_SSH_KEY`, `HOSTINGER_TARGET_DIR`) sont **déjà configurés** dans
Settings → Secrets and variables → Actions. Voir le README pour le détail.

Le workflow peut aussi être lancé à la main : onglet Actions → « Déploiement
Hostinger » → Run workflow.

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
- **Le compte Hostinger héberge plusieurs domaines**, et c'est
  **`myfocal.online`** qui sert `~/public_html/`, donc notre déploiement. Les
  autres (`focalclub.fr`, `.eu`) affichent un site Hostinger Horizons sans
  rapport, qui renvoie une page « 200 » pour *n'importe quelle* adresse — un
  faux 404. Ne pas en conclure à une fuite de nos fichiers : ils n'y sont pas.
  Vérifié le 16/08/2026 en interrogeant chaque domaine.
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

## Conventions

- Tout le contenu visible est en **français**.
- Ne pas inventer de fonctionnalité qui n'existe pas (pas de faux formulaire de
  connexion, pas de faux blog) — préférer une page « bientôt disponible ».
