<?php
$time_start = microtime(true);

$user = '';
$pass = '';
$server = '';
$port = "143";
$inboxfolder = 'INBOX'; // Funktion zum Finden von Ordnernamen ist am Ende des Skripts
$spamfolder = "Spam";

// Ungesehene Mails aus dem Ordner SPAM lesen, daraus die Senderdomains (@domain.com) filtern und in das $array[] schreiben
$inbox = imap_open('{' . $server . ':' . $port . '}' . $spamfolder, $user, $pass) or die('Cannot connect...: ' . imap_last_error());
$emails = imap_search($inbox, 'ALL');
if ($emails) {
    rsort($emails);
    foreach ($emails as $email_number) {
        $header = imap_headerinfo($inbox, $email_number);
        $senderadresse = $header->from[0]->mailbox . "@" . $header->from[0]->host;
        $arr[] = trim(substr(strstr($senderadresse, '@'), 1));
    }
}
// Sprungmarke, falls der Spamordner leer ist müssen keine neuen Domains hinzugefügt werden
if (empty($ar)) {
  goto vergleichen;
}
echo "Mails einlesen<br>";print_r($arr);

// die bestehenden Domains aus der Textdatei einlesen und im Array $dat[] speichern
$zeile = file("blacklist.txt");
for ($i=0;$i < count($zeile); $i++) {
    $dat[] = trim($zeile[$i]);
}
echo "<br><br>Datei einlesen<br>";print_r($dat);

// beide Arrays zusammenführen
$dat = array_merge($dat, $arr);
echo "<br><br>merge<br>";print_r($dat);

// doppelte Einträge löschen, Leerzeilen löschen, Index neu setzen
$dat = array_unique($dat);
$dat = array_filter($dat);
$dat = array_values($dat);
echo "<br><br>unique<br>";print_r($dat);echo "<br><br><br>";

// bestehende Datei leeren
$f=fopen("blacklist.txt", "w+");
fclose($f);

// Domains in die Datei schreiben
foreach ($dat as $adr) {
    file_put_contents("blacklist.txt", $adr . "\r\n", FILE_APPEND);
}

// Sprungmarke, falls der Spamordner leer ist müssen keine neuen Domains hinzugefügt werden
vergleichen:
// Inbox öffnen, Blacklist Datei öffnen zeilenweise ausesen
// Ungesehenen Mails durchgehen und Senderdomain mit der Liste aus der Datei abgleichen
// bei Treffer Mail marikeren
$inbox = imap_open('{' . $server . ':' . $port . '}' . $inboxfolder, $user, $pass) or die('Cannot connect...: ' . imap_last_error());
$zeile = file("blacklist.txt");
foreach ($zeile as $line) {
    $emails = imap_search($inbox, 'Unseen');
    if ($emails) {
        foreach ($emails as $email_number) {
            $uid = imap_uid($inbox, $email_number);
            $header = imap_headerinfo($inbox, $email_number);
            if (trim($header->from[0]->host) == trim($line)) {
                $marked[] = $uid;
                echo $line . "markiert <br><br>";
            } // if host == list
        } // foreach $emails as $email_number
    } // if emials
} // foreach $lines as $line

// Markierte Mails bearbeiten
if ($marked) {
    foreach ($marked as $mark) {
        imap_setflag_full($inbox, $mark, "\\Seen", ST_UID);
        imap_clearflag_full($inbox, $mark, "\\Flagged", ST_UID);
        imap_mail_move($inbox, $mark, 'mySpam', ST_UID);
        imap_expunge($inbox);
    }
}


// Ordnernamen des Postfaches anzeigenlassen
// Hilfreich, wenn Mails verschoben werden sollen
// $mailboxes = imap_list($inbox, '{' . $server . ':' . $port . '}', '*');
// echo "<br><br>";
// foreach ($mailboxes as $mailbox) {
//     print $mailbox . "<br>";
// }

imap_close($inbox, CL_EXPUNGE);

$time = microtime(true) - $time_start;

echo "<br>In $time Sekunden nichts getan\n";
