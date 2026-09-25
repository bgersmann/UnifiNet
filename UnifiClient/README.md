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

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0
- Eine konfigurierte [UnifiGateway](../UnifiGateway)-Instanz

### 3. Software-Installation

* Über den Module Store das 'UnifiNet'-Modul installieren.
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

#### Profile

Keine vorhanden. Die Darstellung erfolgt über Variablen-Presentations.

### 6. Visualisierung

Keine besondere Funktionalität. Die Statusvariablen können direkt in der Visualisierung verwendet werden.

### 7. PHP-Befehlsreferenz

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
