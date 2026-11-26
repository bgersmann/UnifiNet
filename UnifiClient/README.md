# UnifiClient

Repräsentiert ein einzelnes am UniFi Netzwerk angemeldetes Endgerät in IP-Symcon und
hält dessen Verbindungsdaten — Online-Status, IP, Verbindungszeitpunkt und zugehöriges
UniFi Gerät — als Statusvariablen aktuell.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Visualisierung](#6-visualisierung)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Zeigt die Daten eines UniFi Endgeräts an (Name, Typ, IP, MAC)
* Überwacht den Online-Status und lässt sich damit zur Anwesenheitserkennung nutzen
* Zeigt, seit wann das Endgerät verbunden ist und an welchem UniFi Gerät es hängt
* Optional (inoffizielle API): aktuelle Aktivität (Download/Upload) und Blockieren/Freigeben des Endgeräts

### 2. Voraussetzungen

- IP-Symcon ab Version 8.1
- Eine konfigurierte [UnifiNetworkGateway](../UnifiNetworkGateway)-Instanz

### 3. Software-Installation

* Über den Module Store das Modul 'Unifi Network' installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/bgersmann/UnifiNet`

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'UnifiClient'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Timer    | Timer des Abfrageintervalls in Sekunden. 0 = Deaktiviert.
Client ID | Bitte das Endgerät aus der Liste wählen. Die Liste wird über 'Endgeräte abrufen' gefüllt.
MAC-Adresse anzeigen | Erzeugt eine Variable mit der MAC-Adresse des Endgeräts.
ID anzeigen | Erzeugt eine Variable mit der UUID des Endgeräts.
Inoffizielle API > Aktivität auslesen | Erzeugt Variablen mit dem aktuellen Download und Upload des Endgeräts in Mbit/s.
Inoffizielle API > Schaltbare Variable zum Blockieren | Erzeugt die schaltbare Variable 'Blockiert'.

Die Optionen der inoffiziellen API setzen voraus, dass sie im Gateway aktiviert ist. Ein blockiertes Endgerät ist offline und verschwindet aus der offiziellen API; die Instanz merkt sich deshalb seine MAC-Adresse, damit es sich wieder freigeben lässt. Die MAC wird bei der ersten erfolgreichen Abfrage gespeichert.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
Endgeräte Name | String | Im Controller vergebener Name des Endgeräts
Endgeräte ID | String | UUID des Endgeräts (nur bei aktivierter Option)
Endgeräte Typ | String | Verbindungsart, z. B. WIRED oder WIRELESS
Endgeräte IP | String | Aktuelle IP-Adresse
Endgeräte MAC | String | MAC-Adresse (nur bei aktivierter Option)
Verbunden Seit | Integer | Zeitpunkt der Verbindung als Unix-Zeitstempel
Online | Boolean | Online / Offline
Übergeordnetes Gerät | String | Name des UniFi Geräts, mit dem das Endgerät verbunden ist
Aktivität Download | Float | Aktuelle Datenrate zum Endgerät in Mbit/s (inoffizielle API, nur bei aktivierter Option)
Aktivität Upload | Float | Aktuelle Datenrate vom Endgerät in Mbit/s (inoffizielle API, nur bei aktivierter Option)
Blockiert | Boolean | Endgerät blockiert/freigegeben, schaltbar (inoffizielle API, nur bei aktivierter Option)

#### Profile

Keine vorhanden. Die Darstellung erfolgt über Variablen-Presentations.

### 6. Visualisierung

Die Instanz bringt eine eigene Statuskachel mit: Online-Zustand mit Dauer, Verbindungsart, übergeordnetes Gerät (ab IP-Symcon 8.2 antippbar), IP und MAC. Mit den Optionen der inoffiziellen API zeigt sie zusätzlich den aktuellen Download und Upload und einen Schalter zum Blockieren. Blockieren braucht ein zweites Antippen. Die Statusvariablen können weiterhin direkt verwendet werden.

### 7. PHP-Befehlsreferenz

```php
boolean UNIFICL_SetBlocked(integer $InstanzID, boolean $Blockiert);
```
Blockiert das Endgerät im Netzwerk oder gibt es wieder frei (inoffizielle API).

Beispiel:
```php
UNIFICL_SetBlocked(12345, true);
```

```php
void UNIFICL_Send(integer $InstanzID, string $Api, string $Param1);
```
Stößt eine Abfrage beim Gateway an. Wird von den Schaltflächen der Konfigurationsseite
verwendet und eignet sich für eine Aktualisierung außerhalb des Timers.
Mögliche Werte für `$Api`: `getClients`, `getClientData`.

Beispiel:
```php
UNIFICL_Send(12345, 'getClientData', '');
```
