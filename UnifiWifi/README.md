# UnifiWifi

Repräsentiert ein einzelnes im UniFi Controller konfiguriertes WLAN in IP-Symcon,
zeigt dessen Konfiguration an und erlaubt das Ein- und Ausschalten des WLANs.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Visualisierung](#6-visualisierung)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Zeigt Name, Typ, Sicherheitsverfahren und Frequenzbänder eines UniFi WLANs an
* Schaltet das WLAN über die Statusvariable 'Aktiv' ein und aus

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0
- Eine konfigurierte [UnifiGateway](../UnifiGateway)-Instanz
- UniFi Network Application ab Version 10

### 3. Software-Installation

* Über den Module Store das 'UnifiNet'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/bgersmann/UnifiNet`

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'UnifiWifi'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Timer    | Timer des Abfrageintervalls in Sekunden. 0 = Deaktiviert.
Wifi ID  | Bitte das WLAN aus der Liste wählen. Die Liste wird über 'WLANs abrufen' gefüllt.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
Name | String | SSID des WLANs
Typ | String | Standard oder IoT-Optimized
Aktiv | Boolean | WLAN aktiviert / deaktiviert — schaltbar
Sicherheit | String | Verwendetes Sicherheitsverfahren, z. B. WPA2 + WPA3 Personal
Frequenzen | String | Gesendete Frequenzbänder, z. B. 2,4 + 5 GHz

#### Profile

Keine vorhanden. Die Darstellung erfolgt über Variablen-Presentations.

### 6. Visualisierung

Die Variable 'Aktiv' ist schaltbar und kann in der Visualisierung als Schalter zum
Ein- und Ausschalten des WLANs verwendet werden.

### 7. PHP-Befehlsreferenz

```php
void UNIFIWF_Send(integer $InstanzID, string $Api, string $Param1);
```
Stößt eine Abfrage beim Gateway an. Wird von den Schaltflächen der Konfigurationsseite
verwendet und eignet sich für eine Aktualisierung außerhalb des Timers.
Mögliche Werte für `$Api`: `getWifis`, `getWifiDetails`.

Beispiel:
```php
UNIFIWF_Send(12345, 'getWifiDetails', '');
```
