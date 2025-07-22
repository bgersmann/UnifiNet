# UnifiDevice
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

* Zeigt die Daten eines Unifi Geräts an

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0

### 3. Software-Installation

* Über den Module Store das 'UnifiNet'-Modul installieren.

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'UnifiDevice'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Timer    | Timer des Abfrageintervalls. 0 = Deaktiviert.
Device-ID | Bitte das Gerät aus der Liste wählen. 
Ports anzeigen | Erzeugt Variablen für alle verfügbaren Ports des Geräts.
Radios anzeigen | Erzeugt Variablen für die verfügbaren Wlan-Radios.
Mac-Adresse anzeigen | Erzeugt eine Variable mit der MAC Adresse des Endgerätes.
ID anzeigen | Erzeugt eine Variable mit der uuid des Clients.
Auslastung auslesen | Erzeugt Variablen für die CPU Auslastung und den Speicher Verbrauch.
### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
Stellt die Verfügbaren abfrage punkte der API dar. 
Anzahl der Variablen kann je nach Gerät variieren.

#### Profile

Keine vorhanden

### 6. Visualisierung

Keine vorhanden

### 7. PHP-Befehlsreferenz

UNIFIDV_PowerCycle($ID,$INT); -> Führt ein Power Cycle auf dem Ausgewählten POE Port aus
UNIFIDV_RestartDevice($ID); -> Führt ein Restart vom Unifi Gerät aus