# UnifiConfigurator

Listet alle UniFi Geräte und Endgeräte der ausgewählten Site auf und legt daraus
mit einem Klick die passenden UnifiDevice- bzw. UnifiClient-Instanzen an.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Visualisierung](#6-visualisierung)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Zeigt alle Geräte und Endgeräte der UniFi Site an
* Erlaubt das Anlegen der zugehörigen Instanzen direkt aus der Liste

### 2. Voraussetzungen

- IP-Symcon ab Version 8.0
- Eine konfigurierte [UnifiGateway](../UnifiGateway)-Instanz

### 3. Software-Installation

* Über den Module Store das 'UnifiNet'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/bgersmann/UnifiNet`

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'UnifiConfigurator'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Unifi Geräte | Zeigt alle UniFi Geräte und Endgeräte der Site an und legt die gewählten als Instanz an.

### 5. Statusvariablen und Profile

#### Statusvariablen

Keine vorhanden.

#### Profile

Keine vorhanden.

### 6. Visualisierung

Keine vorhanden.

### 7. PHP-Befehlsreferenz

```php
void UNIFICG_Send(integer $InstanzID, string $Api);
```
Liest die Geräteliste erneut aus dem Gateway. Wird von der Schaltfläche der
Konfigurationsseite verwendet. Möglicher Wert für `$Api`: `getDevicesConfig`.

Beispiel:
```php
UNIFICG_Send(12345, 'getDevicesConfig');
```
