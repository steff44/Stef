<?php
/*
 * Générateur minimal de fichier .xlsx, sans dépendance externe (pas de
 * Composer/PHPSpreadsheet) — même philosophie que le reste du site (voir
 * inc/mail.php, écrit avec mail() natif plutôt que PHPMailer). Une seule
 * feuille, cellules en texte brut (type inlineStr : le texte est écrit
 * directement dans la cellule, pas de table de chaînes partagées à gérer),
 * ce qui suffit largement pour un export de données tabulaire.
 *
 * S'appuie sur ZipArchive, une extension PHP standard (présente sur
 * l'hébergement mutualisé Hostinger comme sur la quasi-totalité des
 * hébergements PHP) — un fichier .xlsx est une simple archive zip
 * contenant des fichiers XML au format OOXML/SpreadsheetML.
 *
 * Mise en forme (choix explicite de l'utilisateur, 28/08/2026, « je veux
 * que tu formates le fichier Excel de façon à ce qu'il soit plus lisible ») :
 * un bandeau titre + sous-titre (avec la date d'export) sur les deux
 * premières lignes, en dégradé bleu nuit — reprend la couleur d'accent du
 * site (#0f172a, voir css/style.css) — puis une ligne d'en-tête de colonnes
 * dans le même bandeau, et des lignes de données zébrées (une claire sur
 * deux) pour rester lisible sur un grand tableau.
 */

declare(strict_types=1);

/* Convertit un index de colonne 0-based en référence de colonne Excel
   (0 -> A, 25 -> Z, 26 -> AA...). */
function colonne_excel(int $index): string
{
    $lettres = '';
    $index++;
    while ($index > 0) {
        $reste   = ($index - 1) % 26;
        $lettres = chr(65 + $reste) . $lettres;
        $index   = intdiv($index - 1, 26);
    }
    return $lettres;
}

/* Échappe un texte pour un contenu XML : entités (&, <, >, ", ') et retrait
   des caractères de contrôle interdits en XML 1.0 (un champ saisi à la main
   pourrait en contenir, ex. collé depuis un autre document). */
function texte_xml(string $texte): string
{
    $nettoye = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texte);
    return htmlspecialchars((string) $nettoye, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/*
 * Construit un classeur .xlsx en mémoire et renvoie son contenu binaire,
 * prêt à être servi en téléchargement — une seule feuille :
 *   ligne 1 : $titre, en bandeau (fusionné sur toute la largeur) ;
 *   ligne 2 : $sous_titre, même bandeau, pour la date d'export ;
 *   ligne 4 : $entetes, dans le même bandeau (ligne 3 laissée vide, pour
 *             respirer entre le sous-titre et le tableau) ;
 *   lignes 5+ : une ligne par élément de $lignes (mêmes clés que $entetes,
 *             dans le même ordre), zébrées une sur deux.
 * $largeurs_colonnes est facultatif : largeur de chaque colonne, en
 * nombre de caractères (unité Excel) — sans quoi Excel retombe sur sa
 * largeur par défaut, bien trop étroite pour la plupart des champs.
 */
function generer_xlsx(
    string $nom_feuille,
    string $titre,
    string $sous_titre,
    array $entetes,
    array $lignes,
    array $largeurs_colonnes = []
): string {
    $nb_colonnes       = max(1, count($entetes));
    $derniere_colonne  = colonne_excel($nb_colonnes - 1);

    // Styles (voir xl/styles.xml plus bas pour le détail) :
    //   0 = donnée normale · 1 = titre · 2 = sous-titre
    //   3 = en-tête de colonne · 4 = donnée zébrée (ligne claire)
    $construire_cellule = static function (int $colonne, int $ligne, string $valeur, int $style): string {
        $reference = colonne_excel($colonne) . $ligne;
        $s         = $style !== 0 ? ' s="' . $style . '"' : '';
        return '<c r="' . $reference . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">'
            . texte_xml($valeur) . '</t></is></c>';
    };

    // Bandeau titre/sous-titre/en-tête : chaque cellule de la ligne porte le
    // même style, sinon la couleur de fond ne s'étend pas visuellement
    // au-delà de la première colonne dans certains lecteurs.
    $construire_bandeau = static function (int $ligne, array $valeurs, int $style, ?string $hauteur = null) use ($nb_colonnes, $construire_cellule): string {
        $cellules = [];
        for ($colonne = 0; $colonne < $nb_colonnes; $colonne++) {
            $cellules[] = $construire_cellule($colonne, $ligne, (string) ($valeurs[$colonne] ?? ''), $style);
        }
        $hauteur_attr = $hauteur !== null ? ' customHeight="1" ht="' . $hauteur . '"' : '';
        return '<row r="' . $ligne . '"' . $hauteur_attr . '>' . implode('', $cellules) . '</row>';
    };

    $lignes_xml   = [];
    $lignes_xml[] = $construire_bandeau(1, [$titre], 1, '28');
    $lignes_xml[] = $construire_bandeau(2, [$sous_titre], 2, '20');
    // Ligne 3 volontairement absente (espace visuel avant le tableau).
    $lignes_xml[] = $construire_bandeau(4, array_values($entetes), 3, '18');

    $numero_ligne = 5;
    foreach ($lignes as $index => $ligne) {
        $style    = $index % 2 === 1 ? 4 : 0;
        $cellules = [];
        foreach (array_values($ligne) as $colonne => $valeur) {
            $cellules[] = $construire_cellule($colonne, $numero_ligne, (string) $valeur, $style);
        }
        $lignes_xml[] = '<row r="' . $numero_ligne . '">' . implode('', $cellules) . '</row>';
        $numero_ligne++;
    }

    $derniere_ligne = max(4, $numero_ligne - 1);
    $dimension      = 'A1:' . $derniere_colonne . $derniere_ligne;

    $cols_xml = '';
    if ($largeurs_colonnes) {
        $definitions = [];
        foreach (array_values($largeurs_colonnes) as $index => $largeur) {
            $numero        = $index + 1;
            $definitions[] = '<col min="' . $numero . '" max="' . $numero . '" width="' . (float) $largeur . '" customWidth="1"/>';
        }
        $cols_xml = '<cols>' . implode('', $definitions) . '</cols>';
    }

    $feuille_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A5" sqref="A5"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . $cols_xml
        . '<sheetData>' . implode('', $lignes_xml) . '</sheetData>'
        . '<mergeCells count="2">'
        . '<mergeCell ref="A1:' . $derniere_colonne . '1"/>'
        . '<mergeCell ref="A2:' . $derniere_colonne . '2"/>'
        . '</mergeCells>'
        . '</worksheet>';

    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . texte_xml($nom_feuille) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    // 4 polices (normale, titre, sous-titre, en-tête), 4 fonds (aucun,
    // l'emplacement réservé par Excel — voir plus bas —, bleu nuit #0F172A
    // pour le bandeau, gris clair pour le zébrage), 5 formats de cellule
    // (voir la liste au-dessus de generer_xlsx()).
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="4">'
        . '<font><sz val="11"/><color rgb="FF111111"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><i/><sz val="11"/><color rgb="FFCBD5E1"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        . '<fill><patternFill patternType="none"/></fill>'
        // Emplacement réservé par Excel (peu importe ce qu'on y met, l'index 1
        // est toujours affiché comme le motif gris à 12,5 % intégré) — laissé
        // inutilisé pour ne pas écraser accidentellement un vrai fond par ce
        // quadrillage gris. Voir la remarque juste au-dessus de generer_xlsx().
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border/></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="5">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $chemin_temp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip         = new ZipArchive();
    $zip->open($chemin_temp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $content_types);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $feuille_xml);
    $zip->close();

    $contenu = (string) file_get_contents($chemin_temp);
    @unlink($chemin_temp);

    return $contenu;
}
