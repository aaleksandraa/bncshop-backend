Ovo je primjer PHP dokumenta koji je bio na WordPressu i komunicirao sa eLine ERP programom.

Nova implementacija u Laravel/Next.js platformi opisana je u [`ELINE-INTEGRATION.md`](ELINE-INTEGRATION.md).

## Zahtjevi (sažetak)

- Povlačenje artikala i cjenovnika iz eLine JSON feedova
- Admin mapiranje eLine kategorija na glavne BNC kategorije (iz A1 API-ja)
- Uključivanje/isključivanje kategorija i pojedinačnih proizvoda
- Označavanje stanja: refurbished ili novo (filter na pretrazi)
- Cijena = MPC iz cjenovnika (bez legacy 10% popusta)

## Legacy WordPress skripta (referenca)

> **Napomena:** Originalni skript je sadržavao hardkodirane DB credentials — uklonjeni iz sigurnosnih razloga.

```php
<?php
/* Template name: GetCSV — legacy referenca */

$artikli_url    = 'https://www8.eline.ba/bl/RestWebShop.svc/json/ArtikliZaWeb/{TOKEN}/bncshop';
$cjenovnici_url = 'https://www8.eline.ba/bl/RestWebShop.svc/json/CjenovniciZaWeb/{TOKEN}/bncshop';

$artikli_json    = file_get_contents($artikli_url);
$cjenovnici_json = file_get_contents($cjenovnici_url);

$artikli_data    = json_decode($artikli_json, true);
$cjenovnici_data = json_decode($cjenovnici_json, true);

$artikli    = $artikli_data['artikli'] ?? [];
$cjenovnici = $cjenovnici_data['cjenovnici'] ?? [];

// Legacy logika:
// - match po SKU (sifra)
// - kategorija: grupakategorija ?? grupanaziv
// - opis: opis ?? strip htmlOpis
// - cijena: MPC - 10% (ZASTARJELO — nova platforma koristi pun MPC)
// - stock iz cjenovnika
```

Za punu implementaciju pogledaj backend servise u `backend/app/Services/Eline/`.
