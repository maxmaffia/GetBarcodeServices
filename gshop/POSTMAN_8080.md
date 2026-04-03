Chiave sicura generata e salvata in gshop/config.php.

Valore impostato:
_9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

Esempi Postman pronti

1. Verifica servizio (senza API key)
- Metodo: GET
- URL: http://localhost:8080/gshop/api/health
- Fallback (se ricevi 404): http://localhost:8080/gshop/index.php?route=/gshop/api/health

2. Lettura dati categorie
- Metodo: GET
- URL: http://localhost:8080/gshop/api/data/categorie?CatCode=ABC&limit=50&sort=CatCode&order=ASC
- Fallback (se ricevi 404): http://localhost:8080/gshop/index.php?route=/gshop/api/data/categorie&CatCode=ABC&limit=50&sort=CatCode&order=ASC
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

3. Export CSV categorie
- Metodo: POST
- URL: http://localhost:8080/gshop/api/export/categorie
- Fallback (se ricevi 404): http://localhost:8080/gshop/index.php?route=/gshop/api/export/categorie
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {
    "CatCode": "ABC"
  },
  "sort": "CatCode",
  "order": "ASC",
  "limit": 5000
}

4. Download file export (dopo la POST)
- Metodo: GET
- URL: http://localhost:8080/gshop/api/export/files/yyyymmdd/nomefile.csv
- Fallback (se ricevi 404): http://localhost:8080/gshop/index.php?route=/gshop/api/export/files/yyyymmdd/nomefile.csv
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

5. Lettura dati tipi
- Metodo: GET
- URL: http://localhost:8080/gshop/api/data/tipi?TipoCode=&limit=50&sort=TipoCode&order=ASC
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

6. Export CSV tipi
- Metodo: POST
- URL: http://localhost:8080/gshop/api/export/tipi
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {},
  "sort": "TipoCode",
  "order": "ASC",
  "limit": 5000
}

7. Lettura dati reparti
- Metodo: GET
- URL: http://localhost:8080/gshop/api/data/reparti?RepCode=&limit=50&sort=RepCode&order=ASC
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

8. Export CSV reparti
- Metodo: POST
- URL: http://localhost:8080/gshop/api/export/reparti
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {},
  "sort": "RepCode",
  "order": "ASC",
  "limit": 5000
}

9. Lettura dati stagioni
- Metodo: GET
- URL: http://localhost:8080/gshop/api/data/stagioni?StaCode=&limit=50&sort=StaCode&order=ASC
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

10. Export CSV stagioni
- Metodo: POST
- URL: http://localhost:8080/gshop/api/export/stagioni
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {},
  "sort": "StaCode",
  "order": "ASC",
  "limit": 5000
}

11. Lettura dati pellami
- Metodo: GET
- URL: http://localhost:8080/gshop/api/data/pellami?PelCode=&limit=50&sort=PelCode&order=ASC
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

12. Export CSV pellami
- Metodo: POST
- URL: http://localhost:8080/gshop/api/export/pellami
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {},
  "sort": "PelCode",
  "order": "ASC",
  "limit": 5000
}

13. Lettura dati fornitori
- Metodo: GET
- URL: http://localhost:8080/gshop/api/data/fornitori?ForCode=&limit=50&sort=ForCode&order=ASC
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

14. Export CSV fornitori
- Metodo: POST
- URL: http://localhost:8080/gshop/api/export/fornitori
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {},
  "sort": "ForCode",
  "order": "ASC",
  "limit": 5000
}

15. Lettura dati marchi
- Metodo: GET
- URL: http://localhost:8080/gshop/api/data/marchi?MarCode=&limit=50&sort=MarCode&order=ASC
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

16. Export CSV marchi
- Metodo: POST
- URL: http://localhost:8080/gshop/api/export/marchi
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {},
  "sort": "MarCode",
  "order": "ASC",
  "limit": 5000
}

17. Lettura dati discipline
- Metodo: GET
- URL: http://localhost:8080/gshop/api/data/discipline?DisCode=&limit=50&sort=DisCode&order=ASC
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

18. Export CSV discipline
- Metodo: POST
- URL: http://localhost:8080/gshop/api/export/discipline
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {},
  "sort": "DisCode",
  "order": "ASC",
  "limit": 5000
}

19. Lettura dati numerazioni
- Metodo: GET
- URL: http://localhost:8080/gshop/api/data/numerazioni?NumCode=&limit=50&sort=NumCode&order=ASC
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

20. Export CSV numerazioni
- Metodo: POST
- URL: http://localhost:8080/gshop/api/export/numerazioni
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {},
  "sort": "NumCode",
  "order": "ASC",
  "limit": 5000
}

21. Export filtrato palmari (memorizza query + genera file)
- Metodo: POST
- URL: http://localhost:8080/gshop/api/palmari/export
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {
    "modcat": "003",
    "modstag": "E19",
    "modforn": "001"
  },
  "limit": 5000
}

22. Download file palmari filtrato
- Metodo: GET
- URL: http://localhost:8080/gshop/api/palmari/files/{export_id}
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

23. Lettura dati modelli
- Metodo: GET
- URL: http://localhost:8080/gshop/api/data/modelli?ModArticolo=&limit=50&sort=ModArticolo&order=ASC
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

24. Export CSV modelli
- Metodo: POST
- URL: http://localhost:8080/gshop/api/export/modelli
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {},
  "sort": "ModArticolo",
  "order": "ASC",
  "limit": 5000
}

25. Refresh masterdata schedulato (salva sempre masterdata.csv)
- Metodo: POST
- URL: http://localhost:8080/gshop/api/palmari/masterdata/refresh
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Nota:
  Se filters e vuoto (o assente), il servizio usa i filtri salvati nel profilo masterdata.
  L'export genera piu file: masterdata_1.csv ... masterdata_N.csv (split ogni X record).
  Prima di generare, i chunk masterdata gia presenti vengono cancellati.
- Body raw JSON:
{
  "filters": {
    "modcat": "003",
    "modstag": "E19"
  },
  "limit": 999999
}

26. Salva profilo filtri masterdata
- Metodo: POST
- URL: http://localhost:8080/gshop/api/palmari/masterdata/profile
- Header:
  Content-Type: application/json
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
- Body raw JSON:
{
  "filters": {
    "modcat": "003",
    "modstag": "E19",
    "modforn": "001"
  },
  "limit": 999999
}

27. Leggi profilo filtri masterdata
- Metodo: GET
- URL: http://localhost:8080/gshop/api/palmari/masterdata/profile
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J

28. Download file masterdata (csv/json)
- Metodo: GET
- URL CSV primo file disponibile: http://localhost:8080/gshop/api/palmari/masterdata/file?format=csv
- URL CSV per numero file: http://localhost:8080/gshop/api/palmari/masterdata/file?part=1&format=csv
- URL JSON per numero file: http://localhost:8080/gshop/api/palmari/masterdata/file?part=1&format=json
- Default senza parametro part: primo file disponibile
- Compat legacy supportata: ?file=masterdata_1.csv
- Header:
  X-Api-Key: _9C_8gpMEkVPE7Cx-TGC5EiSNsObLPakcMghtOuvWkq5EHs3Xf3Vat9x-lRgoA-J
