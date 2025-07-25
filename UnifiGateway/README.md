# UnifiGateway
Beschreibung des Moduls.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Die Schnittstelle zwischen Symcon und der Local Unifi Network API

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0

### 3. Software-Installation

* Über den Module Store das 'UnifiNet'-Modul installieren.

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'UnifiGateway'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Unifi Device IP      | IP Adresse des Gateways mit dem UNA Controller.
APIKey               | API Key bitte unter "UniFi Network > Settings > Control Plane > Integrations" erzeugen.
Site                 | Nach übernehmen der IP + ApiKey kann die gewünschte Site ausgewählt werden.
Show Application Version | Erzeugt eine Variable mit der aktuellen UNA Version.
### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Keine vorhanden

#### Profile

Keine vorhanden

### 6. Visualisierung

Keine vorhanden

### 7. PHP-Befehlsreferenz

Keine vorhanden