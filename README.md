# Familien-Rezeptesammlung

Mobile-first Webanwendung für Familienrezepte mit Chatbot, Teilen und Druckansicht.

## Zielbild

- Rezepte gemeinsam erfassen und verwalten
- Interne Freigabe zwischen Parteien
- Externe Freigabe per Link
- Chatbot für Suche, Erklärung und Kochhilfe

## Tech-Stack

- Hosting: all-inkl
- Datenbank: MariaDB
- Backend: PHP 8+ REST API
- Frontend: React + Vite

## Projektstruktur

```text
backend/
  public/
  src/
  sql/
frontend/
  src/
_docs/   (lokal/temporär, via .gitignore ausgeschlossen)
```

## Lokaler Start

### Alles mit einem Befehl (empfohlen)

```bash
npm install
npm run dev:all
```

Damit starten automatisch:
- Docker MariaDB
- PHP-Backend auf `http://127.0.0.1:8000`
- Frontend auf `http://127.0.0.1:5173`

### Backend

```bash
cd backend
cp .env.example .env
# DB_* und OPENROUTER_API_KEY setzen
```

Anschließend `backend/sql/001_init.sql` in der MariaDB ausführen.

### Lokale MariaDB per Docker (empfohlen)

```bash
docker compose up -d
```

Enthalten:
- MariaDB auf `127.0.0.1:3307`
- Adminer auf `http://127.0.0.1:8080`

Schema importieren:

```powershell
Get-Content -Raw backend\sql\001_init.sql | docker exec -i family-recipes-mariadb mariadb -ufamily_app -pfamily_app_local family_recipes
```

### Frontend

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

## API (Start)

- `GET /api/health`
- `POST /api/chat`
- `GET /api/insights`

## KI-Hinweis

- API-Keys nur im Backend (`backend/.env`) speichern.
- `frontend/.env` ist nur für `VITE_*` Variablen gedacht.

## Deployment auf all-inkl

- Frontend-Build (`frontend/dist`) in den Webspace hochladen
- Backend unter `/api` bereitstellen (DocumentRoot: `backend/public`)
- `.env` auf dem Server pflegen (`DB_*`, `OPENROUTER_API_KEY`)

## Lizenz

Privates Familienprojekt.
