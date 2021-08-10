# SPD Mobilisierungsplaner Crawler
Erhalte die Rohdaten des SPD Mobiliseungsplaners für detaillierte Auswertungen zu den einzelnen Straßenzügen. Um die Daten zu erhalten muss dein SPD Account als "Campaigner" freigeschaltet sein. Eine Fehlerbehandlung ist wenig bis gar nicht vorhanden. Im Zweifel einfach einen Issue erstellen ;)

## Konfiguration
| Parameter | Type | Description
|---|---|---|
| `user`| `string` | spd.de Nutzername |
| `password`| `string` | spd.de Password |
| `type` | `string` | Indextyp. Mögliche Werte: spd-stronghold,conviction|
| `wk_key` | `string` | Wahlkreisschlüssel. Den Schlüssel findet ihr im Mobilisierungsplaner bei Wahlkreisauswahl. Dort steht die entsprechendde Zahl in runden Klammern neben dem Namen. Beispiel: Düren(090) |

## Installation
- Sicherstellen das PHP 7.4 installiert und lauffähig ist
- Download ZIP Ordner mit Code [Download](https://github.com/JUVOJustin/spd-mobi-crawler/archive/refs/heads/main.zip)
- Entpacken
- 'Composer update' ausführen zur Installation der Abhängigkeiten

## Benutzung
```php spd.php user@tld.com secretpassword type wk_key```

Die Ergebnisdatei liegt im root Verzeichnis des Projektes und hat folgendes Namesschema: "structuredData_{$type}_wk_{$wkKey}.csv"