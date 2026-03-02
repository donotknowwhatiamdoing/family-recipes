# Backend (PHP + MariaDB)

## Voraussetzungen

- PHP 8.1+
- MariaDB Datenbank (all-inkl)
- `pdo_mysql` und `curl` aktiv

## Einrichtung

1. `.env.example` zu `.env` kopieren und Werte eintragen.
2. SQL aus `sql/001_init.sql` in MariaDB ausführen.
3. `public/index.php` als Einstiegspunkt im Webserver nutzen.
4. Bei Apache/all-inkl sorgt `public/.htaccess` für Weiterleitung auf die API.

### KI-Konfiguration (OpenRouter)

Empfohlen in `backend/.env`:

```env
AI_PROVIDER=openrouter
AI_MODEL=openai/gpt-4o-mini
OPENROUTER_API_KEY=...
```

Optional:

```env
OPENROUTER_HTTP_REFERER=https://deine-domain.tld
OPENROUTER_APP_TITLE=Familien-Rezepte
```

Wichtig: API-Keys nur im Backend speichern, nicht im Frontend.

## API-Endpoints (Start)

- `GET /api/health`
- `POST /api/chat`
- `GET /api/insights?hour=8&locale=de-DE`
- Alternativ bei `/api` als eigener Host/Ordner: `GET /health`, `POST /chat`

Beispiel für `/api/chat`:

```json
{
  "message": "Ich habe Kartoffeln und Eier. Was kann ich kochen?"
}
```

## Deployment auf all-inkl (Basis)

- Frontend Build in Webspace hochladen (z.B. `/www/`).
- API unter `/api` bereitstellen (DocumentRoot auf `backend/public` oder per Rewrite).
- `.env` auf dem Server pflegen, insbesondere:
- `DB_*` für MariaDB
- `OPENROUTER_API_KEY` (oder alternativ `OPENAI_API_KEY`) für Chatbot
