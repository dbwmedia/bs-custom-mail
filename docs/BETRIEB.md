# Betrieb: serverseitige Schritte

Diese Datei listet alles auf, was **außerhalb des Plugin-Codes** eingerichtet werden
muss. Ohne Schritt 1 ist die Zustell-Garantie von 60 Minuten nicht belastbar.

---

## 1. Zuverlässiger Auslöser für Hintergrundaufgaben (Pflicht)

### Warum

WordPress hat keinen echten Cron. `wp-cron.php` wird nur dann ausgeführt, wenn
jemand die Seite aufruft. Nachts oder bei wenig Traffic bedeutet das: eine
Bestellung, deren Zustellung einen zweiten Anlauf braucht, wartet, bis der
nächste Besucher kommt. Genau das erklärt die beobachtete Verzögerung von rund
1,5 Stunden.

Der Action Scheduler (Teil von WooCommerce) versucht zusätzlich, sich selbst per
"Loopback"-Request anzustoßen, also einem HTTP-Aufruf der Seite an sich selbst.
Viele Shared-Hosting-Umgebungen blockieren das.

### Ob es auf diesem Host nötig ist: selbst messen

Das Plugin misst das für dich. Im Backend unter **Bootsschule Mail → Zustellung &
System**:

| Anzeige | Bedeutung |
|---|---|
| Überfällige Aufgaben = 0 und Loopback = ok | Der Loopback funktioniert. Zustellung in Sekunden, ein externer Cron ist reine Härtung. |
| Überfällige Aufgaben > 0 | Es fehlt ein zuverlässiger Auslöser. Schritt 1 ist Pflicht. |
| Loopback = blockiert | Der Host lässt keine Selbstaufrufe zu. Schritt 1 ist Pflicht. |

**Diese Messung erst nach dem Deployment auf Staging durchführen.** Ohne Zugang
zum Server lässt sie sich vorher nicht seriös beantworten.

### Einrichtung (kein SSH und kein Plesk nötig)

Das Plugin stellt eine schlüsselgeschützte URL bereit, die die Warteschlange
abarbeitet. Die vollständige URL steht auf der Seite **Zustellung & System** und
sieht so aus:

```
https://DEINE-DOMAIN/wp-json/bs-custom-mail/v1/run-queue?key=XXXXXXXX
```

Diese URL muss **einmal pro Minute** aufgerufen werden. Zwei Wege:

**Variante A - Cronjob beim Hoster (bevorzugt)**
Im checkdomain-Kundenbereich den Cronjob-/Zeitplan-Bereich des Webhosting-Pakets
öffnen und einen Job anlegen, der die URL minütlich aufruft. Falls der Tarif nur
Skript-Cronjobs statt URL-Aufrufe erlaubt:

```bash
/usr/bin/curl -s "https://DEINE-DOMAIN/wp-json/bs-custom-mail/v1/run-queue?key=XXXXXXXX" > /dev/null
```

**Variante B - externer Cron-Dienst**
Falls der Tarif keine Cronjobs bietet: einen kostenlosen Dienst wie cron-job.org
nutzen und die URL dort im Minutentakt eintragen.

**Optional, zusätzlich:** Wenn ein Cron sicher läuft, kann WP-Cron abgeschaltet
werden, damit Besucher nicht mehr für Hintergrundaufgaben zahlen. In
`wp-config.php` oberhalb von `/* That's all, stop editing! */`:

```php
define( 'DISABLE_WP_CRON', true );
```

Das erst setzen, **nachdem** der externe Aufruf nachweislich funktioniert
(Zeile "Letzter externer Cron-Aufruf" auf der Systemseite füllt sich).

### Sicherheit des Endpunkts

Der Schlüssel ist ein 40 Zeichen langes Zufallsgeheimnis, wird per `hash_equals`
verglichen und ist auf einen Lauf alle 20 Sekunden gedrosselt. Der Endpunkt
liefert keine Kundendaten aus, sondern nur Zähler. Falls der Schlüssel je
öffentlich wird: Option `bs_custom_mail_runner_key` löschen, das Plugin erzeugt
beim nächsten Aufruf einen neuen.

---

## 2. ModSecurity-Sperre auf Staging (Voraussetzung für den Test)

Auf `staging.berlin-bootsschule.de` blockt ModSecurity aktuell den
Checkout-POST mit HTTP 403. Solange das so ist, lässt sich kein einziger Kauf
testen.

Beim Hoster anfordern: ModSecurity für die Staging-Domain deaktivieren oder die
auslösende Regel-ID auf der Whitelist eintragen. Die Regel-ID steht im
ModSecurity-Log zum Zeitpunkt des 403; falls kein Log-Zugriff besteht, den
Hoster-Support um die Regel-ID zum Vorfallszeitpunkt bitten.

Auf Live muss dieselbe Frage geklärt sein, bevor ausgerollt wird - sonst
verschiebt man das Problem nur.

---

## 3. E-Mail-Zustellbarkeit (SMTP, SPF, DKIM, DMARC)

Das Plugin ruft `wp_mail()` auf. Ohne konfiguriertes SMTP versendet PHP direkt
vom Webserver - Mails mit PDF-Anhängen landen dann überdurchschnittlich oft im
Spam. Das ist die häufigste Ursache für "Gutschein kam nicht an", die **nicht**
im Code liegt.

Zu prüfen und einzurichten:

1. **SMTP**: Versand über einen authentifizierten Postausgangsserver der
   Absenderdomain (checkdomain-Postfach) oder über einen Transaktions-Dienst
   (Brevo, Postmark, Mailgun). Konfiguration über ein SMTP-Plugin.
2. **Absenderadresse**: Unter *Bootsschule Mail → Einstellungen* muss die
   Absenderadresse zur Domain gehören, von der aus versendet wird. Eine
   Absenderadresse `@gmail.com` scheitert zwangsläufig an DMARC.
3. **SPF**: DNS-TXT-Eintrag, der den versendenden Server autorisiert.
4. **DKIM**: Signaturschlüssel des Versanddienstes im DNS hinterlegen.
5. **DMARC**: Start mit `v=DMARC1; p=none; rua=mailto:...`, um Reports zu
   bekommen, bevor schärfer gestellt wird.

Prüfen lässt sich das Ergebnis mit einem Test an eine Adresse bei mail-tester.com
über *Bootsschule Mail → Templates → Test senden*.

---

## 4. Schutz der Gutschein-PDFs

**Was das Plugin jetzt selbst löst:** Gutschein-PDFs bekommen einen zufälligen
Dateinamen aus 32 Zeichen. Der Gutscheincode steht nicht mehr im Dateinamen, und
die Datei wird nirgends öffentlich verlinkt - sie reist ausschließlich als
E-Mail-Anhang.

**Warum nicht anders gelöst:** Das Verzeichnis
`wp-content/uploads/bs-vouchers/` enthält eine `.htaccess` mit `deny from all`.
Unter nginx wird diese Datei schlicht ignoriert. Der zufällige Dateiname ist
deshalb der Schutz, der auf diesem Host tatsächlich greift.

**Optionale Härtung** (nur mit Zugriff auf die nginx-Konfiguration):

```nginx
location ^~ /wp-content/uploads/bs-vouchers/ {
    deny all;
    return 404;
}
```

**Altbestand:** Vor diesem Update erzeugte PDFs tragen den Gutscheincode im
Dateinamen. Sie sind theoretisch erratbar. Da sie nach 90 Tagen ohnehin
automatisch gelöscht werden (neuer Aufräum-Job), besteht kein akuter
Handlungsbedarf; wer auf Nummer sicher gehen will, löscht den Ordnerinhalt nach
dem Deployment einmalig.

---

## 5. HPOS aktivieren (nach bestandener Abnahme)

Das Plugin deklariert jetzt HPOS-Kompatibilität und greift nirgends mehr direkt
auf `postmeta` zu. Nach erfolgreicher Staging-Abnahme:

*WooCommerce → Einstellungen → Erweitert → Features →* "High-Performance Order
Storage" aktivieren. WooCommerce bietet vorher einen Synchronisationslauf an -
den abwarten.

Wichtig: **Alle** aktiven Plugins müssen HPOS-kompatibel sein. Vor dem Umschalten
die Kompatibilitätsliste auf derselben Seite prüfen; `bs-bootsschule-booking`
und `bootsschule-kategorie-sorter` sind noch nicht geprüft.

---

## 6. Benötigte Zugänge

| Zweck | Was gebraucht wird | Status |
|---|---|---|
| Plugin auf Staging deployen | SFTP/SSH **oder** WP-Admin mit Plugin-Upload | offen |
| Systemseite ablesen, Einstellungen | WP-Admin auf Staging (Administrator) | offen |
| Checkout testbar machen | ModSecurity-Ausnahme (Hoster) | offen |
| Cron einrichten | checkdomain-Kundenbereich | offen |
| Ursache endgültig belegen | WooCommerce-Logs, PHP-Error-Log | offen |
| Webhook-Verhalten prüfen | Stripe-Dashboard (WooPayments) | offen |
| Apple-Pay-Test | echtes iOS-Gerät mit hinterlegter Karte | offen |
