# UnifiNet

Bindet die lokale UniFi Network Integration API in IP-Symcon ein: Geräte, Endgeräte,
Netzwerke und WLANs einer UniFi Site werden als eigene Instanzen mit Statusvariablen
abgebildet.

Folgende Module beinhaltet das UnifiNet Repository:

- __UnifiGateway__ ([Dokumentation](UnifiGateway))  
	Die Schnittstelle zwischen IP-Symcon und der lokalen UniFi Network API. Basis für alle weiteren Module.

- __UnifiConfigurator__ ([Dokumentation](UnifiConfigurator))  
	Der Konfigurator richtet UniFi Geräte und Endgeräte in IP-Symcon ein.

- __UnifiDevice__ ([Dokumentation](UnifiDevice))  
	Dieses Modul repräsentiert die UniFi Geräte in IP-Symcon.

- __UnifiClient__ ([Dokumentation](UnifiClient))  
	Dieses Modul repräsentiert die UniFi Endgeräte in IP-Symcon.

- __UnifiNetwork__ ([Dokumentation](UnifiNetwork))  
	Dieses Modul repräsentiert die UniFi Netzwerke in IP-Symcon.

- __UnifiWifi__ ([Dokumentation](UnifiWifi))  
	Dieses Modul repräsentiert die UniFi WLANs in IP-Symcon.

## Voraussetzungen

- IP-Symcon ab Version 8.0
- Eine UniFi Konsole mit aktivierter Network Integration API
- Ein API-Key, erzeugt unter 'UniFi Network > Settings > Control Plane > Integrations'

## Installation

* Über den Module Store das 'UnifiNet'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: `https://github.com/bgersmann/UnifiNet`

Anschließend eine UnifiGateway-Instanz anlegen, IP-Adresse und API-Key hinterlegen und
die gewünschte Site auswählen. Die übrigen Instanzen lassen sich danach am schnellsten
über den UnifiConfigurator anlegen.

## Lizenz

[MIT](LICENSE)
