# Semi-Automatic Blacklist for Email Account

Ein PHP-Skript zur automatischen Verwaltung einer E-Mail-Domain-Blacklist mit IMAP-Integration.

## 📋 Funktionsweise

Das Skript durchläuft automatisch folgende Schritte:

1. **Spam-Ordner analysieren**: Liest alle E-Mails aus dem konfigurierten Spam-Ordner und extrahiert die Absender-Domains
2. **Blacklist aktualisieren**: Fügt neue Domains zur `blacklist.txt` hinzu (ohne Duplikate)
3. **Inbox durchsuchen**: Prüft ungelesene Mails im Posteingang gegen die Blacklist
4. **Automatische Aktion**: Markiert übereinstimmende Mails als gelesen und verschiebt sie in einen Zielordner

## 🚀 Installation

### Voraussetzungen

- PHP 7.4 oder höher
- PHP IMAP-Erweiterung aktiviert
- IMAP-Zugriff auf das E-Mail-Konto

### Setup

1. Repository klonen oder Dateien herunterladen:
   ```bash
   git clone https://github.com/KoljaL/semi-automatic-blacklist-for-Email-Account.git
   cd semi-automatic-blacklist-for-Email-Account
   ```

2. Konfigurationsdatei erstellen:
   ```bash
   cp config.example.ini config.ini
   ```

3. `config.ini` mit eigenen Zugangsdaten bearbeiten:
   ```ini
   [email]
   user = "deine@email.de"
   pass = "deinPasswort"
   server = "imap.example.com"
   port = "143"
   
   [folders]
   inbox = "INBOX"
   spam = "Spam"
   target = "mySpam"
   
   [settings]
   debug = false
   ```

4. Skript ausführen:
   ```bash
   php blacklist.php
   ```

## ⚙️ Konfiguration

### E-Mail-Einstellungen (`[email]`)

- **user**: Vollständige E-Mail-Adresse
- **pass**: Passwort für den E-Mail-Account (siehe Sicherheitshinweise)
- **server**: IMAP-Server-Adresse
- **port**: IMAP-Port (Standard: 143 für IMAP, 993 für IMAPS)

### Ordner-Einstellungen (`[folders]`)

- **inbox**: Name des Posteingangs-Ordners (meist "INBOX")
- **spam**: Name des Spam-Ordners, aus dem Domains extrahiert werden
- **target**: Zielordner für gefilterte Mails

### Allgemeine Einstellungen (`[settings]`)

- **debug**: `true` aktiviert ausführliche HTML-Ausgaben für Debugging

## 🔒 Sicherheitshinweise

### ⚠️ Wichtige Warnungen

1. **Zugangsdaten schützen**:
   - `config.ini` wird automatisch von Git ignoriert (siehe `.gitignore`)
   - **Niemals** `config.ini` in ein öffentliches Repository hochladen
   - Überprüfen: `git status` sollte `config.ini` nicht anzeigen

2. **App-Passwörter verwenden**:
   - Bei Gmail, Yahoo, etc.: Verwende App-spezifische Passwörter statt Haupt-Passwort
   - Aktiviere 2-Faktor-Authentifizierung und erstelle dann ein App-Passwort

3. **Datei-Berechtigungen**:
   ```bash
   chmod 600 config.ini  # Nur Besitzer kann lesen/schreiben
   ```

4. **Backup-System**:
   - Das Skript erstellt automatisch ein Backup von `blacklist.txt` vor Änderungen
   - Bei Fehlern wird das Backup automatisch wiederhergestellt

### SSL/TLS

Für verschlüsselte Verbindungen:
```ini
server = "{imap.example.com:993/imap/ssl}INBOX"
port = "993"
```

## 📁 Dateien

- **blacklist.php**: Haupt-Skript
- **blacklist.txt**: Blacklist-Datei (wird automatisch erstellt)
- **config.ini**: Persönliche Konfiguration (nicht im Repository)
- **config.example.ini**: Template für die Konfiguration
- **.gitignore**: Schützt sensible Dateien

## 🛠️ Automatisierung

### Cronjob einrichten (Linux/macOS)

Täglich um 2 Uhr ausführen:
```bash
crontab -e
```

Zeile hinzufügen:
```
0 2 * * * /usr/bin/php /pfad/zu/blacklist.php > /dev/null 2>&1
```

### Task Scheduler (Windows)

1. Task Scheduler öffnen
2. "Einfache Aufgabe erstellen"
3. PHP-Executable und Skript-Pfad angeben
4. Zeitplan festlegen

## 🐛 Fehlerbehandlung

Das Skript verfügt über umfassende Fehlerbehandlung:

- **Fehlende config.ini**: Aussagekräftige Fehlermeldung mit Anleitung
- **IMAP-Verbindungsfehler**: Automatisches Cleanup und klare Fehlermeldung
- **Dateifehler**: Automatische Erstellung von `blacklist.txt` falls nicht vorhanden
- **Backup-System**: Wiederherstellung bei Schreibfehlern

### Debug-Modus

Für detaillierte Ausgaben setze in `config.ini`:
```ini
[settings]
debug = true
```

Dann zeigt das Skript:
- Anzahl gefundener E-Mails
- Neu gefundene Domains
- Bereits bekannte Domains
- Anzahl geprüfter/markierter Mails
- Ausführungszeit

## 📝 Blacklist-Format

Die `blacklist.txt` enthält eine Domain pro Zeile:
```
spam-domain.com
another-spam.net
example-spam.org
```

- Keine Präfixe wie `@` oder `http://`
- Nur die reine Domain
- Leerzeilen werden automatisch entfernt

## 🤝 Beitragen

Beiträge sind willkommen! Bei Bugs oder Feature-Wünschen bitte ein Issue erstellen.

## 📄 Lizenz

MIT License - siehe Repository für Details

## 🙏 Credits

Entwickelt von [KoljaL](https://github.com/KoljaL)
