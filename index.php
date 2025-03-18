<?php
// BMECat zu Datanorm5 Konverter
// index.php - Hauptdatei

// Fehlerberichterstattung für Entwicklung (in Produktion auskommentieren)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Maximale Ausführungszeit erhöhen für große Dateien
ini_set('max_execution_time', 300);

// Speichergrenze erhöhen
ini_set('memory_limit', '256M');

// Einstellungen für Multi-Upload
ini_set('file_uploads', 'On');
ini_set('max_file_uploads', '20');

// Temporäres Verzeichnis für Upload-Dateien
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Temporäres Verzeichnis für Ausgabedateien
$outputDir = 'output/';
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Funktion zur Bereinigung temporärer Dateien (älter als 1 Stunde)
function cleanupTempFiles($dir) {
    if (is_dir($dir)) {
        $files = scandir($dir);
        $now = time();
        
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $path = $dir . $file;
                if (is_file($path) && ($now - filemtime($path) >= 3600)) {
                    unlink($path);
                }
            }
        }
    }
}

// Temporäre Dateien bereinigen
cleanupTempFiles($uploadDir);
cleanupTempFiles($outputDir);

// Nachricht-Variable initialisieren
$message = '';
$downloadFile = '';

// Überprüfen, ob ein Upload stattgefunden hat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bmecatFiles'])) {
    // Debug-Ausgabe (bei Bedarf aktivieren)
    // echo "<pre>FILES: " . print_r($_FILES, true) . "</pre>";
    
    $uploaded_files = $_FILES['bmecatFiles'];
    $file_count = count($uploaded_files['name']);
    
    // Nur fortfahren, wenn mindestens eine Datei hochgeladen wurde
    if ($file_count > 0) {
        // Ausgabetyp bestimmen (Einzeldatei oder mehrere Dateien)
        $outputType = isset($_POST['outputType']) && $_POST['outputType'] === 'multi' ? 'multi' : 'single';
        
        // Datanorm-Version bestimmen
        $datanormVersion = isset($_POST['datanormVersion']) && $_POST['datanormVersion'] === '04' ? '04' : '050';
        
        // Arrays für die verschiedenen Dateipfade initialisieren
        $mainFilePath = null;
        $classFiles = [];
        
        // Alle hochgeladenen Dateien verarbeiten
        for ($i = 0; $i < $file_count; $i++) {
            // Überprüfen, ob die Datei erfolgreich hochgeladen wurde
            if ($uploaded_files['error'][$i] === UPLOAD_ERR_OK) {
                $original_filename = $uploaded_files['name'][$i];
                $temp_file = $uploaded_files['tmp_name'][$i];
                
                // Generiere einen eindeutigen Dateinamen im Upload-Verzeichnis
                $filename = uniqid('bmecat_') . '_' . basename($original_filename);
                $filepath = $uploadDir . $filename;
                
                // Verschiebe die temporäre Datei ins Upload-Verzeichnis
                if (move_uploaded_file($temp_file, $filepath)) {
                    // Klassifiziere die Datei basierend auf dem Dateinamen
                    if (strtolower($original_filename) === 'class.xml' || stripos($original_filename, 'class') !== false) {
                        // Es handelt sich um eine Klassifikationsdatei
                        $classFiles[] = $filepath;
                    } elseif ($mainFilePath === null) {
                        // Die erste Nicht-Klassifikationsdatei wird als Hauptdatei betrachtet
                        $mainFilePath = $filepath;
                    } else {
                        // Jede weitere Datei wird als zusätzliche Klassifikationsdatei betrachtet
                        $classFiles[] = $filepath;
                    }
                } else {
                    $message = "Fehler beim Speichern der Datei: " . $original_filename;
                }
            } else {
                $message = "Fehler beim Upload der Datei " . ($i + 1) . ": " . getUploadErrorMessage($uploaded_files['error'][$i]);
            }
        }
        
        // Stellen Sie sicher, dass zumindest eine Hauptdatei hochgeladen wurde
        if ($mainFilePath !== null) {
            // Konvertierung starten
            require_once 'converter.php';
            try {
                // Übergabe der Datanorm-Version als zusätzlicher Parameter
                $converter = new BMECatToDatanormConverter($mainFilePath, $classFiles, null, $datanormVersion);
                
                $baseFileName = uniqid('datanorm_');
                
                if ($outputType === 'single') {
                    // Einzelne Datei erzeugen
                    $outputFileName = $baseFileName . '.001';
                    $outputFilePath = $outputDir . $outputFileName;
                    
                    $result = $converter->convert($outputFilePath);
                    
                    if ($result) {
                        $message = "Konvertierung erfolgreich! (Datanorm Version: " . ($datanormVersion === '04' ? '4.0' : '5.0') . ")";
                        $downloadFile = $outputFileName;
                    } else {
                        $message = "Fehler bei der Konvertierung.";
                    }
                } else {
                    // Mehrere Dateien erzeugen
                    $outputFiles = [
                        '001' => $outputDir . $baseFileName . '.001',
                        '002' => $outputDir . $baseFileName . '.002',
                        '003' => $outputDir . $baseFileName . '.003'
                    ];
                    
                    $result = $converter->convertMultiFile($outputFiles);
                    
                    if ($result) {
                        // ZIP-Datei für den Download erstellen
                        $zipFileName = $baseFileName . '.zip';
                        $zipFilePath = $outputDir . $zipFileName;
                        
                        $zip = new ZipArchive();
                        if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
                            foreach ($outputFiles as $extension => $filePath) {
                                if (file_exists($filePath) && filesize($filePath) > 0) {
                                    $zip->addFile($filePath, basename($filePath));
                                }
                            }
                            $zip->close();
                            
                            $message = "Konvertierung erfolgreich! Mehrere Datanorm-Dateien wurden erzeugt. (Datanorm Version: " . ($datanormVersion === '04' ? '4.0' : '5.0') . ")";
                            $downloadFile = $zipFileName;
                        } else {
                            $message = "Konvertierung erfolgreich, aber ZIP-Erstellung fehlgeschlagen.";
                            $downloadFile = $baseFileName . '.001'; // Fallback zur ersten Datei
                        }
                    } else {
                        $message = "Fehler bei der Konvertierung.";
                    }
                }
            } catch (Exception $e) {
                $message = "Fehler: " . $e->getMessage();
            }
        } else {
            $message = "Keine gültige BMECat-Hauptdatei gefunden.";
        }
    } else {
        $message = "Bitte wählen Sie mindestens eine Datei aus.";
    }
}

// Hilfsfunktion für Upload-Fehlermeldungen
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "Die hochgeladene Datei überschreitet die upload_max_filesize Direktive in php.ini.";
        case UPLOAD_ERR_FORM_SIZE:
            return "Die hochgeladene Datei überschreitet die MAX_FILE_SIZE Direktive im HTML-Formular.";
        case UPLOAD_ERR_PARTIAL:
            return "Die Datei wurde nur teilweise hochgeladen.";
        case UPLOAD_ERR_NO_FILE:
            return "Es wurde keine Datei hochgeladen.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Temporärer Ordner fehlt.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Fehler beim Schreiben der Datei auf die Festplatte.";
        case UPLOAD_ERR_EXTENSION:
            return "Eine PHP-Erweiterung hat den Upload gestoppt.";
        default:
            return "Unbekannter Upload-Fehler.";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMECat zu Datanorm Konverter</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <style>
        body {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }
        .custom-file-input:lang(de)~.custom-file-label::after {
            content: "Durchsuchen";
        }
        .container {
            max-width: 800px;
        }
        .header {
            margin-bottom: 2rem;
        }
        .footer {
            margin-top: 3rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        #drop-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        #drop-area:hover, #drop-area.bg-light {
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05) !important;
        }
        #drop-area i {
            color: #6c757d;
            margin-bottom: 1rem;
        }
        #drop-area:hover i {
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BMECat zu Datanorm Konverter</h1>
            <p class="lead">Konvertieren Sie BMECat-XML-Dateien in das Datanorm-Format</p>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="alert <?php echo strpos($message, 'Fehler') !== false ? 'alert-danger' : 'alert-success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="bmecatFiles[]" class="form-label fw-bold">BMECat-Dateien auswählen:</label>
                        
                        <div id="drop-area" class="card p-4 mb-2 text-center">
                            <div id="drop-message">
                                <i class="bi bi-cloud-arrow-up fs-1"></i>
                                <p>Dateien hier ablegen oder klicken zum Auswählen</p>
                                <p class="text-muted small">BMECat-Hauptdatei und class.xml unterstützt</p>
                            </div>
                            <div id="file-list" class="d-none mt-3">
                                <h6>Ausgewählte Dateien:</h6>
                                <ul class="list-group" id="selected-files"></ul>
                            </div>
                            <input class="form-control d-none" type="file" id="bmecatFiles" name="bmecatFiles[]" multiple accept=".xml">
                        </div>
                        <div class="form-text">Wählen Sie die BMECat-Hauptdatei und ggf. die class.xml per Drag & Drop oder Dateiauswahl</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ausgabeformat:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="outputType" id="singleFile" value="single" checked>
                            <label class="form-check-label" for="singleFile">
                                Einzelne Datei (.001) - Alle Datensätze in einer Datei
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="outputType" id="multiFile" value="multi">
                            <label class="form-check-label" for="multiFile">
                                Mehrere Dateien (Datanorm-Standard) - Separate Dateien für verschiedene Datensatztypen
                            </label>
                            <div class="form-text ms-4">
                                • .001: Artikeldaten (A-Sätze)<br>
                                • .002: Warengruppendaten (W-Sätze)<br>
                                • .003: Textdaten (T/B-Sätze)
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Datanorm-Version:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="datanormVersion" id="version50" value="050" checked>
                            <label class="form-check-label" for="version50">
                                Version 5.0 (Modern) - Semikolon-getrennte Felder
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="datanormVersion" id="version40" value="04">
                            <label class="form-check-label" for="version40">
                                Version 4.0 (Legacy) - Kompatibel mit älteren Systemen
                            </label>
                            <div class="form-text ms-4">
                                Empfohlen für ältere ERP- und Warenwirtschaftssysteme, die das neuere Format nicht unterstützen.
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Konvertieren</button>
                </form>
                
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const dropArea = document.getElementById('drop-area');
                        const fileInput = document.getElementById('bmecatFiles');
                        const fileList = document.getElementById('file-list');
                        const selectedFiles = document.getElementById('selected-files');
                        const dropMessage = document.getElementById('drop-message');
                        
                        // Datei-Input mit Drop-Area verknüpfen
                        dropArea.addEventListener('click', function() {
                            fileInput.click();
                        });
                        
                        // Dateiliste aktualisieren
                        function updateFileList() {
                            selectedFiles.innerHTML = '';
                            
                            if (fileInput.files.length > 0) {
                                fileList.classList.remove('d-none');
                                dropMessage.classList.add('d-none');
                                
                                Array.from(fileInput.files).forEach(file => {
                                    const li = document.createElement('li');
                                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                                    
                                    // Icon basierend auf Dateiname hinzufügen
                                    let icon = '<i class="bi bi-file-earmark-code"></i>';
                                    if (file.name.toLowerCase().includes('class')) {
                                        icon = '<i class="bi bi-diagram-3"></i>';
                                    }
                                    
                                    li.innerHTML = `${icon} <span class="ms-2">${file.name}</span> <small class="text-muted">${(file.size / 1024).toFixed(1)} KB</small>`;
                                    selectedFiles.appendChild(li);
                                });
                            } else {
                                fileList.classList.add('d-none');
                                dropMessage.classList.remove('d-none');
                            }
                        }
                        
                        // Bei Änderung des Datei-Inputs
                        fileInput.addEventListener('change', updateFileList);
                        
                        // Drag & Drop Events
                        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                            dropArea.addEventListener(eventName, preventDefaults, false);
                        });
                        
                        function preventDefaults(e) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                        
                        // Styling beim Drag-Over
                        ['dragenter', 'dragover'].forEach(eventName => {
                            dropArea.addEventListener(eventName, highlight, false);
                        });
                        
                        ['dragleave', 'drop'].forEach(eventName => {
                            dropArea.addEventListener(eventName, unhighlight, false);
                        });
                        
                        function highlight() {
                            dropArea.classList.add('bg-light');
                        }
                        
                        function unhighlight() {
                            dropArea.classList.remove('bg-light');
                        }
                        
                        // Dateien beim Drop verarbeiten
                        dropArea.addEventListener('drop', handleDrop, false);
                        
                        function handleDrop(e) {
                            const dt = e.dataTransfer;
                            const files = dt.files;
                            
                            // In modernen Browsern kann DataTransfer.files nicht direkt zugewiesen werden
                            // Wir müssen daher einen FileList simulieren
                            const fileListInput = new DataTransfer();
                            for (let i = 0; i < files.length; i++) {
                                // Nur XML-Dateien hinzufügen
                                if (files[i].name.toLowerCase().endsWith('.xml')) {
                                    fileListInput.items.add(files[i]);
                                }
                            }
                            fileInput.files = fileListInput.files;
                            updateFileList();
                        }
                    });
                </script>
            </div>
        </div>
        
        <?php if (!empty($downloadFile)): ?>
        <div class="mt-4">
            <a href="<?php echo htmlspecialchars('download.php?file=' . urlencode($downloadFile)); ?>" class="btn btn-success" download>
                Datanorm-Datei herunterladen
            </a>
            <p class="form-text mt-2">Falls der Download nicht startet, <a href="<?php echo htmlspecialchars('output/' . urlencode($downloadFile)); ?>" download>klicken Sie hier für den direkten Download</a>.</p>
        </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <div class="accordion" id="accordionInfo">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingOne">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                            Informationen zum Konverter
                        </button>
                    </h2>
                    <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionInfo">
                        <div class="accordion-body">
                            <p>Dieser Konverter wandelt BMECat-XML-Dateien in das Datanorm-Format um.</p>
                            <p><strong>BMECat:</strong> Ein XML-basierter Standard für elektronische Produktkataloge.</p>
                            <p><strong>Datanorm:</strong> Ein Standard für den Austausch von Artikeldaten im deutschen Bauwesen/Handwerk.</p>
                            <p>Der Konverter extrahiert relevante Daten aus der BMECat-Struktur und ordnet sie dem entsprechenden Datanorm-Format zu.</p>
                            <p><strong>Unterstützte Versionen:</strong></p>
                            <ul>
                                <li><strong>Datanorm 5.0 (Modern):</strong> Die aktuelle Version mit Semikolon-getrennten Feldern</li>
                                <li><strong>Datanorm 4.0 (Legacy):</strong> Ältere Version für Kompatibilität mit bestehenden Systemen</li>
                            </ul>
                            <p><strong>Funktionsweise:</strong></p>
                            <ul>
                                <li>Laden Sie die BMECat-Hauptdatei und optional die class.xml hoch</li>
                                <li>Die Hauptdatei enthält Artikeldaten, während die class.xml Warengruppendefinitionen enthält</li>
                                <li>Sie können die Dateien per Drag & Drop oder über die Dateiauswahl hochladen</li>
                                <li>Wählen Sie das gewünschte Ausgabeformat: eine einzelne Datei oder mehrere Dateien</li>
                                <li>Wählen Sie die Datanorm-Version entsprechend Ihrem Zielsystem</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer text-center">
            <p>BMECat zu Datanorm Konverter &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>