# BMECat zu Datanorm Konverter

Diese Webanwendung konvertiert BMECat-XML-Dateien in das Datanorm-Format (Version 4.0 oder 5.0) für den einfachen Datenaustausch im Bauwesen und Handwerk.

## Installation

1. Laden Sie alle Dateien auf Ihren Webserver hoch
2. Stellen Sie sicher, dass die Verzeichnisse `uploads` und `output` existieren und beschreibbar sind:
   ```
   mkdir uploads output
   chmod 755 uploads output
   ```
3. Überprüfen Sie, dass die `.htaccess`-Datei korrekt übertragen wurde

## Systemanforderungen

- PHP 7.2 oder höher
- DOM und XML-Erweiterungen für PHP
- ZipArchive Erweiterung für das Erstellen von ZIP-Dateien bei Multi-Datei-Ausgabe
- Webserver mit Schreibrechten für die Verzeichnisse uploads/ und output/

## Dateien

- `index.php` - Hauptdatei mit Upload-Formular und Benutzeroberfläche
- `converter.php` - Konverter-Klasse zur Umwandlung von BMECat zu Datanorm
- `download.php` - Skript zum Herunterladen der konvertierten Datei
- `.htaccess` - Sicherheitseinstellungen für den Webserver

## Funktionsweise

1. Der Benutzer lädt eine BMECat-XML-Datei über das Webformular hoch
2. Die Anwendung validiert die XML-Datei und prüft, ob es sich um ein gültiges BMECat-Format handelt
3. Der Benutzer wählt die gewünschte Datanorm-Version (4.0 oder 5.0) aus
4. Der Konverter extrahiert relevante Daten und konvertiert sie in das gewählte Datanorm-Format
5. Eine Datanorm-Datei wird erzeugt und zum Download angeboten

## Datanorm-Formate

Die Anwendung unterstützt zwei Datanorm-Versionen:

### Datanorm 5.0 (Modern)
- Durchgängig semikolongetrennte Felder
- Aktuelle Version mit T-Sätzen für mehrzeilige Texte
- Format: `V;050;A;20250318;EUR;Lieferantenname;;;;;;;;;;`

### Datanorm 4.0 (Legacy)
- Teilweise feste Feldbreite, teilweise semikolongetrennte Felder
- Ältere Version mit B-Sätzen für Beschreibungen
- Format: `V 250318Lieferantenname Artikeldaten                                                                                    04EUR`
- Bietet Kompatibilität mit älteren ERP-Systemen

Die Anwendung erzeugt folgende Datanorm-Sätze:

- V-Satz (Vorlaufsatz): Enthält Lieferanten- und Versionsinformationen
- A-Satz (Artikelsatz): Enthält grundlegende Artikeldaten wie Nummer, Beschreibung, Preis
- B-Satz/T-Satz (Textsatz): Enthält erweiterte Artikelbeschreibungen (formatabhängig)
- W-Satz (Warengruppensatz): Enthält Informationen zu Warengruppen/Kategorien
- Z-Satz/E-Satz (Endesatz): Markiert das Ende der Datanorm-Datei

## Ausgabeoptionen

Die Anwendung bietet zwei Ausgabeoptionen:

1. **Einzeldatei**: Alle Sätze werden in einer Datei mit der Erweiterung .001 gespeichert
2. **Mehrere Dateien**: 
   - .001: Artikeldaten (A-Sätze)
   - .002: Warengruppendaten (W-Sätze)
   - .003: Textdaten (B-Sätze bei Version 4.0 / T-Sätze bei Version 5.0)

## Fehlerbehebung

- **Upload-Probleme**: Überprüfen Sie die Dateigrößenbeschränkungen in der .htaccess und php.ini
- **Konvertierungsfehler**: Prüfen Sie, ob Ihre BMECat-Datei dem Standard entspricht
- **Leere Output-Datei**: Überprüfen Sie die Schreibrechte für das output/-Verzeichnis
- **Format-Kompatibilitätsprobleme**: Falls Ihr Zielsystem die konvertierte Datei nicht lesen kann, versuchen Sie die andere Datanorm-Version (4.0 oder 5.0)

## Anpassung

Sie können die folgenden Parameter anpassen:

- Maximale Dateigrößen in der `.htaccess`-Datei
- Zeitlimit für die Verarbeitung in der `index.php` und `.htaccess`
- Speicherlimit für große Dateien in der `index.php` und `.htaccess`
- Standardversion des Datanorm-Formats in der `converter.php`

## Hinweise

- Die temporären Dateien werden automatisch nach einer Stunde gelöscht
- Für große Kataloge kann es notwendig sein, die Ausführungszeit und den Speicher zu erhöhen
- Bei Kompatibilitätsproblemen mit älteren Systemen verwenden Sie Datanorm 4.0