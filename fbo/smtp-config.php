<?php
/**
 * SMTP Configuration – Passwort-Reset per E-Mail
 *
 * Diese Datei mit deinen echten Zugangsdaten ausfüllen.
 * Sie darf NIEMALS über den Browser erreichbar sein!
 * Falls kein .htaccess-Schutz vorhanden ist, die Datei in
 * fbo/backend/ verschieben (der Ordner ist serverseits
 * already durch direkten Zugriff geschützt) und den Pfad
 * in load_smtp_config() in index.php anpassen.
 *
 * Gmail: Ein App-Passwort (16-stellig) unter
 * https://myaccount.google.com/apppasswords erstellen.
 * 2-Faktor-Authentifizierung muss dafür aktiv sein.
 */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit;
}

return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 465,                  // 465 = SSL/TLS  |  587 = STARTTLS
    'smtp_user' => 'fbeingongmail@gmail.com',    // Deine vollständige Gmail-Adresse
    'smtp_pass' => 'cdfi zcxr ljnc cqwn', // 16-stelliges App-Passwort (ohne Leerzeichen)
    'smtp_from' => 'fbeingongmail@gmail.com',    // Absender-Adresse (= smtp_user bei Gmail)
];
