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
 * Construit un classeur .xlsx en mémoire — une seule feuille, $entetes en
 * première ligne puis une ligne par élément de $lignes (mêmes clés que
 * $entetes, dans le même ordre) — et renvoie son contenu binaire, prêt à
 * être servi en téléchargement.
 */
function generer_xlsx(string $nom_feuille, array $entetes, array $lignes): string
{
    $construire_ligne = static function (int $numero, array $valeurs): string {
        $cellules = [];
        foreach (array_values($valeurs) as $index => $valeur) {
            $reference  = colonne_excel($index) . $numero;
            $cellules[] = '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">'
                . texte_xml((string) $valeur) . '</t></is></c>';
        }
        return '<row r="' . $numero . '">' . implode('', $cellules) . '</row>';
    };

    $lignes_xml   = [$construire_ligne(1, $entetes)];
    $numero_ligne = 2;
    foreach ($lignes as $ligne) {
        $lignes_xml[] = $construire_ligne($numero_ligne, $ligne);
        $numero_ligne++;
    }

    $derniere_colonne = colonne_excel(max(0, count($entetes) - 1));
    $dimension        = 'A1:' . $derniere_colonne . max(1, $numero_ligne - 1);

    $feuille_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<sheetData>' . implode('', $lignes_xml) . '</sheetData>'
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

    // Styles minimaux, sans aucune mise en forme particulière (une seule
    // police, un seul format de cellule) : suffisant pour qu'Excel/
    // LibreOffice ouvrent le fichier sans le juger endommagé.
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border/></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
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
