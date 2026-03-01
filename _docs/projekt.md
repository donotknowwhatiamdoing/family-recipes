# Projekt: Minimale Rezeptverwaltung mit KI-unterstützter Weboberfläche

## Ziel

Ein erster funktionaler Prototyp, der Rezepte strukturiert speichert, in einer ansprechenden Kacheloptik durchsuchbar darstellt und einfache Interaktionen wie Bewertungen, Bildgenerierung und Feedback erlaubt.

## Hauptmerkmale

### Rezepte in strukturierter Form

- Titel
- Kategorien und Tags
- Zutatenliste
- Zubereitung in Schritten (mit optionalen Bildern)
- Nährwerttabelle

### Benutzerinterface

- Webseite im Kachel-Design
- Filter- und Suchfunktion nach Kategorien, Tags und Bewertung
- Möglichkeit, Rezepte zu bewerten und eigene zu speichern

### Interaktive Funktionen

- Bewertungs- und Speicher-Logik (z.B. Feedback nach dem Kochen)
- Optionale Bildgenerierung bei fehlendem Foto (z.B. mit API für KI-Bilder)
- Dialogfluss, um Rezepte zu besuchen, zu bewerten und zu verwalten

## Technischer Rahmen

- Datenhaltung: JSON-Datei für die erste Version (lokale Speicherung)
- Frontend: Einfaches HTML, CSS, JavaScript
- Backend: Optional, z.B. Node.js, für Verwaltung und API-Integration; später erweiterbar
- Zugriff: Repository-basiert, offen für Weiterentwicklung

## Langfristiges Ziel

Ein flexibles, erweiterbares System, das bei Bedarf mit einer echten Datenbank (z.B. SQLite, MariaDB) verbunden werden kann, um mehr Nutzer, Rezepte und Features zu unterstützen.
