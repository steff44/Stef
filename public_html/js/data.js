/* Données du club — à modifier librement pour ajouter/retirer des adhérents ou des photos. */
const CLUB_DATA = {
  nom: "Focal Club Turballais",
  accroche: "Capturer la lumière, partager le regard.",
  membres: [
    {
      id: "claire-martin",
      nom: "Claire Martin",
      role: "Présidente du club",
      bio: "Passionnée de photographie de paysage depuis quinze ans, Claire aime capturer la lumière du petit matin en montagne.",
      hue: 210,
      photos: [
        { titre: "Lever de soleil sur les Alpes", theme: "Paysage" },
        { titre: "Brume matinale en vallée", theme: "Paysage" },
        { titre: "Reflets sur le lac", theme: "Paysage" },
        { titre: "Sentier de randonnée", theme: "Paysage" },
        { titre: "Sommet enneigé", theme: "Paysage" },
        { titre: "Forêt de mélèzes", theme: "Paysage" }
      ]
    },
    {
      id: "youssef-benali",
      nom: "Youssef Benali",
      role: "Trésorier",
      bio: "Adepte du street photography, Youssef arpente les rues de la ville à la recherche d'instants de vie spontanés.",
      hue: 15,
      photos: [
        { titre: "Marché du samedi matin", theme: "Rue" },
        { titre: "Passage piéton sous la pluie", theme: "Rue" },
        { titre: "Terrasse de café", theme: "Rue" },
        { titre: "Ombres sur le trottoir", theme: "Rue" },
        { titre: "Vélo contre le mur", theme: "Rue" },
        { titre: "Néons du soir", theme: "Rue" }
      ]
    },
    {
      id: "elena-rossi",
      nom: "Elena Rossi",
      role: "Responsable des sorties",
      bio: "Elena se spécialise dans le portrait en lumière naturelle et organise les sorties photo mensuelles du club.",
      hue: 330,
      photos: [
        { titre: "Portrait à contre-jour", theme: "Portrait" },
        { titre: "Regard en lumière douce", theme: "Portrait" },
        { titre: "Sourire au parc", theme: "Portrait" },
        { titre: "Profil sur fond flou", theme: "Portrait" },
        { titre: "Mains et lumière", theme: "Portrait" },
        { titre: "Portrait en noir et blanc", theme: "Portrait" }
      ]
    },
    {
      id: "thomas-lefevre",
      nom: "Thomas Lefèvre",
      role: "Adhérent",
      bio: "Passionné de macrophotographie, Thomas explore l'infiniment petit dans son jardin et les parcs alentour.",
      hue: 140,
      photos: [
        { titre: "Goutte de rosée sur pétale", theme: "Macro" },
        { titre: "Aile de papillon", theme: "Macro" },
        { titre: "Texture d'écorce", theme: "Macro" },
        { titre: "Fourmi sur une feuille", theme: "Macro" },
        { titre: "Pistils de fleur", theme: "Macro" },
        { titre: "Toile d'araignée givrée", theme: "Macro" }
      ]
    },
    {
      id: "sofia-nguyen",
      nom: "Sofia Nguyen",
      role: "Adhérente",
      bio: "Sofia travaille principalement en noir et blanc argentique, avec une attention particulière aux contrastes et aux textures.",
      hue: 45,
      photos: [
        { titre: "Escalier en colimaçon", theme: "Noir et blanc" },
        { titre: "Façade abandonnée", theme: "Noir et blanc" },
        { titre: "Silhouette au port", theme: "Noir et blanc" },
        { titre: "Vagues et rochers", theme: "Noir et blanc" },
        { titre: "Fenêtre et ombre", theme: "Noir et blanc" },
        { titre: "Ruelle pavée", theme: "Noir et blanc" }
      ]
    },
    {
      id: "marc-dubois",
      nom: "Marc Dubois",
      role: "Adhérent",
      bio: "Marc se consacre à la photographie animalière et anime les ateliers d'initiation du club pour les nouveaux membres.",
      hue: 265,
      photos: [
        { titre: "Héron au bord de l'étang", theme: "Animalier" },
        { titre: "Renard en lisière de forêt", theme: "Animalier" },
        { titre: "Vol de mésanges", theme: "Animalier" },
        { titre: "Biche au crépuscule", theme: "Animalier" },
        { titre: "Écureuil roux", theme: "Animalier" },
        { titre: "Libellule sur roseau", theme: "Animalier" }
      ]
    }
  ]
};
