# Convertisseur Photos

Convertisseur Photos est un petit logiciel de bureau qui prépare vos photos
pour l'écran (site du club, diaporama, envoi par e-mail…). Il accepte :

- les **fichiers RAW de tous les constructeurs** : Nikon (`.nef`), Canon
  (`.cr2`, `.cr3`), Sony (`.arw`), Fujifilm (`.raf`), Olympus / OM System
  (`.orf`), Panasonic (`.rw2`), Pentax (`.pef`), Leica, Hasselblad, DNG…
- les **JPEG**,
- et la plupart des autres formats : TIFF, PNG, HEIC (iPhone), WebP, BMP,
  PSD (image aplatie)…

et les transforme, au choix, en **JPEG** ou en **WebP** :

- plus grand côté ramené à **1920 pixels** (réglable) — une photo plus
  petite garde sa taille : elle n'est **jamais agrandie** ;
- **poids maximum de 500 Ko** par photo (réglable, 0 = sans limite) : si la
  photo est trop lourde, le logiciel baisse d'abord un peu la qualité (sans
  descendre sous 70), puis, si ça ne suffit pas, réduit légèrement ses
  dimensions ;
- résolution **72 ppp** ;
- **netteté accentuée pour l'écran**, appliquée après la réduction (Aucune,
  Légère, Normale, Forte) ;
- couleurs converties en **sRGB**, l'espace des écrans et des navigateurs
  (une photo en Adobe RGB ne paraîtra donc pas terne sur le web) ;
- rotation portrait/paysage appliquée automatiquement ;
- **métadonnées conservées ou retirées, au choix** (appareil, objectif,
  date, vitesse, ouverture, ISO, auteur, copyright…). La position GPS a sa
  propre case, décochée par défaut, pour ne pas publier par mégarde
  l'endroit où une photo a été prise.

Vos photos d'origine ne sont jamais modifiées, et un fichier déjà présent
dans le dossier de destination n'est jamais écrasé (le nouveau reçoit
« (2) », « (3) »… à la fin de son nom).

## Installation

Il faut Python 3 (sur Windows, téléchargez-le sur
[python.org](https://www.python.org/downloads/) en cochant bien « Add
Python to PATH »), puis ces modules, en une seule commande :

```
pip install pillow rawpy exifread pillow-heif
```

- `pillow` : indispensable ;
- `rawpy` : pour les fichiers RAW (il contient LibRaw, le moteur de
  développement RAW utilisé par de nombreux logiciels photo) ;
- `exifread` : pour lire les métadonnées de certains RAW (CR3, RAF, ORF…) ;
- `pillow-heif` : pour les photos HEIC d'iPhone.

S'il en manque un, le logiciel vous le dit au démarrage et fonctionne quand
même pour les autres formats.

## Lancer Convertisseur Photos

Dans le dossier `convertisseur_photos` :

```
python convertisseur_photos.py
```

## Fabriquer un exécutable Windows (.exe)

Comme pour Convertisseur PDF : sur un PC Windows où Python est installé,
double-cliquez sur `build_exe.bat`. Au bout d'une minute ou deux, vous
obtenez `convertisseur_photos\dist\ConvertisseurPhotos.exe`, à copier où
vous voulez (Bureau, clé USB…) — il n'a plus besoin de Python.

## Utilisation

1. **Photos à convertir** : « Ajouter des photos… » (une seule ou
   plusieurs, avec Ctrl ou Maj dans la fenêtre de choix) et/ou « Ajouter un
   dossier… » (avec ou sans ses sous-dossiers). Vous pouvez combiner les
   deux, et retirer des photos de la liste (touche Suppr). Le tableau
   indique pour chaque photo ses dimensions et son poids, ainsi que le poids
   total (et celui des photos sélectionnées).
2. **Réglages** : format (JPEG ou WebP), taille, poids maximum, qualité (90
   conseillé en JPEG, 85 en WebP), netteté, métadonnées. Sur un petit écran,
   cette colonne défile avec la molette.
3. **Nom des photos** : garder le nom d'origine, ou renommer avec un
   numéro — début du nom (ex. `Sortie_Croisic_`), premier numéro, nombre de
   chiffres (3 → `001`), et ordre de numérotation (nom de fichier, date de
   prise de vue, ou ordre de la liste). Un exemple s'affiche en direct.
4. **Dossier de destination** (en bas de la fenêtre) : un dossier « Photos
   converties » à côté de vos photos est proposé ; le bouton « Choisir le
   dossier… » permet d'en choisir un autre.
5. Cliquez sur **« Convertir les photos »**. Chaque photo s'affiche avec une
   coche verte (✓), ses dimensions et son poids final (et la qualité
   utilisée si elle a dû être baissée), ou une croix rouge (✗) et la raison
   en cas d'échec — les autres photos continuent. Le bouton « Arrêter »
   interrompt le lot.

Vos réglages sont mémorisés d'une fois sur l'autre.

À savoir : le format WebP n'a pas de notion de « résolution en ppp » ; la
valeur 72 ppp y est seulement inscrite dans les métadonnées quand vous les
conservez. Pour un écran, cela ne change rien : seule la taille en pixels
compte.
