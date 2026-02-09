# Vergleich: Alt vs. Neu

## Performance-Verbesserung

### ❌ Vorher (Ineffizient)
```php
foreach ($zeile as $line) {  // Für jede Domain in der Blacklist...
    $emails = imap_search($inbox, 'Unseen');  // ...rufe ALLE ungelesenen Mails ab!
    if ($emails) {
        foreach ($emails as $email_number) {
            // Prüfe Email gegen eine Domain
        }
    }
}
```
**Problem:** Bei 100 Blacklist-Domains = 100× IMAP-Abfragen für dieselben Emails!

### ✅ Nachher (Optimiert)
```php
$unseen_emails = imap_search($inbox, 'UNSEEN');  // Nur 1× alle Mails abrufen
if ($unseen_emails) {
    foreach ($unseen_emails as $email_number) {
        $sender_domain = trim($header->from[0]->host);
        // Prüfe gegen ALLE Domains auf einmal
        if (in_array($sender_domain, $blacklist_domains)) {
            // Match!
        }
    }
}
```
**Vorteil:** 1× IMAP-Abfrage statt N×, drastisch schneller!

---

## Sicherheits-Verbesserungen

### ❌ Vorher
```php
$user = '';  // Hardcoded im Code!
$pass = '';  // Gefahr wenn versehentlich committed!
$server = '';
```

### ✅ Nachher
```php
// config.ini (wird von .gitignore ausgeschlossen)
[email]
user = "meine@email.de"
pass = "geheim123"
server = "imap.example.com"
```
**Vorteil:** Keine Credentials im Code-Repository!

---

## Fehlerbehandlung

### ❌ Vorher
```php
$inbox = imap_open(...) or die('Cannot connect...: ' . imap_last_error());
// Skript stirbt abrupt, keine Cleanup-Möglichkeit
```

### ✅ Nachher
```php
try {
    $inbox_connection = connectIMAP(...);
    // Arbeite mit Verbindung
} catch (Exception $e) {
    echo "❌ FEHLER: " . $e->getMessage();
    // Cleanup durchführen
    restoreBlacklist();  // Backup wiederherstellen
} finally {
    if ($inbox_connection) {
        imap_close($inbox_connection);  // Ressourcen freigeben
    }
}
```
**Vorteil:** Sauberes Exception-Handling, automatisches Cleanup!

---

## Code-Qualität

### ❌ Vorher (goto)
```php
if (empty($arr)) {
  goto vergleichen;
}
// Code...
vergleichen:
// Mehr Code...
```

### ✅ Nachher (Strukturiert)
```php
if (!empty($new_domains)) {
    // Blacklist aktualisieren
}
// Immer: Inbox durchsuchen (kein goto nötig!)
```
**Vorteil:** Lesbarer, wartbarer Code!

---

## Debug-Ausgaben

### ❌ Vorher
```php
echo "Mails einlesen<br>";print_r($arr);  // Immer aktiv
echo "<br><br>Datei einlesen<br>";print_r($dat);
```

### ✅ Nachher
```php
if ($debug) {  // Steuerbar über config.ini
    echo "<h3>📧 Schritt 1: Spam-Ordner analysieren</h3>";
    echo "✅ Gefundene E-Mails im Spam-Ordner: " . count($emails) . "<br>";
    echo "🆕 Neue Domains: " . $added . "<br>";
}
```
**Vorteil:** Strukturiert, optional, informativ!

---

## Datensicherheit

### ❌ Vorher
```php
$f=fopen("blacklist.txt", "w+");  // Datei gelöscht!
fclose($f);
foreach ($dat as $adr) {
    file_put_contents("blacklist.txt", $adr . "\r\n", FILE_APPEND);
}
// Bei Fehler: Daten verloren!
```

### ✅ Nachher
```php
backupBlacklist();  // 1. Backup erstellen
try {
    writeBlacklistAtomic($domains);  // 2. In temp-Datei schreiben
    // 3. Atomar umbenennen (rename)
} catch (Exception $e) {
    restoreBlacklist();  // 4. Bei Fehler: Backup wiederherstellen
}
```
**Vorteil:** Atomares Schreiben + Backup = Keine Datenverluste!

---

## Zusammenfassung

| Aspekt | Vorher | Nachher |
|--------|--------|---------|
| **Performance** | O(N×M) IMAP-Calls | O(1) IMAP-Call + O(N×M) Array |
| **Sicherheit** | Hardcoded Credentials | config.ini (gitignored) |
| **Fehlerbehandlung** | die() Aufrufe | try-catch + finally |
| **Backup** | ❌ Keine | ✅ Automatisch |
| **goto** | ✅ Vorhanden | ❌ Eliminiert |
| **Debug** | Immer an | Optional (config) |
| **Dokumentation** | ❌ Keine | ✅ README.md |
