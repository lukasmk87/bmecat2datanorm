<?php
// csv_converter.php - Vereinfachter Konverter für CSV zu Datanorm ohne externe Abhängigkeiten

class CsvToDatanormConverter {
    private $filePath;
    private $supplierName = '';
    private $datanormVersion = '050'; // Standardversion ist 5.0
    private $columnMap = [];
    private $data = [];
    private $headerRow = [];
    
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
            throw new Exception("CSV-Datei nicht gefunden: $filePath");
        }
        
        // Datei laden und Spaltenzuordnungen initialisieren
        $this->loadCsvFile();
        $this->initializeColumnMap();
    }
    
    // CSV-Datei laden
    private function loadCsvFile() {
        $handle = fopen($this->filePath, 'r');
        if ($handle === false) {
            throw new Exception("Konnte CSV-Datei nicht öffnen: {$this->filePath}");
        }
        
        try {
            // Erste Zeile als Header lesen
            $this->headerRow = $this->readCsvLine($handle);
            if ($this->headerRow === false) {
                throw new Exception("CSV-Datei ist leer oder hat ein ungültiges Format");
            }
            
            // Restliche Zeilen als Daten lesen
            while (($row = $this->readCsvLine($handle)) !== false) {
                if (count($row) >= count($this->headerRow)) {
                    $this->data[] = $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }
    
    // Hilfsfunktion zum Lesen einer CSV-Zeile mit korrekter Kodierung und Trennzeichen
private function readCsvLine($handle) {
    // Versuchen mit unterschiedlichen Trennzeichen (Semikolon, Komma)
    // Parameter für fgetcsv: handle, length, delimiter, enclosure, escape
    $line = fgetcsv($handle, 0, ';', '"', '\\');
    
    // Wenn Semikolon nicht funktioniert, versuchen wir es mit Komma
    if ($line !== false && count($line) <= 1 && strpos($line[0], ',') !== false) {
        // Zurück zum Anfang der Zeile
        fseek($handle, ftell($handle) - strlen(implode(';', $line)) - 2);
        $line = fgetcsv($handle, 0, ',', '"', '\\');
    }
    
    // Kodierungskonvertierung falls nötig (ISO-8859-1 zu UTF-8)
    if ($line !== false) {
        foreach ($line as &$value) {
            // Versuchen zu erkennen, ob die Kodierung angepasst werden muss
            if (!mb_check_encoding($value, 'UTF-8') && mb_check_encoding($value, 'ISO-8859-1')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }
            $value = trim($value);
        }
    }
    
    return $line;
}
    
    // Spaltenüberschriften ermitteln und Mapping erstellen
    private function initializeColumnMap() {
        // Standardspaltenzuordnungen (basierend auf der DATANORM-writer Dokumentation)
        $defaultMappings = [
            'artikelnummer' => ['Artikelnummer', 'ArtikelNr', 'Artikel-Nr', 'ArtNr'],
            'kurztext1' => ['Kurztext1', 'Kurztext', 'Bezeichnung', 'Beschreibung'],
            'kurztext2' => ['Kurztext2', 'Ergänzung', 'Zusatz'],
            'mengeneinheit' => ['Mengeneinheit', 'ME', 'Einheit'],
            'preis' => ['Preis', 'Listenpreis', 'VP', 'VK'],
            'einkaufspreis' => ['Einkaufspreis', 'EK', 'EP'],
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
            'bild5' => ['Bilddateiname 5', 'Bild5', 'Bildverweis5'],
            'wrgbeschreibung' => ['WRG-Beschreibung', 'Warengruppenbeschreibung', 'WGBeschreibung']
        ];
        
        // Spaltenzuordnung initialisieren
        $this->columnMap = [];
        
        // Spalten anhand der Namen zuordnen (unabhängig von Groß-/Kleinschreibung)
        foreach ($this->headerRow as $colIndex => $colName) {
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
        
        // Debug-Ausgabe entfernen in Produktion
        // echo "<pre>Erkannte Spalten: " . print_r($this->columnMap, true) . "</pre>";
        
        // Prüfen, ob die Pflichtfelder vorhanden sind
        if (!isset($this->columnMap['artikelnummer']) || !isset($this->columnMap['kurztext1'])) {
            throw new Exception("Die Pflichtfelder 'Artikelnummer' und 'Kurztext1' müssen in der CSV-Datei vorhanden sein.");
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
            
            // P-Sätze (Preisänderungen) schreiben - wenn einkaufspreis vorhanden
            $this->writePriceChangeRecords($outputFile);
            
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
        if (!isset($outputFiles['001']) || !isset($outputFiles['002']) || !isset($outputFiles['003']) || !isset($outputFiles['004'])) {
            throw new Exception("Nicht alle erforderlichen Ausgabedateien angegeben.");
        }
        
        // Dateien für die verschiedenen Satzarten öffnen
        $file001 = fopen($outputFiles['001'], 'w'); // Artikeldaten (A-Sätze)
        $file002 = fopen($outputFiles['002'], 'w'); // Warengruppen (W-Sätze)
        $file003 = fopen($outputFiles['003'], 'w'); // Texte (B/T-Sätze)
        $file004 = fopen($outputFiles['004'], 'w'); // WRG-Datei (erweiterte Warengruppen)
        
        if ($file001 === false || $file002 === false || $file003 === false || $file004 === false) {
            // Geöffnete Dateien schließen
            if ($file001 !== false) fclose($file001);
            if ($file002 !== false) fclose($file002);
            if ($file003 !== false) fclose($file003);
            if ($file004 !== false) fclose($file004);
            
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
            
            // .004 Datei: V-Satz, WRG-Sätze und Z-Satz (erweiterte Warengruppen)
            $this->writeVRecord($file004);
            $this->writeWRGRecords($file004);
            $this->writePriceChangeRecords($file004); // P-Sätze in die WRG-Datei
            $this->writeZRecord($file004);
            
            return true;
        } finally {
            // Alle Dateien schließen
            fclose($file001);
            fclose($file002);
            fclose($file003);
            fclose($file004);
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
        // Zeilen verarbeiten (ab Index 0, da Header bereits getrennt)
        foreach ($this->data as $rowData) {
            // Zeilendaten in assoziatives Array umwandeln
            $mappedData = $this->mapRowToColumns($rowData);
            
            // Leere Zeilen überspringen
            if (empty($mappedData['artikelnummer'])) {
                continue;
            }
            
            // Artikeldaten extrahieren
            $articleId = $mappedData['artikelnummer'];
            $shortDesc = $mappedData['kurztext1'];
            $unit = isset($mappedData['mengeneinheit']) ? $mappedData['mengeneinheit'] : 'Stck';
            $price = isset($mappedData['preis']) ? (float)str_replace(',', '.', $mappedData['preis']) : 0;
            
            // A-Satz für den Artikel formatieren und schreiben
            $aRecord = $this->formatARecord($articleId, $shortDesc, $unit, $price, $mappedData);
            fwrite($outputFile, $aRecord . PHP_EOL);
            
            // Für Version 5.0: T-Sätze für Artikelbeschreibung schreiben (wenn vorhanden)
            if ($this->datanormVersion === '050' && isset($mappedData['ausschreibungstext']) && !empty($mappedData['ausschreibungstext'])) {
                $longDesc = $mappedData['ausschreibungstext'];
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
                    if (isset($mappedData['bild' . $i]) && !empty($mappedData['bild' . $i])) {
                        $imageReferences[] = $mappedData['bild' . $i];
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
        // Zeilen verarbeiten
        foreach ($this->data as $rowData) {
            // Zeilendaten in assoziatives Array umwandeln
            $mappedData = $this->mapRowToColumns($rowData);
            
            // Leere Zeilen überspringen
            if (empty($mappedData['artikelnummer'])) {
                continue;
            }
            
            // Artikeldaten extrahieren
            $articleId = $mappedData['artikelnummer'];
            $shortDesc = $mappedData['kurztext1'];
            $unit = isset($mappedData['mengeneinheit']) ? $mappedData['mengeneinheit'] : 'Stck';
            $price = isset($mappedData['preis']) ? (float)str_replace(',', '.', $mappedData['preis']) : 0;
            
            // A-Satz für den Artikel formatieren und schreiben
            $aRecord = $this->formatARecord($articleId, $shortDesc, $unit, $price, $mappedData);
            fwrite($outputFile, $aRecord . PHP_EOL);
        }
    }
    
    // Nur Textsätze schreiben - für .003 Datei
    private function writeTextRecordsOnly($outputFile) {
        // Zeilen verarbeiten
        foreach ($this->data as $rowData) {
            // Zeilendaten in assoziatives Array umwandeln
            $mappedData = $this->mapRowToColumns($rowData);
            
            // Leere Zeilen überspringen
            if (empty($mappedData['artikelnummer'])) {
                continue;
            }
            
            // Artikeldaten extrahieren
            $articleId = $mappedData['artikelnummer'];
            $shortDesc = $mappedData['kurztext1'];
            
            if ($this->datanormVersion === '050') {
                // Für Version 5.0: T-Sätze 
                if (isset($mappedData['ausschreibungstext']) && !empty($mappedData['ausschreibungstext'])) {
                    $longDesc = $mappedData['ausschreibungstext'];
                    $tRecords = $this->formatTRecords($articleId, $longDesc);
                    fwrite($outputFile, $tRecords);
                }
                
                // Für Version 5.0: T-Sätze für Bildverweise schreiben (wenn vorhanden)
                $imageReferences = [];
                for ($i = 1; $i <= 5; $i++) {
                    if (isset($mappedData['bild' . $i]) && !empty($mappedData['bild' . $i])) {
                        $imageReferences[] = $mappedData['bild' . $i];
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
    
    // WRG-Sätze schreiben (erweiterte Warengruppen-Informationen) - für .004 Datei
    private function writeWRGRecords($outputFile) {
        // Eindeutige Warengruppen und ihre Beschreibungen sammeln
        $wrgGroups = [];
        
        // Nur verarbeiten, wenn Warengruppen-Spalten existieren
        if (!isset($this->columnMap['hauptwarengruppe'])) {
            return; // Keine Warengruppen-Spalten
        }
        
        // Zeilen verarbeiten
        foreach ($this->data as $rowData) {
            // Zeilendaten in assoziatives Array umwandeln
            $mappedData = $this->mapRowToColumns($rowData);
            
            // Leere Zeilen überspringen
            if (empty($mappedData['artikelnummer'])) {
                continue;
            }
            
            // Warengruppendaten extrahieren
            if (isset($mappedData['hauptwarengruppe']) && !empty($mappedData['hauptwarengruppe'])) {
                $groupId = $mappedData['hauptwarengruppe'];
                
                // Beschreibung aus wrgbeschreibung-Spalte oder warengruppe (oder leer lassen)
                $groupDesc = '';
                if (isset($mappedData['wrgbeschreibung']) && !empty($mappedData['wrgbeschreibung'])) {
                    $groupDesc = $mappedData['wrgbeschreibung'];
                } elseif (isset($mappedData['warengruppe']) && !empty($mappedData['warengruppe'])) {
                    $groupDesc = $mappedData['warengruppe'];
                }
                
                // Eindeutige Warengruppen speichern
                if (!empty($groupId) && !isset($wrgGroups[$groupId])) {
                    $wrgGroups[$groupId] = $groupDesc;
                }
            }
        }
        
        // WRG-Sätze für eindeutige Warengruppen schreiben
        foreach ($wrgGroups as $groupId => $groupDesc) {
            $wrgRecord = $this->formatWRGRecord($groupId, $groupDesc);
            fwrite($outputFile, $wrgRecord . PHP_EOL);
        }
    }
    
    // WRG-Satz formatieren (erweiterte Warengruppen)
    private function formatWRGRecord($groupId, $groupDesc) {
        if ($this->datanormVersion === '050') {
            // Datanorm 5.0 Semikolon-Format für WRG-Satz:
            // G;Warengruppennr;Warengruppenbezeichnung;EbeneNr;ÜbergeordneteGrp;
            
            // Standard-Werte für Ebene und übergeordnete Gruppe (falls nicht vorhanden)
            $level = "1"; // Standardmäßig Ebene 1
            $parentGroup = ""; // Standardmäßig keine übergeordnete Gruppe
            
            return "G;{$groupId};{$groupDesc};{$level};{$parentGroup};";
        } else {
            // Datanorm 4.0 Format für WRG-Satz
            // G;Warengruppennr;Warengruppenbezeichnung;
            
            return "G;{$groupId};{$groupDesc};;;";
        }
    }
    
    // P-Sätze (Preisänderungen) schreiben
    private function writePriceChangeRecords($outputFile) {
        // Nur verarbeiten, wenn Einkaufspreis und Listenpreis vorhanden sind
        if (!isset($this->columnMap['preis']) || !isset($this->columnMap['einkaufspreis'])) {
            return; // Keine Preisänderungen möglich ohne beide Preise
        }
        
        // Zeilen verarbeiten
        foreach ($this->data as $rowData) {
            // Zeilendaten in assoziatives Array umwandeln
            $mappedData = $this->mapRowToColumns($rowData);
            
            // Leere Zeilen überspringen
            if (empty($mappedData['artikelnummer'])) {
                continue;
            }
            
            // Nur verarbeiten, wenn beide Preise vorhanden sind
            if (!isset($mappedData['preis']) || !isset($mappedData['einkaufspreis']) || 
                empty($mappedData['preis']) || empty($mappedData['einkaufspreis'])) {
                continue;
            }
            
            // Artikeldaten extrahieren
            $articleId = $mappedData['artikelnummer'];
            $listPrice = (float)str_replace(',', '.', $mappedData['preis']);
            $purchasePrice = (float)str_replace(',', '.', $mappedData['einkaufspreis']);
            
            // P-Satz formatieren und schreiben
            $pRecord = $this->formatPRecord($articleId, $listPrice, $purchasePrice, $mappedData);
            fwrite($outputFile, $pRecord . PHP_EOL);
        }
    }
    
    // P-Satz formatieren (Preisänderungen)
    private function formatPRecord($articleId, $listPrice, $purchasePrice, $rowData) {
        if ($this->datanormVersion === '050') {
            // Datanorm 5.0 Semikolon-Format für P-Satz:
            // P;Artikel-Nr;Preis;EK;RABKZ;PREISEINH;ZEITEINHEIT;MWST;PREISFORM;
            
            // Formatierte Preise mit Punkt als Dezimaltrenner
            $formattedListPrice = number_format($listPrice, 2, '.', '');
            $formattedPurchasePrice = number_format($purchasePrice, 2, '.', '');
            
            // Zusätzliche Felder
            $rabattKz = isset($rowData['rabattgruppe']) ? $rowData['rabattgruppe'] : '';
            $preiseinheit = isset($rowData['preiseinheit']) ? $rowData['preiseinheit'] : '0'; // Standardmäßig pro Stück
            
            // Standardwerte für zusätzliche Felder
            $zeiteinheit = ""; // Keine Zeiteinheit
            $mwst = "19"; // Standardmehrwertsteuer (19%)
            $preisform = ""; // Keine besondere Preisform
            
            return "P;{$articleId};{$formattedListPrice};{$formattedPurchasePrice};{$rabattKz};{$preiseinheit};{$zeiteinheit};{$mwst};{$preisform};";
        } else {
            // Datanorm 4.0 Format für P-Satz:
            // P;Artikel-Nr;Preis;EK;RABKZ;ZEITEINHEIT;MWST;PREISFORM;
            
            // Formatierte Preise ohne Dezimalstellen für Version 4.0
            $formattedListPrice = number_format($listPrice, 0, '', '');
            $formattedPurchasePrice = number_format($purchasePrice, 0, '', '');
            
            // Zusätzliche Felder
            $rabattKz = isset($rowData['rabattgruppe']) ? $rowData['rabattgruppe'] : '';
            
            // Standardwerte für zusätzliche Felder
            $zeiteinheit = ""; // Keine Zeiteinheit
            $mwst = "19"; // Standardmehrwertsteuer (19%)
            $preisform = ""; // Keine besondere Preisform
            
            return "P;{$articleId};{$formattedListPrice};{$formattedPurchasePrice};{$rabattKz};{$zeiteinheit};{$mwst};{$preisform};";
        }
    }
    
    // Hilfsmethode zum Extrahieren von Daten aus einer Zeile anhand der Spaltenzuordnung
    private function mapRowToColumns($rowData) {
        $result = [];
        foreach ($this->columnMap as $key => $colIndex) {
            $result[$key] = isset($rowData[$colIndex]) ? trim($rowData[$colIndex]) : '';
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
            $priceFlag = isset($rowData['preiskennzeichen']) ? $rowData['preiskennzeichen'] : '1'; // 1=Listenpreis (Standard)
            $priceUnit = isset($rowData['preiseinheit']) ? $rowData['preiseinheit'] : '0';     // 0=Preis bezieht sich auf 1 Einheit
            $rabattGrp = isset($rowData['rabattgruppe']) ? $rowData['rabattgruppe'] : '';
            $warenGrp = isset($rowData['hauptwarengruppe']) ? $rowData['hauptwarengruppe'] : '';
            
            // A-Satz zusammenbauen mit Semikolons
            return "A;{$articleId};{$desc};{$formattedPrice};{$unit};{$priceFlag};{$priceUnit};{$rabattGrp};{$warenGrp};";
        } else {
            // Datanorm 4.0 Format für A-Satz:
            // A;N;Artikel-Nr;00;Beschreibung;Zusatzinfo;1;0;Mengeneinheit;Preis;15;42; ;
            
            // Preis formatieren ohne Dezimalstellen
            $formattedPrice = number_format($price, 0, '', '');
            
            // Zusatzinfo (kann leer sein oder eine zusätzliche Beschreibung)
            $additionalInfo = isset($rowData['kurztext2']) ? $rowData['kurztext2'] : "";
            
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
        // Nur verarbeiten, wenn Warengruppen-Spalten existieren
        if (!isset($this->columnMap['warengruppe']) && !isset($this->columnMap['hauptwarengruppe'])) {
            return; // Keine Warengruppen-Spalten
        }
        
        // Eindeutige Warengruppen sammeln
        $productGroups = [];
        
        // Zeilen verarbeiten
        foreach ($this->data as $rowData) {
            // Zeilendaten in assoziatives Array umwandeln
            $mappedData = $this->mapRowToColumns($rowData);
            
            // Leere Zeilen überspringen
            if (empty($mappedData['artikelnummer'])) {
                continue;
            }
            
            // Warengruppendaten extrahieren
            if (isset($mappedData['hauptwarengruppe']) && !empty($mappedData['hauptwarengruppe'])) {
                $groupId = $mappedData['hauptwarengruppe'];
                // Warengruppe als Beschreibung verwenden, falls vorhanden, sonst ID
                $groupDesc = isset($mappedData['warengruppe']) && !empty($mappedData['warengruppe']) ? 
                    $mappedData['warengruppe'] : $groupId;
                
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
            $info = "Erstellt mit CSV zu Datanorm Konverter";
            
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