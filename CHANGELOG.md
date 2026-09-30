# Changelog

## 3.0.1 - Dauerfehler stoppen, Kundenmail zuerst

Anlass: Vorfall #4671/#4673 (26.-30.09.2026). Ein zu großes PNG-Logo in der
WCPDF-Rechnung hat jeden Job mit „memory exhausted" beendet, rund 140 Fatals.

### Ursachen im Code

- Versuche wurden erst NACH der Arbeit gezählt. Ein Fatal kam nie dort an, der
  Zähler blieb bei 0, das Sicherheitsnetz startete endlos neu.
- Die Rechnung wird in `send_product_email()` für JEDE Produktmail per
  `wcpdf_get_document()->get_pdf()` erzeugt, auch ohne Vorlagen-Anhänge. Ein
  Speicher-Fatal dort ist nicht abfangbar und riss die Kundenmail mit.
- „erledigt & erneut versuchen" löschte den Vorfall und verarbeitete dann
  synchron im Admin-Request (Fehlerseite).
- Das Sicherheitsnetz sah nur Bestellungen der letzten 24 h. Deshalb endeten
  die Versuche für #4673 am 28.09. gegen 18:20 still, nicht wegen des Klicks.

### Behoben

- **Versuchszähler vor der Arbeit** gespeichert, max. 3 Versuche (1/5/15 Min.),
  danach Status `failed`, genau ein Vorfall + eine Warnmail, dann Ruhe.
- **Rechnung kann die Kundenmail nicht mehr blockieren**: Markierung vor dem
  Rendern; ist sie beim nächsten Versuch noch da, geht die Mail ohne Rechnung
  raus (Bestellnotiz). Ebenso bei < 96 MB freiem Speicher
  (Filter `bs_custom_mail_invoice_min_free_memory`) und bei Exceptions.
- **Fehlerursache wird erfasst**: Shutdown-Handler mit 1 MB Reserve speichert
  Meldung, Datei:Zeile, Schritt und Peak-Speicher. Erscheint im Vorfall, in der
  Warnmail und live im Banner („Versuch 2/3, Ursache: Speicher voll in …").
- **Zwei getrennte Admin-Aktionen**: „Erneut versuchen" plant einen
  Hintergrund-Job (Vorfall bleibt offen bis zum Erfolg), „Erledigt" schließt
  die Bestellung ohne Verarbeitung.
- **Doppelter Sweep**: Einplanen mit `$unique`, bestehende Duplikate werden
  stündlich geprüft und bereinigt.
- Sicherheitsnetz-Fenster 24 h → 3 Tage.
- Warnmail-Betreff nennt nicht mehr pauschal „Gutschein".

## 3.0.0 — Zuverlässigkeits-Überarbeitung

Behebt die Ursache wiederkehrender Vorfälle, bei denen eine Zahlung durchlief,
der Kunde aber weder Bestätigung noch geleerten Warenkorb sah. Zusätzlich die
Findings aus dem technischen Audit vom 21.08.2026 (K1–K3, H1–H4, M1–M8).

### Die Kernänderung

Bisher lief die gesamte Schwerarbeit — Coupon anlegen, PDF rendern,
Rechnungs-PDF erzeugen, mehrere Mails mit Anhängen versenden — **synchron im
Checkout-Request**. Stirbt dieser Request (Timeout bei Express-Zahlungen, Fatal,
abgebrochener Webhook), bleibt der Kunde ohne Bestätigung und mit vollem
Warenkorb zurück, obwohl das Geld abgebucht ist.

Ab 3.0.0 macht der Checkout-Request nur noch eins: einen Job einplanen. Alles
Weitere läuft in einem eigenen, sauberen Request. Die sichtbare Bestätigung und
das Leeren des Warenkorbs gehören damit wieder WooCommerce und WooPayments — das
Plugin kann sie nicht mehr blockieren.

### Neu

- **Async-Warteschlange** (`Bs_Custom_Mail_Queue`). Statuswechsel plant nur noch
  einen Job ein (`as_enqueue_async_action`), abgesichert gegen jeden Fehler, der
  sonst den Checkout mitreißen würde.
- **Wiederholung mit Backoff**: 1, 5, 15, 30 Minuten. Nach vier Fehlversuchen
  wird eskaliert statt aufgegeben.
- **Vorfall-Meldungen** (`Bs_Custom_Mail_Health`): dauerhafter Backend-Hinweis
  plus Alarm-Mail. Der Hinweis ist die primäre Meldung, weil eine Alarm-Mail
  häufig an derselben Ursache scheitern würde wie die Kunden-Mail.
- **SLA-Wächter**: Bestellungen, die 30 Minuten unverarbeitet bleiben, werden
  gemeldet — auch wenn das Sicherheitsnetz sie kurz danach heilt.
- **Systemseite** *Bootsschule Mail → Zustellung & System*: misst, ob geplante
  Aufgaben auf diesem Host tatsächlich laufen, listet offene Vorfälle und zeigt
  die Cron-URL. Bewusst in reinem PHP, damit sie auch dann lesbar bleibt, wenn
  das JavaScript-Bundle klemmt.
- **Externer Queue-Runner** (`/wp-json/bs-custom-mail/v1/run-queue?key=…`):
  schlüsselgeschützt, gedrosselt. Damit lässt sich die Zustellung ohne SSH und
  ohne Hosting-Panel über einen beliebigen Cron-Dienst antreiben.
- **Aufräum-Job**: Gutschein-PDFs werden nach 90 Tagen gelöscht
  (`cleanup_old_pdfs()` wurde bisher nie aufgerufen).

### Behoben

| ID | Problem | Lösung |
|---|---|---|
| K1 | Schwerarbeit im Checkout-Request | Async-Warteschlange |
| K2 | Gutschein-Mail wurde auch bei fehlgeschlagenem Versand als „gesendet" markiert | Flag nur bei `wp_mail() === true`, Fehler wird geloggt und wiederholt |
| K3 | Zustellung hing an traffic-abhängigem WP-Cron | Selbstdiagnose plus externer Cron-Endpunkt, Anleitung in `docs/BETRIEB.md` |
| H1 | Parallele Läufe konnten zwei gültige Coupons erzeugen | Atomare DB-Sperre pro Bestellung, mit TTL gegen verwaiste Sperren |
| H2 | HPOS-Blocker durch direkte `postmeta`-Zugriffe | Durchgehend Order-CRUD, `meta_query` im Sweep entfernt, Kompatibilität deklariert |
| H3 | Storno über die Admin-Oberfläche ließ den Coupon einlösbar | REST-Storno entwertet den Coupon jetzt wirklich; Reaktivierung ebenfalls |
| H4 | Express-Checkout übertrug Gutschein-Felder möglicherweise nicht | Express-Buttons auf Gutschein-Produkten standardmäßig aus (abschaltbar), zusätzlich harte Warenkorb-Validierung |
| M1 | Code-Eindeutigkeit nur gegen die eigene Tabelle geprüft | Prüft zusätzlich bestehende WooCommerce-Coupons |
| M2 | Entwertung per `usage_count` war fragil | Ablaufdatum in die Vergangenheit **und** Nutzungszähler |
| M3 | Ungültiger Gutscheinwert erzeugte nur eine Meldung, das Produkt landete trotzdem im Warenkorb | Validierung in `woocommerce_add_to_cart_validation`, blockiert den Kauf |
| M4 | Endgültig fehlgeschlagene Sendungen wurden stillschweigend übersprungen | Vorfall-Meldung mit Alarm |
| M5 | Tippfehler im Trigger-Status legte den Mailversand still | Validierung gegen die echten Bestellstatus, Fallback auf `processing` |
| M6 | Gutscheine liefen vor der gesetzlichen Frist ab | Gültig bis 31.12. des dritten Folgejahres |
| M7 | Rechnungs-PDF nutzte einen vorhersagbaren Temp-Dateinamen | Eindeutiges Suffix |
| — | Gutschein-PDF trug den Code im Dateinamen; `.htaccess` wirkt unter nginx nicht | 32 Zeichen Zufallsname, Datei wird nie öffentlich verlinkt |
| — | Virtuelle Produkte konnten von „ausstehend" direkt auf „abgeschlossen" springen und nie eine Bestätigung auslösen | `processing` **und** `completed` planen den Job ein |
| — | Ein bereits weitergerückter Bestellstatus verhinderte die Nacharbeit | Es wird alles verarbeitet außer storniert/erstattet/fehlgeschlagen |
| — | Käufer sah den Code nie, wenn eine Empfängeradresse gesetzt war | Kopie an den Käufer (abschaltbar) |
| — | Teilerstattungen blieben unbemerkt | Bestellnotiz zur manuellen Prüfung |

### Geändert

- `maybe_send_order_emails()` merkt sich den Fortschritt **pro Vorlage**. Eine
  Wiederholung nach einem Teilfehler versendet nur noch das, was wirklich fehlte.
- `send_product_email()` liefert `sent` / `failed` / `template_not_found` statt
  eines Booleans. Eine fehlende Vorlage ist kein wiederholbarer Fehler und wird
  gemeldet statt endlos versucht.
- `process_order_vouchers()` ist öffentlich und gibt einen Erfolgswert zurück.
  Die Markierung `_bs_vouchers_generated` wird erst gesetzt, wenn wirklich alles
  zugestellt wurde.
- Fehlgeschlagene CC-Kopien markieren die Kunden-Mail nicht mehr als
  fehlgeschlagen.
- Logging läuft unter der Quelle `bs-custom-mail` (vorher
  `bs-custom-mail-safety-net`).
- Neue Einstellungen: `express_guard`, `buyer_copy`.
- Deaktivierung räumt wiederkehrende Aufgaben ab; laufende Bestell-Jobs bleiben
  bewusst erhalten.

### Migration

Keine Datenbank-Migration nötig. Bereits eingeplante Jobs der Version 2.1.0
(`bs_custom_mail_safety_net_check`) werden weiterhin angenommen und in den neuen
Worker geleitet, damit beim Deployment keine offene Bestellung verloren geht.

**Nach dem Deployment zwingend:** `docs/BETRIEB.md` §1 (zuverlässiger Auslöser)
und `docs/ABNAHME.md` (Testprotokoll).
