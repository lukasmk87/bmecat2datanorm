# BMECat zu Datanorm5 Konverter

Diese Webanwendung konvertiert BMECat-XML-Dateien in das Datanorm5-Format für den einfachen Datenaustausch im Bauwesen und Handwerk.

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
- Webserver mit Schreibrechten für die Verzeichnisse uploads/ und output/

## Dateien

- `index.php` - Hauptdatei mit Upload-Formular und Benutzeroberfläche
- `converter.php` - Konverter-Klasse zur Umwandlung von BMECat zu Datanorm5
- `download.php` - Skript zum Herunterladen der konvertierten Datei
- `.htaccess` - Sicherheitseinstellungen für den Webserver

## Funktionsweise

1. Der Benutzer lädt eine BMECat-XML-Datei über das Webformular hoch
2. Die Anwendung validiert die XML-Datei und prüft, ob es sich um ein gültiges BMECat-Format handelt
3. Der Konverter extrahiert relevante Daten und konvertiert sie in das Datanorm5-Format
4. Eine Datanorm5-Datei wird erzeugt und zum Download angeboten

## Datanorm5-Format

Die Anwendung erzeugt folgende Datanorm5-Sätze:

- V-Satz (Vorlaufsatz): Enthält Lieferanten- und Versionsinformationen
- A-Satz (Artikelsatz): Enthält grundlegende Artikeldaten wie Nummer, Beschreibung, Preis
- B-Satz (Textsatz): Enthält erweiterte Artikelbeschreibungen
- W-Satz (Warengruppensatz): Enthält Informationen zu Warengruppen/Kategorien
- Z-Satz (Endesatz): Markiert das Ende der Datanorm-Datei

## Fehlerbehebung

- **Upload-Probleme**: Überprüfen Sie die Dateigrößenbeschränkungen in der .htaccess und php.ini
- **Konvertierungsfehler**: Prüfen Sie, ob Ihre BMECat-Datei dem Standard entspricht
- **Leere Output-Datei**: Überprüfen Sie die Schreibrechte für das output/-Verzeichnis

## Anpassung

Sie können die folgenden Parameter anpassen:

- Maximale Dateigrößen in der `.htaccess`-Datei
- Zeitlimit für die Verarbeitung in der `index.php` und `.htaccess`
- Speicherlimit für große Dateien in der `index.php` und `.htaccess`

## Hinweise

- Die temporären Dateien werden automatisch nach einer Stunde gelöscht
- Für große Kataloge kann es notwendig sein, die Ausführungszeit und den Speicher zu erhöhen