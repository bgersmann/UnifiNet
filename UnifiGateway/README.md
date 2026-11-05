# UnifiGateway

Die Schnittstelle zwischen IP-Symcon und der lokalen UniFi Network Integration API.
Alle übrigen Module dieses Repositories laufen als Geräte-Instanzen unter dieser
Instanz und beziehen ihre Daten darüber.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Visualisierung](#6-visualisierung)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Stellt die Verbindung zur lokalen UniFi Network API her (API-Key-Authentifizierung)
* Wählt die zu verwendende Site aus
* Verteilt die Daten an die untergeordneten UnifiDevice-, UnifiClient-, UnifiNetwork- und UnifiWifi-Instanzen
* Optional: zeigt die Version der UniFi Network Application an und aktualisiert sie per Timer
* Optional: legt die Hierarchie der UniFi Geräte, auf Wunsch mit Endgeräten, als JSON in einer Stringvariable ab, zum Beispiel für die Darstellung mit EChartsTree

### 2. Voraussetzungen

- IP-Symcon ab Version 8.1
- Eine UniFi Konsole mit aktivierter Network Integration API
- Ein API-Key, erzeugt unter 'UniFi Network > Settings > Control Plane > Integrations'

### 3. Software-Installation

* Über den Module Store das 'UnifiNet'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/bgersmann/UnifiNet`

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'UnifiGateway'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Unifi Device IP | IP-Adresse oder Hostname der Konsole mit der UniFi Network Application.
API Key | API-Key, erzeugt unter 'UniFi Network > Settings > Control Plane > Integrations'.
Site | Nach dem Übernehmen von IP und API-Key kann die gewünschte Site ausgewählt werden.
Anwendungsversion anzeigen | Erzeugt eine Variable mit der Version der UniFi Network Application.
Aktualisierungsintervall Version | Intervall in Sekunden, in dem die Version abgefragt wird (Standard: 3600, 0 = aus). Nur aktiv, wenn die Anwendungsversion angezeigt wird.
Netzwerkhierarchie als JSON anlegen | Erzeugt eine Stringvariable mit der Hierarchie der UniFi Geräte.
Aktualisierungsintervall Hierarchie | Intervall in Sekunden, in dem die Hierarchie aktualisiert wird (Standard: 3600, 0 = aus). Jede Aktualisierung fragt jedes Gerät einzeln ab.
Endgeräte in die Hierarchie aufnehmen | Ergänzt die aktuell verbundenen Endgeräte unter ihrem Uplink-Gerät (Standard: aus).

Beim Übernehmen der Konfiguration werden die aktivierten Werte sofort aktualisiert. Im Aktionsbereich lassen sie sich zusätzlich über die Schaltflächen 'Version aktualisieren' und 'Hierarchie aktualisieren' abrufen.

__Netzwerkhierarchie in EChartsTree darstellen__:

In der [EChartsTree](https://github.com/Burki24/SymconECharts/blob/dev/EChartsTree/README.md#eigene-hierarchie-als-json)-Instanz als Datenquelle 'JSON-Stringvariable' wählen und die Variable 'Netzwerkhierarchie' dieser Instanz auswählen.

Die Wurzel des Baums ist die Site. Darunter steht jedes Gerät unter seinem übergeordneten Gerät (Uplink), als Werttext werden Modell und IP-Adresse angezeigt, bei nicht verbundenen Geräten zusätzlich der Zustand. Geräte ohne Uplink, mit unbekanntem Uplink oder mit einem Ringbezug hängen direkt an der Wurzel. Schlägt eine Abfrage fehl, bleibt der letzte vollständige Stand erhalten.

Mit aktivierter Option stehen die Endgeräte unter ihrem Uplink-Gerät, als Werttext werden Verbindungsart und IP-Adresse angezeigt. Sie erscheinen als Rolle `variable` und heben sich damit farblich von den Geräten ab. Die API liefert nur verbundene Endgeräte, getrennte verschwinden daher mit der nächsten Aktualisierung aus dem Baum. Der Baum wird dadurch tiefer und größer, die Ansichtsgrenzen der EChartsTree-Instanz müssen dazu passen.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
Anwendungsversion | String | Version der UniFi Network Application (nur bei aktivierter Option)
Netzwerkhierarchie | String | Hierarchie der UniFi Geräte als JSON im Format `de.burki24.symconecharts.tree` 1.0 (nur bei aktivierter Option)

#### Profile

Keine vorhanden.

### 6. Visualisierung

Keine vorhanden. Die Instanz dient ausschließlich als Schnittstelle.

### 7. PHP-Befehlsreferenz

Die Kommunikation läuft über die untergeordneten Instanzen, die ihre Anfragen intern an
das Gateway weiterleiten. Für die optionalen Variablen der Instanz gibt es diese Befehle:

`UNIFIGW_UpdateApplicationVersion(integer $InstanzID);`  
Aktualisiert die Variable 'Anwendungsversion', sofern die Option aktiviert ist.

`UNIFIGW_UpdateNetworkHierarchy(integer $InstanzID);`  
Aktualisiert die Variable 'Netzwerkhierarchie', sofern die Option aktiviert ist.

Darüber hinaus sind die API-Methoden des Gateways als `UNIFIGW_`-Befehle erreichbar
(z. B. `UNIFIGW_getSites`, `UNIFIGW_getDevices`, `UNIFIGW_getNetworkVersion`). Sie sind für
die interne Nutzung durch die Kind-Instanzen gedacht, liefern ihre Ergebnisse in einem
nicht zugesicherten Format und können sich in künftigen Versionen ohne Vorankündigung
ändern. Für eigene Skripte bitte die Statusvariablen der Geräte-, Endgeräte-, Netzwerk-
und WLAN-Instanzen verwenden.
