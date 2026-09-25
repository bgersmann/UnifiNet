# UnifiDevice

Repräsentiert ein einzelnes UniFi Gerät (Access Point, Switch, Gateway) in IP-Symcon und
hält dessen Betriebsdaten — Status, Firmware, Laufzeit, Uplink-Durchsatz, Port- und
Radio-Zustand — als Statusvariablen aktuell.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Visualisierung](#6-visualisierung)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Zeigt die Daten eines UniFi Geräts an (Name, Modell, IP, MAC, Firmware)
* Überwacht Online-Status, Laufzeit und Uplink-Durchsatz
* Optional: CPU- und Arbeitsspeicher-Auslastung
* Optional: eine Variable je Netzwerk-Port inklusive PoE-Zustand
* Optional: eine Variable je WLAN-Radio
* Optional: Anzahl der verbundenen Endgeräte
* Löst per PHP-Befehl einen PoE-Power-Cycle oder einen Geräte-Neustart aus

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0
- Eine konfigurierte [UnifiGateway](../UnifiGateway)-Instanz

### 3. Software-Installation

* Über den Module Store das 'UnifiNet'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/bgersmann/UnifiNet`

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'UnifiDevice'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Timer    | Timer des Abfrageintervalls in Sekunden. 0 = Deaktiviert.
Device ID | Bitte das Gerät aus der Liste wählen. Die Liste wird über 'Geräte abrufen' gefüllt.
Ports anzeigen | Erzeugt Variablen für alle verfügbaren Ports des Geräts.
Radios anzeigen | Erzeugt Variablen für die verfügbaren WLAN-Radios.
MAC-Adresse anzeigen | Erzeugt eine Variable mit der MAC-Adresse des Geräts.
ID anzeigen | Erzeugt eine Variable mit der UUID des Geräts.
Verbundene Endgeräte anzeigen | Erzeugt eine Variable mit der Anzahl verbundener Endgeräte.
Auslastung auslesen (CPU + Arbeitsspeicher) | Erzeugt Variablen für CPU- und Arbeitsspeicher-Auslastung.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
Geräte Name | String | Im Controller vergebener Name des Geräts
Geräte ID | String | UUID des Geräts (nur bei aktivierter Option)
Geräte Modell | String | Modellbezeichnung
Geräte IP | String | Aktuelle IP-Adresse
Geräte MAC | String | MAC-Adresse (nur bei aktivierter Option)
Firmware | String | Installierte Firmware-Version
Firmware-Update | Boolean | Aktuell / Update verfügbar
Laufzeit | Integer | Laufzeit seit dem letzten Neustart in Sekunden
Uplink TX | Float | Senderate des Uplinks in Mbit/s
Uplink RX | Float | Empfangsrate des Uplinks in Mbit/s
Online | Boolean | Online / Offline
Übergeordnetes Gerät | String | Name des Geräts, an dem der Uplink hängt
CPU Auslastung | Float | CPU-Auslastung in % (nur bei aktivierter Option)
Arbeitsspeicher Auslastung | Float | Speicherauslastung in % (nur bei aktivierter Option)
Verbundene Endgeräte | Integer | Anzahl verbundener Endgeräte (nur bei aktivierter Option)
Port *n* | String | Verbindungsgeschwindigkeit bzw. Zustand des Ports (nur bei aktivierter Option)
Port *n*-POE | String | PoE-Standard bzw. PoE-Zustand des Ports (nur bei aktivierter Option)
WLAN *x* GHz | String | WLAN-Standard und Kanalbreite des Radios (nur bei aktivierter Option)

Die Anzahl der Port- und WLAN-Variablen hängt vom jeweiligen Gerät ab.

#### Profile

Keine vorhanden. Die Darstellung erfolgt über Variablen-Presentations.

### 6. Visualisierung

Keine besondere Funktionalität. Die Statusvariablen können direkt in der Visualisierung verwendet werden.

### 7. PHP-Befehlsreferenz

```php
void UNIFIDV_PowerCycle(integer $InstanzID, integer $Port);
```
Führt auf dem angegebenen PoE-Port einen Power-Cycle aus.

Beispiel:
```php
UNIFIDV_PowerCycle(12345, 4);
```

```php
void UNIFIDV_RestartDevice(integer $InstanzID);
```
Startet das UniFi Gerät neu.

Beispiel:
```php
UNIFIDV_RestartDevice(12345);
```

```php
void UNIFIDV_Send(integer $InstanzID, string $Api, string $Param1);
```
Stößt eine Abfrage beim Gateway an. Wird von den Schaltflächen der Konfigurationsseite
verwendet und eignet sich für eine Aktualisierung außerhalb des Timers.
Mögliche Werte für `$Api`: `getDevices`, `getDeviceData`, `getDeviceStats`, `getConnectedClients`.

Beispiel:
```php
UNIFIDV_Send(12345, 'getDeviceData', '');
```
