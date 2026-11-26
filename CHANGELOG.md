# Changelog

## Version 2.3 (2026-11-26)

### Inoffizielle API: Ports, PoE und Clients

Die offizielle Integration API kann keine Ports schalten, keinen PoE-Modus setzen und keine Clients blockieren. Das Gateway kann dafür zusätzlich die klassische (inoffizielle) API der Network Application nutzen. Die Anmeldung erfolgt mit einem lokalen UniFi-Benutzer, Anfragen und Felder folgen [aiounifi](https://github.com/Kane610/aiounifi) (Home Assistant).

**Gateway:** Neuer Bereich „Inoffizielle API (optional)“ mit Schalter, lokalem Benutzer und Passwort. Die Schaltfläche **„Anmeldung testen“** meldet sich neu an und zeigt das Ergebnis. Nach einer Ablehnung wird automatisch neu angemeldet. Passwort, Cookie und CSRF-Token erscheinen nicht in der Debug-Ausgabe.

**Geräte** (Bereich „Inoffizielle API“, einzeln aktivierbar):

- **Port aktivieren/deaktivieren:** eine schaltbare Variable „Port *n* aktiv“ je Port. Der Uplink-Port erhält keine, damit sich das Gerät nicht selbst vom Netz trennt. Ports mit MAC-Filter (Port Security) werden nicht verändert.
- **VLANs bleiben erhalten:** Beim Deaktivieren setzt die Konsole Netzwerk und VLANs des Ports zurück. Das Gateway sichert diese Einstellungen vorher und stellt sie beim Aktivieren wieder her.
- **PoE-Modus:** eine Auswahl „Port *n* PoE“ je PoE-Port mit Auto / Aus und, sofern der Port es unterstützt, 24V passiv / Durchleitung.
- Neue Befehle `UNIFIDV_SetPortEnabled` und `UNIFIDV_SetPoeMode`.

**Clients** (Bereich „Inoffizielle API“, einzeln aktivierbar):

- **Aktivität:** aktueller Download und Upload in Mbit/s.
- **Blockieren/Freigeben:** eine schaltbare Variable „Blockiert“ und der Befehl `UNIFICL_SetBlocked`. Die Instanz merkt sich die MAC-Adresse, weil ein blockierter Client aus der offiziellen API verschwindet. So lässt er sich auch wieder freigeben.

### Umbenennung zur Abgrenzung vom Protect-Modul

- **Module umbenannt:** `UnifiGateway` heißt jetzt `UnifiNetworkGateway`, `UnifiConfigurator` heißt `UnifiNetworkConfigurator`. Die alten Namen bleiben als Suchbegriff (Alias) erhalten.
- **Bestehende Instanzen und Skripte laufen weiter:** GUIDs und Befehls-Präfixe (`UNIFIGW_`, `UNIFICG_`) sind unverändert.

### Kacheln

Die neuen Werte erscheinen auch in den Statuskacheln, sofern die Option in der Instanz aktiv ist.

- **Client:** aktueller Download und Upload sowie ein Schalter zum Blockieren. Blockieren braucht ein zweites Antippen, weil es die Verbindung des Anzeigegeräts selbst trennen kann. Ein blockierter Client wird rot als „Blockiert“ angezeigt.
- **Gerät:** Deaktivierte Ports erscheinen rot schraffiert mit eigenem Legendeneintrag. Die Port-Details zeigen zusätzlich den PoE-Modus. Ports werden in der Kachel bewusst nicht geschaltet, dafür gibt es die Variablen.

## Version 2.2 (2026-11-20)

### Statuskacheln für die Kachel-Visualisierung

Alle Instanzen liefern eine eigene HTML-Kachel (HTML-SDK), angelehnt an das Symcon-Design und die Symcon-Farben:

- **Gateway:** Übersicht der Site mit Geräten, Clients und WAN.
- **Geräte:** je nach Typ (Gateway, Switch, Access Point) belegte Ports mit PoE, verbundene Clients, Funkbänder, CPU/RAM, Uplink, Laufzeit und Firmware-Update. Antippen eines Ports zeigt Details, erneutes Antippen führt zurück.
- **Clients:** Online-Zustand mit Dauer, Verbindungsart, übergeordnetes Gerät (ab IP-Symcon 8.2 antippbar), IP und MAC.
- **Netzwerke und WLANs:** Kenndaten und Zustand. Das WLAN lässt sich direkt in der Kachel ein- und ausschalten.

Neue Einstellung „Kachel-Aktualisierung (s)“ im Gateway (Standard 60 s). Die WLAN-Passphrase erscheint nicht mehr in der Debug-Ausgabe.

### Weitere Änderungen

- **Bibliothek umbenannt** in „Unifi Network“. GUIDs, Präfixe und die Repository-URL bleiben gleich, bestehende Instanzen und Skripte laufen unverändert weiter.
- **Optionaler Port im Gateway**, zum Beispiel für den UniFi OS Server in Docker (meist 11443). 0 = Standard 443.
- **Kompatibilität vor IP-Symcon 9.0:** Die Konstante `INSTANCE_VISUALIZATION_TYPE_HTML` wird abgefangen.
- Abgleich mit den Vorgaben des Module Store. Ein auskommentierter Rest mit `IPS_LogMessage` wurde entfernt.
