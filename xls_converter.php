<?php
// xls_converter.php - Einfacher Konverter für XLS zu CSV zu Datanorm (ohne PhpSpreadsheet)

class XlsToDatanormConverter {
    private $xlsFilePath;
    private $csvFilePath;
    private $datanormVersion = '050';
    private $supplierName = '';
    
    // Konstruktor mit Versionsoption
    public function __construct($xlsFilePath, $datanormVersion = '050') {
        $this->xlsFilePath = $xlsFilePath;
        
        // Versionsvalidierung
        if ($datanormVersion === '04' || $datanormVersion === '050') {
            $this->datanormVersion = $datanormVersion;
        } else {
            throw new Exception("Ungültige Datanorm-Version. Nur '04' oder '050' sind erlaubt.");
        }
        
        // Überprüfen, ob die Datei existiert
        if (!file_exists($xlsFilePath)) {
            throw new Exception("XLS-Datei nicht gefunden: $xlsFilePath");
        }
        
        // XLS in CSV umwandeln
        $this->convertXlsToCsv();
    }
    
    // XLS nach CSV umwandeln mit externer Konvertierung
    private function convertXlsToCsv() {
        // Temporäre CSV-Datei erstellen
        $this->csvFilePath = sys_get_temp_dir() . '/' . uniqid('csv_') . '.csv';
        
        // Prüfen, ob wir mit OLE-Parsern arbeiten können (für alte XLS)
        $useOleParser = class_exists('OLERead');
        
        if ($useOleParser) {
            // OLE-Parser zur XLS-Konvertierung verwenden
            $this->convertXlsWithOle();
        } else {
            // Externe Konvertierung versuchen (Unix-basierte Systeme)
            $this->convertWithExternalTools();
        }
        
        // Wenn keine CSV erzeugt werden konnte, Fehlermeldung
        if (!file_exists($this->csvFilePath) || filesize($this->csvFilePath) < 10) {
            throw new Exception("Konnte XLS nicht nach CSV konvertieren. Bitte konvertieren Sie die Datei manuell zu CSV.");
        }
    }
    
    // OLE-Parser für XLS-Konvertierung (falls OLE Klassen verfügbar)
    private function convertXlsWithOle() {
        require_once 'OLERead.php';
        
        try {
            $ole = new OLERead();
            $ole->read($this->xlsFilePath);
            
            $buffer = "";
            
            // Lese Arbeitsblätter
            foreach ($ole->worksheets as $worksheet) {
                $rows = [];
                
                // Lese alle Zeilen
                for ($row = 0; $row <= $worksheet->maxrow; $row++) {
                    $rowData = [];
                    
                    // Lese alle Spalten
                    for ($col = 0; $col <= $worksheet->maxcol; $col++) {
                        $cell = $worksheet->cellsInfo[$row][$col];
                        $rowData[] = isset($cell['val']) ? $cell['val'] : '';
                    }
                    
                    // Zeile zu CSV-Format hinzufügen
                    $rows[] = $this->formatCsvRow($rowData);
                }
                
                // Erste Seite ist genug
                $buffer = implode("\n", $rows);
                break;
            }
            
            // In CSV-Datei speichern
            file_put_contents($this->csvFilePath, $buffer);
        } catch (Exception $e) {
            // Stille Fehler - wir versuchen es mit externen Tools
        }
    }
    
    // Externe Tools für XLS-Konvertierung versuchen
    private function convertWithExternalTools() {
        // Versuche mit verschiedenen Fallback-Methoden
        
        // 1. Python mit Pandas/xlrd
        $command = "python -c \"import pandas as pd; pd.read_excel('{$this->xlsFilePath}').to_csv('{$this->csvFilePath}', index=False, sep=';')\" 2>/dev/null";
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($this->csvFilePath) && filesize($this->csvFilePath) > 10) {
            return; // Erfolgreich mit Python konvertiert
        }
        
        // 2. LibreOffice/OpenOffice
        $command = "libreoffice --headless --convert-to csv:\"Text - txt - csv (StarCalc)\":\"59,34,76,1,1/2\" --outdir " . dirname($this->csvFilePath) . " {$this->xlsFilePath} 2>/dev/null";
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0) {
            $baseName = basename($this->xlsFilePath, '.xls');
            $convertedFile = dirname($this->csvFilePath) . "/{$baseName}.csv";
            
            if (file_exists($convertedFile)) {
                rename($convertedFile, $this->csvFilePath);
                return; // Erfolgreich mit LibreOffice konvertiert
            }
        }
        
        // 3. Letzte Möglichkeit: SSL
        $command = "ssconvert {$this->xlsFilePath} {$this->csvFilePath} 2>/dev/null";
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($this->csvFilePath) && filesize($this->csvFilePath) > 10) {
            return; // Erfolgreich mit Gnumeric konvertiert
        }
        
        // Wenn alle Versuche fehlschlagen, erstellen wir eine leere CSV-Datei mit Fehlermeldung
        $errorMessage = "Artikelnummer;Kurztext1;Fehler\n";
        $errorMessage .= "ERROR;Konvertierung fehlgeschlagen;Bitte konvertieren Sie die XLS-Datei manuell zu CSV\n";
        file_put_contents($this->csvFilePath, $errorMessage);
    }
    
    // Hilfsfunktion zum Formatieren einer CSV-Zeile
    private function formatCsvRow($data) {
        $formattedData = [];
        
        foreach ($data as $value) {
            // Entferne Zeilenumbrüche und doppelte Anführungszeichen richtig behandeln
            $value = str_replace("\n", " ", $value);
            $value = str_replace('"', '""', $value);
            
            // Wenn Wert Semikolon enthält, in Anführungszeichen setzen
            if (strpos($value, ';') !== false) {
                $value = '"' . $value . '"';
            }
            
            $formattedData[] = $value;
        }
        
        return implode(';', $formattedData);
    }
    
    // Nutzung des CSV-Konverters für die tatsächliche Konvertierung
    public function convert($outputFilePath) {
        // CSV-Konverter instanziieren und verwenden
        require_once 'csv_converter.php';
        $csvConverter = new CsvToDatanormConverter($this->csvFilePath, $this->datanormVersion);
        
        // Lieferantennamen übertragen
        if (!empty($this->supplierName)) {
            $csvConverter->setSupplierName($this->supplierName);
        }
        
        // Tatsächliche Konvertierung durchführen
        return $csvConverter->convert($outputFilePath);
    }
    
    // Multi-Datei Konvertierung mit CSV-Konverter
    public function convertMultiFile($outputFiles) {
        // CSV-Konverter instanziieren und verwenden
        require_once 'csv_converter.php';
        $csvConverter = new CsvToDatanormConverter($this->csvFilePath, $this->datanormVersion);
        
        // Lieferantennamen übertragen
        if (!empty($this->supplierName)) {
            $csvConverter->setSupplierName($this->supplierName);
        }
        
        // Tatsächliche Konvertierung durchführen
        return $csvConverter->convertMultiFile($outputFiles);
    }
    
    // Aufräumen
    public function __destruct() {
        // Temporäre CSV-Datei löschen, wenn vorhanden
        if (file_exists($this->csvFilePath)) {
            @unlink($this->csvFilePath);
        }
    }
    
    // Lieferantennamen setzen
    public function setSupplierName($name) {
        $this->supplierName = $name;
    }
}