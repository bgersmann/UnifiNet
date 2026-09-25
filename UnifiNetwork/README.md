# UnifiNetwork

Repräsentiert ein einzelnes im UniFi Controller konfiguriertes Netzwerk (VLAN) in
IP-Symcon und hält dessen Konfiguration als Statusvariablen aktuell.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Visualisierung](#6-visualisierung)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Zeigt Name, Typ, VLAN-ID und Aktivierungszustand eines UniFi Netzwerks an
* Zeigt die vertrauenswürdigen DHCP-Server des Netzwerks an
* Bei Netzwerken vom Typ 'Gateway' zusätzlich: Host-IP-Adresse, DHCP-Modus und
  automatische Netzwerk-Skalierung

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0
- Eine konfigurierte [UnifiGateway](../UnifiGateway)-Instanz
- UniFi Network Application ab Version 10

### 3. Software-Installation

* Über den Module Store das 'UnifiNet'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/bgersmann/UnifiNet`

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'UnifiNetwork'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Timer    | Timer des Abfrageintervalls in Sekunden. 0 = Deaktiviert.
Network ID | Bitte das Netzwerk aus der Liste wählen. Die Liste wird über 'Netzwerke abrufen' gefüllt.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
Netzwerkname | String | Im Controller vergebener Name des Netzwerks
Typ | String | Unmanaged, Gateway oder Switch
Aktiv | Boolean | Netzwerk aktiviert / deaktiviert
VLAN | Integer | VLAN-ID des Netzwerks
Vertrauenswürdige DHCP-Server | String | Im DHCP-Guarding hinterlegte DHCP-Server
Host-IP-Adresse | String | IP-Adresse des Gateways in diesem Netzwerk (nur bei Typ Gateway)
Netzwerk automatisch skalieren | Boolean | Automatische Skalierung des Adressbereichs (nur bei Typ Gateway)
Modus | String | DHCP Server oder DHCP Relay (nur bei Typ Gateway)

#### Profile

Keine vorhanden. Die Darstellung erfolgt über Variablen-Presentations.

### 6. Visualisierung

Keine besondere Funktionalität. Die Statusvariablen können direkt in der Visualisierung verwendet werden.

### 7. PHP-Befehlsreferenz

```php
void UNIFINW_Send(integer $InstanzID, string $Api, string $Param1);
```
Stößt eine Abfrage beim Gateway an. Wird von den Schaltflächen der Konfigurationsseite
verwendet und eignet sich für eine Aktualisierung außerhalb des Timers.
Mögliche Werte für `$Api`: `getNetworks`, `getNetworkDetails`.

Beispiel:
```php
UNIFINW_Send(12345, 'getNetworkDetails', '');
```
