<?php
// excel_converter.php - Konverter-Klasse für Excel/CSV zu Datanorm

// Manuelle Autoloader-Datei einbinden
require_once 'autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelToDatanormConverter {
    private $filePath;
    private $fileType;
    private $spreadsheet;
    private $supplierName = '';
    private $datanormVersion = '050'; // Standardversion ist 5.0
    private $columnMap = [];
    
    // Konstruktor mit Versionsoption
    public function __construct($filePath, $datanormVersion = '050') {
        $this->filePath = $filePath;
        
        // Versionsvalidierung (nur 04 oder 050 erlaubt)
        if ($datanormVersion === '04' || $datanormVersion === '050') {
            $this->datanormVersion = $datanormVersion;
        } else {
            throw new Exception("Ungültige Datanorm-Version. Nur '04' oder '050' sind erlaubt.");
        }
        
        // Überprüfen, ob die Datei existiert
        if (!file_exists($filePath)) {
            throw new Exception("Excel/CSV-Datei nicht gefunden: $filePath");
        }
        
        // Dateityp ermitteln
        $this->fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Datei laden und Spaltenzuordnungen initialisieren
        $this->loadFile();
        $this->initializeColumnMap();
    }
    
    // Dateiinhalt basierend auf Dateityp laden
    private function loadFile() {
        try {
            if ($this->fileType === 'csv') {
                // Für CSV spezifischen Reader mit Einstellungen verwenden
                $reader = IOFactory::createReader('Csv');
                $reader->setDelimiter(';');  // Semikolon als Standard-Trennzeichen
                $reader->setEnclosure('"');
                $reader->setSheetIndex(0);
                $this->spreadsheet = $reader->load($this->filePath);
            } else {
                // Für Excel-Dateien (xls, xlsx)
                $this->spreadsheet = IOFactory::load($this->filePath);
            }
        } catch (Exception $e) {
            throw new Exception("Fehler beim Laden der Datei: " . $e->getMessage());
        }
    }
    
    // Spaltenüberschriften ermitteln und Mapping erstellen
    private function initializeColumnMap() {
        $worksheet = $this->spreadsheet->getActiveSheet();
        $highestColumn = $worksheet->getHighestColumn();
        $headerRow = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0];
        
        // Standardspaltenzuordnungen (basierend auf der DATANORM-writer Dokumentation)
        $defaultMappings = [
            'artikelnummer' => ['Artikelnummer', 'ArtikelNr', 'Artikel-Nr', 'ArtNr'],
            'kurztext1' => ['Kurztext1', 'Kurztext', 'Bezeichnung', 'Beschreibung'],
            'kurztext2' => ['Kurztext2', 'Ergänzung', 'Zusatz'],
            'mengeneinheit' => ['Mengeneinheit', 'ME', 'Einheit'],
            'preis' => ['Preis', 'Listenpreis', 'VP', 'VK'],
            'rabattgruppe' => ['Rabattgruppe', 'RabattGrp', 'RG'],
            'preiskennzeichen' => ['Preiskennzeichen', 'PreisKZ', 'PKZ'],
            'preiseinheit' => ['Preiseinheit', 'PE'],
            'hauptwarengruppe' => ['Hauptwarengruppe', 'HWG', 'Warengruppe1'],
            'warengruppe' => ['Warengruppe', 'WG', 'Warengruppe2'],
            'ean' => ['EAN-Nummer', 'EAN', 'GTIN'],
            'matchcode' => ['Matchcode', 'Suchbegriff'],
            'ausschreibungstext' => ['Ausschreibungstext', 'Langtext'],
            'bild1' => ['Bilddateiname 1', 'Bild1', 'Bildverweis1'],
            'bild2' => ['Bilddateiname 2', 'Bild2', 'Bildverweis2'],
            'bild3' => ['Bilddateiname 3', 'Bild3', 'Bildverweis3'],
            'bild4' => ['Bilddateiname 4', 'Bild4', 'Bildverweis4'],
            'bild5' => ['Bilddateiname 5', 'Bild5', 'Bildverweis5']
        ];
        
        // Spaltenzuordnung initialisieren
        $this->columnMap = [];
        
        // Spalten anhand der Namen zuordnen (unabhängig von Groß-/Kleinschreibung)
        foreach ($headerRow as $colIndex => $colName) {
            $colName = trim($colName);
            foreach ($defaultMappings as $key => $possibleNames) {
                foreach ($possibleNames as $possibleName) {
                    if (strcasecmp($colName, $possibleName) === 0) {
                        $this->columnMap[$key] = $colIndex;
                        break 2; // Beende beide Schleifen
                    }
                }
            }
        }
        
        // Prüfen, ob die Pflichtfelder vorhanden sind
        if (!isset($this->columnMap['artikelnummer']) || !isset($this->columnMap['kurztext1'])) {
            throw new Exception("Die Pflichtfelder 'Artikelnummer' und 'Kurztext1' müssen in der Datei vorhanden sein.");
        }
    }
    
    // Hauptkonvertierungsmethode für eine einzelne Datei
    public function convert($outputFilePath) {
        // Ausgabedatei öffnen
        $outputFile = fopen($outputFilePath, 'w');
        if ($outputFile === false) {
            throw new Exception("Konnte Ausgabedatei nicht erstellen: $outputFilePath");
        }
        
        try {
            // V-Satz (Vorlaufsatz) schreiben
            $this->writeVRecord($outputFile);
            
            // A-Sätze (Artikeldaten) und B-Sätze/T-Sätze (Artikeltexte) schreiben
            $this->writeArticleRecords($outputFile);
            
            // W-Satz (Warengruppensätze) schreiben - optional
            $this->writeProductGroupRecords($outputFile);
            
            // Z-Satz (END-Record) schreiben
            $this->writeZRecord($outputFile);
            
            return true;
        } finally {
            // Datei schließen
            fclose($outputFile);
        }
    }
    
    // Konvertierungsmethode für Ausgabe in mehrere Datanorm-Dateien
    public function convertMultiFile($outputFiles) {
        // Prüfen, ob alle erforderlichen Dateinamen vorhanden sind
        if (!isset($outputFiles['001']) || !isset($outputFiles['002']) || !isset($outputFiles['003'])) {
            throw new Exception("Nicht alle erforderlichen Ausgabedateien angegeben.");
        }
        
        // Dateien für die verschiedenen Satzarten öffnen
        $file001 = fopen($outputFiles['001'], 'w'); // Artikeldaten (A-Sätze)
        $file002 = fopen($outputFiles['002'], 'w'); // Warengruppen (W-Sätze)
        $file003 = fopen($outputFiles['003'], 'w'); // Texte (B/T-Sätze)
        
        if ($file001 === false || $file002 === false || $file003 === false) {
            // Geöffnete Dateien schließen
            if ($file001 !== false) fclose($file001);
            if ($file002 !== false) fclose($file002);
            if ($file003 !== false) fclose($file003);
            
            throw new Exception("Konnte eine oder mehrere Ausgabedateien nicht erstellen.");
        }
        
        try {
            // .001 Datei: V-Satz, A-Sätze und Z-Satz
            $this->writeVRecord($file001);
            $this->writeArticleRecordsWithoutTexts($file001);
            $this->writeZRecord($file001);
            
            // .002 Datei: V-Satz, W-Sätze und Z-Satz
            $this->writeVRecord($file002);
            $this->writeProductGroupRecords($file002);
            $this->writeZRecord($file002);
            
            // .003 Datei: V-Satz, B/T-Sätze und Z-Satz
            $this->writeVRecord($file003);
            $this->writeTextRecordsOnly($file003);
            $this->writeZRecord($file003);
            
            return true;
        } finally {
            // Alle Dateien schließen
            fclose($file001);
            fclose($file002);
            fclose($file003);
        }
    }
    
    // V-Satz (Vorlaufsatz) schreiben - unterstützt beide Versionen
    private function writeVRecord($outputFile) {
        $date = date('Ymd'); // YYYYMMDD für Version 5.0
        $currency = "EUR";
        $supplierName = $this->supplierName ?: "Artikeldaten"; // Standard, falls nicht gesetzt
        
        if ($this->datanormVersion === '050') {
            // Datanorm 5.0 Semikolon-Format für V-Satz:
            // V;Version;Satzkennung;Datum;Währung;Lieferantenname;[weitere Felder...]
            $identifier = "A";  // Satzkennung (meist A)
            
            // V-Satz zusammenbauen mit Semikolons
            $vRecord = "V;{$this->datanormVersion};{$identifier};{$date};{$currency};{$supplierName};;;;;;;;;;";
        } else {
            // Datanorm 4.0 Format für V-Satz:
            // V YYMMDD[Lieferantenname                      ]VVWWW
            $date = date('ymd'); // YYMMDD für Version 4.0
            
            // Feste Breite für Lieferantennamen + Leerzeichen als Füllung
            // 103 Zeichen Gesamtlänge für das Namensfeld
            $paddedName = str_pad($supplierName . " Artikeldaten", 103, " ", STR_PAD_RIGHT);
            
            // V-Satz zusammenbauen mit fester Feldbreite
            $vRecord = "V {$date}{$paddedName}{$this->datanormVersion}{$currency}";
        }
        
        // In Ausgabedatei schreiben
        fwrite($outputFile, $vRecord . PHP_EOL);
    }
    
    // A-Sätze (Artikeldaten) und Textsätze schreiben
    private function writeArticleRecords($outputFile) {
        $worksheet = $this->spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        
        // Zeilen ab Zeile 2 verarbeiten (Annahme: Zeile 1 ist Header)
        for ($row = 2; $row <= $highestRow; $row++) {
            // Zeilendaten abrufen
            $rowData = $this->getRowData($worksheet, $row);
            
            // Leere Zeilen überspringen
            if (empty($rowData['artikelnummer'])) {
                continue;
            }
            
            // Artikeldaten extrahieren
            $articleId = $rowData['artikelnummer'];
            $shortDesc = $rowData['kurztext1'];
            $unit = $rowData['mengeneinheit'] ?? 'Stck';
            $price = isset($rowData['preis']) ? (float)$rowData['preis'] : 0;
            
            // A-Satz für den Artikel formatieren und schreiben
            $aRecord = $this->formatARecord($articleId, $shortDesc, $unit, $price, $rowData);
            fwrite($outputFile, $aRecord . PHP_EOL);
            
            // Für Version 5.0: T-Sätze für Artikelbeschreibung schreiben (wenn vorhanden)
            if ($this->datanormVersion === '050' && isset($rowData['ausschreibungstext']) && !empty($rowData['ausschreibungstext'])) {
                $longDesc = $rowData['ausschreibungstext'];
                $tRecords = $this->formatTRecords($articleId, $longDesc);
                fwrite($outputFile, $tRecords);
            } else if ($this->datanormVersion === '04') {
                // Für Version 4.0: B-Satz für Artikelbeschreibung schreiben
                $shortDesc = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $shortDesc), 0, 12));
                $bRecord = $this->formatBRecord($articleId, $shortDesc);
                fwrite($outputFile, $bRecord . PHP_EOL);
            }
            
            // Für Version 5.0: T-Sätze für Bildverweise schreiben (wenn vorhanden)
            if ($this->datanormVersion === '050') {
                $imageReferences = [];
                for ($i = 1; $i <= 5; $i++) {
                    if (isset($rowData['bild' . $i]) && !empty($rowData['bild' . $i])) {
                        $imageReferences[] = $rowData['bild' . $i];
                    }
                }
                
                if (!empty($imageReferences)) {
                    $imageText = "Bildverweise:\n" . implode("\n", $imageReferences);
                    $tRecords = $this->formatTRecords($articleId, $imageText);
                    fwrite($outputFile, $tRecords);
                }
            }
        }
    }
    
    // Nur A-Sätze schreiben - für .001 Datei
    private function writeArticleRecordsWithoutTexts($outputFile) {
        $worksheet = $this->spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        
        // Zeilen ab Zeile 2 verarbeiten (Annahme: Zeile 1 ist Header)
        for ($row = 2; $row <= $highestRow; $row++) {
            // Zeilendaten abrufen
            $rowData = $this->getRowData($worksheet, $row);
            
            // Leere Zeilen überspringen
            if (empty($rowData['artikelnummer'])) {
                continue;
            }
            
            // Artikeldaten extrahieren
            $articleId = $rowData['artikelnummer'];
            $shortDesc = $rowData['kurztext1'];
            $unit = $rowData['mengeneinheit'] ?? 'Stck';
            $price = isset($rowData['preis']) ? (float)$rowData['preis'] : 0;
            
            // A-Satz für den Artikel formatieren und schreiben
            $aRecord = $this->formatARecord($articleId, $shortDesc, $unit, $price, $rowData);
            fwrite($outputFile, $aRecord . PHP_EOL);
        }
    }
    
    // Nur Textsätze schreiben - für .003 Datei
    private function writeTextRecordsOnly($outputFile) {
        $worksheet = $this->spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        
        // Zeilen ab Zeile 2 verarbeiten (Annahme: Zeile 1 ist Header)
        for ($row = 2; $row <= $highestRow; $row++) {
            // Zeilendaten abrufen
            $rowData = $this->getRowData($worksheet, $row);
            
            // Leere Zeilen überspringen
            if (empty($rowData['artikelnummer'])) {
                continue;
            }
            
            // Artikeldaten extrahieren
            $articleId = $rowData['artikelnummer'];
            $shortDesc = $rowData['kurztext1'];
            
            if ($this->datanormVersion === '050') {
                // Für Version 5.0: T-Sätze 
                if (isset($rowData['ausschreibungstext']) && !empty($rowData['ausschreibungstext'])) {
                    $longDesc = $rowData['ausschreibungstext'];
                    $tRecords = $this->formatTRecords($articleId, $longDesc);
                    fwrite($outputFile, $tRecords);
                }
                
                // Für Version 5.0: T-Sätze für Bildverweise schreiben (wenn vorhanden)
                $imageReferences = [];
                for ($i = 1; $i <= 5; $i++) {
                    if (isset($rowData['bild' . $i]) && !empty($rowData['bild' . $i])) {
                        $imageReferences[] = $rowData['bild' . $i];
                    }
                }
                
                if (!empty($imageReferences)) {
                    $imageText = "Bildverweise:\n" . implode("\n", $imageReferences);
                    $tRecords = $this->formatTRecords($articleId, $imageText);
                    fwrite($outputFile, $tRecords);
                }
            } else {
                // Für Version 4.0: B-Satz
                $shortDesc = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $shortDesc), 0, 12));
                $bRecord = $this->formatBRecord($articleId, $shortDesc);
                fwrite($outputFile, $bRecord . PHP_EOL);
            }
        }
    }
    
    // Hilfsmethode zum Abrufen der Zeilendaten mit Spaltenzuordnung
    private function getRowData($worksheet, $row) {
        $highestColumn = $worksheet->getHighestColumn();
        $range = 'A' . $row . ':' . $highestColumn . $row;
        $rowArray = $worksheet->rangeToArray($range, null, true, false)[0];
        
        $result = [];
        foreach ($this->columnMap as $key => $colIndex) {
            $result[$key] = isset($rowArray[$colIndex]) ? trim((string)$rowArray[$colIndex]) : '';
        }
        
        return $result;
    }
    
    // A-Satz formatieren
    private function formatARecord($articleId, $desc, $unit, $price, $rowData) {
        if ($this->datanormVersion === '050') {
            // Datanorm 5.0 Semikolon-Format für A-Satz:
            // A;Artikel-Nr;Beschreibung;Preis;Mengeneinheit;PreisKZ;PreisEinheit;RabattGrp;WarenGrp;...
            
            // Preis formatieren mit Punkt als Dezimaltrenner
            $formattedPrice = number_format($price, 2, '.', '');
            
            // Zusätzliche Felder
            $priceFlag = $rowData['preiskennzeichen'] ?? '1'; // 1=Listenpreis (Standard)
            $priceUnit = $rowData['preiseinheit'] ?? '0';     // 0=Preis bezieht sich auf 1 Einheit
            $rabattGrp = $rowData['rabattgruppe'] ?? '';
            $warenGrp = $rowData['hauptwarengruppe'] ?? '';
            
            // A-Satz zusammenbauen mit Semikolons
            return "A;{$articleId};{$desc};{$formattedPrice};{$unit};{$priceFlag};{$priceUnit};{$rabattGrp};{$warenGrp};";
        } else {
            // Datanorm 4.0 Format für A-Satz:
            // A;N;Artikel-Nr;00;Beschreibung;Zusatzinfo;1;0;Mengeneinheit;Preis;15;42; ;
            
            // Preis formatieren ohne Dezimalstellen
            $formattedPrice = number_format($price, 0, '', '');
            
            // Zusatzinfo (kann leer sein oder eine zusätzliche Beschreibung)
            $additionalInfo = $rowData['kurztext2'] ?? "";
            
            // A-Satz zusammenbauen mit Semikolons im Format 4.0
            return "A;N;{$articleId};00;{$desc};{$additionalInfo};1;0;{$unit};{$formattedPrice};15;42; ;";
        }
    }
    
    // T-Sätze für Artikeltexte formatieren - für Version 5.0
    private function formatTRecords($articleId, $text) {
        // Datanorm 5.0 Semikolon-Format für T-Satz:
        // T;N;Artikel-Nr;1;Zeilennr;Text;
        
        // Text in Zeilen aufteilen (max. 40 Zeichen pro Zeile ist Konvention)
        $lines = [];
        $textRows = explode("\n", $text);
        
        foreach ($textRows as $textRow) {
            $words = explode(' ', $textRow);
            $currentLine = '';
            
            foreach ($words as $word) {
                if (strlen($currentLine) + strlen($word) + 1 <= 40) {
                    if (!empty($currentLine)) {
                        $currentLine .= ' ';
                    }
                    $currentLine .= $word;
                } else {
                    $lines[] = $currentLine;
                    $currentLine = $word;
                }
            }
            
            // Letzte Zeile der aktuellen Textzeile hinzufügen
            if (!empty($currentLine)) {
                $lines[] = $currentLine;
            }
        }
        
        $result = '';
        
        // T-Sätze für jede Zeile erstellen
        foreach ($lines as $index => $line) {
            $lineNum = str_pad($index + 1, 2, '0', STR_PAD_LEFT);  // 01, 02, 03, ...
            $result .= "T;N;{$articleId};1;{$lineNum};{$line};" . PHP_EOL;
        }
        
        return $result;
    }
    
    // B-Satz für Artikeltexte formatieren - für Version 4.0
    private function formatBRecord($articleId, $shortDesc) {
        // Datanorm 4.0 Format für B-Satz:
        // B;N;Artikel-Nr;KURZTEXT; ; ;0;0;0; ; ; ;0;0; ; ;
        
        return "B;N;{$articleId};{$shortDesc}; ; ;0;0;0; ; ; ;0;0; ; ;";
    }
    
    // W-Sätze (Warengruppensätze) schreiben
    private function writeProductGroupRecords($outputFile) {
        $worksheet = $this->spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        
        // Nur verarbeiten, wenn Warengruppen-Spalten existieren
        if (!isset($this->columnMap['warengruppe']) && !isset($this->columnMap['hauptwarengruppe'])) {
            return; // Keine Warengruppen-Spalten
        }
        
        // Eindeutige Warengruppen sammeln
        $productGroups = [];
        
        // Zeilen ab Zeile 2 verarbeiten (Annahme: Zeile 1 ist Header)
        for ($row = 2; $row <= $highestRow; $row++) {
            // Zeilendaten abrufen
            $rowData = $this->getRowData($worksheet, $row);
            
            // Leere Zeilen überspringen
            if (empty($rowData['artikelnummer'])) {
                continue;
            }
            
            // Warengruppendaten extrahieren
            if (isset($rowData['hauptwarengruppe']) && !empty($rowData['hauptwarengruppe'])) {
                $groupId = $rowData['hauptwarengruppe'];
                // Warengruppe als Beschreibung verwenden, falls vorhanden, sonst ID
                $groupDesc = isset($rowData['warengruppe']) && !empty($rowData['warengruppe']) ? 
                    $rowData['warengruppe'] : $groupId;
                
                // Eindeutige Warengruppen speichern
                if (!empty($groupId) && !isset($productGroups[$groupId])) {
                    $productGroups[$groupId] = $groupDesc;
                }
            }
        }
        
        // W-Sätze für eindeutige Warengruppen schreiben
        foreach ($productGroups as $groupId => $groupDesc) {
            $wRecord = $this->formatWRecord($groupId, $groupDesc);
            fwrite($outputFile, $wRecord . PHP_EOL);
        }
    }
    
    // W-Satz formatieren (Warengruppen)
    private function formatWRecord($groupId, $groupDesc) {
        if ($this->datanormVersion === '050') {
            // Datanorm 5.0 Semikolon-Format für W-Satz:
            // W;Warengruppennr;Warengruppenbezeichnung;
            
            return "W;{$groupId};{$groupDesc};;;";
        } else {
            // Datanorm 4.0 Format für W-Satz
            return "W;{$groupId};{$groupDesc};;;";
        }
    }
    
    // Z-Satz (END-Record) schreiben
    private function writeZRecord($outputFile) {
        if ($this->datanormVersion === '050') {
            // Datanorm 5.0 Semikolon-Format für E-Satz (entspricht Z-Satz):
            // E;Anzahl Datensätze;Erstellungsinfo;
            
            $recordCount = "0"; // Optional: Hier könnte die Anzahl der Datensätze stehen
            $info = "Erstellt mit Excel/CSV zu Datanorm Konverter";
            
            $eRecord = "E;{$recordCount};{$info};";
            fwrite($outputFile, $eRecord . PHP_EOL);
        } else {
            // Datanorm 4.0 Format für Z-Satz (einfach nur "Z")
            $zRecord = "Z";
            fwrite($outputFile, $zRecord . PHP_EOL);
        }
    }
    
    // Lieferantennamen setzen
    public function setSupplierName($name) {
        $this->supplierName = $name;
    }
}