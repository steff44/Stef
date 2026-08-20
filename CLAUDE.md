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
    `telecharger.php?type=sortie`.
  - Le Club → `espace/le-club.php` (voir « Le Club » plus bas) : **réservé
    aux adhérents connectés depuis le 18/08/2026** — ce n'est plus la liste
    publique des adhérents. `membres.html` est désormais une redirection
    (même principe qu'`evenements.html`).
  - Nous Contacter → `contact.html`.
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
  Le Club (+ Adhérents, Réglages du site pour un responsable). Les classes
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
- **Boutons flottants « section précédente » / « retour en haut »** (choix
  explicite de l'utilisateur, 20/08/2026) : posés automatiquement par
  `js/main.js` (`.retour-nav`, bas-droite de l'écran) sur toute page comptant
  au moins deux `<section>` — générique, s'applique donc à toute page
  publique ou de l'espace adhérents (y compris les pages réservées aux
  responsables) sans rien ajouter à la main, y compris pour une page future.
  Absent sur une page à une seule section (rien à survoler). Le premier
  bouton remonte au début de la `<section>` précédente (pas seulement en
  haut de la section actuelle) ; le second va toujours en haut de la page.
  Les deux restent invisibles (`opacity:0` + `pointer-events:none`) tant que
  la page n'a pas défilé d'au moins 60 % de la hauteur de l'écran.

## Modifier le contenu

- **Adhérents et photos :** tout est dans `public_html/js/data.js`, tableau
  `membres`. Les pages se régénèrent seules.
- **Vraies photos :** les vignettes sont encore des dégradés de couleur générés
  en CSS. Pour de vraies images, les déposer dans `public_html/images/` et
  remplacer le `background` des `.photo-frame` / `.member-cover` dans `js/main.js`.
- **Agenda :** une seule page, `espace/agenda.php` — voir plus bas.

## Espace adhérents (`public_html/espace/`)

Vraie authentification en **PHP + MySQL** sur Hostinger (choix explicite de
l'utilisateur). Quatre rubriques une fois connecté : galerie privée, documents,
agenda des sorties avec inscriptions, annuaire. Deux rôles : adhérent et
responsable (`administrateur = 1`).

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
à une sortie (`exige_administrateur()` reste nécessaire pour en créer ou en
supprimer, y compris le formulaire « Ajouter une sortie » lui-même,
invisible si on n'est pas responsable). Un visiteur non connecté voit les
sorties et qui y participe, avec un lien « Se connecter pour participer » à
la place du bouton d'inscription. Ne pas réintroduire de calendrier public
séparé.

`espace/agenda.php` affiche aussi un **calendrier du mois**, connecté aux
mêmes lignes de la table `sorties` que les listes juste en dessous (aucune
donnée séparée) — navigation par `?mois=AAAA-MM`, en rechargement de page
classique, sans JavaScript. Chaque sortie a une **catégorie** (colonne
`categorie` sur `sorties`, ajoutée via `COLONNES_SORTIES_ATTENDUES` dans
`migration.php`) parmi celles listées dans `CATEGORIES_SORTIES` en haut de
`agenda.php` — actuellement Sortie photo / Cours / Réunion, choisie à la
création par un responsable, affichée en pastille colorée dans le
calendrier et en badge sur chaque carte. Ajouter une catégorie se fait en
un seul endroit : la constante `CATEGORIES_SORTIES` (le mapping couleur
`classe_categorie()` a un cas par défaut, donc une catégorie oubliée dans
ce mapping retombe simplement sur le style « sortie » plutôt que de
planter).

**« Galerie » existe en trois versions**, sans confondre les deux publiques
avec la privée : `galerie.html` (page publique, ouverte à tous) d'un côté,
`galerie.php` (**Galerie privée**, réservée aux adhérents connectés, table
`photos_privees`) de l'autre — et depuis le 20/08/2026, `galerie-club.php`
(**Galerie du Club**, choix explicite de l'utilisateur) : un adhérent y
dépose une photo, la classe dans une catégorie (portrait, paysage…), et
cette photo devient **publique**, reprise automatiquement sur `galerie.html`
(voir plus bas). Le lien « Galerie » du menu principal pointe **toujours**
vers `galerie.html`, connecté ou non (choix explicite de l'utilisateur,
19/08/2026, qui revient sur un choix inverse du 17/08/2026) : la galerie
privée et la Galerie du Club ne s'ouvrent que depuis « Le Club »
(`espace/le-club.php`) ou le menu déroulant « {pseudo} connecté »
(`$onglets` de `espace/inc/page.php`). Avant ce changement, cliquer sur
« Galerie » depuis une page de l'espace adhérents ouvrait la galerie privée
— piège reconnu par l'utilisateur comme non voulu, à ne pas réintroduire.

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
documents) dans la table `categories_galerie`, gérées par un responsable
depuis `parametres.php` (`ajouter_categorie_galerie` / `renommer_...` /
`supprimer_...`, refusé si des photos y sont encore classées) — semées une
seule fois avec `CATEGORIES_GALERIE_PAR_DEFAUT` (`inc/migration.php`, reprend
les catégories de la rubrique « Thèmes photographiques » des documents).
Tout adhérent peut déposer (titre, catégorie, nom affiché facultatif — pour
signer autrement que son identifiant de connexion —, note facultative) ;
seul l'auteur ou un responsable peut supprimer sa photo (même règle que
`galerie.php`). La catégorie n'est pas répétée sur chaque photo : elle est
déjà donnée par le titre du groupe (`inc/photo-club-carte.php`, partiel
inclus une fois par catégorie non vide, même principe que
`inc/document-ligne.php`). Fichiers dans `espace/photos_club/` (fermé par
`.htaccess`), servis par `telecharger.php?type=galerie_club` — **public**,
comme `type=sortie`, contrairement à `type=photo`/`document` qui exigent une
connexion.

**La page publique `galerie.html` affiche les vraies photos de la Galerie du
Club, par-dessus les photos de démonstration.** `infos-galerie-club.php`
(à la racine, hors de `espace/` — même principe qu'`infos-club.php` : public
par conception, connexion autonome à la base, jamais de dépendance à une
page de l'espace déjà visitée) renvoie en JSON les photos de `photos_club`
avec leur catégorie et leur auteur affiché. `js/main.js` les ajoute au
tableau de photos de démonstration existant (`CLUB_DATA` de `js/data.js`) —
la grille et les filtres par thème s'affichent donc immédiatement avec la
démo, puis se complètent sans à-coup dès que l'appel réussit ; il échoue
silencieusement sinon (hors ligne, ou préversion GitHub Pages, qui ne peut
pas exécuter PHP), laissant la démo seule comme repli. Une vraie photo
affiche l'image réelle (`background: url(...)`) là où une photo de
démonstration garde son dégradé de couleur généré
(`photoBackground()` dans `main.js` choisit entre les deux). Toute catégorie
de photo inconnue de `CLUB_DATA.themes` s'ajoute automatiquement aux
filtres.

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
choisit la catégorie par `categorie_id` (menu `<select>` avec un
`<optgroup>` par rubrique) ; la page regroupe les documents par rubrique
puis catégorie (dans l'ordre de `rubriques_documents()`, pas celui de la
requête SQL), et n'affiche que les groupes non vides — pour ne pas montrer
une dizaine de rubriques vides sur un club qui débute, la structure complète
(reconstruite depuis la base, donc toujours à jour même après un
renommage) est expliquée en tête de page dans un texte d'intro plutôt qu'en
rubriques creuses. Un champ de recherche (affiché dès qu'au moins un
document existe) filtre les lignes par titre en JavaScript pur, `data-titre`
sur chaque `<li class="document-ligne">`, sans aller-retour serveur — script
inline en bas de `documents.php`, sur le même principe que l'ancien
`.auth-tabs` de `connexion.php` (page-spécifique, pas dans `main.js`). Le
HTML de chaque ligne est factorisé dans `inc/document-ligne.php`, inclus une
fois par sous-catégorie.

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

**Un compte auto-inscrit doit être validé par un responsable avant de
pouvoir se connecter** (choix explicite de l'utilisateur, 18/08/2026 —
remplace l'activation immédiate décidée la veille). Colonne `valide` sur
`adherents`, **distincte de `actif`** (qui sert à désactiver/bannir un
compte existant) : DEFAULT 1, pour qu'un compte créé par un responsable
depuis `adherents.php` (et les comptes déjà en base avant l'ajout de cette
colonne) reste immédiatement utilisable ; l'inscription publique force
explicitement `valide = 0` à l'INSERT. `tenter_connexion()` (`inc/auth.php`)
refuse la connexion tant que `valide = 0`, avec un message dédié — « Votre
inscription est en attente de validation par un responsable. » — qui ne
compte pas comme un échec dans le compteur de blocage (le mot de passe était
correct). Dans `adherents.php`, une case **Valider/Invalider**
(`basculer_valide`, même schéma que `basculer_actif`/`basculer_admin`) à
côté des autres actions, avec un badge ambre « en attente de validation »
sur la ligne (les comptes non validés remontent en tête de liste, `ORDER BY
valide ASC, actif DESC, nom`).

Trois e-mails accompagnent ce cycle, envoyés par `envoyer_mail()`
(`inc/mail.php`, `mail()` natif de PHP — pas de PHPMailer/Composer, cohérent
avec un projet sans build ; un échec d'envoi est seulement consigné dans
`error_log`, il ne doit jamais faire échouer l'inscription ou la
validation) :
1. à l'inscription, vers l'e-mail du club (`parametres_site.email`, réglable
   dans `parametres.php`) — nouvelle inscription à valider ;
2. à l'inscription, vers la personne inscrite — confirmation que son compte
   est enregistré et en attente ;
3. à la validation (bascule de `valide` 0→1 dans `adherents.php`), vers la
   personne — son compte est actif, elle peut se connecter.

`code_postal` et `ville` sont des colonnes ajoutées à `adherents` (voir
`schema.sql` et `COLONNES_ATTENDUES` dans `migration.php`) ; rien d'autre ne
les affiche pour l'instant (l'annuaire ne montre encore que
nom/identifiant/contact).

```
espace/
  connexion.php  deconnexion.php  inscription.php  index.php    ← tableau de bord
  galerie.php    galerie-club.php documents.php     agenda.php   annuaire.php
  adherents.php      ← gestion des comptes, responsables uniquement
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
  Le Club, + Adhérents/Réglages du site pour un responsable). Sur les pages
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
