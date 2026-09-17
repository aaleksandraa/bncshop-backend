# Public API (1.0.0)

These pages are dedicated to help you empower your applications with our API.

Here you can find our API documentation, with each endpoint described.

If you believe you're expiriencing a bug, open an issue on our [Git Hub issue tracker.](https://github.com/ananasGit/ananas-dev-portal-issues/issues)

## Additional information

If for whatever reason a different request or response body is necessary, it can be arranged and changed after it has been reviewed and approved by our development team.

For those requests, please contact one of development team leads or apisupport@ananas.rs

# Rate limits

Rate limits are imposed as a safety measure to prevent resource abuse and potential degradation of Ananas services. The goal of rate limiting is to ensure fair use of services among all users.

Rate limits are implemented for endpoints by restricting number of requests within a certain time frame (usually per minute and/or second).

| Category | API | Max No. of requests per minute | Max No. of requests per second | Max No. of items per request |
| :---: | :---: | :---: | :---: | :---: |
| Products | Get products | 60 | 5 | 200 |
| Products | Get products (basic) | 60 | 5 | 2500 |
| Products | Add or edit products in bulk | 60 | 5 | 30000 |
| Products | Edit products in bulk | 60 | 5 | 30000 |
| Products | Edit single product | 60 | 5 | \- |
| Products | Check if EAN exists | 60 | 5 | \- |
| Products | Get all product types | 60 | 5 | \- |
| Warehouses | Get merchant warehouses | 300 | 5 | \- |
| Payment | Get all invoices | 300 | 5 | \- |
| Payment | Get all invoice corrections | 300 | 5 | \- |
| Payment | Get merchant inventory prices for date | 60 | \- | \- |
| Payment | Get all invoice URLs | 300 | 5 | \- |
| Payment | Get all invoice correction URLs | 300 | 5 | \- |
| Discounts | Schedule discounts in bulk | 60 | \- | \- |
| Discounts | Update discounts in bulk | 60 | \- | \- |
| Discounts | Get discount prices | 60 | \- | \- |
| Discounts | Cancel discount prices | 500 | \- | \- |
| Orders | Get order statuses | 300 | 5 | \- |
| Orders | Get order statuses by groups | 300 | 5 | \- |
| Orders | Get all orders | 300 | 5 | \- |
| Orders | Confirm outbound Master Order quantities | 300 | 5 | \- |
| Shipments | Confirm pack is completed | 300 | 5 | \- |
| Shipments | Fetch shipments and shipment data | 300 | 5 | \- |

# Get token

In order to login, we need to perform /token request using clientId and clientSecret we received via Merchant Portal.

After following these steps, you should take this access token and pass it in all the next mentioned REST API endpoints as an “Authorization” header with “Bearer” prefix.

## Request

*Request:*

| HTTP Method | POST |  |
| :---: | :---: | :---: |
| URL | Stage | *https://api.qa2.ananastest.com/iam/api/v1/auth/token* |
|  | Production | *https://api.ananas.rs/iam/api/v1/auth/token* |

Request Body:

| Fields name | Type | Description |
| :---: | :---: | :---: |
| grantType | String | Always “CLIENT\_CREDENTIALS” |
| clientId | String | Received via Merchant Portal |
| clientSecret | String | Received via Merchant Portal |
| scope | String | Always “public\_api/full\_access” |

Example of request:  
{  
  "grantType": "CLIENT\_CREDENTIALS",  
  "clientId": "1e1hpa0akecovl2c8nus08ha9",  
  "clientSecret": "XWvxKGAtO31aa5jQ+OHPkhgoDAfoynCbB5TlEAu/Us1JBuXpOV1DAlbY6zsYvfby28Gbk78lQyjFgz26wl0ZdOl5k/ M=",  
  "scope": "public\_api/full\_access"

}

Response Body:

| Fields name | Type | Description |
| :---: | :---: | :---: |
| id\_token | Long | Not available |
| access\_token | String | Token which will be used to access other API endpoints |
| refresh\_token | String | Not available |
| expires\_in | String | Time in seconds when token will expire |
| token\_type | String | Always Bearer |

Example of response:  
{  
  "id\_token": null,  
  "access\_token": "eyJraWQiOiJvY1ZZbHpcL3E1RDlNZnh4TGtHODZFYzVFQmp5WkN3MjMxaUt1a0h2Y1ZMbz0iLCJhbGciOiJSUz\\n    I1NiJ9.eyJzdWIiOiIxZTFocGEwYWtlY292bDJjOG51czA4aGE5IiwidG9rZW5fdXNlIjoiYWNjZXNzIiwic2NvcGUiOiJw\\n    dWJsaWNfYXBpXC9mdWxsX2FjY2VzcyIsImF1dGhfdGltZSI6MTY0MTg5MDI5NywiaXNzIjoiaHR0cHM6XC9cL2N\\n    vZ25pdG8taWRwLmV1LWNlbnRyYWwtMS5hbWF6b25hd3MuY29tXC9ldS1jZW50cmFsLTFfSm8xVmdORHZLIiwi\\n    ZXhwIjoxNjQxODkxMTk3LCJpYXQiOjE2NDE4OTAyOTcsInZlcnNpb24iOjIsImp0aSI6IjU1M2FmZDkxLWVkMzktN\\n    DA5OS1hOWFiLTRiODcyM2I0MzA2OSIsImNsaWVudF9pZCI6IjFlMWhwYTBha2Vjb3ZsMmM4bnVzMDhoYTkifQ.\\n    iWlniCebgKNF4XkpUF0bU\_KGVxqNwAIBwOwwv7Jp\_wG5v\_kjYfx7tlqNOtfsorEz7\_Iav0dxZqRHHYs1FhKg0W3Njj\\n    w3M77MBdR-tByt-N-yAyR60sc-9t9yxiHZABeDXvfeEnsmT7rBHRaF9KtccXAhaJZq5r9GRBLsYOOQ87NIslILeUd\_C-foH8f2JDtj2ofyF\_3w4P29q0RshXaHBk0nqLGQlyX3yaj-fySwWxC0qgcP1yfYvuu5rOi312f9IFeQFacsDB9Fs586EzlDv27g8NK4LJtDxHbOoBOVH9P6P1WTL5eSekNLx1WHwHC2xWaHbT\\n    aH5mwhKXOjFsQ",  
  "refresh\_token": null,  
  "expires\_in": 900,  
  "token\_type": "Bearer"

}

# Get Products

GET REST API request that returns list of all current products that are linked to merchant. Response example is shown below.

Alongside "Authorization" header, parameter "search" can be passed with stringvalue, that will filter out current products by name, SKU and EAN. For example:

https://api.qa2.ananastest.com/product/api/v1/merchant-integration/products?search=TESLA

Parameter "ean" will filter out current product faster than "search" parameter by multiple EAN, but must contain full EAN string of product, otherwise it won't find anything. For example:

https://api.qa2.ananastest.com/product/api/v1/merchant-integration/products?ean=8806092081918\&ean=8606019604615

Parameter "date-modified-after" will filter out all products that are modified after a specified date. The pattern for date format is yyyy-MM-dd. For example:

https://api.qa2.ananastest.com/product/api/v1/merchant-integration/products?date-modified-after=2022-09-12

Other 2 parameters are "page" and "size", that are used for pagination. Maximum page size is 200\.

## Request

*Request:*

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/product/api/v1/merchant-integration/products* |
|  | Production | *https://api.ananas.rs/product/api/v1/merchant-integration/products* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description search Search by name, SKU and EAN ean Search only by EAN date-modified-after Search products modified after specified date page Page number \- Starts from 0 size Number of products show by page  |

## Response

*Response Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| id | Long | Product unique identifier |
| externalId | String | Unique product identifier on merchant side |
| ean | String | European Article Number |
| ananasCode | String | Ananas code |
| groupId | String | Unique identifier for all the variations of the same product |
| name | String | Product's name |
| description | String | Product description |
| brand | String | Product's brand |
| sku | String | Stock keeping unit |
| productType | String | Type of product |
| categories | List\<String\> | List of categories where product belongs |
| newBasePrice | BigDecimal/Double | Product price with VAT included for the next day |
| basePrice | BigDecimal/Double | Product price with VAT included |
| vat | BigDecimal/Double | Product VAT |
| stockLevel | Integer | Product available stock level |
| status | String | Product status. Possible values \[“READY\_FOR\_PUBLISH”, “PUBLISHED”, “SOLD\_OUT”, “UNPUBLISHED”, “UNPUBLISHED\_OOS”\] |
| packageWeightValue | BigDecimal/Double | Package weight of product |
| packageWeightUnit | String | Product weight package unit |
| packageHeightValue | BigDecimal/Double | Package height of product |
| packageWidthValue | BigDecimal/Double | Package width of product |
| packageLengthValue | BigDecimal/Double | Package length of product |
| packageDimensionUnit | String | Product package dimension unit |
| specifications | String | Additional product attributes in JSON format |

Example of response:  
\[  
    {  
        "id": 17,  
        "externalId": "123",  
        "ean": "2349589484368",  
        "ananasCode": null,  
        "groupId": "5a91ec2e-c51d-4b0e-848a-2771585eef79",  
        "name": "Autic 146",  
        "description": "Pokažite svoju autentičnost u Under Armour UA HOVR Summit Mid patikama za slobodne  
                aktivnosti.\\nSavršeno lagane i udobne, ove jedinstvene patike svaki korak čine savršeno komfornim, a vaš stil smelim i  
                drugačijim.\\nGornjište je izrađeno od prozračnog tekstila i ojačano izdržljivim sintetičkim slojevima materijala.\\nPleteni deo  
                oko gležnja vam daje osećaj “zaključavanja”, a lagani uložak od EVA pene responzivnost i dodatni komfor.\\nUA HOVR  
                tehnologija pene u srednjem đonu vraća energiju i smanjuje pritisak na stopalo za osećaj “nulte gravitacije” tokom  
                kretanja.\\nGumeni đon sa dubokim fleksibilnim kanalima i čvrstim “kramponima” na peti omogućava sigurnu trakciju i  
                izdržljivost.\\nSpecifikacija:\\nMuške lifestyle patike\\nGornjište: tekstil, sintetika\\nSrednji đon: UA HOVR pena\\nĐon: izdržljiva  
                trakciona guma\\nBoja: zelena\- Baroque Green\\nŠifra artikla: 3022949\-301",  
        "brand": "{\\"id\\":29785,\\"name\\":\\"Nike\\"}",  
        "sku": "neki\_novi\_klinci",  
        "productType": "Automotive",  
        "categories": \[  
            "Auto radio"  
        \],  
        "newBasePrice": 31900.00,  
        "basePrice": 123123.00,  
        "vat": 10.00,  
        "stockLevel": 13,  
        "status": "PUBLISHED",  
        "packageWeightValue": 200.55,  
        "packageWeightUnit": "kg",  
        "packageHeightValue": 101.00,  
        "packageWidthValue": 102.00,  
        "packageLengthValue": 103.00,  
        "packageDimensionUnit": "cm",  
        "specifications": "{"attributes":\[{"type":"CompositeAttribute","key":"weight\_dimensions","trait":"Težina i dimenzije","attributes":\[{"type":"MeasurementAttribute","key":"Weight","trait":"Težina","value":0.84,"unit":"kg"}\]}\]}"  
    }

\]

# Get Products (basic) NEW \!\!\! (2026-03-25)

GET REST API request that returns list of all current products that are linked to merchant. Response example is shown below.

"Authorization" header

Parameter "dateModifiedAfter" will filter out all products that are modified after a specified date. The pattern for date format is yyyy-MM-ddTHH-mm-ss. For example:

https://api.qa2.ananastest.com/product/api/v1/merchant-integration/basic-products?dateModifiedAfter=2025-07-10T13:05:07

Other 2 parameters are "page" and "size", that are used for pagination. Maximum page size is 2500\.

## Request

*Request:*

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/product/api/v1/merchant-integration/basic-products* |
|  | Production | *https://api.ananas.rs/product/api/v1/merchant-integration/basic-products* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description dateModifiedAfter Search products modified after specified date page Page number \- Starts from 0 size Number of products show by page  |

## Response

*Response Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| id | Long | Product unique identifier |
| ean | String | European Article Number |
| sku | String | Stock keeping unit |
| newBasePrice | BigDecimal/Double | Product price with VAT included for the next day |
| basePrice | BigDecimal/Double | Product price with VAT included |
| stockLevel | Integer | Product available stock level |
| warehouse | String | Warehouse possible values \['ANANAS\_WAREHOUSE', 'MERCHANT\_WAREHOUSE'\] |

Example of response:  
\[  
    {  
        "id": 19365,  
        "sku": null,  
        "ean": "0436421111444",  
        "stockLevel": 11,  
        "newBasePrice": 11245.00,  
        "basePrice": 11245.00,  
        "warehouse": "ANANAS\_WAREHOUSE"  
    }  

\]

# Add or edit products in bulk

POST REST API request that requires a JSON body with authorization token in order to acquire, add or edit desired products.

In order for a product to be linked with a merchant, it requires an EAN that needs to be sent alongside desired changed data for that product.

Required fields for successful request are name, description, coverImage, EAN, but this will be subject to change during later iterations.

## Request

*Request:*

| HTTP Method | POST |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/product/api/v1/merchant-integration/import* |
|  | Production | *https://api.ananas.rs/product/api/v1/merchant-integration/import* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

*Request Body:*

| Fields name | Type | Description | Required |
| :---: | :---: | :---: | :---: |
| name | String | Product name | Add: yes Edit: yes |
| description | String | Product description (allows html tags, \<br\>,\<ul\>,\<ol\>,\<li\> excluding \<b\> or \<i\>) | Add: yes Edit: yes |
| coverImage | String | Product cover image | Add: yes Edit: yes |
| ean | String | European Article Number | Add: yes Edit: yes |
| brand | String | Product brand | Add: yes Edit: no |
| gallery | List\<String\> | Product images | Add: yes Edit: no |
| parentEan | String | The product will be a variation of the product to which the EAN is entered | Add: no Edit: no |
| packageWeightValue | BigDecimal/Double | Package weight of product | Add: yes Edit: no |
| packageWeightUnit | String | Product weight package unit | Add: yes Edit: no |
| basePrice | BigDecimal/Double | Product price with VAT included | Add: yes Edit: no |
| vat | BigDecimal/Double | Product VAT | Add: yes Edit: no |
| stockLevel | Integer | Product available stock level | Add: yes Edit: no |
| sku | String | Stock keeping unit | Add: yes Edit: no |
| externalId | String | Unique product identifier on merchant side | Add: no Edit: no |
| productType | String | Type of product | Add: no Edit: no |
| category | String | Main category where product belongs | Add: no Edit: no |
| attributes | Map\<String,List\<String\>\> | Attributes of product like color, size, material type, number of HDMI’s, type of RAM, etc. | Add: no Edit: no |
| packageHeightValue | BigDecimal/Double | Package height of product | Add: no Edit: no |
| packageWidthValue | BigDecimal/Double | Package width of product | Add: no Edit: no |
| packageLengthValue | BigDecimal/Double | Package length of product | Add: no Edit: no |
| productWidthValue | BigDecimal/Double | Product width | Add: no Edit: no |
| productLengthValue | BigDecimal/Double | Product length | Add: no Edit: no |
| productWeightValue | BigDecimal/Double | Product weight | Add: no Edit: no |

Note: Required fields will be subject to change during later iterations.

Example of request body:  
\[  
  {  
    "name": "Samsung Pametni telefon Galaxy SM-A325F plavi",  
    "description": "Samsung Galaxy SM-A325F. Dijagonala monitora: 16,3 cm (6.4\\"), Rezolucije ekrana: 1800 x\\n2400 piksela, Tip ekrana: SAMOLED. Takt procesora: 2 GHz. RAM kapacitet: 4 GB, Kapacitet interne memorije: 128 GB. Rezolucija zadnje kamere (numerička): 64 MP, Tip zadnje kamere: Četvorostruka kamera. Mogućnosti SIM kartice: Dve SIM kartice. Trajanje baterije: 5000 mAh. Boja proizvoda: Plavo. Težina: 184 g",  
    "coverImage": "https://ananas.rs/\_next/image?url=https%3A%2F%2Fstatic.ananas.rs%2Ftmp%2Fimage- thumbnails%2FProduct\_Images%2FSmartphones%2Fsamsung\_galaxy\_sm\_a325f\_16\_3\_cm\_6\_4\_dve\_sim\_karti ce\_4g\_usb\_tipa\_c\_4\_gb\_128\_gb\_5000\_mah\_plavo%2Fimage- thumb\_\_295826\_\_product\_thumbnail%2F951c5c5b9fdddc72.jpeg\&w=3200\&q=75",  
    "ean": "9788644105886",  
    "brand": "Samsung",  
    "gallery": \[  
      "https://ananas.rs/\_next/image?url=https%3A%2F%2Fstatic.ananas.rs%2Ftmp%2Fimage- thumbnails%2FProduct\_Images%2FSmartphones%2Fsamsung\_galaxy\_sm\_a325f\_16\_3\_cm\_6\_4\_dve\_sim\_karti ce\_4g\_usb\_tipa\_c\_4\_gb\_128\_gb\_5000\_mah\_plavo%2Fimage- thumb\_\_295826\_\_product\_thumbnail%2F951c5c5b9fdddc72.jpeg\&w=3200\&q=75"  
    \],  
    "parentEan": "",  
    "packageWeightValue": 184,  
    "packageWeightUnit": "KG",  
    "packageHeightValue": 110,  
    "packageLengthValue": 120,  
    "packageWidthValue": 130,  
    "productWeightValue": 180,  
    "productHeightValue": 100,  
    "productLengthValue": 110,  
    "productWidthValue": 120,  
    "basePrice": 30990,  
    "vat": 20,  
    "stockLevel": 5,  
    "sku": "LXG9HET6O3",  
    "externalId": "1",  
    "productType": "Telefon",  
    "category": "SMART mobilni telefoni",  
    "attributes": {  
      "Boja": \[  
        "Plava"  
      \],  
      "Standard veličine": \[  
        "Monoblokovi"  
      \],  
      "Sirina": \[  
        "73.6 mm"  
      \],  
      "Dubina": \[  
        "8.4 mm"  
      \],  
      "Visina": \[  
        "158.9 mm"  
      \],  
      "Tezina": \[  
        "184 g"  
      \]  
    }  
  }

\]

## Important Request information

If for whatever reason you do not have information on any of the non-required fields, you can simply place null value instead.

Since we are working with different stock levels, price and other linked things for a given product that could impact the status of merchant selling, all the provided data for on required fields will be kept as previous or existing ones in case they are missing from the request. Exception is stockLevel, if stockLevel has negative value, previous stockLevel will be set to zero.

StockLevel can be 0 or greater, but packageWeightValue and basePrice must be positive non 0 numbers.

Currently the packageWeight unit is hardcoded and will always be KG, even if some other value is passed.

Valid VAT values are 0, 10 and 20, if included in the request

## Response

If EAN is not yet linked to a given merchant, a new product for him will be created in our database and its respective ID will be generated alongside it. If EAN is already linked to a given merchant, then the product will be updated in the database

If EAN does not exist on our database, then the product will be added and linked to merchant later.

Once the desired product is updated, if the basePrice that was sent differs from the basePrice, it will be updated after midnight at 00:01.

Request will process asynchronous, and progression of the process can be tracked from value that is given in response, but at this moment is not open to public use.

*Response Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| id | UUID | Progress id which can be used to track progress |

Example of response:  
   {  
        "id": "c52813ec-f69b-4202-bb03-0a2534c93781"

    }

# Edit products in bulk

PUT REST API request that requires a JSON body with authorization token in order to acquire, edit desired product.

## Request

*Request:*

| HTTP Method | PUT |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/product/api/v1/merchant- integration/product/bulk* |
|  | Production | *https://api.ananas.rs/product/api/v1/merchant-integration/product/bulk* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

*Request Body*

The request body represents an array of elements that have the following fields:

| Fields name | Type | Description |
| :---: | :---: | :---: |
| id | Long | Product id |
| packageWeightValue | BigDecimal/Double | Package weight of product |
| packageWeightUnit | String | Product weight package unit |
| packageHeightValue | BigDecimal/Double | Package height of product |
| packageWidthValue | BigDecimal/Double | Package width of product |
| packageLengthValue | BigDecimal/Double | Package length of product |
| basePrice | BigDecimal/Double | Product price with VAT included |
| stockLevel | integer | Product available stock level |
| serviceable | boolean | Serviceable flag |
| sku | String | SKU value |

Example of request body:  
\[  
  {  
    "id": 1770663,  
    "stockLevel": 11,  
    "basePrice": 155.3,  
    "vat": 0,  
    "packageWeightValue": 2.55,  
    "packageWeightUnit": "kg",  
    "packageHeightValue": 101,  
    "packageWidthValue": 102,  
    "packageLengthValue": 103,  
    "serviceable": true,  
    "sku": "00280106"  
  },  
  {  
    "id": 123,  
    "stockLevel": 11,  
    "basePrice": 155.3,  
    "vat": 0,  
    "packageWeightValue": 2.55,  
    "packageWeightUnit": "kg",  
    "serviceable": true,  
    "sku": "00280107"  
  },  
  {  
    "id": 1770662,  
    "stockLevel": 11,  
    "basePrice": 155.3,  
    "vat": 4,  
    "packageWeightValue": 2.55,  
    "packageWeightUnit": "kg",  
    "serviceable": true,  
    "sku": "00280108"  
  }

\]

## Response

If the product with the ID does not exist, the endpoint will return a status of 404\. Otherwise, it returns status 200 with the corresponding message

*Response Body:*

The response body represents an array of elements that have the following fields:

| Fields name | Type | Description |
| :---: | :---: | :---: |
| status | String | Action status. Values: “SUCCESS” or “FAIL” |
| errors | Array of string | Errors if action failed |
| myProductId | Long | Product id |

Example of response:  
\[  
  {  
    "status": "SUCCESS",  
    "errors": \[\],  
    "myProductId": 1770663,  
    "ean": "2349589484368",  
    "productName": "UNDER ARMOUR UA HOVR Summit Mid"  
  },  
  {  
    "status": "FAIL",  
    "errors": \["Proizvod ne postoji"\],  
    "myProductId": 123,  
    "ean": null,  
    "productName": "Adidas Running"  
  },  
  {  
    "status": "FAIL",  
    "errors": \[  
      "PDV mora biti jedan od sledecih vrednosti: 0, 10, 20\. Prosleđena vrednost je 4",  
    \],  
    "myProductId": 1770662,  
    "ean": null,  
    "productName": "UNDER ARMOUR UA HOVR"  
  },

\]

# Edit single product

PUT REST API request that requires a JSON body with authorization token in order to acquire, edit desired product

## Request

*Request:*

| HTTP Method | PUT |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/product/api/v1/merchant-integration/product/id* |
|  | Production | *https://api.ananas.rs/product/api/v1/merchant-integration/product/id* |
| URL Parameters |  |  Parameter name Parameter value id Product id  |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

*Request Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| packageWeightValue | BigDecimal/Double | Package weight of product |
| packageWeightUnit | String | Product weight package unit |
| basePrice | BigDecimal/Double | Product price with VAT included |
| vat  | BigDecimal/Double | Product VAT |
| stockLevel | Integer | Product available stock level |
| serviceable | boolean | Serviceable flag |
| sku | String  | SKU value |
| packageHeightValue | BigDecimal/Double | Package height of product |
| packageWidthValue | BigDecimal/Double | Package width of product |
| packageLengthValue | BigDecimal/Double | Package length of product |

Example of request body:  
{  
    "basePrice": 31900.00,  
    "vat": 10.00,  
    "packageWeightValue": 200.55,  
    "packageWeightUnit": "KG",  
    "packageHeightValue": 101,  
    "packageWidthValue": 102,  
    "packageLengthValue": 103,  
    "stockLevel": 10,  
    "serviceable": true,  
    "sku": "00280106"

}

## Response

If the product with the ID does not exist, the endpoint will return a status of 404\. Otherwise, it returns status 200 with the corresponding message

*Response Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| status | String  | Action status. Values: “SUCCESS” or “FAIL” |
| errors | Array of string | Errors if action failed |
| myProductId | Long | Product id |

Reponse example:  
{  
    "type": "edit",  
    "status": "SUCCESS",  
    "errors": \[\],  
    "myProductId": 17,  
    "ean": "2349589484368",  
    "productName": "Autic 146"

}

# Check if EAN exists

PUT REST API request that requires a JSON body with authorization token in order to acquire, edit desired product.

## Request

*Request:*

| HTTP Method | POST |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/product/api/v1/merchant- integration/ean/exists* |
|  | Production | *https://api.ananas.rs/product/api/v1/merchant-integration/ean/exists* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

*Request Body*

The request body represents an array of EANs.

Example of request body:

\[  
  "088280476892",  
  "757670268125",  
  "875441735984"

\];

## Response

The endpoint will return status 200 with key-value object representing whether the EAN exists or not.

*Response Body:*

The response body represents an object with key-value pair (EAN:BOOLEAN)

Example of response:  
Click to copy  
\[  
  {  
    "088280476892": true,  
    "757670268125": false,  
    "875441735984": true,  
  },

\];

# Get all product types

GET REST API request that returns list of all current active product types (in more detail: returns a list of all available product import templates that relate to currently active categories)

Resposne example is shown below.

## Request

*Request:*

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/product/api/v1/merchant-integration/product-type* |
|  | Production | *https://api.ananas.rs/product/api/v1/merchant-integration/product-type* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token} Accept-Language \<language\>  |

## Response

*Response Body:*

| Type | Description |
| :---: | :---: |
| List\<String\> | List of active product types |

Example of response:  
Click to copy  
\[  
  "Bebi žvakalice",  
  "Kafemati",  
  "Prajmeri za šminku",  
  "Pomoćni stočići",  
  "Daske za uzglavlje i podnožje kreveta",  
  "Izbeljivanje zuba"

\]

# Publish products in bulk

POST REST API request that requires a JSON body with authorization token in order to acquire, publish desired products.

In order for a product to be visible on our Customer Portal, it needs to be published.

## Request

*Request:*

| HTTP Method | POST |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/product/api/v1/merchant-integration/product/publish* |
|  | Production | *https://api.ananas.rs/product/api/v1/merchant-integration/product/publish* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

*Request Body:*

| Fields name | Type | Description | Required |
| :---: | :---: | :---: | :---: |
| / | List\<Integer\> | Merchant inventory ids | Yes |

Example of request body:  
\[  
 123456,  
 234567,  
 345678

\]

## Response

Request will process asynchronous, and progression of the process can be tracked from value that is given in response, but at this moment is not open to public use.

When request processing is finished, an email will be sent with information about affected products.

*Response Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| id | UUID | Progress id which can be used to track progress |

Example of response:  
Click to copy  
   {  
        "id": "c52813ec-f69b-4202-bb03-0a2534c93781"

    }

# Unpublish products in bulk

POST REST API request that requires a JSON body with authorization token in order to acquire, unpublish desired products.

In order to hide a product from customers, it needs to be unpublished.

## Request

*Request:*

| HTTP Method | POST |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/product/api/v1/merchant-integration/product/unpublish* |
|  | Production | *https://api.ananas.rs/product/api/v1/merchant-integration/product/unpublish* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

*Request Body:*

| Fields name | Type | Description | Required |
| :---: | :---: | :---: | :---: |
| / | List\<Integer\> | Merchant inventory ids | Yes |

Example of request body:  
\[  
 123456,  
 234567,  
 345678

\]

## Response

Request will process asynchronous, and progression of the process can be tracked from value that is given in response, but at this moment is not open to public use.

When request processing is finished, an email will be sent with information about affected products.

*Response Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| id | UUID | Progress id which can be used to track progress |

Example of response:  
Click to copy  
   {  
        "id": "c52813ec-f69b-4202-bb03-0a2534c93781"

    }

# Get merchant warehouses

GET REST API request that returns list of all merchant outbound addresses (merchant warehouses).

## Request

*Request:*

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.svc.qa2.ananastest.com/order/api/v1/merchant-integration/merchant-warehouses* |
|  | Production | *https://api.svc.ananas.rs/order/api/v1/merchant-integration/merchant-warehouses* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

## Response

*Response Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| content | List \<MerchantAddress\> | List of merchant addresse |

*Content:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| id | Long | Unique merchant address identifier |
| warehouseName | String | Warehouse name |
| defaultAddres | boolean | Indicate that address is a default outbound address (Only one element of the collection can have this value set to true) |

Example of response:  
  {  
            "content":  
            \[  
                {  
                    "id":1,  
                    "warehouseName":"Downtown warehouse",  
                    "defaultAddress ":false  
                },  
                {  
                    "id":2,  
                    "warehouseName":"Airport warehouse",  
                    "defaultAddress ":false  
                },  
                {  
                    "id":3,  
                    "warehouseName":"Central warehouse",  
                    "defaultAddress ":true  
                }  
            \]

        }

# Get all invoices

GET REST API request that returns list of all invoices that are linked to merchant. Response example is shown below.

Alongside “Authorization” header, parameter “type” can be passed with string value FISCAL or NON\_FISCAL, that will filter out invoices by type. If this parameter is not provided, all invoices will be returned. Non fiscalized merchant will not be able to get invoices with parameter type FISCAL, response will be bad request.

Parameters “dateFrom” and “dateTo” are zoned dates with time and are required and inclusive. They represent time range for invoiced dates. Date from is only viable from 1stof May 2022, and date range can’t be longer than 3 months. Example:

https://api.qa2.ananastest.com/order/api/v1/merchant-integration/invoices?dateFrom=2022-05-01T10:20:45.1457765Z\&dateTo=2022-06-07T10:20:45.1457765Z\&type=FISCAL

## Request

*Request:*

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/invoices* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/invoices* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description type FISCAL, NON\_FISCAl (filters invoices by type) dateFrom Referes to invoicedDateDate must be sent with time zone dateTo Referes to invoicedDateDate must be sent with time zone  |

## Response

Response is a collection of invoices.

*Invoice*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| invoiceHeader | InvoiceHeader | Invoice header information that includes information about type of invoice, customer, merchant, fiscal data and basic order details |
| productSpecification | ProductSpecification | Details about products, shipping and grand total prices |
| taxRateSpecification | TaxRateSpecification | Details about taxes |

*InvoiceHeader*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| invoicetype | Invoicetype | Represents invoice type. Invoice can be of type FISCAL or NON\_FISCAL |
| merchantDetails | MerchantDetails | Details about merchant |
| customerDetails | CustomerDetails | Details about customer |
| fiscalDetails | FiscalDetails | Fiscal data provided by tax administration |
| orderDetails | OrderDetails | Details about order |

*MerchantDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| name | String | Merchant's name |
| businessSpaceCode | String | Unique code for business space |
| taxIdentificationNumber | String | Unique merchant tax identification number(PIB) |
| identificationNumber | String | Unique merchant identification number (MB) |
| taxAdministrationId | String | Unique tax administration id (ESIR) |
| cashier | String | Cashier that issued the invoice |
| contactDetails | ContactDetails | Merchant's contact details |
| address | Address | Merchant’s headquarters address |

*ContactDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| phone | Sting | Merchant's phone |
| email | String | Merchant's email |

*Address*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| street | Sting | Street name and number |
| city | String | City |

*CustomerDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| firstName | Sting | Customer's fist name |
| lastName | String | Customer's last name |
| address | CustomerAddress | Customer's billing address |

*CustomerAddress*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| street | Sting | Street name and number |
| city | String | City |
| postcode | string | Postal code |

*FiscalDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| invoiceCounter | Sting | Invoice counter generated by tax administration |
| verificationUrl | String | Verification url for fiscal transaction generated by tax administration |
| fiscalInvoiceDate | LocalDateTime | Tax administration invoiced date in UTC |
| invoiceNumber | String | Unique tax administration invoice number |

*OrderDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| invoiceId | Sting | Unique Ananas invoice id |
| invoicedDate | LocalDateTime | Ananas invoiced date in UTC |
| orderId | String | User friendly unique order id |
| suborderId | String | User friendly unique suborder id |
| fba | boolean | Fullfilment by Ananas |
| paymentMethods | List\[String\] | Order payment methods. Example: \[COD, VOUCHER\] Translation of payment methods: COD- cash on delivery PBC- pay by card POS – pay on site VOUCHER \- voucher |
| warehouseAddress | WarehouseAddress | Address of the warehouse from where the order was picked up |
| orderDate | LocalDateTime | Ddate of creating order |

*WarehouseAddress*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| WarehouseId | Long | Merchant’s warehouse identity |
| street | String | Street name and number |
| city | String | City |

*ProductSpecification*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| items | List(Item) | List of all suborder items with details |
| GrandTotalDetails | GrandTotalDetails | Grand total prices |

*Item*

| Fields name | Type | Description |
| :---- | :---- | :---- |
| productDetails | ProductDetails | Details about product |
| quantity | int | Packed quantity for an item |
| itemPrice | Price | Price for single item |
| grandTotalPrice | Price | Item prices multiplied by quantity |

*ProductDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| productDetails | ProductDetails | Details about product |
| name | String | Product name |
| apId | String | Ananas platform id |
| sku | String | SKU value |
| ean | String | European Article Number |
| aCode | String | Ananas code |

*Price*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| unitPrice | BigDecimal | Unit price |
| unitPriceWithoutVat | BigDecimal | Unit price without added vat |
| basePrice | BigDecimal | Sellable price with discount |
| basePriceWithoutVat | BigDecimal | Sellable price with discount and without vat |
| discountVat | BigDecimal | Discount percentage |
| discountAmount | BigDecimal | Discount |
| vat | BigDecimal | Vat percentage |
| vatAmount | BigDecimal | Vat amount |

*GrandTotalDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| basePrice | BigDecimal | Sellable price Sum of items base prices and shipping |
| basePriceWithoutVat | BigDecimal | Sellable price without vat |
| vatAmount | BigDecimal | Vat percentage |
| chargedPrice | BigDecimal | Price charged to customer |

*TaxRateSpecification*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| taxRateSpecification | List(TaxRateDetails) | List of tax rate details |
| grandTotal | BigDecimal | Total tax amountSum of tax rate base prices |

*TaxRateDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| label | String | Tax rate label |
| name | String | Tax rate name |
| basePriceWithoutVat | BigDecimal | Sellable price with without vat |
| vat | BigDecimal | Vat percentage |
| vatAmount | BigDecimal | Vat amount |

Example of response:  
Click to copy  
\[  
  {  
    "invoiceHeader": {  
      "invoiceType": "FISCAL",  
      "merchantDetails": {  
        "name": "Maki doo",  
        "businessSpaceCode": "",  
        "taxIdentificationNumber": "111606273",  
        "identificationNumber": "20862467",  
        "taxAdministrationId": "854/1.0",  
        "cashier": "",  
        "contactDetails": {  
          "phone": "+381669879009",  
          "email": "merchant@gmail.com",  
        },  
        "address": {  
          "street": "ZAOB.PUT ZVORNIK-LJUBOVIJA 23",  
          "city": "LJUBOVIJA",  
        },  
      },  
      "customerDetails": {  
        "firstName": "Pera",  
        "lastName": "Segedinac",  
        "address": {  
          "street": "PERE SEGEDINCA 55",  
          "city": "BEOGRAD-NOVI BEOGRAD",  
          "postcode": "11010"  
        },  
      },  
      "fiscalDetails": {  
        "invoiceCounter": "1757/3189ПП",  
        "verificationUrl":  
          "https://sandbox.suf.purs.gov.rs/v/?vl=A1JFUkNaS002RHQxT3YxbzB1DAAA3QYAAECdCBAAAAAAAAABgItDMLkAAAB228TA9h5b1hyhwICcG8JmBts0IPX9Qu5OlntWAktzDqQzNskEfv0ENM8l19irPzzwNaeBYFrkym%2BgBW5rnoMT3T3WSoKAWdhR6%2BPoUeDB4vftjCxC6pnpYOyJfecyUAangAl6mdZcsrF472oguahaoHXc8ENXhX1RMSU7qP2gxAL%2FbRY7Jq8PpPxqbN3AcnoVPoLVH%2B85r2jyyoKItPA7fMjMb3gHf%2BH66cP0pegse0P93aX%2BH3SAASSjXwNYQROiL1frth5XRAn4crwNiwWFHECnSy00oZySi47sFS8Fm2j0ZGZafRP8eB1cIv2elgrXVAkqzd8WKC%2FB9jW7L41uFI05BzUcvqQYQEB5ryr79ppa2bxJOR86D4iBikNQpVl2OF8oo28DuzPlsZbT9xmCK%2F17n%2FR5w3RvPJHo4%2FJJDuG4rdY3YVf%2BQQJHeFRt2KEukQcKhZgvuF7N7Ggd%2Fmyk09ojp%2F7oZt3j3yhTg8UiD1XJcz51jNVj50V%2FlXWYxct%2BOTMs7d3xwt8u2RMo4nS7JUzycPyu5TExeDZTZZ7i2e6xfD2qnNJH3OoNLuaes3To9864xszHq8roHOMZU%2FlUKgyQSBSS%2B9EftlmVzzPjHhAmAM%2BWneSyD3oG2Ft6aZ0LMy6JmjKnmqmKpTqmkxSYGL1WJnQ0KwBuknT30%2BrVeLExl7q3HStApjRCqce5mXzL5tiyxMJiXjY2sOIPEE5GZT6He6L2Vnva1Rs5ziXyjP9Ifkl%2Bj6dkkLtYHQ9S1jMN5qoJgDtNQlVxWVFGZZALSKlKUsi4YyUffC9GyQ4V1jaf3U9zDj6cS%2BpTmCAB5yhCz3cLu5Wsdw7yrZwMZIRR6FKJ3mDZ42%2BqxNAuMoVZ5XwRA5Lb9TnAi83%2F36EMrM2N%2Fhkyt1VfZc7ajoPiSPjVAqeIp2P%2FoPeKPl6bWZSoUrbOVQGDBmnpT%2FFCzQMI74veLOEohrvrn8mthiG68xYGzvao82ZQBOrPYMe7LMxtT44GZGGa8RojmlDmQ%2FY0jtKspRjDfWE7xh8rIvrNtmQl",  
        "fiscalInvoiceDate": "2022-05-03T18:51:18Z",  
        "invoiceNumber": "RERCZKM6-Dt1Ov1o0-3171"  
      },  
      "orderDetails": {  
        "invoiceNumber": "4599335419",  
        "invoicedDate": "2022-05-03T00:00:00Z",  
        "orderId": "69N73-9E0P9",  
        "suborderId": "69N73-9E0P9-DS-1",  
        "fba": false,      
        "paymentMethods": \["PBC","VOUCHER"\],        
        "warehouseAddress": {  
          "warehouseId": 586749  
          "street": "DRINSKA 23",  
          "city": "LJUBOVIJA"            
        },  
      },  
    },  
    "productSpecification": {  
      "items": \[  
        {  
          "productDetails": {  
            "name": "TESLA Televizor 40T319SFS SMART",  
            "apId": "VT8YI1VRS7",  
            "sku": "AA-123456-AB",  
            "ean": "8606018852161",  
            "acode": "test123",  
          },  
          "quantity": 1,  
          "itemPrice": {  
            "unitPrice": 27900,  
            "unitPriceWithoutVat": 23250,  
            "basePrice": 26900,  
            "basePriceWithoutVat": 22416.67,  
            "discountVat": 3.59,  
            "discountAmount": 1000,  
            "vat": 20,  
            "vatAmount": 4483.33,  
          },  
          "grandTotalPrice": {  
            "unitPrice": 27900,  
            "unitPriceWithoutVat": 23250,  
            "basePrice": 26900,  
            "basePriceWithoutVat": 22416.67,  
            "discountVat": 3.59,  
            "discountAmount": 1000,  
            "vat": 20,  
            "vatAmount": 4650,  
          },  
        },  
      \],     
      "totalDetails": {  
        "basePrice": 26900,  
        "basePriceWithoutVat": 22416.67,  
        "vatAmount": 4483.33,  
        "chargedPrice": 26900,  
      },  
    },  
    "taxRateSpecification": {  
      "taxRateSpecification": \[  
        {  
          "label": "Ж",  
          "name": "О-ПДВ",  
          "basePriceWithoutVat": 22416.67,  
          "vat": 20,  
          "vatAmount": 4483.33,  
        },  
      \],  
      "grandTotal": 4483.33,  
    }  
  },

\];

# Get all invoice corrections

GET REST API request that returns list of all invoice corrections that are linked to merchant. Response example is shown below.

Alongside “Authorization” header, parameter “type” can be passed with string value FISCAL or NON\_FISCAL, that will filter out invoice corrections by type. If this parameter is not provided, all invoice corrections will be returned. Non fiscalized merchant will not be able to get invoice corrections with parameter type FISCAL, response will be bad request.

Parameters “dateFrom” and “dateTo” are zoned dates with time and are required and inclusive. They represent time range for invoiced dates. Date from is only viable from 1st of May 2022, and date range can’t be longer than 3 months. Example:

https://api.qa2.ananastest.com/order/api/v1/merchant-integration/invoice-corrections?dateFrom=2022-05-01T10:20:45.1457765Z\&dateTo=2022-06-07T10:20:45.1457765Z\&type=FISCAL

## Request

*Request:*

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/invoice-corrections* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/invoice-corrections* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description type FISCAL, NON\_FISCAl (filters invoice corrections by type) dateFrom Referes to invoicedDate Date must be sent with time zone dateTo Referes to invoicedDate Date must be sent with time zone  |

## Response

Response is a collection of Invoice corrections.

*InvoiceCorrection*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| invoiceHeader | InvoiceHeader | Invoice header information that includes information about type of invoice correction, customer, merchant, fiscal data and basic order details |
| productSpecification | ProductSpecification | Details about products, shipping and grand total prices |
| taxRateSpecification | TaxRateSpecification | Details about taxes |

*InvoiceHeader*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| invoicetype | Invoicetype | Represents invoice correction type. Type can be FISCAL or NON\_FISCAL |
| merchantDetails | MerchantDetails | Details about merchant |
| customerDetails | CustomerDetails | Details about customer |
| fiscalDetails | FiscalDetails | Fiscal data provided by tax administration |
| orderDetails | OrderDetails | Details about order |
| invoiceCorrectionReason | String | Reason for generating invoice correction |

*MerchantDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| name | String | Merchant's name |
| businessSpaceCode | String | Unique code for business space |
| taxIdentificationNumber | String | Unique merchant tax identification number(PIB) |
| identificationNumber | String | Unique merchant identification number (MB) |
| taxAdministrationId | String | Unique tax administration id (ESIR) |
| cashier | String | Cashier that issued the invoice |
| contactDetails | ContactDetails | Merchant's contact details |
| address | Address | Merchant’s headquarters address |

*ContactDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| phone | String | Merchant's phone |
| email | String | Merchant's email |

*Address*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| street | String | Street name and number |
| city | String | City |

*CustomerDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| firstName | Sting | Customer’s first name |
| lastName | String | Customer’s last name |
| address | Address | Customer’s billing address |

*FiscalDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| invoiceCounter | Sting | Invoice counter generated by tax administration |
| verificationUrl | String | Verification url for fiscal transaction generated by tax administration |
| fiscalInvoiceDate | LocalDateTime | Tax administration invoiced date in UTC |
| referentDocumentNumber | String | Invoice external reference id |
| invoiceNumber | String | Unique tax administration invoice number |

*OrderDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| invoiceid | Sting | Unique Ananas invoice number |
| invoicedDate | LocalDateTime | Ananas invoiced date in UTC |
| orderId | String | User friendly unique order id |
| suborderId | String | User friendly unique suborder id |
| fba | boolean | Fullfilment by Ananas |
| paymentMethods | List\[String\] | Order payment methods. Example: \[COD, VOUCHER\] Translation of payment methods: COD \- cash on delivery PBC- pay by card POS – pay on site VOUCHER \- voucher |
| warehouseAddress | WarehouseAddress | Address of the warehouse from where the order was picked up |
| orderDate | LocalDateTime | Date of creating order |

*WarehouseAddress*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| warehouseId | Long | Merchant’s warehouse identity |
| street | String | Street name and number |
| city | String | City |

*ProductSpecification*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| items | List(Item) | List of all suborder items with details |
| GrandTotalDetailsDto | GrandTotalDetails | Grand total prices |

*Item*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| productDetails | ProductDetails | Details about product |
| quantity | int | Packed quantity for an item |
| itemPrice | Price | Price for single item |
| grandTotalPrice | Price | Item prices multiplied by quantity |

*ProductDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| name | String | Product name |
| apId | String | Ananas platform id |
| sku | String | SKU value |
| ean | String | European Article Number |
| aCode | String | Ananas code |

*Price*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| unitPrice | BigDecimal | Unit price |
| unitPriceWithoutVat | BigDecimal | Unit price without added vat |
| basePrice | BigDecimal | Sellable price with discount |
| basePriceWithoutVat | BigDecimal | Sellable price with discount and without vat |
| discountVat | BigDecimal | Discount percentage |
| discountAmount | BigDecimal | Discount |
| vat | BigDecimal | Vat percentage |
| vatAmount | BigDecimal | Vat amount |

*GrandTotalDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| basePrice | BigDecimal | Sellable price Sum of items base prices and shipping |
| basePriceWithoutVat | BigDecimal | Sellable price without vat |
| vatAmount | BigDecimal | Vat percentage |
| chargedPrice | BigDecimal | Price charged to customer |

*TaxRateSpecification*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| taxRateSpecification | List(TaxRateDetails) | List of tax rate details |
| grandTotal | BigDecimal | Total tax amountSum of tax rate base prices |

*TaxRateDetails*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| label | String | Tax rate label |
| name | String | Tax rate name |
| basePriceWithoutVat | BigDecimal | Sellable price with without vat |
| vat | BigDecimal | Vat percentage |
| vatAmount | BigDecimal | Vat amount |

\[  
  {  
    "invoiceHeader": {  
      "invoiceType": "FISCAL",  
      "invoiceCorrectionReason": "Reklamacija",  
      "merchantDetails": {  
        "name": "Maki doo",  
        "businessSpaceCode": "",  
        "taxIdentificationNumber": "111606273",  
        "identificationNumber": "20862467",  
        "taxAdministrationId": "854/1.0",  
        "cashier": "",  
        "contactDetails": {  
          "phone": "+381669879009",  
          "email": "merchant@gmail.com",  
        },  
        "address": {  
          "street": "ZAOB.PUT ZVORNIK-LJUBOVIJA 23",  
          "city": "LJUBOVIJA",  
        },  
      },  
      "customerDetails": {  
        "firstName": "Pera",  
        "lastName": "Segedinac",  
        "address": {  
          "street": "PERE SEGEDINCA 55",  
          "city": "BEOGRAD-NOVI BEOGRAD",  
          "postcode": "12345"  
        },  
      },  
      "fiscalDetails": {  
        "invoiceCounter": "1757/3189ПП",  
        "verificationUrl":  
          "https://sandbox.suf.purs.gov.rs/v/?vl=A1JFUkNaS002RHQxT3YxbzB1DAAA3QYAAECdCBAAAAAAAAABgItDMLkAAAB228TA9h5b1hyhwICcG8JmBts0IPX9Qu5OlntWAktzDqQzNskEfv0ENM8l19irPzzwNaeBYFrkym%2BgBW5rnoMT3T3WSoKAWdhR6%2BPoUeDB4vftjCxC6pnpYOyJfecyUAangAl6mdZcsrF472oguahaoHXc8ENXhX1RMSU7qP2gxAL%2FbRY7Jq8PpPxqbN3AcnoVPoLVH%2B85r2jyyoKItPA7fMjMb3gHf%2BH66cP0pegse0P93aX%2BH3SAASSjXwNYQROiL1frth5XRAn4crwNiwWFHECnSy00oZySi47sFS8Fm2j0ZGZafRP8eB1cIv2elgrXVAkqzd8WKC%2FB9jW7L41uFI05BzUcvqQYQEB5ryr79ppa2bxJOR86D4iBikNQpVl2OF8oo28DuzPlsZbT9xmCK%2F17n%2FR5w3RvPJHo4%2FJJDuG4rdY3YVf%2BQQJHeFRt2KEukQcKhZgvuF7N7Ggd%2Fmyk09ojp%2F7oZt3j3yhTg8UiD1XJcz51jNVj50V%2FlXWYxct%2BOTMs7d3xwt8u2RMo4nS7JUzycPyu5TExeDZTZZ7i2e6xfD2qnNJH3OoNLuaes3To9864xszHq8roHOMZU%2FlUKgyQSBSS%2B9EftlmVzzPjHhAmAM%2BWneSyD3oG2Ft6aZ0LMy6JmjKnmqmKpTqmkxSYGL1WJnQ0KwBuknT30%2BrVeLExl7q3HStApjRCqce5mXzL5tiyxMJiXjY2sOIPEE5GZT6He6L2Vnva1Rs5ziXyjP9Ifkl%2Bj6dkkLtYHQ9S1jMN5qoJgDtNQlVxWVFGZZALSKlKUsi4YyUffC9GyQ4V1jaf3U9zDj6cS%2BpTmCAB5yhCz3cLu5Wsdw7yrZwMZIRR6FKJ3mDZ42%2BqxNAuMoVZ5XwRA5Lb9TnAi83%2F36EMrM2N%2Fhkyt1VfZc7ajoPiSPjVAqeIp2P%2FoPeKPl6bWZSoUrbOVQGDBmnpT%2FFCzQMI74veLOEohrvrn8mthiG68xYGzvao82ZQBOrPYMe7LMxtT44GZGGa8RojmlDmQ%2FY0jtKspRjDfWE7xh8rIvrNtmQl",  
        "fiscalInvoiceDate": "2022-05-03T18:51:18Z",  
        "referentDocumentNumber": "RERCZKM6-Dt10v1o)-3189",  
      },  
      "orderDetails": {  
        "invoiceNumber": "4599335419",  
        "invoicedDate": "2022-05-03T00:00:00Z",  
        "orderId": "69N73-9E0P9",  
        "suborderId": "69N73-9E0P9-DS-1",  
        "fba": false,  
        "paymentMethods": "\[COD, VOUCHER\]",  
        "warehouseAddress": {  
          "warehouseId": 3,  
          "street": "DRINSKA 23",  
          "city": "LJUBOVIJA",  
        },  
      },  
    },  
    "productSpecification": {  
      "items": \[  
        {  
          "productDetails": {  
            "name": "TESLA Televizor 40T319SFS SMART",  
            "apId": "VT8YI1VRS7",  
            "sku": "AA-123456-AB",  
            "ean": "8606018852161",  
            "acode": "test123",  
          },  
          "quantity": 1,  
          "itemPrice": {  
            "unitPrice": 27900,  
            "unitPriceWithoutVat": 23250,  
            "basePrice": 26900,  
            "basePriceWithoutVat": 22416.67,  
            "discountVat": 3.59,  
            "discountAmount": 1000,  
            "vat": 20,  
            "vatAmount": 4483.33,  
          },  
          "grandTotalPrice": {  
            "unitPrice": 27900,  
            "unitPriceWithoutVat": 23250,  
            "basePrice": 26900,  
            "basePriceWithoutVat": 22416.67,  
            "discountVat": 3.59,  
            "discountAmount": 1000,  
            "vat": 20,  
            "vatAmount": 4650,  
          },  
        },  
      \],        
      "totalDetails": {  
        "basePrice": 26900,  
        "basePriceWithoutVat": 22416.67,  
        "vatAmount": 4483.33,  
        "chargedPrice": 26900,  
      },  
    },  
    "taxRateSpecification": {  
      "taxRateSpecification": \[  
        {  
          "label": "Ж",  
          "name": "О-ПДВ",  
          "basePriceWithoutVat": 22416.67,  
          "vat": 20,  
          "vatAmount": 4483.33,  
        },  
      \],  
      "grandTotal": 4483.33,  
    },  
  },

\];

# Get merchant inventory prices

Endpoint for fetching prices for given merchant inventory IDs and date time. This is a protected route, which means that authorization token with PUBLIC API full permissions is needed to access this route. Beside “Authorization” header, we must forward twomore parameters –dateFrom and merchantInventoryIds.

## Request

*Request:*

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.svc.qa2.ananastest.com/payment/api/v1/merchant-integration/prices* |
|  | Production | *https://api.ananas.rs/payment/api/v1/merchant-integration/prices* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description dateFrom Present or future date for which price should be returned merchantInventoryIds List of merchant inventory IDs for which price should be returned with a limit of max 100 IDs  |

*Response Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| merchantInventoryId | Long | Merchant inventory ID |
| basePrice | BigDecimal | Merchant inventory price without discount on given date |
| sellablePrice | BigDecimal | Merchant inventory price with discount on given date |
| discountId | String | End date of discount |

*Example of request:*

url\--location\--requestGET'https://api.svc.qa2.ananastest.com/payment/api/v1/merchant-integration/prices?dateFrom=20/07/2022\&merchantInventoryIds=1776331, 1776332'\\\--header'Authorization: Bearer eyJraWQiOiJvY1ZZbHpcL3E1RDlNZnh4TGtHODZFYzVFQmp5WkN3MjMxaUt1a0h2Y1ZMbz0iLCJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIyNnZqamMzbnBkaDBiMG1qcTJsMzFwN3MxaiIsInRva2VuX3VzZSI6ImFjY2VzcyIsInNjb3BlIjoicHVibGljX2FwaVwvZnVsbF9hY2Nlc3MiLCJhdXRoX3RpbWUiOjE2NTgzMzQ3MTYsImlzcyI6Imh0dHBzOlwvXC9jb2duaXRvLWlkcC5ldS1jZW50cmFsLTEuYW1hem9uYXdzLmNvbVwvZXUtY2VudHJhbC0xX0pvMVZnTkR2SyIsImV4cCI6MTY1ODMzNTYxNiwiaWF0IjoxNjU4MzM0NzE2LCJ2ZXJzaW9uIjoyLCJqdGkiOiIyZmMxYmU2Zi0xOGIxLTRiZjAtOTRiNS05NzM3MzcxMTY5YjUiLCJjbGllbnRfaWQiOiIyNnZqamMzbnBkaDBiMG1qcTJsMzFwN3MxaiJ9.idjWDhMehfBLyvTTDxp1ymTSApYNW972X5bdeakm5npXUgWtTu1P0Kh5PVcPvbRHRqoznCm1jpKxGWztHhtcn5QHDsnWzPdIuUZP5tczVyXz5SIMnQJJHK8CmgBhx-pRtvSZUrA7p\_r5dSK2w8MkrgVO3-a65AJKeL9wINlNEEHRUjzriPg0YHZp5fqBE7wt8PxKM4myI8TGIpTI74AzIqoHukeImKH1Sr-InT3fcnjxIqKV1B5ZUlqFuyxLYa1e4rFLN3kRKNQkh-nw1WeWexV\_X63ELLCwMKIFeeOL0U13-SDgUGRQI0dsJJPMSQOcFL4D1CP7b35VTTjS4RC\_zA'

*Example of response:*

Click to copy  
\[  
  {  
    merchantInventoryId: 1776331,  
    basePrice: 24000,  
    sellablePrice: 18000,  
    discountId: "b8a14bba-a504-46d4-a627-c8eb5eb262af",  
  },

\];

# Get all invoice URLs

GET REST API request that returns a list of all invoice URLs that are linked to the merchant. The response example is shown below. Filtering documents is done in two ways regarding parameters that can be passed.

First is directly by passing collection of suborder ids and for those suborders we are fetching invoice URLs.

The second is by the date-range filter. Along with date parameters also we can pass the non-required parameter “type. It is given with string value FISCAL or NON\_FISCAL, which will filter out invoice URLs by type. If this parameter is not provided, all invoice URLs will be returned. Non-fiscal merchants will not be able to get invoice URLs with parameter type FISCAL, response will be a bad request. On the other hand, fiscal merchants can query non-fiscal invoice URLs.

Parameters “dateFrom” and “dateTo” are zoned dates with time and are required and inclusive. They represent time range for invoiced dates. The date from is only viable from 1stof May 2022, and date range cannotbe longer than 3 months. Examples:

https://api.qa2.ananastest.com/order/api/v1/merchant-integration/invoices/urls?dateFrom=2022-05-01T10:20:45.1457765Z\&dateTo=2022-06-07T10:20:45.1457765Z\&type=FISCAL  
https://api.qa2.ananastest.com/order/api/v1/merchant-integration/invoices/urls?suborderIds=XJLV3-CBHLH-DS-1

## Request

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/invoices/urls* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/invoices/urls* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description type FISCAL, NON\_FISCAl (filters invoices by type) dateFrom Referes to invoicedDateDate must be sent with time zone dateTo Referes to invoicedDateDate must be sent with time zone suborderIds Collection of suborder ids for which we issue fetching invoice document URLs.  |

## Response

The response is a map of Invoice URLs. The key is the suborder for which we issued the document, and the value is thelist ofobjectswith the link and id of the document.

*Invoice URLs*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| documentCorrelationId | String | The documentCorrelationId is a unique identification of a document. It is referred to the invoiceId field from the document table. |
| link | String | Link to PDF document. |

Example of response:  
Click to copy  
{  
  "XJLV3-CHBLH-DS-1": \[  
    {  
      "documentCorrelationId": "0429118274",  
      "link": "https://ananas-dev1-logistics.s3.eu-central-1.amazonaws.com/order/XJLV3-CHBLH/invoice/080822\_1848\_Makidoo\_Fiskalni\_Ra%C4%8Dun\_XJLV3-CHBLH\_DS.pdf?X-Amz-Algorithm=AWS4-HMAC-SHA256\&X-Amz-Date=20220823T080849Z\&X-Amz-SignedHeaders=host\&X-Amz-Expires=600\&X-Amz-Credential=AKIA22VGE46BRDXKOUWD%2F20220823%2Feu-central-1%2Fs3%2Faws4\_request\&X-Amz-Signature=7b7f22f6993b10ac892b073c0bb84c9a17acb71d1825ee65336081decccd242f"  
    }  
  \]

}

# Get all invoice correction URLs

GET REST API request that returns a collection of all invoice correction URLs that are linked to the merchant. The response example is shown below. Filtering documents is done in two ways regarding parameters that can be passed.

First is directly by passing collection of suborder ids and for those suborders we are fetching invoice correction URLs.

The second is by the date-range filter. Along with date parameters also we can pass the non-required parameter “type”. It is given with string value FISCAL or NON\_FISCAL, which will filter out invoice correction URLs by type. If this parameter is not provided, all invoice correction URLs will be returned. Non-fiscal merchants will not be able to get invoice correction URLs with parameter type FISCAL, response will be a bad request. On the other hand, fiscal merchants can query non-fiscal invoice correction URLs.

Parameters “dateFrom” and “dateTo” are zoned dates with time and are required and inclusive. They represent time range for invoiced dates. The date from is only viable from 1stof May 2022, and date range cannot be longer than 3 months. Examples:

https://api.qa2.ananastest.com/order/api/v1/merchant-integration/invoice-corrections/urls?dateFrom=2022-05-01T10:20:45.1457765Z\&dateTo=2022-06-07T10:20:45.1457765Z\&type=FISCAL  
https://api.qa2.ananastest.com/order/api/v1/merchant-integration/invoice-corrections/urls?suborderIds=XJLV3-CBHLH-DS-1

## Request

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/invoice-corrections/urls* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/invoice-corrections/urls* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description type FISCAL, NON\_FISCAl (filters invoices by type) dateFrom Referes to invoicedDateDate must be sent with time zone dateTo Referes to invoicedDateDate must be sent with time zone suborderIds Collection of suborder ids for which we issue fetching invoice document URLs.  |

## Response

The response is a map of Invoice correction URLs. The key is the suborder for which we issued the document, and the value is the list of objects with the link and id of the document.

*Invoice correction URLs*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| documentCorrelationId | String | The documentCorrelationId is a unique identification of a document. It is referred to the invoiceId field from the document table. |
| link | String | Link to PDF document. |

Example of response:  
Click to copy  
{  
  "J6NC3-ZWNLD-DS-2": \[  
    {  
      "documentCorrelationId": "D-3272020280-1",  
      "link": "https://ananas-dev1-logistics.s3.eu-central-1.amazonaws.com/order/J6NC3-ZWNLD/invoice\_correction/Makidoo\_040822\_1407\_J6NC3-ZWNLD\_DS\_D-3272020280-1.pdf?X-Amz-Algorithm=AWS4-HMAC-SHA256\&X-Amz-Date=20220823T083722Z\&X-Amz-SignedHeaders=host\&X-Amz-Expires=600\&X-Amz-Credential=AKIA22VGE46BRDXKOUWD%2F20220823%2Feu-central-1%2Fs3%2Faws4\_request\&X-Amz-Signature=4bbacd7949e0bf91ac6ee18a8741bd77de4d4074ce952a88ac99f93412a4ea97"  
    }  
  \]

}

# Schedule discounts in bulk

Endpoint for scheduling discounts in bulk. All requests must be in JSON format. All responses are in JSON format. This is a protected route, which means that authorization token with PUBLIC API full permissions is needed to access this route.

## Request

*Request:*

| HTTP Method | POST |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/payment/api/v1/merchant- integration/discounts* |
|  | Production | *https://api.ananas.rs/payment/api/v1/merchant-integration/discounts* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

*Request Body:*

The request body represents an array of elements that have the following fields:

| Fields name | Type | Description |
| :---: | :---: | :---: |
| discounts | Array | List of discounts to be scheduled |
| merchantInventoryId | Long | Inventory ID |
| discountPrice | BigDecimal | Merchant inventory price on discount |
| discountPriceCurrency | String | Currency. Allowed values \[ "RSD" \] |
| dateFrom | Date | Discount start date. Must be in dd/MM/yyyy format. The discount will be applied immediately after a successful service call if the specified date is today; otherwise, it will be applied starting from 00:01 on the specified day. |
| dateTo | Date | Discount end date. Must be in dd/MM/yyyy format. Discounts always end at 23:59. |
| discountType | String | Allowed values \[“SALE”, “SEASONAL\_SALE”, “CLEARANCE\_SALE”\] |

Example of a JSON body request:  
{  
  "discounts": \[  
    {  
      "merchantInventoryId": 1194,  
      "discountPrice": "800",  
      "discountPriceCurrency": "RSD",  
      "dateFrom": "22/07/2022",  
      "dateTo": "29/07/2022",  
      "discountType": "SALE"  
    },  
    {  
      "merchantInventoryId": 1195,  
      "discountPrice": "12000",  
      "discountPriceCurrency": "RSD",  
      "dateFrom": "15/05/2022",  
      "dateTo": "15/06/2022",  
      "discountType": "SEASONAL\_SALE"  
    },  
    {  
      "merchantInventoryId": 1196,  
      "discountPrice": "9000",  
      "discountPriceCurrency": "RSD",  
      "dateFrom": "14/04/2022",  
      "discountType": "CLEARANCE\_SALE"  
    }  
  \]

}

## Response

Response will contain info about every discount and whether it was successfully scheduled or not. It is possible to have a partially successful response, i.e. some discounts are successfully scheduled and some are not. If the request body is invalid HTTP status code 400 will be returned. If the token is missing HTTP status code 401 will be returned. If the token is present, but does not have permissions for accessing this route, HTTP status code 403 will be returned.

*Response Body*

The response body represents an array of elements each indicating info about one discount:

| Fields name | Type | Description |
| :---: | :---: | :---: |
| scheduleResult | Array | Array of discounts |
| success | Boolean | Indicates whether the discount has been successfully scheduled or not. If the value is true, data object will be present. If the value is false, an error object will be present. |
| data | Object | Present when the discount has been successfully scheduled. Contains information about scheduled discounts. |
| error | Object | Present when the discount has not been successfully scheduled. Contains validation error that prevented discount scheduling. |
| merchantInventoryId | Long | Merchant Inventory ID |
| discountId | UUID | ID of the newly scheduled discount |
| errorMessage | String | Validation error that prevented the discount from being scheduled |

Example of the response:  
{  
  "scheduleResult": \[  
    {  
      "success": false,  
      "error": {  
        "merchantInventoryId": 1194,  
        "errorMessage": "Discount start date can not be after end date "  
      }  
    },  
    {  
      "success": false,  
      "error": {  
        "merchantInventoryId": 1195,  
        "errorMessage": " Discount interval overlaps with present discount ee7c907d-b2cc-48f8-9226-1ba6e4db1055"  
      }  
    },  
    {  
      "success": false,  
      "error": {  
        "merchantInventoryId": 1196,  
        "errorMessage": " Seasonal sale must begin between 1st July & 15th July for summer sales or 25th December & 10th January for winter sales"  
      }  
    },  
    {  
      "success": true,  
      "data": {  
        "merchantInventoryId": 1197,  
        "discountId": "ee7c907d-b2cc-48f8-9226-1ba6e4db1055"  
      }  
    }  
  \]

}

## Discount validation rules

When setting discounts there are validation rules that need to satisfiedfor the discount to be valid. In this section we’re going to cover all those rules.

There are three discount types: sale, seasonal sale and clearance sale. Sale discounts must not be longer than 31 days. Seasonal sale discount duration must not exceed 60 days and it has to start between 1stJuly & 15thJuly (for summer sale) or 25thDecember & 10thJanuary (for winter sale).For clearance sale, only start Date must be sent. Clerance sale does not have an end date, it lasts until all goods on promotion are sold out. While a product is on clearance sale, its stock can’t be changed.

Additional discount validations:

1\. The discount price can be reduced to 95% percent of the regular price.

2\. Discounts can’t overlap, I.e., two discounts can’t be active at the same time

3\. When the discount is running, it is only possible to decrease discount Price. Changing start date, end date, discount type or increasing the price is not possible.

# Update discounts in bulk

Endpoint for updating scheduling discounts in bulk. All requests must be in JSON format. All responses are in JSON format. This is a protected route, which means that the authorization token with PUBLI API full permissions is needed to access this route.

## Request

*Request:*

| HTTP Method | PUT |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/payment/api/v1/merchant-integration/discounts* |
|  | Production | *https://api.ananas.rs/payment/api/v1/merchant-integration/discounts* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

*Request Body:*

The request body represents an array of elements that have the following fields:

| Fields name | Type | Description |
| :---: | :---: | :---: |
| discounts | Array | List of discounts to be updated |
| discountId | UUID | Discount ID |
| newDiscountPrice | BigDecimal | New merchant inventory price on discount |
| newDiscountPriceCurrency | String | Currency. Allowed values \[ “RSD” \] |
| newDateFrom | Date | New discount start date. Must be in dd/MM/yyyy format. Discount always start at 00:00. Cannot be changed if discount is already active. |
| newDateTo | Date | New discount end date. Must be in dd/MM/yyyy format. Discounts always end at 23:59. |
| newDiscountType | String | Allowed values \[“SALE”, “SEASONAL\_SALE”, “CLEARANCE\_SALE”\] |

When updating already active discounts, properties discountId, merchantInventoryId, newDiscountPrice and newDiscountPriceCurrency are mandaotry. The rest must be null.

When updating pending discounts, all properties are mandatory and must be supplied. For additional validations check Discount validation rules section.

Example of a JSON body request:  
{  
  "discounts": \[  
    {  
      "discountId": "43b49da5-9f9c-4458-b755-f9cb606aea85",  
      "newDateFrom": "20/07/2022",  
      "newDateTo": "29/07/2022",  
      "newDiscountPrice": "690",  
      "newDiscountPriceCurrency": "RSD",  
      "newDiscountType": "SALE"  
    },  
    {  
      "discountId": "43b49da5-9f9c-4458-b755-f9cb606aea85",  
      "newDateFrom": "20/07/2022",  
      "newDateTo": "29/07/2022",  
      "newDiscountPrice": "690",  
      "newDiscountPriceCurrency": "RSD",  
      "newDiscountType": "SEASONAL\_SALE"  
    }  
  \]

}

## Response

Response will contain info about every discount and whether it was successfully updated or not. It is possible to have a partially successful response, i.e. some discounts are successfully updated and some are not. If the request body is invalid HTTP status code 400 will be returned. If the token is missing HTTP status code 401 will be returned. If the token is present, but does not have permissions for accessing this route, HTTP status code 403 will be returned.

*Response Body:*

The response body represents an array of elements each indicating info about one discount:

| Fields name | Type | Description |
| :---: | :---: | :---: |
| updateResult | Array | Array of discounts |
| success | Boolean | Indicates whether the discount has been successfully updated or not. If the value is true, data object will be present. If the value is false, error object wil be present. |
| data | Object | Present if success is true. |
| error | Object | Present if success is false |
| discountId | UUID | Discount ID |
| errorMessage | String | Message indicating why discount update failed |

Example of the response:  
Click to copy  
{  
  "updateResult": \[  
    {  
      "success": false,  
      "error": {  
        "discountId": "43b49da5-9f9c-4458-b755-f9cb606aea85",  
        "errorMessage": "Active discounts can only be updated to have lower price than before the update"  
      }  
    },  
    {  
      "success": false,  
      "error": {  
        "discountId": "43b49da5-9f9c-4458-b755-f9cb606aea85",  
        "errorMessage": "Discounts that are ended can not be updated"  
      }  
    },  
    {  
      "success": true,  
      "data": {  
        "discountId": "43b49da5-9f9c-4458-b755-f9cb606aea85"  
      }  
    }  
  \]

}

# Get discount prices

Endpoint for fetching discount prices for given interval of time. This is a protected route, which means that authorization token with PUBLIC API full permissions is needed to access this route. Beside “Authorization” header, we must forward two more parameters –dateFrom and dateTo.

## Request

*Request:*

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/payment/api/v1/merchant-integration/discounts* |
|  | Production | *https://api.ananas.rs/payment/api/v1/merchant-integration/discounts* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description dateFrom Start date od interval dateTo End date od interval  |

*Response Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| discounts | UUID | ID of discount action |
| merchantInventoryId | Long | Assigned merchant inventory ID |
| discountPrice | BigDecimal | Discount Price |
| dateFrom | LocalDateTime | Start date of discount |
| dataTo | LocalDateTime | End date of discount |

Example of response:  
\[  
  {  
    "discountId": " fbdd6fd0-81cf-42da-958a-d3cff1860144",  
    "merchantInventoryId": 1770369,  
    "discountPrice": 3000,  
    "dateFrom": "2022-01-26T00:00:00",  
    "dateTo": "2022-01-28T00:00:00"  
  }

\]

# Cancel discount prices

Endpoint for canceling discount prices for given action id. This is a protected route, which means that authorization token with PUBLIC API full permissions is needed to access this route. Beside “Authorization” header, we must forward action parameter.

## Request

*Request:*

| HTTP Method | PUT |  |
| :---: | :---- | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/payment/api/v1/merchant- integration/discounts/discountId/cancellations* |
|  | Production | *https://api.ananas.rs/payment/api/v1/merchant- integration/discounts/discountId/cancellations* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| Path Parameters |  |  Parameter name Parameter description discountId Discount action ID  |

# Get order statuses

GET REST API request that returns list of all order statuses.

## Request

*Request:*

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/statuses* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/statuses* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

## Response

Response is a collection of order statuses. Response is a list of String values.

Example of response:  
  \[  
        "CREATED",  
        "PENDING",  
        "NOT\_READY",  
        "CANCELLED",  
        "MISSING\_ITEMS",  
        "CANCELLED\_BY\_JOB",  
        "CANCELLED\_BY\_CUSTOMER",  
        "CANCELLED\_BY\_MERCHANT",  
        "CANCELLED\_BY\_EMPLOYEE",  
        "PARTIALLY\_CONFIRMED",  
        "CONFIRMED",  
        "PACKING\_INITIATED",  
        "PARTIALLY\_PACKED",  
        "PACKED",  
        "READY\_FOR\_COLLECTION",  
        "TERMINATED",  
        "COLLECTED",  
        "ON\_DELIVERY",  
        "ATTEMPTED\_DELIVERY",  
        "REJECTED\_DELIVERY",  
        "CUSTOMER\_ADDRESS\_INVALID",  
        "RETURN\_TO\_SELLER",  
        "RETURN\_TO\_SELLER\_UNVERIFIED",  
        "RETURN\_TO\_SELLER\_CANCELLED",  
        "RETURNED\_TO\_SELLER",  
        "REDIRECTED\_DELIVERY",  
        "DELAYED\_DELIVERY",  
        "DAMAGED",  
        "DAMAGED\_UNVERIFIED",  
        "DAMAGED\_CANCELLED",  
        "LOST",  
        "LOST\_UNVERIFIED",  
        "LOST\_CANCELLED",  
        "DELIVERED",  
        "COLLECTED\_PUDO",  
        "ON\_DELIVERY\_PUDO",  
        "DELIVERED\_BY\_CARRIER\_PUDO",  
        "DELIVERED\_PUDO",  
        "ATTEMPTED\_DELIVERY\_PUDO",  
        "REJECTED\_DELIVERY\_PUDO",  
        "ADDRESS\_INVALID\_PUDO",  
        "DAMAGED\_AT\_PUDO",  
        "DAMAGED\_AT\_PUDO\_UNVERIFIED",  
        "DAMAGED\_AT\_PUDO\_CANCELLED",  
        "LOST\_AT\_PUDO",  
        "LOST\_AT\_PUDO\_UNVERIFIED",  
        "LOST\_AT\_PUDO\_CANCELLED",  
        "COMPLETED\_PUDO",  
        "COLLECTED\_LOCKER",  
        "ON\_DELIVERY\_LOCKER",  
        "DELIVERED\_LOCKER",  
        "COMPLETED\_LOCKER"

   \]

# Get order statuses by groups

GET REST API request that returns list of all order statuses by groups. Response example is shown below.

## Request

*Request:*

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/status-groups* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/status-groups* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

## Response

Response is a collection of order statuses by groups.

*Response Body:*

| Fields name | Type | Description |
| :---: | :---: | :---: |
| group | String | Suborder status group (the highest level of grouping). Values: SG\_ON\_HOLD, SG\_PROCESSING, SG\_ON\_DELIVERY, SG\_COMPLETED |
| subGroups | List\<SubgroupStatuses\> | List of objects which contain suborder subgroup statuses and suborder internal statuses which belong to them |

*GroupStatuses:*

| Fields name | Type | Description |
| :---- | :---- | :---- |
| subGroup | String | Suborder status subgroup (the middle level of grouping). Values: SSG\_CREATED, SSG\_PARTIALLY\_CONFIRMED, SSG\_CONFIRMED, SSG\_PARTIALLY\_PACKED, SSG\_PACKED, SSG\_TRANSPORT\_INITIATED, SSG\_ON\_DELIVERY, SSG\_DELIVERED, SSG\_CANCELLED, SSG\_NOT\_DELIVERED |
| statuses | List\<String\> | List which contains suborder internal statuses. Values: CREATED, PENDING, NOT\_READY, CANCELLED, MISSING\_ITEMS, CANCELLED\_BY\_JOB, CANCELLED\_BY\_CUSTOMER, CANCELLED\_BY\_MERCHANT, CANCELLED\_BY\_EMPLOYEE, PARTIALLY\_CONFIRMED, CONFIRMED, PACKING\_INITIATED, PARTIALLY\_PACKED, PACKED, READY\_FOR\_COLLECTION, TERMINATED, COLLECTED, ON\_DELIVERY, ATTEMPTED\_DELIVERY, REJECTED\_DELIVERY, CUSTOMER\_ADDRESS\_INVALID, RETURN\_TO\_SELLER, RETURN\_TO\_SELLER\_UNVERIFIED, RETURN\_TO\_SELLER\_CANCELLED, RETURNED\_TO\_SELLER, REDIRECTED\_DELIVERY, DELAYED\_DELIVERY, DAMAGED, DAMAGED\_UNVERIFIED, DAMAGED\_CANCELLED, LOST, LOST\_UNVERIFIED, LOST\_CANCELLED, DELIVERED, COLLECTED\_PUDO, ON\_DELIVERY\_PUDO, DELIVERED\_BY\_CARRIER\_PUDO, DELIVERED\_PUDO, ATTEMPTED\_DELIVERY\_PUDO, REJECTED\_DELIVERY\_PUDO, ADDRESS\_INVALID\_PUDO, DAMAGED\_AT\_PUDO, DAMAGED\_AT\_PUDO\_UNVERIFIED, DAMAGED\_AT\_PUDO\_CANCELLED, LOST\_AT\_PUDO, LOST\_AT\_PUDO\_UNVERIFIED, LOST\_AT\_PUDO\_CANCELLED, COMPLETED\_PUDO, COLLECTED\_LOCKER, ON\_DELIVERY\_LOCKER, DELIVERED\_LOCKER, COMPLETED\_LOCKER |

Example of response:  
Click to copy  
  \[  
   {  
      "group":"SG\_ON\_HOLD",  
      "subGroups":\[  
         {  
            "subGroup":"SSG\_CREATED",  
            "statuses":\[  
               "CREATED"  
            \]  
         }  
      \]  
   },  
   {  
      "group":"SG\_PROCESSING",  
      "subGroups":\[  
         {  
            "subGroup":"SSG\_PARTIALLY\_CONFIRMED",  
            "statuses":\[  
               "PARTIALLY\_CONFIRMED",  
               "PENDING"  
            \]  
         },  
         {  
            "subGroup":"SSG\_CONFIRMED",  
            "statuses":\[  
               "CONFIRMED",  
               "PACKING\_INITIATED"  
            \]  
         },  
         {  
            "subGroup":"SSG\_PARTIALLY\_PACKED",  
            "statuses":\[  
               "PARTIALLY\_PACKED"  
            \]  
         },  
         {  
            "subGroup":"SSG\_PACKED",  
            "statuses":\[  
               "PACKED"  
            \]  
         },  
         {  
            "subGroup":"SSG\_TRANSPORT\_INITIATED",  
            "statuses":\[  
               "READY\_FOR\_COLLECTION"  
            \]  
         }  
      \]  
   },  
   {  
      "group":"SG\_ON\_DELIVERY",  
      "subGroups":\[  
         {  
            "subGroup":"SSG\_ON\_DELIVERY",  
            "statuses":\[  
               "COLLECTED",  
               "ON\_DELIVERY",  
               "ATTEMPTED\_DELIVERY",  
               "REDIRECTED\_DELIVERY",  
               "DELAYED\_DELIVERY",  
               "COLLECTED\_PUDO",  
               "ON\_DELIVERY\_PUDO",  
               "DELIVERED\_PUDO",  
               "DELIVERED\_BY\_CARRIER\_PUDO",  
               "COLLECTED\_LOCKER",  
               "ON\_DELIVERY\_LOCKER",  
               "DELIVERED\_LOCKER"  
            \]  
         }  
      \]  
   },  
   {  
      "group":"SG\_COMPLETED",  
      "subGroups":\[  
         {  
            "subGroup":"SSG\_DELIVERED",  
            "statuses":\[  
               "DELIVERED",  
               "COMPLETED\_PUDO",  
               "COMPLETED\_LOCKER"  
            \]  
         },  
         {  
            "subGroup":"SSG\_CANCELLED",  
            "statuses":\[  
               "CANCELLED",  
               "CANCELLED\_BY\_JOB",  
               "CANCELLED\_BY\_CUSTOMER",  
               "CANCELLED\_BY\_MERCHANT",  
               "CANCELLED\_BY\_EMPLOYEE",  
               "TERMINATED",  
               "MISSING\_ITEMS"  
            \]  
         },  
         {  
            "subGroup":"SSG\_NOT\_DELIVERED",  
            "statuses":\[  
               "REJECTED\_DELIVERY",  
               "CUSTOMER\_ADDRESS\_INVALID",  
               "RETURN\_TO\_SELLER",  
               "RETURN\_TO\_SELLER\_CANCELLED",  
               "RETURN\_TO\_SELLER\_UNVERIFIED",  
               "RETURNED\_TO\_SELLER",  
               "DAMAGED",  
               "DAMAGED\_CANCELLED",  
               "DAMAGED\_UNVERIFIED",  
               "LOST",  
               "LOST\_CANCELLED",  
               "LOST\_UNVERIFIED",  
               "ATTEMPTED\_DELIVERY\_PUDO",  
               "REJECTED\_DELIVERY\_PUDO",  
               "ADDRESS\_INVALID\_PUDO",  
               "DAMAGED\_AT\_PUDO",  
               "DAMAGED\_AT\_PUDO\_CANCELLED",  
               "DAMAGED\_AT\_PUDO\_UNVERIFIED",  
               "LOST\_AT\_PUDO",  
               "LOST\_AT\_PUDO\_CANCELLED",  
               "LOST\_AT\_PUDO\_UNVERIFIED"  
            \]  
         }  
      \]  
   }

\]

# Get all Orders

GET REST API request that returns a list of all current orders linked to a merchant. The response example is shown below.

The parameter orderId will try to find an order by orderId and return it in the response list. The filter parameter statusGroup will filter orders by order status group. Two possible values for this filter are SG\_FOR\_CONFIRMATION (return all orders that need to be confirmed) and SG\_PROCESSED (return all orders that are processed). Both of these parameters are optional.

The other two parameters are page and size, which are used for pagination. If not passed, by default, only 10 orders will be listed. However, by setting the size to higher values, the list can be increased. The maximum number of orders per page is 100\.

## Request

| HTTP Method | GET |  |
| :---: | :---: | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/orders* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/orders* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description orderId Find order by orderId and return it in the response list statusGroup Filter orders by order status group (SG\_FOR\_CONFIRMATION or SG\_PROCESSED) page Pagination \- page number size Pagination \- number of orders per page (max 100\) dateFrom Order created date from filter in yyyy-MM-dd'T'HH:mm:ss.SSSXXX format (e.g., 2023-11-15T00:00:00Z) dateTo Order created date to filter in yyyy-MM-dd'T'HH:mm:ss.SSSXXX format (e.g., 2023-11-16T00:00:00Z)  |

### Response

Response is a collection of order details.

The API method defaults sorting by “orderCreatedDate” in descending order.

| Field Name | Type | Description |
| :---: | :---: | :---: |
| content | List\<OrderDetails\> | List of orders with details |
| pageable | Pageable | Details of pagination |
| totalPages | int | Number of total pages |
| totalElements | long | Total amount of elements |
| last | boolean | Indicates if it's the last page |
| size | Integer | Size |
| number | int | Number of items on that page |
| sort | Sort | Details about sorting |
| numberOfElements | long | Number of elements |
| first | boolean | First |
| empty | boolean | Empty |

### Pageable

| Field Name | Type | Description |
| :---: | :---: | :---: |
| sort | Sort | Details about sorting |
| offset | Long | Offset |
| pageSize | int | Page size |
| pageNumber | int | Page number |
| paged | boolean | Paged |
| unpaged | boolean | Unpaged |

### Sort:

| Field Name | Type | Description |
| :---: | :---: | :---: |
| empty | boolean | Empty |
| unsorted | boolean | Unsorted |
| sorted | boolean | Sorted |

### OrderDetails

| Field Name | Type | Description |
| :---: | :---: | ----- |
| id | String | Unique order identifier |
| createdDate | LocalDateTime | Date of creation in UTC |
| totalPrice | BigDecimal | Total price of an order |
| currency | String | Price currency |
| paymentMethod | List\<String\> | List of order payment methods. Possible values: COD \- cash on delivery PBC \- pay by card POS \- pay on site VOUCHER \- paid by voucher |
| numberOfItems | Long | Number of items that belong to the order |
| sumOfItemOrderedQuantities | Long | Sum of all quantities of all items that are belonging to an order |
| items | List\<ItemDetails\> | List of items |
| billingAddress | AddressDto | Billing address for this order, nullable |

### ItemDetails

| Field Name | Type | Description |
| ----- | ----- | ----- |
| id | String | Unique item identifier |
| suborderWarehouseAddressId | Long | Unique warehouse address identifier |
| quantity | Integer | Item ordered quantity |
| confirmedQuantity | Integer | Item confirmed quantity |
| packedQuantity | Integer | Item packed quantity |
| vat | BigDecimal | Vat percentage |
| basePriceBeforeDeductionsWithoutVat | BigDecimal | Sellable price without discount and without vat |
| basePriceBeforeDeductions | BigDecimal | Sellable price without discount and with vat |
| basePriceWithoutVat | BigDecimal | Sellable price with discount and without vat |
| basePrice | BigDecimal | Sellable price with discount and with vat included |
| grandTotalWithoutVat | BigDecimal | Sum of item sellable price without vat |
| grandTotal | BigDecimal | Sum of items sellable price without vat |
| takeRate | Integer | Take rate |
| takeRateBasePrice | BigDecimal | Take rate base price |
| takeRateTotal | BigDecimal | Take rate total price |
| shippingCost | BigDecimal | Price of shipping cost with vat and discount included |
| shippingCostWithoutVat | BigDecimal | Price of shipping cost with discount and without vat |
| shippingCostVat | BigDecimal | Shipping cost vat percentage |
| shippingCostBasePrice | BigDecimal | Price of shipping cost without discount and with vat included |
| shippingCostBasePriceWithoutVat | BigDecimal | Price of shipping cost without discount and vat |
| shippingCostAdjustment | BigDecimal | Shipping cost adjustment value |
| chargedPrice | BigDecimal | Price charged to the customer |
| chargedPriceWithoutVat | BigDecimal | Price charged to the customer without vat |
| paymentData | List\<PaymentDetails\> | List of payment details |
| merchant | MerchantDetails | Merchant details |
| productId | String | Unique product identifier |
| productEan | String | European Article Number |
| productACode | String | Ananas code of product |
| productApid | String | Ananas product identifier |
| assetRootUrl | String | Root URL |
| thumbnailBaseUrl | String | Thumbnail base URL |
| productSku | String | Stock Keeping Unit of product |
| lux | boolean | Indicator to show whether the item is lux or not |
| productName | String | Product name – this field will be populated based on the language passed in the Accept-Language header. |
| image | String | JSON that contains data related to product image \- this field will be populated based on the language passed in the Accept-Language header. |
| specificationsTranslations | String | Attributes of the item like color, size, material type, weight, etc. \- this field will be populated based on the language passed in the Accept-Language header. |
| translations | List\<TranslationDetails\> | List of translation details |
| shippingEligibleQuantity | Integer | Quantity eligible for shipping |
| grandTotalBasePriceBeforeDeductions | BigDecimal | Total price without discount |

### PaymentDetails

| Field Name | Type | Description |
| ----- | ----- | ----- |
| paymentMethod | String | Possible values: COD \- cash on delivery PBC \- pay by card POS \- pay on site VOUCHER \- paid by voucher |
| paidAmount | BigDecimal | Paid amount |
| paidAmountAdjustment | BigDecimal | Paid amount adjustment |

### MerchantDetails

| Field Name | Type | Description |
| ----- | ----- | ----- |
| id | String | Unique merchant identifier |
| name | String | Merchant name |
| code | String | Merchant code |

### TranslationDetails

| Field Name | Type | Description |
| ----- | ----- | ----- |
| name | String | Product name |
| coverImage | String | JSON that contains data related to product image |
| specification | String | Attributes of item like color, size, material type, weight, etc. |
| language | String | Language into which the above-mentioned fields have been translated |

#### Example of request:

### JSON:

Click to copy  
{  
  "content": \[  
    {  
      "id": "HPGUJ-1SQQI",  
      "createdDate": "2023-05-26T12:38:47.234741Z",  
      "totalPrice": 45.0,  
      "currency": "RSD",  
      "paymentMethods": \[  
        "PBC",  
        "VOUCHER"  
      \],  
      "numberOfItems": 1,  
      "sumOfItemOrderedQuantities": 1,  
      "billingAddress": {  
        "type": "BILLING",  
        "firstName": "John",  
        "lastName": "Doe",  
        "city": "New York",  
        "postcode": "10512",  
        "streetName": "Woodland Hills Rd",  
        "streetNumber": "12",  
        "apartmentNumber": "21b",  
        "additionalInfo": "Elevator is not working, take the stairs",  
        "buyerType": "TIN",  
        "buyerId": "113095370",  
        "buyerName": "SpaceX"  
      },  
      "items": \[  
        {  
          "id": "1776591",  
          "suborderWarehouseAddressId": 987,  
          "quantity": 1,  
          "confirmedQuantity": null,  
          "packedQuantity": null,  
          "vat": 20.0,  
          "basePriceBeforeDeductionsWithoutVat": 41.67,  
          "basePriceBeforeDeductions": 50.0,  
          "basePriceWithoutVat": 37.5,  
          "basePrice": 45.0,  
          "grandTotalWithoutVat": 37.5,  
          "grandTotal": 45.0,  
          "takeRate": 10,  
          "takeRateBasePrice": 4.5,  
          "takeRateTotal": 4.5,  
          "shippingCost": 249.0,  
          "shippingCostWithoutVat": 207.5,  
          "shippingCostVat": 20.0,  
          "shippingCostBasePrice": 249.0,  
          "shippingCostBasePriceWithoutVat": 207.5,  
          "shippingCostAdjustment": 0.0,  
          "chargedPrice": 45.0,  
          "chargedPriceWithoutVat": 37.5,  
          "paymentData": \[  
            {  
              "paymentMethod": "VOUCHER",  
              "paidAmount": 45.0,  
              "paidAmountAdjustment": 0.0  
            }  
          \],  
          "merchant": {  
            "id": 309,  
            "name": "Maxi Taxi",  
            "code": "XN8ACP301C"  
          },  
          "productId": "5715daab-f0de-4555-929d-dcd6f56a1c19",  
          "productEan": "584763589715",  
          "productACode": null,  
          "productApid": "5P4RKOJYKU",  
          "assetRootUrl": "https://static.dev.ananastest.com",  
          "thumbnailBaseUrl": "https://static.dev.ananastest.com",  
          "productSku": "VOJASKU",  
          "lux": false,  
          "productName": "Inter Olovka",  
          "image": "{\\"key\\":\\"CoverImage\\",\\"trait\\":\\"Glavna fotografija\\",\\"type\\":\\"ImageAttribute\\",\\"image\\":{\\"path\\":\\"/assets/Product\_Images/BallpointPens/inter\_olovka/8271b1731dfc9318.jpeg\\",\\"width\\":700,\\"height\\":525},\\"thumbnail\\":{\\"path\\":\\"/tmp/image-thumbnails/Product\_Images/BallpointPens/inter\_olovka/image-thumb\_\_85840\_\_product\_thumbnail/8271b1731dfc9318.jpeg\\",\\"width\\":240,\\"height\\":180}}",  
          "specifications": "{\\"attributes\\": \[{\\"key\\": \\"features\_14764\\", \\"type\\": \\"CompositeAttribute\\", \\"trait\\": \\"Karakteristike\\", \\"attributes\\": \[{\\"hex\\": \\"\#000000\\", \\"key\\": \\"Color\\", \\"name\\": \\"Crna\\", \\"type\\": \\"ColorAttribute\\", \\"trait\\": \\"Boja\\", \\"multicolor\\": false}\]}\]}",  
          "translations": \[  
            {  
              "name": "Inter Olovka",  
              "coverImage": "{\\"key\\":\\"CoverImage\\",\\"trait\\":\\"Glavna fotografija\\",\\"type\\":\\"ImageAttribute\\",\\"image\\":{\\"path\\":\\"/assets/Product\_Images/BallpointPens/inter\_olovka/8271b1731dfc9318.jpeg\\",\\"width\\":700,\\"height\\":525},\\"thumbnail\\":{\\"path\\":\\"/tmp/image-thumbnails/Product\_Images/BallpointPens/inter\_olovka/image-thumb\_\_85840\_\_product\_thumbnail/8271b1731dfc9318.jpeg\\",\\"width\\":240,\\"height\\":180}}",  
              "specification": "{\\"attributes\\": \[{\\"key\\": \\"features\_14764\\", \\"type\\": \\"CompositeAttribute\\", \\"trait\\": \\"Karakteristike\\", \\"attributes\\": \[{\\"hex\\": \\"\#000000\\", \\"key\\": \\"Color\\", \\"name\\": \\"Black\\", \\"type\\": \\"ColorAttribute\\", \\"trait\\": \\"Boja\\", \\"multicolor\\": false}\]}\]}",  
              "language": "en"  
            },  
            {  
              "name": "Inter Olovka",  
              "coverImage": "{\\"key\\":\\"CoverImage\\",\\"trait\\":\\"Glavna fotografija\\",\\"type\\":\\"ImageAttribute\\",\\"image\\":{\\"path\\":\\"/assets/Product\_Images/BallpointPens/inter\_olovka/8271b1731dfc9318.jpeg\\",\\"width\\":700,\\"height\\":525},\\"thumbnail\\":{\\"path\\":\\"/tmp/image-thumbnails/Product\_Images/BallpointPens/inter\_olovka/image-thumb\_\_85840\_\_product\_thumbnail/8271b1731dfc9318.jpeg\\",\\"width\\":240,\\"height\\":180}}",  
              "specification": "{\\"attributes\\": \[{\\"key\\": \\"features\_14764\\", \\"type\\": \\"CompositeAttribute\\", \\"trait\\": \\"Karakteristike\\", \\"attributes\\": \[{\\"hex\\": \\"\#000000\\", \\"key\\": \\"Color\\", \\"name\\": \\"Crna\\", \\"type\\": \\"ColorAttribute\\", \\"trait\\": \\"Boja\\", \\"multicolor\\": false}\]}\]}",  
              "language": "sr"  
            }  
          \],  
          "shippingEligibleQuantity": 1,  
          "grandTotalBasePriceBeforeDeductions": 50.0  
        }  
      \]  
    }  
  \],  
  "pageable": {  
    "sort": {  
      "empty": false,  
      "unsorted": false,  
      "sorted": true  
      },  
    "offset": 0,  
    "pageNumber": 0,  
    "pageSize": 1,  
    "paged": true,  
    "unpaged": false  
  },  
  "last": true,  
  "totalElements": 1,  
  "totalPages": 1,  
  "size": 10,  
  "number": 0,  
  "sort": {  
    "empty": false,  
    "unsorted": false,  
    "sorted": true  
   },  
  "first": true,  
  "numberOfElements": 1,  
  "empty": false

  }

# Confirm outbound Master Order quantities

PUT REST API request for merchants, requiring a JSON body and an authorization token, to confirm master order quantities for each item. For each item, it is necessary to pass the warehouseAddressId.

### Request

| HTTP Method | PUT |  |
| :---: | :---- | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/orders/{orderId}/confirm* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/orders/{orderId}/confirm* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

### Example of the request:

\[  
  {  
    "merchantInventoryId": 1,  
    "confirmedQuantities": \[  
      {  
        "warehouseAddressId": 1,  
        "quantity": 4,  
      },  
      {  
        "warehouseAddressId": 2,  
        "quantity": 3,  
      },  
    \],  
  },  
  {  
    "merchantInventoryId": 2,  
    "confirmedQuantities": \[  
      {  
        "warehouseAddressId": 3,  
        "quantity": 2,  
      },  
      {  
        "warehouseAddressId": 4,  
        "quantity": 1,  
      },  
    \],  
  },

\];

### Response

The endpoint will return status 201 \- Created.

# Get carrier billing suborders

GET REST API endpoint intended for carrier-biller-integration. This API allows carrier billers to retrieve all suborders associated with the merchant’s carrier\_biller configuration, including financial and shipping cost details required for billing.

The endpoint is structurally similar to/api/v1/merchant-integration/outbound-orders, but exposes a simplified and purpose-specific payload. Only essential information relevant to carrier billing is returned.

Authentication uses the ClientCredentials OAuth flow. From the provided access token, the system resolves merchant\_id. Using merchant\_id, the merchant's PIB is retrieved and used to determine the associated carrier\_biller. Returned data is limited to suborders belonging to that carrier\_biller.

This API supports several filtering options via query parameters. BothdeliveredDateFrom and deliveredDateTo are required parameters. Suborders are returned only if they satisfy all filters.

### Request

| HTTP Method | GET |  |
| :---: | :---- | :---- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/carrier-biller-integration/outbound-orders* |
|  | Production | *https://api.ananas.rs/order/api/v1/carrier-biller-integration/outbound-orders* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| Query Parameters |  |  Parameter name Description createdDateFrom Optional. Filters by created date (from) createdDateTo Optional. Filters by created date (to) deliveredDateFrom Required. Delivered date (from) deliveredDateTo Required. Delivered date (to) suborderIds Optional. Filter by multiple suborder IDs orderId Optional. Filter by order ID size Optional. Must be ≥ 0 and ≤ 500  |

### Validation Rules

* deliveredDateFrom and deliveredDateTo must not be null.  
* deliveredDateTo must not be before deliveredDateFrom.  
* Date range must not exceed configured max, that is 3 months.  
* If createdDateFrom and createdDateTo are provided, same validations apply as for deliveredDate.  
* size must be ≥ 1 and ≤ 500\.  
* Carrier\_biller TIN must match merchant PIB.

### Response

Response returns all suborders associated with the resolved carrier\_biller, including shipping and fee details required for billing reports.

#### Fields

| Field name | Type | Description |
| :---- | :---- | :---- |
| suborderId | String | User-friendly suborder ID |
| orderId | String | User-friendly order ID |
| carrierName | String | Carrier billing provider name |
| createdDate | LocalDateTime | Created date |
| deliveredDate | LocalDateTime | Delivered date |
| shippingInvoiceId | String | Invoice ID for shipping billing |
| isLegalEntityBuyer | Boolean | Is buyer a legal entity |
| legalEntityBuyer | Object | Buyer legal entity info |
| shippingCostDetails | Object | Shipping cost breakdown |
| suborderFeeDetails | List | List of fee components |

### Example Request

GET https://api.qa2.ananastest.com/order/api/v1/carrier-biller-integration/outbound-orders?deliveredDateFrom=2025-04-02T00:10:00.0000000Z\&deliveredDateTo=2025-06-08T10:10:00.0000000Z Authorization: Bearer ACCESS\_TOKEN

### Example Response

\[  
  {  
    "suborderId": "L9HPA-LWX7G-DS-1",  
    "orderId": "L9HPA-LWX7G",  
    "carrierName": "Ananas Express MK",  
    "createdDate": "2025-10-02T10:51:48.101273Z",  
    "deliveredDate": "2025-10-02T10:54:28.591448Z",  
    "shippingInvoiceId": "25260227412",  
    "isLegalEntityBuyer": false,  
    "legalEntityBuyer": {  
      "buyerTin": null,  
      "buyerName": null  
    },  
    "shippingCostDetails": {  
      "shippingCost": 700.0,  
      "shippingCostWithoutVat": 593.22,  
      "shippingCostVat": 18,  
      "shippingCostBasePrice": 700.0,  
      "shippingCostBasePriceWithoutVat": 593.22  
    },  
    "suborderFeeDetails": \[\]  
  }  
\]

# Get Carrier Biller Fiscal Invoices

GET REST API endpoint for retrieving fiscalized sale invoices for delivery services, intended for carrier-biller-integration. Invoices are linked to the CarrierBiller entity via the Shipment \<-\> Carrier \<-\> CarrierBiller relationship.

Authentication is performed using a Bearer Token (JWT) obtained via the ClientCredentials flow with scope carrierbiller\_public\_api/full\_access. All timestamps are in UTC.

Since responses from the eFiscalization vendor (Cornerstone) may have a chronological delay relative to the actual fiscalization date in the Tax Authority, this endpoint filters by the Cornerstone response received timestamp, not the Tax Authority fiscalization date.

### Request

| HTTP Method | GET |  |
| :---: | :---- | :---- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v2/carrier-biller-integration/invoices/fiscal* |
|  | Production | *https://api.ananas.rs/order/api/v2/carrier-biller-integration/invoices/fiscal* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| Query Parameters |  |  Parameter name Type Required Description receivedEfiscalDateFrom ISO.DATE\_TIME True eFiscal data received date\_time from receivedEfiscalDateTo ISO.DATE\_TIME True eFiscal data received date\_time to  |

### Example Request

GET https://api.qa2.ananastest.com/order/api/v2/carrier-biller-integration/invoices/fiscal?receivedEfiscalDateFrom=2026-03-01T00:00:00.0000000Z\&receivedEfiscalDateTo=2026-03-01T23:59:59.0000000Z Authorization: Bearer ACCESS\_TOKEN

### Response

Returns a list of fiscalized sale invoices for delivery services associated with the resolved CarrierBiller.

#### Fields

| Field name | Type | Description |
| :---- | :---- | :---- |
| shipmentDetails.orderId | String | Order ID |
| shipmentDetails.suborderId | String | Suborder ID |
| shipmentDetails.suborderType | String | Suborder type |
| shipmentDetails.createdDate | ISO DateTime | Suborder created date |
| shipmentDetails.carrierCode | String | Carrier code |
| shipmentDetails.carrierName | String | Carrier name |
| financialDetails.invoiceType | String | Always SALE for this endpoint |
| financialDetails.items | Array | Invoice line items (code, name, quantity, vat, basePrice, basePriceWithoutVat) |
| financialDetails.payment | Array | Payment details (amount, paymentType) |
| financialDetails.isLegalEntityBuyer | Boolean | Whether the buyer is a legal entity |
| financialDetails.legalEntityBuyer | Object | Legal entity buyer info (buyerTin, buyerName). Present only when isLegalEntityBuyer is true |
| fiscalInvoiceDetails.fiscalInvoiceNumber | String | Fiscal invoice number |
| fiscalInvoiceDetails.fiscalInvoiceDate | ISO DateTime | Fiscal invoice date |
| fiscalInvoiceDetails.invoiceCounter | String | Invoice counter |
| fiscalInvoiceDetails.verificationUrl | String | Tax Authority verification URL |
| fiscalInvoiceDetails.journal | String | Fiscal receipt journal text |
| fiscalInvoiceDetails.referentFiscalInvoiceNumber | String | Reference fiscal invoice number. Always null for sale invoices |
| fiscalInvoiceDetails.active | Boolean | Whether the invoice is active |

### Example Response

\[  
  {  
    "shipmentDetails": {  
      "orderId": "OQI39-EURSE",  
      "suborderId": "OQI39-EURSE-DS-1",  
      "suborderType": "DS",  
      "createdDate": "2026-03-22T21:10:17.592064Z",  
      "carrierCode": "CEX01",  
      "carrierName": "City Express"  
    },  
    "financialDetails": {  
      "invoiceType": "SALE",  
      "items": \[  
        {  
          "code": "SHIPPING\_COST",  
          "name": "DOSTAVA (pripadajuća)",  
          "quantity": 1,  
          "vat": 20,  
          "basePrice": 349.00,  
          "basePriceWithoutVat": 290.83  
        },  
        {  
          "code": "CASH\_HANDLING\_FEE",  
          "name": "Naknada za plaćanje pouzećem",  
          "quantity": 1,  
          "vat": 20,  
          "basePrice": 200.00,  
          "basePriceWithoutVat": 166.67  
        }  
      \],  
      "payment": \[  
        {  
          "amount": 549,  
          "paymentType": "COD"  
        }  
      \],  
      "isLegalEntityBuyer": true,  
      "legalEntityBuyer": {  
        "buyerTin": "111111111",  
        "buyerName": "Mile d.o.o."  
      }  
    },  
    "fiscalInvoiceDetails": {  
      "fiscalInvoiceNumber": "7LP837JA-372A5WO0-960",  
      "fiscalInvoiceDate": "2026-03-24T01:25:51Z",  
      "invoiceCounter": "851/960",  
      "verificationUrl": "https://suf.purs.gov.rs/v/....",  
      "journal": "============ ФИСКАЛНИ РАЧУН \============....",  
      "referentFiscalInvoiceNumber": null,  
      "active": true  
    }  
  }  
\]

# Get Carrier Biller Fiscal Invoice Corrections

GET REST API endpoint for retrieving fiscalized refund invoices for delivery services, intended for carrier-biller-integration. Invoice corrections are linked to the CarrierBiller entity via the Shipment \<-\> Carrier \<-\> CarrierBiller relationship.

Authentication is performed using a Bearer Token (JWT) obtained via the ClientCredentials flow with scope carrierbiller\_public\_api/full\_access. All timestamps are in UTC.

Since responses from the eFiscalization vendor (Cornerstone) may have a chronological delay relative to the actual fiscalization date in the Tax Authority, this endpoint filters by the Cornerstone response received timestamp, not the Tax Authority fiscalization date.

### Request

| HTTP Method | GET |  |
| :---: | :---- | :---- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v2/carrier-biller-integration/invoice-corrections/fiscal* |
|  | Production | *https://api.ananas.rs/order/api/v2/carrier-biller-integration/invoice-corrections/fiscal* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| Query Parameters |  |  Parameter name Type Required Description receivedEfiscalDateFrom ISO.DATE\_TIME True eFiscal data received date\_time from receivedEfiscalDateTo ISO.DATE\_TIME True eFiscal data received date\_time to  |

### Example Request

GET https://api.qa2.ananastest.com/order/api/v2/carrier-biller-integration/invoice-corrections/fiscal?receivedEfiscalDateFrom=2026-03-01T00:00:00.0000000Z\&receivedEfiscalDateTo=2026-03-01T23:59:59.0000000Z Authorization: Bearer ACCESS\_TOKEN

### Response

Returns a list of fiscalized refund invoices for delivery services associated with the resolved CarrierBiller.

#### Fields

| Field name | Type | Description |
| :---- | :---- | :---- |
| shipmentDetails.orderId | String | Order ID |
| shipmentDetails.suborderId | String | Suborder ID |
| shipmentDetails.suborderType | String | Suborder type |
| shipmentDetails.createdDate | ISO DateTime | Suborder created date |
| shipmentDetails.carrierCode | String | Carrier code |
| shipmentDetails.carrierName | String | Carrier name |
| financialDetails.invoiceType | String | Always REFUND for this endpoint |
| financialDetails.items | Array | Invoice line items (code, name, quantity, vat, basePrice, basePriceWithoutVat) |
| financialDetails.payment | Array | Payment details (amount, paymentType) |
| financialDetails.isLegalEntityBuyer | Boolean | Whether the buyer is a legal entity |
| financialDetails.legalEntityBuyer | Object | Legal entity buyer info (buyerTin, buyerName). Present only when isLegalEntityBuyer is true |
| fiscalInvoiceDetails.fiscalInvoiceNumber | String | Fiscal invoice number of this correction |
| fiscalInvoiceDetails.fiscalInvoiceDate | ISO DateTime | Fiscal invoice date of this correction |
| fiscalInvoiceDetails.invoiceCounter | String | Invoice counter |
| fiscalInvoiceDetails.verificationUrl | String | Tax Authority verification URL |
| fiscalInvoiceDetails.journal | String | Fiscal receipt journal text |
| fiscalInvoiceDetails.referentFiscalInvoiceNumber | String | Fiscal invoice number of the original sale invoice being corrected |
| fiscalInvoiceDetails.active | Boolean | Whether the invoice correction is active |

### Example Response

\[  
  {  
    "shipmentDetails": {  
      "orderId": "OQI39-EURSE",  
      "suborderId": "OQI39-EURSE-DS-1",  
      "suborderType": "DS",  
      "createdDate": "2026-03-22T21:10:17.592064Z",  
      "carrierCode": "CEX01",  
      "carrierName": "City Express"  
    },  
    "financialDetails": {  
      "invoiceType": "REFUND",  
      "items": \[  
        {  
          "code": "SHIPPING\_COST",  
          "name": "DOSTAVA (pripadajuća)",  
          "quantity": 1,  
          "vat": 20,  
          "basePrice": 349.00,  
          "basePriceWithoutVat": 290.83  
        },  
        {  
          "code": "CASH\_HANDLING\_FEE",  
          "name": "Naknada za plaćanje pouzećem",  
          "quantity": 1,  
          "vat": 20,  
          "basePrice": 200.00,  
          "basePriceWithoutVat": 166.67  
        }  
      \],  
      "payment": \[  
        {  
          "amount": 549,  
          "paymentType": "COD"  
        }  
      \],  
      "isLegalEntityBuyer": false  
    },  
    "fiscalInvoiceDetails": {  
      "fiscalInvoiceNumber": "7LP837JA-372A5WO0-961",  
      "fiscalInvoiceDate": "2026-03-25T01:25:51Z",  
      "invoiceCounter": "851/960",  
      "verificationUrl": "https://suf.purs.gov.rs/v/....",  
      "journal": "============ ФИСКАЛНИ РАЧУН \============....",  
      "referentFiscalInvoiceNumber": "7LP837JA-372A5WO0-960",  
      "active": false  
    }  
  }  
\]

# Get Carrier Biller Non Fiscal Invoices

GET REST API endpoint for retrieving financial data that will become or has already become a Confirmation of Purchase (non-fiscal invoice) for delivery services, intended for carrier-biller-integration. Invoices are linked to the CarrierBiller entity via the Shipment \<-\> Carrier \<-\> CarrierBiller relationship.

Authentication is performed using a Bearer Token (JWT) obtained via the ClientCredentials flow with scope carrierbiller\_public\_api/full\_access. All timestamps are in UTC.

Note: Carrier allocation happens when a shipment transitions to PACKED status. In general, the allocation process is fast, but delays can occur — there may be cases where a shipment is in PACKED status without an assigned carrier, in which case CarrierBiller information will not be available. It is recommended to query from READY\_FOR\_COLLECTION status onwards.

### Request

| HTTP Method | GET |  |
| :---: | :---- | :---- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v2/carrier-biller-integration/invoices/non-fiscal* |
|  | Production | *https://api.ananas.rs/order/api/v2/carrier-biller-integration/invoices/non-fiscal* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| Query Parameters |  |  Parameter name Type Required Description statuses Set\<String\> True Subset of: PARTIALLY\_PACKED, PARTIALLY\_PACKED\_IN\_TRANSIT,PARTIALLY\_PACKED\_ON\_THE\_BORDER, PACKED, PACKED\_IN\_TRANSIT,PACKED\_ON\_THE\_BORDER, READY\_FOR\_COLLECTION, COLLECTED,ON\_DELIVERY, DELIVERED statusChangedDateFrom ISO.DATE\_TIME True Shipment/Suborder status changed date\_time from statusChangedDateTo ISO.DATE\_TIME True Shipment/Suborder status changed date\_time to  |

### Example Request

GET https://api.qa2.ananastest.com/order/api/v2/carrier-biller-integration/invoices/non-fiscal?statuses=READY\_FOR\_COLLECTION\&statusChangedDateFrom=2026-03-01T00:00:00.0000000Z\&statusChangedDateTo=2026-03-01T23:59:59.0000000Z Authorization: Bearer ACCESS\_TOKEN

### Response

Returns a list of non-fiscal invoices for delivery services associated with the resolved CarrierBiller.

#### Fields

| Field name | Type | Description |
| :---- | :---- | :---- |
| shipmentDetails.orderId | String | Order ID |
| shipmentDetails.suborderId | String | Suborder ID |
| shipmentDetails.suborderType | String | Suborder type |
| shipmentDetails.createdDate | ISO DateTime | Suborder created date |
| shipmentDetails.carrierCode | String | Carrier code |
| shipmentDetails.carrierName | String | Carrier name |
| financialDetails.invoiceType | String | Always SALE for this endpoint |
| financialDetails.items | Array | Invoice line items (code, name, quantity, vat, basePrice, basePriceWithoutVat) |
| financialDetails.payment | Array | Payment details (amount, paymentType) |
| financialDetails.isLegalEntityBuyer | Boolean | Whether the buyer is a legal entity |
| financialDetails.legalEntityBuyer | Object | Legal entity buyer info (buyerTin, buyerName). Present only when isLegalEntityBuyer is true |
| nonfiscalInvoiceDetails.invoiceNumber | String | Non-fiscal invoice number |
| nonfiscalInvoiceDetails.invoiceDate | ISO DateTime | Non-fiscal invoice date |
| nonfiscalInvoiceDetails.active | Boolean | Whether the invoice is active |

### Example Response

\[  
  {  
    "shipmentDetails": {  
      "orderId": "OQI39-EURSE",  
      "suborderId": "OQI39-EURSE-DS-1",  
      "suborderType": "DS",  
      "createdDate": "2026-03-22T21:10:17.592064Z",  
      "carrierCode": "CEX01",  
      "carrierName": "City Express"  
    },  
    "financialDetails": {  
      "invoiceType": "SALE",  
      "items": \[  
        {  
          "code": "SHIPPING\_COST",  
          "name": "DOSTAVA (pripadajuća)",  
          "quantity": 1,  
          "vat": 20,  
          "basePrice": 349.00,  
          "basePriceWithoutVat": 290.83  
        },  
        {  
          "code": "CASH\_HANDLING\_FEE",  
          "name": "Naknada za plaćanje pouzećem",  
          "quantity": 1,  
          "vat": 20,  
          "basePrice": 200.00,  
          "basePriceWithoutVat": 166.67  
        }  
      \],  
      "payment": \[  
        {  
          "amount": 549,  
          "paymentType": "COD"  
        }  
      \],  
      "isLegalEntityBuyer": false  
    },  
    "nonfiscalInvoiceDetails": {  
      "invoiceNumber": "25260227412",  
      "invoiceDate": "2026-03-24T01:25:51Z",  
      "active": false  
    }  
  }  
\]

# Get Carrier Biller Non Fiscal Invoice Corrections

GET REST API endpoint for retrieving financial data that will become correction orders (non-fiscal invoice corrections) for delivery services, intended for carrier-biller-integration. Invoice corrections are linked to the CarrierBiller entity via the Shipment \<-\> Carrier \<-\> CarrierBiller relationship.

Authentication is performed using a Bearer Token (JWT) obtained via the ClientCredentials flow with scope carrierbiller\_public\_api/full\_access. All timestamps are in UTC.

Invoice corrections can arise at any point after the COLLECTED status. They are generated inFailed Delivery (LOST, DAMAGED, RETURN\_TO\_SELLER) andCustomer Case (RETURN, CLAIM) processes.

### Request

| HTTP Method | GET |  |
| :---: | :---- | :---- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v2/carrier-biller-integration/invoice-corrections/non-fiscal* |
|  | Production | *https://api.ananas.rs/order/api/v2/carrier-biller-integration/invoice-corrections/non-fiscal* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| Query Parameters |  |  Parameter name Type Required Description statusChangedDateFrom ISO.DATE\_TIME True Shipment/Suborder status changed date\_time from statusChangedDateTo ISO.DATE\_TIME True Shipment/Suborder status changed date\_time to  |

### Example Request

GET https://api.qa2.ananastest.com/order/api/v2/carrier-biller-integration/invoice-corrections/non-fiscal?statusChangedDateFrom=2026-03-01T00:00:00.0000000Z\&statusChangedDateTo=2026-03-01T23:59:59.0000000Z Authorization: Bearer ACCESS\_TOKEN

### Response

Returns a list of non-fiscal invoice corrections for delivery services associated with the resolved CarrierBiller.

#### Fields

| Field name | Type | Description |
| :---- | :---- | :---- |
| shipmentDetails.orderId | String | Order ID |
| shipmentDetails.suborderId | String | Suborder ID |
| shipmentDetails.suborderType | String | Suborder type |
| shipmentDetails.createdDate | ISO DateTime | Suborder created date |
| shipmentDetails.carrierCode | String | Carrier code |
| shipmentDetails.carrierName | String | Carrier name |
| financialDetails.invoiceType | String | Always REFUND for this endpoint |
| financialDetails.items | Array | Invoice line items (code, name, quantity, vat, basePrice, basePriceWithoutVat) |
| financialDetails.payment | Array | Payment details (amount, paymentType) |
| financialDetails.isLegalEntityBuyer | Boolean | Whether the buyer is a legal entity |
| financialDetails.legalEntityBuyer | Object | Legal entity buyer info (buyerTin, buyerName). Present only when isLegalEntityBuyer is true |
| nonfiscalInvoiceDetails.invoiceNumber | String | Non-fiscal invoice correction number |
| nonfiscalInvoiceDetails.invoiceDate | ISO DateTime | Non-fiscal invoice correction date |
| nonfiscalInvoiceDetails.referentInvoiceNumber | String | Invoice number of the original sale invoice being corrected |
| nonfiscalInvoiceDetails.active | Boolean | Whether the invoice correction is active |

### Example Response

\[  
  {  
    "shipmentDetails": {  
      "orderId": "OQI39-EURSE",  
      "suborderId": "OQI39-EURSE-DS-1",  
      "suborderType": "DS",  
      "createdDate": "2026-03-22T21:10:17.592064Z",  
      "carrierCode": "CEX01",  
      "carrierName": "City Express"  
    },  
    "financialDetails": {  
      "invoiceType": "REFUND",  
      "items": \[  
        {  
          "code": "SHIPPING\_COST",  
          "name": "DOSTAVA (pripadajuća)",  
          "quantity": 1,  
          "vat": 20,  
          "basePrice": 349.00,  
          "basePriceWithoutVat": 290.83  
        },  
        {  
          "code": "CASH\_HANDLING\_FEE",  
          "name": "Naknada za plaćanje pouzećem",  
          "quantity": 1,  
          "vat": 20,  
          "basePrice": 200.00,  
          "basePriceWithoutVat": 166.67  
        }  
      \],  
      "payment": \[  
        {  
          "amount": 549,  
          "paymentType": "COD"  
        }  
      \],  
      "isLegalEntityBuyer": true,  
      "legalEntityBuyer": {  
        "buyerTin": "111111111",  
        "buyerName": "Mile d.o.o."  
      }  
    },  
    "nonfiscalInvoiceDetails": {  
      "invoiceNumber": "F-25260227413",  
      "invoiceDate": "2026-03-24T01:25:51Z",  
      "referentInvoiceNumber": "25260227412",  
      "active": true  
    }  
  }  
\]

# Confirm pack is completed

POST REST API request for merchants, requiring a JSON body and an authorization token, in order to confirm packing is completed and that a shipment should be created.

### Request

| HTTP Method | POST |  |
| :---: | :---- | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/{suborderId}/pack* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/{suborderId}/pack* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

#### Example of the request

### JSON:

 {  
    "numberOfBoxes": 1,  
    "products": \[  
      {  
        "id": 2,  
        "quantity": 2,  
      },  
      {  
        "id": 3,  
        "quantity": 3,  
      },  
    \],

  }

### Response

The endpoint will return status 204 \- No Content.

# Fetch shipments and shipment data

GET REST API request for merchants, that returns shipments and shipments data.

* At least one of the parameters search or statusGroup must be provided. If neither is present, the request will result in a 4xx client error.  
* Parameter search will find shipment by shipment id or multiple shipments if order id is used as a search parameter.  
* Results are sorted by purchase date descending by default (purchaseDate DESC).  
* When both statusGroup and statuses are provided:  
  * The backend computes the intersection between:  
    * the statuses implied by statusGroup (its subgroupStatuses), and  
    * the explicit statuses provided in the request.  
  * If none of the requested statuses belong to the selectedstatusGroup (i.e. the intersection is empty), the endpoint returns anempty page (content: \[\], totalElements: 0) with HTTP200 OK.

Parameter statusGroup will filter orders per order status group. Three values for this parameter are SG\_FOR\_PACKAGING, SG\_SHIPMENT\_ON\_DELIVERY and SG\_COMPLETED.

Each of these status groups is internally expanded into corresponding subgroupStatuses used for filtering:

* SG\_FOR\_PACKAGING → SSG\_PARTIALLY\_CONFIRMED, SSG\_PENDING, SSG\_CONFIRMED  
* SG\_SHIPMENT\_ON\_DELIVERY → SSG\_PARTIALLY\_PACKED, SSG\_TRANSPORT\_INITIATED, SSG\_PACKED, SSG\_ON\_DELIVERY  
* SG\_COMPLETED → SSG\_DELIVERED, SSG\_CANCELLED, SSG\_NOT\_DELIVERED  
* If only statusGroup is provided (no statuses), results are filtered by all subgroup statuses mapped from that group.  
* If both statusGroup and statuses are provided, results are filtered by the intersection of:  
  * subgroupStatuses(statusGroup) and  
  * statuses from the request.  
* If the intersection is empty, an empty page is returned.

The other 2 parameters are page and size are used for pagination. The default value for page size is 10\. The default value for page number is 0\. The maximum allowed page size is 100\. If page size specified in the request is bigger than 100, the default page size of 100 will be used.

### Request

| HTTP Method | GET |  |
| :---: | :---- | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/shipments* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/shipments* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description search Refers to the id of an order or shipment statusGroup Status group of an order. Can have three values: SG\_FOR\_PACKAGING, SG\_SHIPMENT\_ON\_DELIVERY, and SG\_COMPLETED warehouseIds Referes to unique merchant address identifiers statuses Internal suborder statuses used for filtering. Valid values:CREATED,PENDING,CANCELLED,CONFIRMED,PARTIALLY\_PACKED,PARTIALLY\_PACKED\_IN\_TRANSIT,PARTIALLY\_PACKED\_ON\_THE\_BORDER,PACKED,PACKED\_IN\_TRANSIT,PACKED\_ON\_THE\_BORDER,READY\_FOR\_COLLECTION,COLLECTED,RETURN\_TO\_SELLER,DELIVERED . When combined with statusGroup, only statuses belonging to that group are considered; if none match, an empty page is returned. paymentMethods Payment methods used to filter shipments (for example PBC, COD). Multiple values are allowed. carriers Carrier identifiers (code/name) used to filter shipments. Multiple values are allowed. confirmedFrom Lower bound for confirmed date/time (ISO 8601). Returns shipments confirmed on or after this value. confirmedTo Upper bound for confirmed date/time (ISO 8601). Returns shipments confirmed on or before this value. suborderTypes Suborder types (for example DS) used to filter shipments. Multiple values are allowed. page Page number size Number of orders shown by page  |

### Examples:

*https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/shipments?search=TV4YK-OLZRU-DS-1*

*https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/shipments?statusGroup=SG\_FOR\_PACKAGING*

### Response

Response is a collection of shipment details

#### JSON

Click to copy  
{  
  "content": \[  
    {  
      "orderId": "H7SRK-Z2IAO",  
      "suborderId": "H7SRK-Z2IAO-DS-1",  
      "status": "READY\_FOR\_COLLECTION",  
      "statusSubgroup": "SSG\_TRANSPORT\_INITIATED",  
      "totalPrice": 5000.00,  
      "packedQuantity": 1,  
      "confirmedQuantity": 1,  
      "warehouseName": "CEZAR PLUS d.o.o. CEZAR Skladište",  
      "warehouseId": 2096,  
      "carrierName": "City Express",  
      "type": "Standard",  
      "createdDate": "2023-11-07T00:10:32.779083Z",  
      "items": \[  
        {  
          "packedQuantity": 1,  
          "confirmedQuantity": 1,  
          "merchantInventoryId": 1777104,  
          "unitPrice": 5000.00,  
          "totalPrice": 5000.00,  
          "orderedQuantity": 1,  
          "productName": "Slušalice05",  
          "productEan": "8606108800409",  
          "productSku": "Slušalice05",  
          "productSpecifications": "{\\"attributes\\": \[\]}"  
        }  
      \]  
    },  
    {  
      "orderId": "XLOGV-0RTNL",  
      "suborderId": "XLOGV-0RTNL-DS-1",  
      "status": "READY\_FOR\_COLLECTION",  
      "statusSubgroup": "SSG\_TRANSPORT\_INITIATED",  
      "totalPrice": 20000.00,  
      "packedQuantity": 2,  
      "confirmedQuantity": 2,  
      "warehouseName": "Test3335 WH1",  
      "warehouseId": 1853,  
      "carrierName": "City Express",  
      "type": "Standard",  
      "createdDate": "2023-11-01T14:47:41.616218Z",  
      "items": \[  
        {  
          "packedQuantity": 2,  
          "confirmedQuantity": 2,  
          "merchantInventoryId": 1777105,  
          "unitPrice": 10000.00,  
          "totalPrice": 20000.00,  
          "orderedQuantity": 2,  
          "productName": "Slušalice10",  
          "productEan": "6995019102118",  
          "productSku": "Slušalice10",  
          "productSpecifications": "{\\"attributes\\": \[\]}"  
        }  
      \]  
    }  
  \],  
  "pageable": {  
    "sort": {  
      "empty": false,  
      "unsorted": false,  
      "sorted": true  
    },  
    "offset": 0,  
    "pageNumber": 0,  
    "pageSize": 100,  
    "paged": true,  
    "unpaged": false  
  },  
  "last": true,  
  "totalPages": 1,  
  "totalElements": 2,  
  "first": true,  
  "size": 100,  
  "number": 0,  
  "sort": {  
    "empty": false,  
    "unsorted": false,  
    "sorted": true  
  },  
  "numberOfElements": 2,  
  "empty": false

}

# Get shipping label

GET REST API request that returns download link for shipping label. Fetching is done by the reference id (suborder id) which is passed in the URL as a request parameter. In the response, a download link will be returned, and with that link, it is possible to download the shipping label as a .zip file, which is connected to the merchant order.

### Request

| HTTP Method | GET |  |
| :---: | :---- | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/lmd/api/v1/merchant-integration/consignment/labels?reference=9Z0DG-G6ITS-DS-1* |
|  | Production | *https://api.ananas.rs/lmd/api/v1/merchant-integration/consignment/labels?reference=9Z0DG-G6ITS-DS-1* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description referenceId Referes to suborderId  |

### Examples:

*https://api.qa2.ananastest.com/lmd/api/v1/merchant-integration/consignment/labels?reference=9Z0DG-G6ITS-DS-1*

*https://api.ananas.rs/lmd/api/v1/merchant-integration/consignment/labels?reference=9Z0DG-G6ITS-DS-1*

### Response

Download link is a URL (Response body)

#### JSON

Click to copy  
{  
  "link": "https://ananas-dev1-logistics.s3.eu-central-1.amazonaws.com/consignment/309/9Z0DG-G6ITS-DS-1/9Z0DG-G6ITS-DS-1.zip?X-Amz-Algorithm=AWS4-HMAC-SHA256\&X-Amz-Date=20221115T104016Z\&X-Amz-SignedHeaders=host\&X-Amz-Expires=600\&X-Amz-Credential=AKIA22VGE46BU67P2UMV%2F20221115%2Feu-central-1%2Fs3%2Faws4\_request\&X-Amz-Signature=c0dfc17dabf347d8728bb61ee699f69b5ae1bc8efb5a3dd1be0fb2a670a49df3112",

};

# Get shipping label v2

GET REST API request that returns download link for shipping label. Fetching is done by the reference id (suborder id) and label format (PDF or ZPL, defaults to PDF) which are passed in the URL as a request parameters. In the response, a download link will be returned, and with that link, it is possible to download the shipping label as a ZIP/PDF file, which is connected to the merchant order.

### Request

| HTTP Method | GET |  |
| :---: | :---- | ----- |
| URL | Stage | *https://api.qa2.ananastest.com/lmd/api/v2/merchant-integration/consignment/labels?reference=9Z0DG-G6ITS-DS-1?format=ZPL* |
|  | Production | *https://api.ananas.rs/lmd/api/v2/merchant-integration/consignment/labels?reference=9Z0DG-G6ITS-DS-1?format=ZPL* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |
| URL Parameters |  |  Parameter name Parameter description reference Refers to suborder ID format Format PDF|ZPL  |

### Examples:

*https://api.qa2.ananastest.com/lmd/api/v2/merchant-integration/consignment/labels?reference=9Z0DG-G6ITS-DS-1?format=ZPL*

*https://api.ananas.rs/lmd/api/v2/merchant-integration/consignment/labels?reference=9Z0DG-G6ITS-DS-1?format=ZPL*

### Response

Download link is a URL (Response body)

#### JSON

Click to copy  
{  
  "link": "https://ananas-dev1-logistics.s3.eu-central-1.amazonaws.com/consignment/309/9Z0DG-G6ITS-DS-1/9Z0DG-G6ITS-DS-1.zpl?X-Amz-Algorithm=AWS4-HMAC-SHA256\&X-Amz-Date=20221115T104016Z\&X-Amz-SignedHeaders=host\&X-Amz-Expires=600\&X-Amz-Credential=AKIA22VGE46BU67P2UMV%2F20221115%2Feu-central-1%2Fs3%2Faws4\_request\&X-Amz-Signature=c0dfc17dabf347d8728bb61ee699f69b5ae1bc8efb5a3dd1be0fb2a670a49df3112",

};

# Allocate courier for suborder

POST REST API request for Ino MTO merchants. This endpoint allocates a courier for the suborder without scheduling the pickup time, since the goods have not yet arrived in Serbia. The endpoint sets the suborder’s status to PACKED and its status\_context to null.

The endpoint requires exactly one path parameter — suborderId. When invoked, the service allocates a courier for the suborder.

### Merchant eligibility

* The merchant must be marked as Ino MTO or Wholeseller.  
* If not, the API returns:

Merchant is not enabled for wholesaler or INO MTO functionalities

### Suborder update

The endpoint performs the following updates on the suborder:

* status → PACKED or PARTIALLY\_PACKED  
* status\_context → null

### Request

| HTTP Method | POST |  |
| :---: | :---- | ----- |
| URL | QA2 | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/{suborderId}/packAllocate* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/{suborderId}/packAllocate* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

### Examples:

*https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/8G0DV-WT7O9-DS-1 /packAllocate*

*https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/8G0DV-WT7O9-DS-1 /packAllocate*

### Response

The endpoint will return status 204 \- No Content.

# Mark suborder as in Transit

POST REST API request for Ino MTO merchants. This endpoint is used when the merchant loads the goods into the truck (or another transport vehicle) and the shipment starts its route toward Serbia. The endpoint updates the suborder’s status\_context to IN\_TRANSIT.

The endpoint requires exactly one path parameter — suborderId. When invoked, the service validates the suborder and sets its status\_context to IN\_TRANSIT.

### Merchant eligibility

* The merchant must be marked as Ino MTO or Wholeseller.  
* If not, the API returns:

Merchant is not enabled for wholesaler or INO MTO functionalities

### Suborder eligibility

The suborder must satisfy both of the following conditions:

* status is PACKED or PARTIALLY\_PACKED  
* status\_context is null

If these conditions are not met, the API returns:  
Suborder is not in correct state for updating status context to IN\_TRANSIT

### Request

| HTTP Method | POST |  |
| :---: | :---- | ----- |
| URL | QA2 | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/{suborderId}/packInTransit* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/{suborderId}/packInTransit* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

### Examples:

*https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/8G0DV-WT7O9-DS-1 /packInTransit*

*https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/8G0DV-WT7O9-DS-1 /packInTransit*

### Response

The endpoint will return status 204 \- No Content.

# Pack suborder on the border

POST REST API request for Ino MTO merchants. This endpoint updates the suborder status to indicate that the goods have reached the customs / border checkpoint by setting the suborder’s status\_context to PACKED\_ON\_THE\_BORDER.

The endpoint requires exactly one path parameter — suborderId. When invoked, the service performs the status-context transition to PACKED\_ON\_THE\_BORDER.

### Merchant eligibility

* The merchant must be marked as Ino MTO or Wholeseller.  
* If not, the API returns the following error:

Merchant is not enabled for wholesaler or INO MTO functionalities

### Suborder eligibility

The suborder must satisfy both of the following conditions:

* status is PACKED or PARTIALLY\_PACKED  
* status\_context is IN\_TRANSIT

If these conditions are not met, the API returns:

Suborder is not in correct state for updating status context to ON\_THE\_BORDER

### Request

| HTTP Method | POST |  |
| :---: | :---- | ----- |
| URL | QA2 | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/{suborderId}/packOnTheBorder* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/{suborderId}/packOnTheBorder* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

### Examples:

*https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/8G0DV-WT7O9-DS-1 /packOnTheBorder*

*https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/8G0DV-WT7O9-DS-1 /packOnTheBorder*

### Response

The endpoint will return status 204 \- No Content.

# Schedule suborder delivery

POST REST API request for Ino MTO merchants. This endpoint schedules a courier that was previously allocated for the suborder and sets the suborder's status\_context to null.

The endpoint requires exactly one path parameter — suborderId. When invoked, the service validates the suborder and schedules the courier if conditions are met.

### Merchant eligibility

* The merchant must be marked as Ino MTO or Wholeseller.  
* If not, the API returns the following error:

Merchant is not enabled for wholesaler or INO MTO functionalities

### Suborder eligibility

The suborder must satisfy both of the following conditions:

* status\_context is IN\_TRANSIT or ON\_THE\_BORDER  
* status is PACKED or PARTIALLY\_PACKED

If these conditions are not met, the API returns:

Suborder is not in correct state for scheduling

### Request

| HTTP Method | POST |  |
| :---: | :---- | ----- |
| URL | QA2 | *https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/{suborderId}/packSchedule* |
|  | Production | *https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/{suborderId}/packSchedule* |
| HTTP Headers |  |  Header name Header value Authorization Bearer {access\_token}  |

### Examples:

*https://api.qa2.ananastest.com/order/api/v1/merchant-integration/outbound-orders/8G0DV-WT7O9-DS-1 /packSchedule*

*https://api.ananas.rs/order/api/v1/merchant-integration/outbound-orders/8G0DV-WT7O9-DS-1 /packSchedule*

### Response

The endpoint will return status 204 \- No Content.

# Order flow

**Get all**  
**ordersConfirm outbound Master**  
**Order quantitiesFetch shipments**  
**and shipment dataConfirm pack**  
**is completedGet shipping**  
**label**

Here we describe order flow process step by step:

1. First, you need to retrieve all orders (with the particular status) using the following endpoint: [https://developer.ananas.rs/developer-portal/get-all-orders/](https://developer.ananas.rs/developer-portal/get-all-orders/)  
2. After you decide to confirm particular order (order quantities), you need to use the following endpoing: [https://developer.ananas.rs/developer-portal/confirm-outbound-master-order-quantities/](https://developer.ananas.rs/developer-portal/confirm-outbound-master-order-quantities/). All potential warehouseIds you can retrieve them by using: [https://developer.ananas.rs/developer-portal/get-merchant-warehouses/](https://developer.ananas.rs/developer-portal/get-merchant-warehouses/)  
3. After the order is confirmed, you can fatch shipment data, using the following endpoint: [https://developer.ananas.rs/developer-portal/fetch-shipments-and-shipment-data/](https://developer.ananas.rs/developer-portal/fetch-shipments-and-shipment-data/)  
4. After the shippment is ready, you need to confirm the package quantities, using the following endpoint: [https://developer.ananas.rs/developer-portal/confirm-pack-is-completed/](https://developer.ananas.rs/developer-portal/confirm-pack-is-completed/)  
5. The last step in the process is to get the shiping label, after the label is ready. You can do this with the following GET endpoint: [https://developer.ananas.rs/developer-portal/get-shipping-label/](https://developer.ananas.rs/developer-portal/get-shipping-label/)

# Additional information

If for whatever reason a different request or response body is necessary, it can be arranged and changed after it has been reviewed and approved by our development team.

For those requests, please contact one of development team leads or apisupport@ananas.rs

# Frequently asked questions

## How can I obtain cliendId and cliendSecret parameters?

Please contact Ananas onboarding team onboarding@ananas.rs.

## How can I upoload product images?

Using endpoint "AddOrEditProductsInBulk", you can pass image URL value (field “coverImage“). Image will be automatically downloaded. If you can’t provide image URL, you can use Merchant Portal, where we accept URL as well as .ZIP folders.

## I called "AddOrEditProductsInBulk" endpoint, got message code 200, but products aren’t loaded. What I did wrong?

After a successful endpoint call, Ananas onboarding team has to load new items manually. After that, you’ll receive a confirmation email message. On the other hand, items with existing EAN code will be loaded instantly.

## What fields I can edit, after product import?

After product import, you can change stockLevel, basePrice, vat, packageWeightValue, packageWeightUnit, serviceable, sku.

## How frequently I need to update the product data (stock and price)?

It’s up to you, however recomendation is one per day. On the days you have a lot of orders, you can keep 2 updates on a daily basis.

Ostalo imas na: [https://developer.ananas.rs/openapi/reference/overview/](https://developer.ananas.rs/openapi/reference/overview/)

[https://developer.ananas.rs/openapi/reference/operation/token/](https://developer.ananas.rs/openapi/reference/operation/token/)

[https://developer.ananas.rs/openapi/reference/operation/getProducts/](https://developer.ananas.rs/openapi/reference/operation/getProducts/)

[https://developer.ananas.rs/openapi/reference/operation/getBasicProducts/](https://developer.ananas.rs/openapi/reference/operation/getBasicProducts/)

[https://developer.ananas.rs/openapi/reference/operation/importOrUpdateProducts/](https://developer.ananas.rs/openapi/reference/operation/importOrUpdateProducts/)

[https://developer.ananas.rs/openapi/reference/operation/updateProducts/](https://developer.ananas.rs/openapi/reference/operation/updateProducts/)

[https://developer.ananas.rs/openapi/reference/operation/updateSingleProduct/](https://developer.ananas.rs/openapi/reference/operation/updateSingleProduct/)

[https://developer.ananas.rs/openapi/reference/operation/getProductsTypes/](https://developer.ananas.rs/openapi/reference/operation/getProductsTypes/)

[https://developer.ananas.rs/openapi/reference/operation/checkIfEANExists/](https://developer.ananas.rs/openapi/reference/operation/checkIfEANExists/)

[https://developer.ananas.rs/openapi/reference/operation/getMerchantWarehouses/](https://developer.ananas.rs/openapi/reference/operation/getMerchantWarehouses/)

[https://developer.ananas.rs/openapi/reference/operation/GetAllInvoices/](https://developer.ananas.rs/openapi/reference/operation/GetAllInvoices/)

[https://developer.ananas.rs/openapi/reference/operation/GetAllInvoiceCorrections/](https://developer.ananas.rs/openapi/reference/operation/GetAllInvoiceCorrections/)

[https://developer.ananas.rs/openapi/reference/operation/GetMerchantInventoryPrices/](https://developer.ananas.rs/openapi/reference/operation/GetMerchantInventoryPrices/)

[https://developer.ananas.rs/openapi/reference/operation/GetInvoiceURLs/](https://developer.ananas.rs/openapi/reference/operation/GetInvoiceURLs/)

[https://developer.ananas.rs/openapi/reference/operation/GetInvoiceCorrectionsURLs/](https://developer.ananas.rs/openapi/reference/operation/GetInvoiceCorrectionsURLs/)

[https://developer.ananas.rs/openapi/reference/operation/scheduleDiscountsInBulk/](https://developer.ananas.rs/openapi/reference/operation/scheduleDiscountsInBulk/)

[https://developer.ananas.rs/openapi/reference/operation/updateDiscountsInBulk/](https://developer.ananas.rs/openapi/reference/operation/updateDiscountsInBulk/)

[https://developer.ananas.rs/openapi/reference/operation/getDiscountPrices/](https://developer.ananas.rs/openapi/reference/operation/getDiscountPrices/)

[https://developer.ananas.rs/openapi/reference/operation/cancelDiscountPrices/](https://developer.ananas.rs/openapi/reference/operation/cancelDiscountPrices/)

