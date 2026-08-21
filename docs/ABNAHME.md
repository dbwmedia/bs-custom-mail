# Abnahmeprotokoll Staging

Durchführung auf `staging.berlin-bootsschule.de`. Kein Deploy auf Live, bevor
alle Kriterien grün sind.

**Voraussetzungen vor Testbeginn**

- [ ] Plugin v3.0.0 auf Staging installiert und aktiviert
- [ ] ModSecurity-Sperre auf dem Checkout-POST entfernt (siehe BETRIEB.md §2)
- [ ] WooPayments auf Staging im Testmodus, Apple Pay aktiviert
- [ ] Gutschein-Produkt vorhanden, PDF-Vorlage zugewiesen
- [ ] Seite *Bootsschule Mail → Zustellung & System* erreichbar

**Messpunkt für alle Tests:** WooCommerce → Status → Protokolle, Quelle
`bs-custom-mail`. Dort steht jeder Schritt mit Zeitstempel.

---

## A1 — Kauf per Karte

| Schritt | Erwartet | Ergebnis |
|---|---|---|
| Gutschein mit Wert 50 € in den Warenkorb, Testkarte `4242 4242 4242 4242` | Kauf geht durch | ☐ |
| Direkt nach Absenden | Seite "Bestellung erhalten" erscheint **sofort** | ☐ |
| Warenkorb-Symbol prüfen | Warenkorb ist leer | ☐ |
| Posteingang | Gutschein-Mail mit PDF **innerhalb von 60 Sekunden** | ☐ |
| Bestellung im Backend | Notiz "Wertgutschein erstellt: …", keine Warnung | ☐ |
| WooCommerce → Marketing → Gutscheine | **genau ein** Coupon zu dieser Bestellung | ☐ |

## A2 — Kauf per Apple Pay / Express-Checkout

> Braucht ein echtes iOS-Gerät mit hinterlegter Testkarte. Der Express-Button ist
> auf Gutschein-Produkten **standardmäßig ausgeblendet** (Schutz gegen verlorene
> Gutschein-Felder). Für diesen Test unter *Einstellungen* `express_guard`
> abschalten.

| Schritt | Erwartet | Ergebnis |
|---|---|---|
| Gutschein-Produktseite auf dem iPhone öffnen | Express-Button sichtbar (nach Abschalten des Schutzes) | ☐ |
| Wert eintragen, Geschenkfelder ausfüllen, per Apple Pay kaufen | Kauf geht durch | ☐ |
| Direkt nach der Zahlung | Bestätigungsseite **sofort**, Warenkorb leer | ☐ |
| Bestellposition im Backend | `_bs_voucher_value` entspricht dem **eingegebenen** Wert, Empfänger und Nachricht vorhanden | ☐ |
| Posteingang des Empfängers | Gutschein-Mail innerhalb von 60 Sekunden | ☐ |
| Posteingang des Käufers | Kopie der Gutschein-Mail | ☐ |

**Wenn der Wert oder die Geschenkfelder fehlen:** Express-Checkout überträgt die
Felder auf diesem Shop nicht. Dann `express_guard` wieder einschalten — der
Schutz bleibt dauerhaft aktiv und der Kunde kauft Gutscheine über den normalen
Checkout. Kein Blocker für den Rollout.

## A3 — Erzwungener Fehlerfall: Mailversand blockiert

Mailversand temporär lahmlegen, am einfachsten per Snippet in der
`functions.php` des Child-Themes:

```php
add_filter( 'pre_wp_mail', '__return_false' );
```

| Schritt | Erwartet | Ergebnis |
|---|---|---|
| Gutschein kaufen (Karte) | Bestätigungsseite erscheint trotzdem sofort, Warenkorb leer | ☐ |
| Backend, Bestellposition | `_bs_voucher_email_sent` ist **nicht** gesetzt | ☐ |
| Log nach ~1 Minute | Eintrag "fehlgeschlagen (Versuch 1/4). Neuer Versuch in 60 s." | ☐ |
| Snippet nach dem 1. Fehlversuch wieder entfernen | — | ☐ |
| Posteingang | Mail kommt **innerhalb von 10 Minuten** an | ☐ |
| Coupon-Liste | weiterhin **genau ein** Coupon | ☐ |

## A4 — Endgültiger Fehlschlag: Admin-Alarm

Snippet aus A3 setzen und **eingeschaltet lassen**.

| Schritt | Erwartet | Ergebnis |
|---|---|---|
| Gutschein kaufen, dann ~35 Minuten warten (alle 4 Versuche) | — | ☐ |
| *Zustellung & System* | Bestellung steht unter "Offene Vorfälle" | ☐ |
| Jede Backend-Seite | roter Hinweis oben | ☐ |
| Bestellnotizen | "⚠️ … konnte nach 4 Versuchen nicht zugestellt werden" | ☐ |
| Snippet entfernen, "Erneut versuchen" klicken | Mail geht raus, Vorfall verschwindet | ☐ |

## A5 — Worst Case: Job wurde nie eingeplant

Simuliert einen Request, der stirbt, bevor der Status-Hook läuft.

| Schritt | Erwartet | Ergebnis |
|---|---|---|
| Bestellung manuell im Backend anlegen, Gutschein-Produkt, Status auf "In Bearbeitung" | — | ☐ |
| In der DB die Meta `_bs_cm_state` dieser Bestellung löschen und die geplante Aktion in WooCommerce → Status → Geplante Aktionen abbrechen | — | ☐ |
| Warten bzw. Cron-URL manuell aufrufen | Sweep verarbeitet die Bestellung, Mail geht raus | ☐ |
| Gesamtzeit | **unter 60 Minuten** | ☐ |

## A6 — Keine Doppel-Coupons bei Parallellauf

| Schritt | Erwartet | Ergebnis |
|---|---|---|
| Testbestellung mit Gutschein anlegen, `_bs_cm_state` und `_bs_vouchers_generated` löschen | — | ☐ |
| Cron-URL zweimal **gleichzeitig** in zwei Browser-Tabs aufrufen | — | ☐ |
| Coupon-Liste | **genau ein** Coupon für diese Bestellung | ☐ |
| Log | ggf. Hinweis auf übernommene oder gehaltene Sperre, aber kein zweiter Coupon | ☐ |

## A7 — HPOS lässt sich aktivieren

| Schritt | Erwartet | Ergebnis |
|---|---|---|
| WooCommerce → Einstellungen → Erweitert → Features | "Bootsschule Mail & Vouchers" gilt als kompatibel | ☐ |
| HPOS aktivieren, Sync abwarten | keine Fehlermeldung | ☐ |
| A1 komplett wiederholen | alle Punkte weiterhin grün | ☐ |
| Bestehende Gutscheine im Backend | Liste und Statistiken unverändert korrekt | ☐ |

> Falls andere Plugins (`bs-bootsschule-booking`, `bootsschule-kategorie-sorter`)
> als inkompatibel gemeldet werden: HPOS wieder ausschalten. Der Rest dieses
> Updates funktioniert unabhängig davon.

## A8 — Regressionen im Normalbetrieb

| Schritt | Erwartet | Ergebnis |
|---|---|---|
| Kurs-Produkt (kein Gutschein) kaufen | passende Bestätigungsmail mit Anhängen und Rechnungs-PDF | ☐ |
| Gutschein einlösen | Rabatt greift, Status wechselt auf "eingelöst" | ☐ |
| Gutschein im Backend stornieren | Coupon ist **nicht mehr einlösbar** (im Checkout gegenprüfen) | ☐ |
| Bestellung stornieren | zugehöriger Gutschein wird entwertet | ☐ |
| Gutscheinwert außerhalb Min/Max eingeben | Produkt landet **nicht** im Warenkorb, Fehlermeldung erscheint | ☐ |
| Neuer Gutschein: Ablaufdatum | 31.12. des dritten Folgejahres | ☐ |
| Einstellungen: ungültigen Trigger-Status speichern | wird abgelehnt | ☐ |

---

## Freigabe

| | Name | Datum |
|---|---|---|
| Getestet durch | | |
| Freigabe Live-Deployment | | |

**Rollback:** Plugin-Ordner vor dem Deployment sichern. Bei Problemen den alten
Ordner zurückspielen. Die neuen Order-Metas (`_bs_cm_*`) stören die alte Version
nicht. Achtung: Gutscheine, die unter v3 erzeugt wurden, behalten ihr
Ablaufdatum zum Jahresende — das ist gewollt und kein Rückschritt.
