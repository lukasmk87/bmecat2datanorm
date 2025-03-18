<?php
// converter.php - Konverter-Klasse für BMECat zu Datanorm mit Versionswechsel 4.0/5.0

class BMECatToDatanormConverter {
    private $bmecatFilePath;
    private $classFilePaths = [];
    private $additionalFilePath = null;
    private $xmlDoc;
    private $classXmlDocs = [];
    private $additionalXmlDoc = null;
    private $supplierName = '';
    private $supplierIdent = '';
    private $catalogVersion = '';
    private $catalogId = '';
    private $datanormVersion = '050'; // Standardversion ist 5.0
    
    // Konstruktor mit Versionsoption
    public function __construct($bmecatFilePath, $classFilePaths = [], $additionalFilePath = null, $datanormVersion = '050') {
        $this->bmecatFilePath = $bmecatFilePath;
        $this->classFilePaths = is_array($classFilePaths) ? $classFilePaths : [$classFilePaths];
        $this->additionalFilePath = $additionalFilePath;
        
        // Versionsvalidierung (nur 04 oder 050 erlaubt)
        if ($datanormVersion === '04' || $datanormVersion === '050') {
            $this->datanormVersion = $datanormVersion;
        } else {
            throw new Exception("Ungültige Datanorm-Version. Nur '04' oder '050' sind erlaubt.");
        }
        
        // Überprüfen, ob die Hauptdatei existiert
        if (!file_exists($bmecatFilePath)) {
            throw new Exception("BMECat-Datei nicht gefunden: $bmecatFilePath");
        }
        
        // XML-Dokumente laden
        $this->loadXmlDocuments();
    }
    
    // XML-Dokumente laden und validieren (unverändert)
    private function loadXmlDocuments() {
        libxml_use_internal_errors(true);
        
        // Hauptdokument laden
        $this->xmlDoc = new DOMDocument();
        if (!$this->xmlDoc->load($this->bmecatFilePath)) {
            $this->reportXmlErrors("Hauptdatei");
        }
        
        // Überprüfen, ob es sich um ein BMECat-Dokument handelt
        $rootElement = $this->xmlDoc->documentElement;
        if ($rootElement->nodeName !== 'BMECAT' && $rootElement->nodeName !== 'bmecat') {
            throw new Exception("Die Hauptdatei scheint kein gültiges BMECat-Format zu haben. Root-Element: " . $rootElement->nodeName);
        }
        
        // Klassifikationsdateien laden, wenn vorhanden
        foreach ($this->classFilePaths as $index => $classFilePath) {
            if ($classFilePath && file_exists($classFilePath)) {
                $classXmlDoc = new DOMDocument();
                if (!$classXmlDoc->load($classFilePath)) {
                    $this->reportXmlErrors("Klassifikationsdatei " . ($index + 1));
                }
                $this->classXmlDocs[] = $classXmlDoc;
            }
        }
        
        // Zusätzliche Datei laden, wenn vorhanden
        if ($this->additionalFilePath && file_exists($this->additionalFilePath)) {
            $this->additionalXmlDoc = new DOMDocument();
            if (!$this->additionalXmlDoc->load($this->additionalFilePath)) {
                $this->reportXmlErrors("Zusätzliche Datei");
            }
        }
        
        // Grundlegende Katalogdaten extrahieren
        $this->extractCatalogHeader();
    }
    
    // Katalog-Header-Informationen extrahieren (unverändert)
    private function extractCatalogHeader() {
        $xpath = new DOMXPath($this->xmlDoc);
        
        // Katalog-Version und ID
        $catalogNodes = $xpath->query('//CATALOG');
        if ($catalogNodes->length > 0) {
            $catalogNode = $catalogNodes->item(0);
            $this->catalogId = $this->getNodeValue($catalogNode, 'CATALOG_ID');
            $this->catalogVersion = $this->getNodeValue($catalogNode, 'CATALOG_VERSION');
        }
        
        // Lieferanten-Informationen
        $supplierNodes = $xpath->query('//SUPPLIER');
        if ($supplierNodes->length > 0) {
            $supplierNode = $supplierNodes->item(0);
            $this->supplierName = $this->getNodeValue($supplierNode, 'SUPPLIER_NAME');
            $this->supplierIdent = $this->getNodeValue($supplierNode, 'SUPPLIER_ID');
        }
    }
    
    // Hilfs-Methode zum Extrahieren von Werten aus Knoten (unverändert)
    private function getNodeValue($parentNode, $childNodeName) {
        $nodes = $parentNode->getElementsByTagName($childNodeName);
        if ($nodes->length > 0) {
            return $nodes->item(0)->nodeValue;
        }
        return '';
    }
    
    // XML-Fehler melden (unverändert)
    private function reportXmlErrors($fileDescription) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        $errorMsg = "XML-Parsing-Fehler in $fileDescription:";
        foreach ($errors as $error) {
            $errorMsg .= " " . $error->message;
        }
        
        throw new Exception($errorMsg);
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
        $supplierName = $this->supplierName;
        
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
    
    // A-Sätze (Artikeldaten) und Textsätze schreiben - unterstützt beide Versionen
    private function writeArticleRecords($outputFile) {
        $xpath = new DOMXPath($this->xmlDoc);
        
        // Alle Artikel im BMECat-Dokument finden
        $articleNodes = $xpath->query('//ARTICLE');
        
        foreach ($articleNodes as $articleNode) {
            // Artikeldaten extrahieren
            $articleId = $this->getNodeValue($articleNode, 'SUPPLIER_AID');
            $articleEan = $this->getNodeValue($articleNode, 'EAN');
            
            // Details extrahieren
            $detailsNode = $xpath->query('.//ARTICLE_DETAILS', $articleNode)->item(0);
            $articleDesc = $this->getNodeValue($detailsNode, 'DESCRIPTION_SHORT');
            $articleUnit = $this->getNodeValue($detailsNode, 'ORDER_UNIT');
            
            // Preisinformationen
            $priceNode = $xpath->query('.//ARTICLE_PRICE_DETAILS/ARTICLE_PRICE', $articleNode)->item(0);
            $price = 0;
            if ($priceNode) {
                $priceAmount = $xpath->query('.//PRICE_AMOUNT', $priceNode)->item(0);
                if ($priceAmount) {
                    $price = floatval($priceAmount->nodeValue);
                }
            }
            
            // A-Satz für den Artikel formatieren und schreiben
            $aRecord = $this->formatARecord($articleId, $articleDesc, $articleUnit, $price);
            fwrite($outputFile, $aRecord . PHP_EOL);
            
            if ($this->datanormVersion === '050') {
                // Für Version 5.0: T-Sätze für Artikelbeschreibung schreiben (wenn vorhanden)
                $longDesc = $this->getNodeValue($detailsNode, 'DESCRIPTION_LONG');
                if (!empty($longDesc)) {
                    $tRecords = $this->formatTRecords($articleId, $longDesc);
                    fwrite($outputFile, $tRecords);
                }
                
                // Weitere T-Sätze für zusätzliche Eigenschaften schreiben
                $features = $xpath->query('.//FEATURE', $articleNode);
                if ($features->length > 0) {
                    $featureText = "";
                    foreach ($features as $feature) {
                        $featureName = $this->getNodeValue($feature, 'FNAME');
                        $featureValue = $this->getNodeValue($feature, 'FVALUE');
                        
                        if (!empty($featureName) && !empty($featureValue)) {
                            if (!empty($featureText)) $featureText .= "\n";
                            $featureText .= $featureName . ": " . $featureValue;
                        }
                    }
                    
                    if (!empty($featureText)) {
                        $tRecords = $this->formatTRecords($articleId, $featureText);
                        fwrite($outputFile, $tRecords);
                    }
                }
            } else {
                // Für Version 4.0: B-Satz für Artikelbeschreibung schreiben
                $shortDesc = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $articleDesc), 0, 12));
                $bRecord = $this->formatBRecord($articleId, $shortDesc);
                fwrite($outputFile, $bRecord . PHP_EOL);
            }
        }
    }
    
    // Nur A-Sätze schreiben - für .001 Datei
    private function writeArticleRecordsWithoutTexts($outputFile) {
        $xpath = new DOMXPath($this->xmlDoc);
        
        // Alle Artikel im BMECat-Dokument finden
        $articleNodes = $xpath->query('//ARTICLE');
        
        foreach ($articleNodes as $articleNode) {
            // Artikeldaten extrahieren
            $articleId = $this->getNodeValue($articleNode, 'SUPPLIER_AID');
            
            // Details extrahieren
            $detailsNode = $xpath->query('.//ARTICLE_DETAILS', $articleNode)->item(0);
            $articleDesc = $this->getNodeValue($detailsNode, 'DESCRIPTION_SHORT');
            $articleUnit = $this->getNodeValue($detailsNode, 'ORDER_UNIT');
            
            // Preisinformationen
            $priceNode = $xpath->query('.//ARTICLE_PRICE_DETAILS/ARTICLE_PRICE', $articleNode)->item(0);
            $price = 0;
            if ($priceNode) {
                $priceAmount = $xpath->query('.//PRICE_AMOUNT', $priceNode)->item(0);
                if ($priceAmount) {
                    $price = floatval($priceAmount->nodeValue);
                }
            }
            
            // A-Satz für den Artikel formatieren und schreiben
            $aRecord = $this->formatARecord($articleId, $articleDesc, $articleUnit, $price);
            fwrite($outputFile, $aRecord . PHP_EOL);
        }
    }
    
    // Nur Textsätze schreiben - für .003 Datei
    private function writeTextRecordsOnly($outputFile) {
        $xpath = new DOMXPath($this->xmlDoc);
        
        // Alle Artikel im BMECat-Dokument finden
        $articleNodes = $xpath->query('//ARTICLE');
        
        foreach ($articleNodes as $articleNode) {
            // Artikeldaten extrahieren
            $articleId = $this->getNodeValue($articleNode, 'SUPPLIER_AID');
            
            // Details extrahieren
            $detailsNode = $xpath->query('.//ARTICLE_DETAILS', $articleNode)->item(0);
            $articleDesc = $this->getNodeValue($detailsNode, 'DESCRIPTION_SHORT');
            
            if ($this->datanormVersion === '050') {
                // Für Version 5.0: T-Sätze 
                $longDesc = $this->getNodeValue($detailsNode, 'DESCRIPTION_LONG');
                if (!empty($longDesc)) {
                    $tRecords = $this->formatTRecords($articleId, $longDesc);
                    fwrite($outputFile, $tRecords);
                }
                
                // Weitere T-Sätze für zusätzliche Eigenschaften
                $features = $xpath->query('.//FEATURE', $articleNode);
                if ($features->length > 0) {
                    $featureText = "";
                    foreach ($features as $feature) {
                        $featureName = $this->getNodeValue($feature, 'FNAME');
                        $featureValue = $this->getNodeValue($feature, 'FVALUE');
                        
                        if (!empty($featureName) && !empty($featureValue)) {
                            if (!empty($featureText)) $featureText .= "\n";
                            $featureText .= $featureName . ": " . $featureValue;
                        }
                    }
                    
                    if (!empty($featureText)) {
                        $tRecords = $this->formatTRecords($articleId, $featureText);
                        fwrite($outputFile, $tRecords);
                    }
                }
            } else {
                // Für Version 4.0: B-Satz
                $shortDesc = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $articleDesc), 0, 12));
                $bRecord = $this->formatBRecord($articleId, $shortDesc);
                fwrite($outputFile, $bRecord . PHP_EOL);
            }
        }
    }
    
    // A-Satz formatieren - unterstützt beide Versionen
    private function formatARecord($articleId, $desc, $unit, $price) {
        if ($this->datanormVersion === '050') {
            // Datanorm 5.0 Semikolon-Format für A-Satz:
            // A;Artikel-Nr;Beschreibung;Preis;Mengeneinheit;...
            
            // Preis formatieren mit Punkt als Dezimaltrenner
            $formattedPrice = number_format($price, 2, '.', '');
            
            // A-Satz zusammenbauen mit Semikolons
            return "A;{$articleId};{$desc};{$formattedPrice};{$unit};;;;;";
        } else {
            // Datanorm 4.0 Format für A-Satz:
            // A;N;Artikel-Nr;00;Beschreibung;Zusatzinfo;1;0;Mengeneinheit;Preis;15;42; ;
            
            // Preis formatieren ohne Dezimalstellen
            $formattedPrice = number_format($price, 0, '', '');
            
            // Zusatzinfo (kann leer sein oder eine zusätzliche Beschreibung)
            $additionalInfo = ""; // Könnte z.B. eine Erklärungszeile sein
            
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
        $words = explode(' ', $text);
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
        
        // Letzte Zeile hinzufügen
        if (!empty($currentLine)) {
            $lines[] = $currentLine;
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
        $xpath = new DOMXPath($this->xmlDoc);
        
        // Kategorien im BMECat finden
        $categoryNodes = $xpath->query('//CLASSIFICATION_SYSTEM/CLASSIFICATION_GROUP');
        
        foreach ($categoryNodes as $categoryNode) {
            $groupId = $this->getNodeValue($categoryNode, 'CLASSIFICATION_GROUP_ID');
            $groupDesc = $this->getNodeValue($categoryNode, 'CLASSIFICATION_GROUP_NAME');
            
            if (!empty($groupId) && !empty($groupDesc)) {
                // W-Satz formatieren und schreiben
                $wRecord = $this->formatWRecord($groupId, $groupDesc);
                fwrite($outputFile, $wRecord . PHP_EOL);
            }
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
            // In der Beispieldatei sind keine W-Sätze, wir nehmen ein ähnliches Format wie bei A-Sätzen an
            return "W;{$groupId};{$groupDesc};;;";
        }
    }
    
    // Z-Satz (END-Record) schreiben
    private function writeZRecord($outputFile) {
        if ($this->datanormVersion === '050') {
            // Datanorm 5.0 Semikolon-Format für E-Satz (entspricht Z-Satz):
            // E;Anzahl Datensätze;Erstellungsinfo;
            
            $recordCount = "0"; // Optional: Hier könnte die Anzahl der Datensätze stehen
            $info = "Created by BMECat to Datanorm Converter";
            
            $eRecord = "E;{$recordCount};{$info};";
            fwrite($outputFile, $eRecord . PHP_EOL);
        } else {
            // Datanorm 4.0 Format für Z-Satz (einfach nur "Z")
            $zRecord = "Z";
            fwrite($outputFile, $zRecord . PHP_EOL);
        }
    }
}