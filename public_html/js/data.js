/* Données du club. Les photos de démonstration (adhérents et clichés
   fictifs créés au début de la construction du site) ont été retirées le
   20/08/2026, à la demande de l'utilisateur, maintenant que de vraies
   photos existent dans la Galerie du Club (espace/galerie-club.php) et
   s'affichent automatiquement sur la page Galerie et l'accueil — voir
   infos-galerie-club.php et js/main.js. Ne pas réintroduire de membres ou
   de photos fictifs ici : les catégories ci-dessous suffisent à générer les
   filtres de la page Galerie, même sans aucune photo pour l'instant.

   Ces thèmes sont désormais IDENTIQUES à CATEGORIES_GALERIE_PAR_DEFAUT
   (espace/inc/migration.php), la vraie liste éditable par un responsable
   depuis Réglages du site (choix explicite de l'utilisateur, 26/08/2026 —
   signalé par capture d'écran : les pastilles de filtre de la page Galerie
   publique et celles de la Galerie du Club affichaient deux listes
   différentes, l'ancienne ici datant d'avant l'introduction de la table
   `categories_galerie`, jamais resynchronisée depuis). Cette liste ne
   sert plus que de pastilles affichées avant que le vrai chargement des
   photos (et de leurs catégories réelles) ne se termine ; toute catégorie
   ajoutée depuis Réglages du site apparaît quand même sur la page Galerie
   dès qu'une photo lui est associée (voir addThemeFilter() dans main.js) —
   mais pour qu'une pastille apparaisse même sans aucune photo, comme ici,
   il faut la lister aux deux endroits. */
const CLUB_DATA = {
  nom: "Focal Club Turballais",
  accroche: "Capturer la lumière, partager le regard.",
  themes: [
    "Portrait",
    "Paysage",
    "Macro / Proxi",
    "Nature",
    "Photo de rue",
    "Architecture",
    "Noir & Blanc",
    "Créatif"
  ],
  membres: []
};
