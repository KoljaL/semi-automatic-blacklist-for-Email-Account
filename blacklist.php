<?php
/**
 * Semi-Automatic Blacklist for Email Account
 * 
 * Dieses Skript liest E-Mails aus einem Spam-Ordner, extrahiert Absender-Domains,
 * aktualisiert eine Blacklist und markiert/verschiebt übereinstimmende Mails im Posteingang.
 */

$time_start = microtime(true);

// Konstanten definieren
define('CONFIG_FILE', 'config.ini');
define('BLACKLIST_FILE', 'blacklist.txt');
define('BACKUP_SUFFIX', '.backup');

/**
 * Konfiguration laden und validieren
 * 
 * @return array Konfigurationsarray
 * @throws Exception wenn config.ini nicht gefunden oder ungültig
 */
function loadConfig() {
    if (!file_exists(CONFIG_FILE)) {
        throw new Exception(
            "FEHLER: Konfigurationsdatei '" . CONFIG_FILE . "' nicht gefunden!\n\n" .
            "Bitte erstellen Sie die Datei:\n" .
            "1. Kopieren Sie 'config.example.ini' nach 'config.ini'\n" .
            "2. Tragen Sie Ihre E-Mail-Zugangsdaten ein\n" .
            "3. Führen Sie das Skript erneut aus\n"
        );
    }
    
    $config = parse_ini_file(CONFIG_FILE, true);
    
    if ($config === false) {
        throw new Exception("FEHLER: Konnte '" . CONFIG_FILE . "' nicht parsen. Bitte Syntax überprüfen.");
    }
    
    // Erforderliche Felder validieren
    $required = ['email' => ['user', 'pass', 'server', 'port'], 
                 'folders' => ['inbox', 'spam', 'target']];
    
    foreach ($required as $section => $fields) {
        if (!isset($config[$section])) {
            throw new Exception("FEHLER: Sektion [$section] fehlt in " . CONFIG_FILE);
        }
        foreach ($fields as $field) {
            if (!isset($config[$section][$field]) || empty($config[$section][$field])) {
                throw new Exception("FEHLER: Feld '$field' in Sektion [$section] ist leer oder fehlt in " . CONFIG_FILE);
            }
        }
    }
    
    return $config;
}

/**
 * Blacklist-Datei sichern
 * 
 * @return bool True bei Erfolg, False wenn Datei nicht existiert
 */
function backupBlacklist() {
    if (!file_exists(BLACKLIST_FILE)) {
        return false;
    }
    return copy(BLACKLIST_FILE, BLACKLIST_FILE . BACKUP_SUFFIX);
}

/**
 * Blacklist aus Backup wiederherstellen
 * 
 * @return bool True bei Erfolg
 */
function restoreBlacklist() {
    if (file_exists(BLACKLIST_FILE . BACKUP_SUFFIX)) {
        return copy(BLACKLIST_FILE . BACKUP_SUFFIX, BLACKLIST_FILE);
    }
    return false;
}

/**
 * Blacklist-Datei atomar schreiben
 * 
 * @param array $domains Array mit Domains
 * @return bool True bei Erfolg
 * @throws Exception bei Schreibfehlern
 */
function writeBlacklistAtomic($domains) {
    $tempFile = BLACKLIST_FILE . '.tmp';
    
    try {
        // In temporäre Datei schreiben
        $content = implode("\r\n", $domains) . "\r\n";
        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            throw new Exception("Konnte nicht in temporäre Datei schreiben");
        }
        
        // Atomar umbenennen
        if (!rename($tempFile, BLACKLIST_FILE)) {
            throw new Exception("Konnte temporäre Datei nicht umbenennen");
        }
        
        return true;
    } catch (Exception $e) {
        // Cleanup: Temporäre Datei löschen
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        throw $e;
    }
}

/**
 * Domain aus E-Mail-Adresse extrahieren
 * 
 * @param string $email E-Mail-Adresse
 * @return string Domain oder leerer String
 */
function extractDomain($email) {
    $parts = explode('@', $email);
    return isset($parts[1]) ? trim($parts[1]) : '';
}

/**
 * Blacklist-Datei laden oder erstellen
 * 
 * @param bool $debug Debug-Modus
 * @return array Array mit Domains
 */
function loadBlacklist($debug) {
    if (!file_exists(BLACKLIST_FILE)) {
        if ($debug) {
            echo "ℹ️ Blacklist-Datei existiert nicht, erstelle neue Datei...<br>";
        }
        file_put_contents(BLACKLIST_FILE, '');
        return [];
    }
    
    if (!is_readable(BLACKLIST_FILE)) {
        throw new Exception("FEHLER: Blacklist-Datei ist nicht lesbar. Bitte Berechtigungen prüfen.");
    }
    
    $lines = file(BLACKLIST_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_map('trim', $lines);
}

/**
 * IMAP-Verbindung öffnen
 * 
 * @param string $server Server
 * @param string $port Port
 * @param string $folder Ordner
 * @param string $user Benutzername
 * @param string $pass Passwort
 * @return resource IMAP-Verbindung
 * @throws Exception bei Verbindungsfehlern
 */
function connectIMAP($server, $port, $folder, $user, $pass) {
    $mailbox = '{' . $server . ':' . $port . '}' . $folder;
    $connection = @imap_open($mailbox, $user, $pass);
    
    if (!$connection) {
        throw new Exception("IMAP-Verbindung fehlgeschlagen: " . imap_last_error());
    }
    
    return $connection;
}

// ========== HAUPTPROGRAMM ==========

try {
    // Konfiguration laden
    $config = loadConfig();
    // Debug-Modus: parse_ini_file konvertiert 'true'/'false' zu Booleans,
    // !empty() prüft zusätzlich ob Setting existiert (defensiv)
    $debug = !empty($config['settings']['debug']) && $config['settings']['debug'];
    
    if ($debug) {
        echo "<h2>🔍 Debug-Modus aktiv</h2>";
        echo "<hr>";
    }
    
    // ========== SCHRITT 1: Spam-Ordner analysieren ==========
    if ($debug) {
        echo "<h3>📧 Schritt 1: Spam-Ordner analysieren</h3>";
    }
    
    $spam_connection = null;
    $new_domains = [];
    
    try {
        $spam_connection = connectIMAP(
            $config['email']['server'],
            $config['email']['port'],
            $config['folders']['spam'],
            $config['email']['user'],
            $config['email']['pass']
        );
        
        $emails = imap_search($spam_connection, 'ALL');
        
        if ($emails) {
            rsort($emails);
            
            if ($debug) {
                echo "✅ Gefundene E-Mails im Spam-Ordner: " . count($emails) . "<br>";
            }
            
            foreach ($emails as $email_number) {
                $header = imap_headerinfo($spam_connection, $email_number);
                if (isset($header->from[0])) {
                    $sender_email = $header->from[0]->mailbox . "@" . $header->from[0]->host;
                    $domain = extractDomain($sender_email);
                    if (!empty($domain)) {
                        $new_domains[] = $domain;
                    }
                }
            }
            
            if ($debug && !empty($new_domains)) {
                echo "📋 Extrahierte Domains: " . count($new_domains) . "<br>";
            }
        } else {
            if ($debug) {
                echo "ℹ️ Keine E-Mails im Spam-Ordner gefunden<br>";
            }
        }
    } finally {
        if ($spam_connection) {
            imap_close($spam_connection);
        }
    }
    
    // ========== SCHRITT 2: Blacklist aktualisieren ==========
    $blacklist_domains = loadBlacklist($debug);
    $original_count = count($blacklist_domains);
    $original_blacklist = $blacklist_domains; // Für korrekte Vergleiche speichern
    
    if (!empty($new_domains)) {
        if ($debug) {
            echo "<h3>📝 Schritt 2: Blacklist aktualisieren</h3>";
            echo "Aktuelle Blacklist-Einträge: $original_count<br>";
        }
        
        // Backup erstellen
        backupBlacklist();
        
        try {
            // Domains zusammenführen
            $blacklist_domains = array_merge($blacklist_domains, $new_domains);
            
            // Duplikate entfernen, Leerzeilen filtern, Index neu setzen
            $blacklist_domains = array_unique($blacklist_domains);
            $blacklist_domains = array_filter($blacklist_domains);
            $blacklist_domains = array_values($blacklist_domains);
            
            // Atomar speichern
            writeBlacklistAtomic($blacklist_domains);
            
            $new_count = count($blacklist_domains);
            $added = $new_count - $original_count;
            
            if ($debug) {
                echo "✅ Blacklist aktualisiert: $new_count Einträge (+" . $added . " neue)<br>";
                if ($added > 0) {
                    // Vergleich gegen ursprüngliche Blacklist vor dem Merge
                    $newly_added_domains = array_diff($new_domains, $original_blacklist);
                    if (!empty($newly_added_domains)) {
                        echo "🆕 Neue Domains: <pre>" . implode("\n", array_slice($newly_added_domains, 0, 10));
                        if (count($newly_added_domains) > 10) echo "\n... und " . (count($newly_added_domains) - 10) . " weitere";
                        echo "</pre>";
                    }
                }
            }
        } catch (Exception $e) {
            // Bei Fehler: Backup wiederherstellen
            restoreBlacklist();
            throw new Exception("Fehler beim Schreiben der Blacklist: " . $e->getMessage());
        }
    } else {
        if ($debug) {
            echo "<h3>📝 Schritt 2: Blacklist aktualisieren</h3>";
            echo "ℹ️ Keine neuen Domains zum Hinzufügen<br>";
            echo "Aktuelle Blacklist-Einträge: $original_count<br>";
        }
    }
    
    // ========== SCHRITT 3: Inbox durchsuchen und Mails markieren ==========
    if ($debug) {
        echo "<h3>🔍 Schritt 3: Inbox durchsuchen</h3>";
    }
    
    $inbox_connection = null;
    $marked_uids = [];
    
    try {
        $inbox_connection = connectIMAP(
            $config['email']['server'],
            $config['email']['port'],
            $config['folders']['inbox'],
            $config['email']['user'],
            $config['email']['pass']
        );
        
        // Ungesehene E-Mails einmal abrufen (Performance-Optimierung!)
        $unseen_emails = imap_search($inbox_connection, 'UNSEEN');
        
        if ($unseen_emails) {
            if ($debug) {
                echo "📬 Ungelesene E-Mails gefunden: " . count($unseen_emails) . "<br>";
                echo "🔎 Prüfe gegen Blacklist mit " . count($blacklist_domains) . " Einträgen...<br>";
            }
            
            $checked_count = 0;
            $matched_count = 0;
            
            foreach ($unseen_emails as $email_number) {
                $header = imap_headerinfo($inbox_connection, $email_number);
                
                if (isset($header->from[0]->host)) {
                    $sender_domain = trim($header->from[0]->host);
                    $checked_count++;
                    
                    // Domain gegen komplette Blacklist prüfen
                    if (in_array($sender_domain, $blacklist_domains)) {
                        $uid = imap_uid($inbox_connection, $email_number);
                        $marked_uids[] = $uid;
                        $matched_count++;
                        
                        if ($debug) {
                            echo "🎯 Match gefunden: <strong>$sender_domain</strong> (UID: $uid)<br>";
                        }
                    }
                }
            }
            
            if ($debug) {
                echo "<br>📊 Zusammenfassung Prüfung:<br>";
                echo "- Geprüfte E-Mails: $checked_count<br>";
                echo "- Blacklist-Treffer: $matched_count<br>";
            }
        } else {
            if ($debug) {
                echo "ℹ️ Keine ungelesenen E-Mails im Posteingang<br>";
            }
        }
        
        // ========== SCHRITT 4: Markierte Mails bearbeiten ==========
        if (!empty($marked_uids)) {
            if ($debug) {
                echo "<h3>✉️ Schritt 4: Markierte Mails bearbeiten</h3>";
                echo "Verschiebe " . count($marked_uids) . " E-Mail(s) nach '{$config['folders']['target']}'...<br>";
            }
            
            foreach ($marked_uids as $uid) {
                imap_setflag_full($inbox_connection, $uid, "\\Seen", ST_UID);
                imap_clearflag_full($inbox_connection, $uid, "\\Flagged", ST_UID);
                imap_mail_move($inbox_connection, $uid, $config['folders']['target'], ST_UID);
            }
            
            imap_expunge($inbox_connection);
            
            if ($debug) {
                echo "✅ E-Mails erfolgreich verschoben<br>";
            }
        } else {
            if ($debug) {
                echo "<h3>✉️ Schritt 4: Markierte Mails bearbeiten</h3>";
                echo "ℹ️ Keine E-Mails zum Verschieben<br>";
            }
        }
        
    } finally {
        if ($inbox_connection) {
            imap_close($inbox_connection, CL_EXPUNGE);
        }
    }
    
    // ========== FERTIG ==========
    $time_elapsed = microtime(true) - $time_start;
    
    if ($debug) {
        echo "<hr>";
        echo "<h3>✅ Fertig!</h3>";
        echo "⏱️ Ausführungszeit: " . round($time_elapsed, 3) . " Sekunden<br>";
    } else {
        echo "✅ Erfolgreich abgeschlossen in " . round($time_elapsed, 3) . " Sekunden<br>";
    }
    
} catch (Exception $e) {
    // Fehlerbehandlung mit Cleanup
    echo "<h3>❌ FEHLER</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    
    // Backup wiederherstellen falls vorhanden
    if (file_exists(BLACKLIST_FILE . BACKUP_SUFFIX)) {
        echo "<p>🔄 Versuche Wiederherstellung aus Backup...</p>";
        if (restoreBlacklist()) {
            echo "<p>✅ Backup erfolgreich wiederhergestellt</p>";
        }
    }
    
    exit(1);
}

/*
 * Hilfsfunktion: Ordnernamen des Postfaches anzeigen
 * 
 * Um die verfügbaren Ordner anzuzeigen, folgenden Code aktivieren:
 * 
 * $connection = connectIMAP($config['email']['server'], $config['email']['port'], 
 *                           '', $config['email']['user'], $config['email']['pass']);
 * $mailboxes = imap_list($connection, '{' . $config['email']['server'] . ':' . 
 *                        $config['email']['port'] . '}', '*');
 * echo "<br><br>Verfügbare Ordner:<br>";
 * foreach ($mailboxes as $mailbox) {
 *     echo htmlspecialchars($mailbox) . "<br>";
 * }
 * imap_close($connection);
 */
