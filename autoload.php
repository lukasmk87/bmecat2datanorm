<?php
/**
 * Manueller Autoloader für PhpSpreadsheet
 * Speichern Sie diese Datei als 'autoload.php' im Hauptverzeichnis Ihres Projekts
 */

// PSR-4 Autoloader-Funktion
function phpspreadsheet_autoloader($class) {
    // Basis-Verzeichnis für Ihre PhpSpreadsheet-Installation
    $baseDir = __DIR__ . '/vendor';
    
    // Namespace-Präfixe und ihre Basisverzeichnisse
    $prefixes = [
        'PhpOffice\\PhpSpreadsheet\\' => $baseDir . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet',
        'Psr\\SimpleCache\\' => $baseDir . '/psr/simple-cache/src',
        'Complex\\' => $baseDir . '/markbaker/complex/classes/src',
        'Matrix\\' => $baseDir . '/markbaker/matrix/classes/src'
    ];

    // Alternative Verzeichnisse, falls Sie eine andere Struktur verwenden
    $altPrefixes = [
        'PhpOffice\\PhpSpreadsheet\\' => __DIR__ . '/phpspreadsheet/src/PhpSpreadsheet',
        'Psr\\SimpleCache\\' => __DIR__ . '/psr/simple-cache/src',
        'Complex\\' => __DIR__ . '/markbaker/complex/src',
        'Matrix\\' => __DIR__ . '/markbaker/matrix/src'
    ];

    // Prüfen der Standard-Präfixe
    foreach ($prefixes as $prefix => $dir) {
        // Prüfen, ob die Klasse mit dem Präfix beginnt
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        // Relativer Klassenname extrahieren
        $relativeClass = substr($class, $len);

        // Datei zusammensetzen
        $file = $dir . '/' . str_replace('\\', '/', $relativeClass) . '.php';

        // Wenn die Datei existiert, einbinden
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }
    
    // Alternative Verzeichnisse prüfen, falls Standard nicht funktioniert hat
    foreach ($altPrefixes as $prefix => $dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $dir . '/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return true;
        }
    }

    return false;
}

// Autoloader registrieren
spl_autoload_register('phpspreadsheet_autoloader');

// Manuelle Erforderung einiger Kern-Klassen
$baseClassFiles = [
    // PhpSpreadsheet Kern-Klassen
    'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php',
    'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Cell/Coordinate.php',
    'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Cell/DataType.php',
    'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Style/Fill.php',
    'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Style/Font.php',
    'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php',
    'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/Ods.php',
    
    // Alternative Verzeichnisse
    'phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php',
    'phpspreadsheet/src/PhpSpreadsheet/Cell/Coordinate.php',
    'phpspreadsheet/src/PhpSpreadsheet/Cell/DataType.php',
    'phpspreadsheet/src/PhpSpreadsheet/Style/Fill.php',
    'phpspreadsheet/src/PhpSpreadsheet/Style/Font.php',
    'phpspreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php',
    'phpspreadsheet/src/PhpSpreadsheet/Writer/Ods.php'
];

// Versuche, die wichtigsten Dateien manuell einzubinden (falls Autoloader nicht richtig funktioniert)
foreach ($baseClassFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        require_once __DIR__ . '/' . $file;
    }
}

// Prüfe, ob die Kern-Klasse vorhanden ist, und gib Hinweis aus
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    if (function_exists('error_log')) {
        error_log("PhpSpreadsheet-Klasse konnte nicht gefunden werden. Prüfen Sie die Installation.");
    }
}