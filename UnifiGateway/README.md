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
* Optional: zeigt die Version der UniFi Network Application an

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0
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

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
Anwendungsversion | String | Version der UniFi Network Application (nur bei aktivierter Option)

#### Profile

Keine vorhanden.

### 6. Visualisierung

Keine vorhanden. Die Instanz dient ausschließlich als Schnittstelle.

### 7. PHP-Befehlsreferenz

Keine vorhanden. Die Kommunikation läuft über die untergeordneten Instanzen.
