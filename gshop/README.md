# GShop REST API

REST API per esportazione dati da SQL Server con supporto CSV.

## Status: ✅ Production Ready

- ✅ Tutti gli endpoint funzionanti
- ✅ Autenticazione API Key
- ✅ Support UTF-8 (ODBC conversion)
- ✅ Export CSV con filters
- ✅ Health check endpoint

## Quick Start

### 1. GET Data
```bash
curl "http://localhost:8080/gshop/index.php?route=/gshop/api/data/tipi" \
  -H "X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J"
```

### 2. Export to CSV
```bash
curl -X POST "http://localhost:8080/gshop/index.php?route=/gshop/api/export/categorie" \
  -H "X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J" \
  -H "Content-Type: application/json" \
  -d '{}'
```

### 3. Download File
```bash
curl "http://localhost:8080/gshop/api/export/files/20260403/categorie_20260403_081325.csv" \
  -H "X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J" \
  -o categorie.csv
```

## Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/health` | No | Health check |
| GET | `/data/{entity}` | Yes | Retrieve data with filters |
| POST | `/export/{entity}` | Yes | Export to CSV |
| GET | `/export/files/{date}/{filename}` | Yes | Download exported file |

## Entities

- **categorie** - 28 rows (product categories)
- **tipi** - 60 rows (product types)

## Documentation

Vedi [API_GUIDE.md](API_GUIDE.md) per la guida completa.

## Configuration

- **ApiKey:** `_9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J`
- **Database:** SQL Server ANGOLO (ODBC Driver 18)
- **Port:** 8080
- **Exports Dir:** `/gshop/exports/`

## Architecture

- PHP 8.5.1 with Apache 2.4.66
- PDO + ODBC with automatic fallback
- Generic repository pattern for all tables
- PSR-4 namespace autoloading
- Clean UTF-8 data conversion from ODBC

Created: 2026-04-03

- `GET /gshop/api/data/{entity}?Campo1=Valore&limit=100&offset=0&sort=Campo&order=ASC`
- `POST /gshop/api/export/{entity}`
  - Header: `X-Api-Key: ...`
  - Body JSON:

```json
{
  "filters": {
    "Codice": "C0001"
  },
  "sort": "IdCliente",
  "order": "ASC",
  "limit": 5000
}
```

- `GET /gshop/api/export/files/{yyyymmdd}/{filename}`

### Esempi rapidi

**categorie:**
- `GET /gshop/api/data/categorie?CatCode=ABC&limit=50&sort=CatCode&order=ASC`
- `POST /gshop/api/export/categorie`

**tipi:**
- `GET /gshop/api/data/tipi?TipoCode=ABC&limit=50&sort=TipoCode&order=ASC`
- `POST /gshop/api/export/tipi`

## Sicurezza applicata ora

- SQL injection prevenuta con:
  - whitelist campi dalle entita registrate
  - validazione identificatori SQL
  - bind dei parametri
- Accesso endpoint protetto da `X-Api-Key` (tranne health)
