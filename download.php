<?php
// download.php - Datei zum Herunterladen der konvertierten Datanorm-Datei

// Fehlerberichterstattung für Entwicklung (in Produktion auskommentieren)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sicherheitscheck - Verhindern von Directory Traversal Angriffen
if (!isset($_GET['file']) || empty($_GET['file']) || strpos($_GET['file'], '/') !== false || strpos($_GET['file'], '\\') !== false) {
    header('HTTP/1.0 400 Bad Request');
    echo "Ungültige Anfrage";
    exit;
}

$filename = $_GET['file'];
$filepath = __DIR__ . '/output/' . $filename; // Absoluter Pfad

// Debug-Ausgabe (nur während der Fehlerbehebung)
// echo "Versuche Datei zu öffnen: " . $filepath; exit;

// Überprüfen, ob die Datei existiert
if (!file_exists($filepath)) {
    header('HTTP/1.0 404 Not Found');
    echo "Datei nicht gefunden: " . $filepath;
    exit;
}

// Überprüfen, ob die Datei lesbar ist
if (!is_readable($filepath)) {
    header('HTTP/1.0 403 Forbidden');
    echo "Keine Leseberechtigung für die Datei";
    exit;
}

// Download-Header setzen
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Ausgabe-Buffer leeren
ob_clean();
flush();

// Dateiinhalt ausgeben
if (readfile($filepath) === false) {
    echo "Fehler beim Lesen der Datei";
}
exit;