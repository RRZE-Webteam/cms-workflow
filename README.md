# CMS-Workflow

Plugin zur Einrichtung eines Redaktioneller Workflows und eines mehrsprachigen Angebots über mehrere WOrdPress-Instanzen

## Download 

GitHub-Repo: https://github.com/RRZE-Webteam/cms-workflow


## Autor 
RRZE-Webteam , http://www.rrze.fau.de

## Copryright

GNU General Public License (GPL) Version 3


## Zweck
 
Das Workflow-Plugin ermöglicht eine redaktionelle Kontrolle von WordPress-Seiten
und -Beiträgen. Sie können damit das vorhandene Rollensystem von WordPress erweitern 
oder einschränken und automatische Benachrichtigungen bei Seitenänderungen versenden. 
Auch die Verknüpfung mehrsprachiger Webauftritte ist mithilfe des Workflow-Plug-ins möglich.

## Entwicklung der Einstellungsseiten

Die Modulübersicht wird in `src/module-overview.jsx`, die Autoren-Einstellungen
in `src/authors-settings.jsx` gepflegt. `@wordpress/scripts`
übersetzt JSX und erstellt JavaScript, CSS und die PHP-Datei mit den Abhängigkeiten
unter `build/`. React und die WordPress-Komponenten werden von WordPress geladen.

```sh
npm ci
npm run build
```

Für die Entwicklung mit automatischem Neubauen: `npm start`.
Die Dateien unter `build/` werden mit dem Plugin ausgeliefert; auf dem Server ist
kein Node.js erforderlich. Nach Änderungen an JSX oder CSS muss neu gebaut werden.

## Dokumentation

Eine vollständige Dokumentation mit vielen Anwendungsbeispielen findet sich auf der Seite: 
https://www.wordpress.rrze.fau.de/plugins/fau-und-rrze-plugins/cms-workflow/
