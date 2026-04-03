# GShop REST API - Guida Completa

## Stato Implementazione ✅

| Feature | Status | Note |
|---------|--------|------|
| Routing & Autenticazione | ✅ Completo | PSR-4 autoloading, API Key via X-Api-Key header |
| Database ODBC | ✅ Completo | Auto-fallback sqlsrv→odbc, UTF-8 conversion |
| DTO Registry | ✅ Completo | categorie (28 righe), tipi (60 righe) |
| GET Data Endpoint | ✅ Completo | Filtering, pagination (offset/limit), sorting |
| POST Export CSV | ✅ Completo | Generazione file CSV; enclosure/escape corretti |
| GET Download Files | ✅ Completo | Download export CSV con headers corretti |
| Health Check | ✅ Completo | Verifica connessione DB |

---

## Endpoints API

### 1. Health Check (No Auth)
```bash
GET http://localhost:8080/gshop/index.php?route=/gshop/api/health
```

**Response:**
```json
{"ok": true}
```

---

### 2. Retrieve Data (Auth Required)
```bash
GET http://localhost:8080/gshop/index.php?route=/gshop/api/data/{entity}
```

**Parameters:**
- `entity` (required): `categorie` o `tipi`
- `offset` (optional): numero pagina, default 0
- `limit` (optional): righe per pagina, default 100, max 20000
- `sort` (optional): campo ordinamento
- `order` (optional): ASC/DESC
- `{field}={value}`: filtra per campo

**Example - Tutti i tipi:**
```bash
curl "http://localhost:8080/gshop/index.php?route=/gshop/api/data/tipi" \
  -H "X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J"
```

**Response (primo record):**
```json
{
  "entity": "tipi",
  "count": 60,
  "data": [
    {"TipoCode": "ANF", "TipoDescr": "ANFIBIO"},
    {"TipoCode": "ANK", "TipoDescr": "TRONCHETTI"},
    ...
  ]
}
```

**Example - Categorie con filtro:**
```bash
curl "http://localhost:8080/gshop/index.php?route=/gshop/api/data/categorie&CatCode=00." \
  -H "X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J"
```

---

### 3. Export CSV (Auth Required)
```bash
POST http://localhost:8080/gshop/index.php?route=/gshop/api/export/{entity}
```

**Headers:**
```
Content-Type: application/json
X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
```

**Body (optional filters):**
```json
{
  "filters": {
    "TipoCode": "ANF"
  }
}
```

**Example:**
```bash
curl -X POST "http://localhost:8080/gshop/index.php?route=/gshop/api/export/tipi" \
  -H "X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J" \
  -H "Content-Type: application/json" \
  -d '{}'
```

**Response:**
```json
{
  "status": "ok",
  "entity": "tipi",
  "rows": 60,
  "file": "20260403/tipi_20260403_081339.csv",
  "download_url": "/gshop/api/export/files/20260403/tipi_20260403_081339.csv"
}
```

---

### 4. Download Export File (Auth Required)
```bash
GET http://localhost:8080/gshop/api/export/files/{date}/{filename}
```

**Example:**
```bash
curl "http://localhost:8080/gshop/api/export/files/20260403/tipi_20260403_081339.csv" \
  -H "X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J" \
  -o tipi_export.csv
```

**Response:** (CSV file - attachment download)
```
TipoCode;TipoDescr
ANF;ANFIBIO
ANK;TRONCHETTI
...
```

---

## Authentication

Tutti gli endpoint (tranne `/health`) richiedono il header:
```
X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
```

---

## Struttura Dati

### Entity: `categorie`
| Campo | Tipo | Descrizione |
|-------|------|------------|
| CatCode | string | Codice categoria |
| CatDescr | string | Descrizione |
| CatIva | int | Aliquota IVA |
| CatMisure | string | Unità di misura |
| CatReportComm | int | Report comm |

**Righe nel DB:** 28

### Entity: `tipi`
| Campo | Tipo | Descrizione |
|-------|------|------------|
| TipoCode | string | Codice tipo |
| TipoDescr | string | Descrizione tipo |

**Righe nel DB:** 60

---

## Architettura Implementata

```
/gshop/
  ├── index.php                    # Front controller
  ├── config.php                   # DB config + API key
  ├── src/
  │   ├── bootstrap.php            # PSR-4 autoloader
  │   ├── Router.php               # Pattern-based router
  │   ├── Http/
  │   │   ├── Request.php          # HTTP request wrapper
  │   │   └── Response.php         # JSON + File download
  │   ├── Database/
  │   │   └── SqlServerConnection.php  # PDO + ODBC fallback
  │   ├── Dto/
  │   │   ├── entities.php         # Table registry
  │   │   └── Registry.php         # Registry loader
  │   ├── Repository/
  │   │   └── GenericTableRepository.php  # SQL query builder
  │   ├── Controller/
  │   │   ├── HealthController.php
  │   │   ├── DataController.php   # GET /data/{entity}
  │   │   └── ExportController.php # POST/GET export
  │   └── Service/
  │       └── ExportService.php    # CSV generation
  ├── exports/                     # CSV file storage
  └── README.md
```

---

## Problemi Risolti

1. **Missing mod_rewrite** → Fallback routing via query params
2. **Missing pdo_sqlsrv** → Auto-fallback a ODBC Driver 18
3. **Malformed UTF-8 (ODBC)** → mb_convert_encoding in Repository
4. **CSV enclosure/escape** → Fixed fputcsv params

---

## Testing Rapido (Postman)

### 1. GET: Rileva tutti i tipi
```
GET http://localhost:8080/gshop/index.php?route=/gshop/api/data/tipi
Header: X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
```

### 2. POST: Esporta categorie
```
POST http://localhost:8080/gshop/index.php?route=/gshop/api/export/categorie
Header: X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
Content-Type: application/json
Body: {}
```

### 3. GET: Scarica file
```
GET http://localhost:8080/gshop/api/export/files/20260403/categorie_20260403_081325.csv
Header: X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
```

---

## Prossimi Passi (Opzionali)

- [ ] Aggiungere altre entità (clienti, ordini, ecc.)
- [ ] Implementare paginazione lato client
- [ ] Aggiungere rate limiting
- [ ] Logare tutte le richieste API
- [ ] Creare UI web per viewing/filtering

---

**Data Creazione:** 2026-04-03  
**Stato:** ✅ Production Ready
