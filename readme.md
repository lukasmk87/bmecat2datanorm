# Datanorm-Konverter

Diese Webanwendung konvertiert BMECat-XML, Excel (XLS/XLSX) und CSV-Dateien in das Datanorm-Format (Version 4.0 oder 5.0) für den einfachen Datenaustausch im Bauwesen und Handwerk. Unterstützt werden auch erweiterte Warengruppen (WRG-Dateien) und Preisänderungssätze.

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
- DOM und XML-Erweiterungen für PHP (für BMECat-Konvertierung)
- ZipArchive Erweiterung für das Erstellen von ZIP-Dateien bei Multi-Datei-Ausgabe
- Webserver mit Schreibrechten für die Verzeichnisse uploads/ und output/

## Dateien

- `index.php` - Hauptdatei mit Upload-Formular und Benutzeroberfläche
- `converter.php` - Konverter-Klasse zur Umwandlung von BMECat zu Datanorm
- `csv_converter.php` - Konverter-Klasse zur Umwandlung von CSV zu Datanorm
- `xls_converter.php` - Konverter-Klasse zur Umwandlung von Excel zu Datanorm (über CSV)
- `download.php` - Skript zum Herunterladen der konvertierten Datei
- `.htaccess` - Sicherheitseinstellungen für den Webserver

## Unterstützte Formate

### Eingabeformate

1. **BMECat XML** - Elektronischer Produktkatalog im XML-Format
- Hauptdatei mit Artikeldaten
- Optional class.xml mit Klassifikationsdaten

2. **Excel (XLS/XLSX)** - Tabellenkalkulationsdateien von Microsoft
- Erste Zeile muss Spaltenüberschriften enthalten
- Mindestanforderung: Spalten für Artikelnummer und Kurztext1

3. **CSV** - Textdateien mit Werten durch Semikolon getrennt
- Erste Zeile muss Spaltenüberschriften enthalten
- Semikolon (;) als Trennzeichen

### Ausgabeformate

1. **Einzeldatei** (.001) - Alle Datensätze in einer Datei

2. **Mehrere Dateien** - Datanorm-Standard:
- .001: Artikeldaten (A-Sätze)
- .002: Warengruppendaten (W-Sätze)
- .003: Textdaten (B/T-Sätze)
- .004: Erweiterte Warengruppen und Preisänderungen (G/P-Sätze)
- .wrg: Identisch mit .004, aber mit Standarderweiterung

## Unterstützte Datanorm-Sätze

- **A-Sätze**: Artikeldaten (Nummer, Beschreibung, Preis, etc.)
- **B-Sätze**: Textbeschreibungen (Datanorm 4.0)
- **T-Sätze**: Mehrzeilige Texte (Datanorm 5.0)
- **W-Sätze**: Warengruppendaten
- **G-Sätze**: Erweiterte Warengruppendaten (in .004/.wrg Datei)
- **P-Sätze**: Preisänderungssätze (Listen- und Einkaufspreise)
- **V-Sätze**: Vorlaufsätze (Header)
- **Z/E-Sätze**: Endesätze (Footer)

## Funktionsweise

1. Der Benutzer lädt eine Datei (BMECat, Excel oder CSV) über das Webformular hoch
2. Die Anwendung erkennt das Format und wählt den entsprechenden Konverter
3. Der Benutzer wählt die gewünschte Datanorm-Version (4.0 oder 5.0) und Ausgabeformat
4. Der Konverter extrahiert relevante Daten und konvertiert sie in das gewählte Datanorm-Format
5. Eine oder mehrere Datanorm-Dateien werden erzeugt und zum Download angeboten

## Excel/CSV-Format

Für die Konvertierung von Excel oder CSV-Dateien wird folgende Spaltenstruktur unterstützt:

### Pflichtfelder:
- **Artikelnummer**: Die eindeutige Artikelnummer 
- **Kurztext1**: Kurzbeschreibung des Artikels (max. 40 Zeichen)

### Optionale Felder:
- **Kurztext2**: Zusätzliche Beschreibung (max. 40 Zeichen)
- **Mengeneinheit**: z.B. Stck, m, kg (Standard: Stck)
- **Preis**: Artikelpreis/Listenpreis ohne MwSt
- **Einkaufspreis**: Einkaufspreis ohne MwSt (für P-Sätze)
- **Rabattgruppe**: Rabattgruppenzuordnung 
- **Preiskennzeichen**: 1=Listenpreis, 2=Einkaufspreis
- **Preiseinheit**: 0=Einzelpreis, 1=10er, 2=100er, 3=1000er
- **Hauptwarengruppe**: Primäre Warengruppenklassifikation
- **Warengruppe**: Sekundäre Warengruppenklassifikation
- **WRG-Beschreibung**: Beschreibung für erweiterte Warengruppen
- **EAN-Nummer**: Europäische Artikelnummer / GTIN
- **Matchcode**: Suchbegriff
- **Ausschreibungstext**: Ausführliche Beschreibung (für Datanorm 5.0)
- **Bild1-Bild5**: Bildverweise (für Datanorm 5.0)

Die Spaltenüberschriften können in verschiedenen Varianten vorkommen (z.B. "Artikelnummer", "ArtikelNr", "Artikel-Nr", etc.) und werden automatisch erkannt.

## Datanorm-Formate

Die Anwendung unterstützt zwei Datanorm-Versionen:

### Datanorm 5.0 (Modern)
- Durchgängig semikolongetrennte Felder
- Aktuelle Version mit T-Sätzen für mehrzeilige Texte
- Unterstützung für G-Sätze und WRG-Dateien
- Format: `V;050;A;20250318;EUR;Lieferantenname;;;;;;;;;;`

### Datanorm 4.0 (Legacy)
- Teilweise feste Feldbreite, teilweise semikolongetrennte Felder
- Ältere Version mit B-Sätzen für Beschreibungen
- Format: `V 250318Lieferantenname Artikeldaten                                                                                    04EUR`
- Bietet Kompatibilität mit älteren ERP-Systemen

## Anpassung

Sie können die folgenden Parameter anpassen:

- Maximale Dateigrößen in der `.htaccess`-Datei
- Zeitlimit für die Verarbeitung in der `index.php` und `.htaccess`
- Speicherlimit für große Dateien in der `index.php` und `.htaccess`
- Standardversion des Datanorm-Formats in den Konverter-Klassen

## Fehlerbehebung

- **Upload-Probleme**: Überprüfen Sie die Dateigrößenbeschränkungen in der .htaccess und php.ini
- **Konvertierungsfehler bei BMECat**: Prüfen Sie, ob Ihre BMECat-Datei dem Standard entspricht
- **Konvertierungsfehler bei Excel**: Bei Problemen konvertieren Sie die Datei manuell zu CSV
- **Leere Output-Datei**: Überprüfen Sie die Schreibrechte für das output/-Verzeichnis
- **Format-Kompatibilitätsprobleme**: Falls Ihr Zielsystem die konvertierte Datei nicht lesen kann, versuchen Sie die andere Datanorm-Version (4.0 oder 5.0)

## Hinweise

- Die temporären Dateien werden automatisch nach einer Stunde gelöscht
- Für große Kataloge kann es notwendig sein, die Ausführungszeit und den Speicher zu erhöhen
- Bei Excel/CSV-Dateien werden Listen- und Einkaufspreise für P-Sätze benötigt
- Bei BMECat-Dateien werden Einkaufspreise gesucht oder als 75% des Listenpreises geschätzt