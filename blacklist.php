<?php
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
        $arr[] = substr(strstr($senderadresse, '@'), 1);
    }
}
echo "Mails einlesen<br>";print_r($arr);

// die bestehenden Domains aus der Textdatei einlesen und im Array $dat[] speichern
// nach dem Auslesen müssen Zeilenumbrüche entfernt werden
$zeile = file("blacklist.txt");
for ($i=0;$i < count($zeile); $i++) {
    $zeile[$i] = str_replace(array(" "), "", $zeile[$i]);
    $dat[] = $zeile[$i];
}
echo "<br><br>Datei einlesen<br>";print_r($dat);

// beide Arrays zusammenführen
$dat = array_merge($dat, $arr);
echo "<br><br>merge<br>";print_r($dat);

// doppelte Einträge löschen, Leerzeilen löschen, Index neu setzen
$dat = array_unique($dat);
$dat = array_filter($dat);
$dat = array_values($dat);
echo "<br><br>unique<br>";print_r($dat);

// bestehende Datei leeren
$f=fopen("blacklist.txt", "w+");
fclose($f);

// Domains in die Datei schreiben
foreach ($dat as $adr) {
    file_put_contents("blacklist.txt", $adr, FILE_APPEND);
}

// Inbox öffnen, Blacklist Datei öffnen zeilenweise ausesen und Zeilenumbrüche und Leerzeilen entfernen
// Ungesehenen Mails durchgehen und Senderdomain mit der Liste aus der Datei abgleichen
// bei Treffer Mail verschieben, marieren oder löschen
$inbox = imap_open('{' . $server . ':' . $port . '}' . $inboxfolder, $user, $pass) or die('Cannot connect...: ' . imap_last_error());
$lines = file("blacklist.txt");
foreach ($lines as $line) {
    $line = str_replace(array("\r\n","\n","\r"," "), "", $line);
    $emails = imap_search($inbox, 'Unseen');
    //echo "<br>";print_r($emails);echo "<br>";
    if ($emails) {
        foreach ($emails as $email_number) {
            $uid            = imap_uid($inbox, $email_number);
            $header         = imap_headerinfo($inbox, $email_number);
            if ($header->from[0]->host == $line) {
                imap_setflag_full($inbox, $uid, "\\Seen", ST_UID);
                // imap_clearflag_full($inbox, $uid, "\\Flagged");
                // imap_mail_move($inbox,$uid,'mySpam');
                echo $line . "markiert <br>";
            }
        } // foreach $emails as $email_number
    } // if emials
} // foreach $lines as $line

// Ordnernamen des Postfaches anzeigenlassen
// Hilfreich, wenn Mails verschoben werden sollen
$mailboxes = imap_list($inbox, '{' . $server . ':' . $port . '}', '*');
echo "<br><br>";
foreach ($mailboxes as $mailbox) {
    print $mailbox . "<br>";
}

imap_close($inbox, CL_EXPUNGE);
