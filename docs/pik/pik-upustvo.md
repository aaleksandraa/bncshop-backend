Authentication
You'll need to authenticate your requests to access any of the endpoints in the OLX API. In this guide, we'll look at how authentication works.

To verify requests, we need a token. We can use the Login endpoint to obtain a Token.

POST
/auth/login
Login
Login ednpoint.

Headers
Name
Content-Type
Type
string
Description
application/json

Required fields
Name
username
Type
string
Description
Username field can be 'username' or 'email'

Name
password
Type
string
Description
Password

Name
device_name
Type
string
Description
Device name ( api_integration )

Request
POST
/auth/login
curl https://api.olx.ba/auth/login \
	-d username="test@olx.ba" \
	-d password="password" \
	-d device_name="integration" 

Copy
Copied!
Response
{
"token": "163|1bA8cqxhtoohFDROFAWYPGhkvYApzLpm2ojzD6Tc",
"user": {
	"id": 1,
	"type": "shop",
	"email": "email@olx.ba",
	"username": "OLX",
	"first_name": "Svijet",
	"last_name": "Kupoprodaje",
	...
	}
}

Copy
Copied!
Requests with Bearer token
Example request with bearer token
curl https://api.olx.ba/me \
  -H "Authorization: Bearer {token}"

Copy
Copied!
Always keep your token safe and reset it if you suspect it has been compromised.

Requests with old tokens
If you don't have access to password you can use older tokens for authorization.

You need to send OLX-CLIENT-ID and OLX-CLIENT-TOKEN headers.

Example request with bearer token
curl https://api.olx.ba/me \
  -H "OLX-CLIENT-ID: {client-id}"
  -H "OLX-CLIENT-TOKEN: {client-token}"




  LISTINGS:
  Listings
All listings endpoints.

GET
/listings/:id
Listing
Get single listing.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
GET
/listings/:id
curl https://api.olx.ba/listings/:id \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"id": 40,
	"type": "single",
	"title": "audi a3",
	"slug": "audi-a3",
	"short_description": "Audi a3",
	"additional": {
		"description": "opis oglasa"
	},
	"user": {
		"id": 1,
		...
	},
	"price": 11990,
	"display_price": "11.990 KM",
	"regular_price": 0,
	"listing_type": "sell",
	"price_by_agreement": false,
	"visible": false,
	"quantity": 1,
	"location": {
		"lat": 43.1973791,
		"lon": 17.5461833
	},
	"status": "active",
	"available": false,
	"state": "used",
	"shipping": "no_shipping"
	...
}


POST
/listings
Create new listing
Create new listing.

Note: Your new listing will have the status DRAFT when you save it. You must publish the listing to make it active.

Publish listing
Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Required attributes
Name
title
Type
string
Description
Listing title.

Optional attributes
Name
short_description
Type
string
Description
Short description.

Name
description
Type
string
Description
Description.

Name
country_id
Type
string
Description
Country ID ( check Location resources ).

Name
city_id
Type
string
Description
City ID ( check Location resources ).

Name
price
Type
numeric
Description
Price

Name
available
Type
boolean
Description
Available

Name
listing_type
Type
string
Description
Listing type:

sell
buy
rent
Name
state
Type
string
Description
new
used
Name
brand_id
Type
string
Description
Brand id ( check Categories resource for brand references )

Name
model_id
Type
string
Description
Brand id ( check Categories resource for model references )

Name
sku_number
Type
string
Description
Internal sku number

Name
attributes
Type
array
Description
To get attributes for category check Categories references. Example of attributes:


Attributes
  "attributes" : [
	{
		"id": 2,
		"value": "2022" // Year
	},
	{
		"id": 7,
		"value": "Dizel" // Fuel type
	},
	{
		"id": 901,
		"value": "2/3" // Door
	},
	{
		"id": 3,
		"value": "50000" // Mileage
	},
	{
		"id": 1144,
		"value": "1.5" // Power
	},
	{
		"id": 5,
		"value": "55" // Cubic Capacity
	}
]




Request
POST
/listings
curl -X POST https://api.olx.ba/listings \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"id": 40,
	"type": "single",
	"title": "audi a3",
	"slug": "audi-a3",
	"short_description": "Audi a3",
	"additional": {
		"description": "opis oglasa"
	},
	"user": {
		"id": 1,
		...
	},
	"price": 11990,
	"display_price": "11.990 KM",
	"regular_price": 0,
	"listing_type": "sell",
	"price_by_agreement": false,
	"visible": false,
	"quantity": 1,
	"location": {
		"lat": 43.1973791,
		"lon": 17.5461833
	},
	"status": "active",
	"available": false,
	"state": "used",
	"shipping": "no_shipping"
	...
}






PUT
/listings/:id
Update listing
Update single listing.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Update request
{
	"title": "audi a3",
	"description" : "listing description",
	"price": 11990
}



Request
PUT
/listings/:id
curl -X PUT https://api.olx.ba/listings/:id \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"id": 40,
	"type": "single",
	"title": "audi a3",
	"slug": "audi-a3",
	"short_description": "Audi a3",
	"additional": {
		"description": "opis oglasa"
	},
	"user": {
		"id": 1,
		...
	},
	"price": 11990,
	"display_price": "11.990 KM",
	"regular_price": 0,
	"listing_type": "sell",
	"price_by_agreement": false,
	"visible": false,
	"quantity": 1,
	"location": {
		"lat": 43.1973791,
		"lon": 17.5461833
	},
	"status": "active",
	"available": false,
	"state": "used",
	"shipping": "no_shipping"
	...
}






POST
/listings/:id/publish
Publish listing
When you save a new listing, it will have the status "DRAFT." In DRAFT status listing is saved but not visible on search. Use this endpoint in order to activate listing.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
POST
/listings/:id/publish
curl -X POST https://api.olx.ba/listings/:id/publish \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"message": "Oglas je uspjesno objavljen",
	"status": "active"
}





DELETE
/listings/:id
Delete listing
Delete single listing.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>


Request
DELETE
/listings/:id
curl -X DELETE https://api.olx.ba/listings/:id \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"message": "Uspješno ste izbrisali oglas"
}

Copy
Copied!






GET
/listing/refresh/limits
Listing refresh limits
Listing refresh limits.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>


Request
GET
/listing/refresh/limits
curl -X GET https://api.olx.ba/listing/refresh/limits \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"free_limit": 750,
	"free_count": 0,
	"paid_count": 0,
	"listing_count": 3
}

Copy
Copied!





GET
/listing-limits
Listing limits
Listing limits.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
GET
/listing-limits
curl -X GET https://api.olx.ba/listing-limits \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
"data": {
	"cars": {
		"limit": 0,
		"listings": 0
	},
	"real-estate": {
		"limit": 0,
		"listings": 1
	},
	"other": {
		"limit": 0,
		"listings": 8
	}
}
}




PUT
/listings/:id/refresh
Listing refresh
Refesh listing date. It will boost listing on search rank.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>


Request
PUT
/listings/:id/refresh
curl -X PUT https://api.olx.ba/listings/:id/refresh \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"message": "Artikal je uspjesno obnovljen."
}






POST
/listings/:id/image-upload
Image upload
Headers
Name
Authorization
Type
string
Description
Beare <token>

Required attributes
Name
images
Type
array<image>
Description
Image files

Attributes
Name
image_url
Type
string
Description
Image url

Request
POST
/listings/:id/image-upload
curl -X POST https://api.olx.ba/listings/:id/image-upload \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
[
	{
		"id": 44,
		"name": "img-1679924109-fd89f8c193d2.jpeg",
		"main": false,
		"order": 0,
		"sizes": {
			"sm": "listings\/40\/sm\/img-1679924109-fd89f8c193d2.jpeg",
			"lg": "listings\/40\/lg\/img-1679924109-fd89f8c193d2.jpeg"
		},
		"created_at": "2023-03-27T13:35:11.000000Z"
	},
	{
	"id": 45,
	"name": "img-1679924111-6249d3519888.jpg",
	"main": false,
	"order": 0,
	"sizes": {
		"sm": "listings\/40\/sm\/img-1679924111-6249d3519888.jpg",
		"lg": "listings\/40\/lg\/img-1679924111-6249d3519888.jpg"
	},
	"created_at": "2023-03-27T13:35:12.000000Z"
	}
]







POST
/listings/:id/image-delete
Image delete
Delete image from listing. In URL is Listing id and in request you need to send imageId.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Required attributes
Name
imageId
Type
integer
Description
Image ID.

Request
POST
/listings/:id/image-delete
curl -X POST https://api.olx.ba/listings/:id/image-delete \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \
	-d '{"imageId":"1"}'

Copy
Copied!
Response
{
	"success" : true
}







POST
/listings/:id/image-main
Image set main
Set main image. In URL is listing ID and in request you need to send imageId.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Required attributes
Name
imageId
Type
integer
Description
Image ID.

Request
POST
/listings/:id/image-main
curl -X PUT https://api.olx.ba/listings/:id/image-main \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \
	-d '{"imageId":"1"}'

Copy
Copied!
Response
{
	"success" : true
}	






POST
/listings/:id/finish
Finish listing
Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
POST
/listings/:id/finish
curl -X POST https://api.olx.ba/listings/:id/finish \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!






POST
/listings/:id/hide
Hide listing
Listings that are hidden won't show up in searches. It will appear on your profile in the "hidden" list.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
POST
/listings/:id/hide
curl -X POST https://api.olx.ba/listings/:id/hide \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \





POST
/listings/:id/unhide
Unhide listing
Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
POST
/listings/:id/unhide
curl -X POST https://api.olx.ba/listings/:id/unhide \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \










USERS:
Users
GET
/users/:username/listings
Active listings
Active listings.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Attributes
Name
page
Type
integer
Description
Page number

Request
GET
/users/:username/listings
curl -X GET https://api.olx.ba/users/:username/listings \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
		{
			"category_id": 2373,
			"score": null,
			"id": 50,
			"type": "single",
			"title": "audi a3",
			"price": 15.5,
			"display_price": "15,50 KM",
			"price_max": 0,
			"date": 1678109326,
			"image": null,
			"sponsored": 0,
			"available": false,
			"visible": true,
			"shipping": 1,
			"user_type": "shop",
			"user_id": 5948,
			"state": "used",
			"status": "active",
			"location": {
				"lat": 43.1973791,
				"lon": 17.5461833
			},
			"labels": [],
			"listing_type": "sell",
			"special_labels": null,
			"refresh_available": true
			}
		],
	"meta": {
		"total": 1,
		"last_page": 1,
		"current_page": 1,
		"per_page": 20,
		"selected_category": 0
	}
}




GET
/users/:id/listings/finished
Finished listings
Finished listings.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Attributes
Name
page
Type
integer
Description
Page number

Request
GET
/users/:id/listings/finished
curl -X GET https://api.olx.ba/users/:id/listings/finished \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
		{
			"category_id": 2373,
			"score": null,
			"id": 50,
			"type": "single",
			"title": "audi a3",
			"price": 15.5,
			"display_price": "15,50 KM",
			"price_max": 0,
			"date": 1678109326,
			"image": null,
			"sponsored": 0,
			"available": false,
			"visible": true,
			"shipping": 1,
			"user_type": "shop",
			"user_id": 5948,
			"state": "used",
			"status": "active",
			"location": {
				"lat": 43.1973791,
				"lon": 17.5461833
			},
			"labels": [],
			"listing_type": "sell",
			"special_labels": null,
			"refresh_available": true
			}
		],
	"meta": {
		"total": 1,
		"last_page": 1,
		"current_page": 1,
		"per_page": 20,
		"selected_category": 0
	}
}

Copy
Copied!






GET
/users/:id/listings/inactive
Inactive listings
Inactive listings.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Attributes
Name
page
Type
integer
Description
Page number

Request
GET
/users/:id/listings/inactive
curl -X GET https://api.olx.ba/users/:id/listings/inactive \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
		{
			"category_id": 2373,
			"score": null,
			"id": 50,
			"type": "single",
			"title": "audi a3",
			"price": 15.5,
			"display_price": "15,50 KM",
			"price_max": 0,
			"date": 1678109326,
			"image": null,
			"sponsored": 0,
			"available": false,
			"visible": true,
			"shipping": 1,
			"user_type": "shop",
			"user_id": 5948,
			"state": "used",
			"status": "active",
			"location": {
				"lat": 43.1973791,
				"lon": 17.5461833
			},
			"labels": [],
			"listing_type": "sell",
			"special_labels": null,
			"refresh_available": true
			}
		],
	"meta": {
		"total": 1,
		"last_page": 1,
		"current_page": 1,
		"per_page": 20,
		"selected_category": 0
	}
}





GET
/users/:id/listings/expired
Expired listings
Expired listings.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Attributes
Name
page
Type
integer
Description
Page number

Request
GET
/users/:id/listings/expired
curl -X GET https://api.olx.ba/users/:id/listings/expired \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
		{
			"category_id": 2373,
			"score": null,
			"id": 50,
			"type": "single",
			"title": "audi a3",
			"price": 15.5,
			"display_price": "15,50 KM",
			"price_max": 0,
			"date": 1678109326,
			"image": null,
			"sponsored": 0,
			"available": false,
			"visible": true,
			"shipping": 1,
			"user_type": "shop",
			"user_id": 5948,
			"state": "used",
			"status": "active",
			"location": {
				"lat": 43.1973791,
				"lon": 17.5461833
			},
			"labels": [],
			"listing_type": "sell",
			"special_labels": null,
			"refresh_available": true
			}
		],
	"meta": {
		"total": 1,
		"last_page": 1,
		"current_page": 1,
		"per_page": 20,
		"selected_category": 0
	}
}





GET
/users/:id/listings/hidden
Hidden listings
Hidden listings.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Attributes
Name
page
Type
integer
Description
Page number

Request
GET
/users/:id/listings/hidden
curl -X GET https://api.olx.ba/users/:id/listings/hidden \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
		{
			"category_id": 2373,
			"score": null,
			"id": 50,
			"type": "single",
			"title": "audi a3",
			"price": 15.5,
			"display_price": "15,50 KM",
			"price_max": 0,
			"date": 1678109326,
			"image": null,
			"sponsored": 0,
			"available": false,
			"visible": true,
			"shipping": 1,
			"user_type": "shop",
			"user_id": 5948,
			"state": "used",
			"status": "active",
			"location": {
				"lat": 43.1973791,
				"lon": 17.5461833
			},
			"labels": [],
			"listing_type": "sell",
			"special_labels": null,
			"refresh_available": true
			}
		],
	"meta": {
		"total": 1,
		"last_page": 1,
		"current_page": 1,
		"per_page": 20,
		"selected_category": 0
	}
}




CATEGORIES:
Category
GET
/categories
Categories
Get all categories.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
GET
/categories
curl -X GET https://api.olx.ba/categories \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
			{
				"id": 2,
				"name": "Nekretnine",
				"name_singular": "Nekretnine",
				"slug": "nekretnine",
				"parent_id": null,
				"order": 100,
				"top_category": true,
				"highlighted": true,
				"shipping_available": false,
				"sensitive_content": false,
				"post_option": null,
				"show_price": true,
				"show_brand": false,
				"brand_required": false,
				"model_required": false,
				"has_models": false,
				"show_condition": true,
				"show_map": false,
				"listing_fee": 0,
				"base_listing_price": 0,
				"icon": "real-estate"
			},
			{
				"id": 3,
				"name": "Mobilni ure\u0111aji",
				"name_singular": "Mobilni ure\u0111aji",
				"slug": "mobilni-uredjaji",
				"parent_id": null,
				"order": 100,
				"top_category": true,
				"highlighted": true,
				"shipping_available": true,
				"sensitive_content": false,
				"post_option": null,
				"show_price": true,
				"show_brand": true,
				"brand_required": false,
				"model_required": false,
				"has_models": false,
				"show_condition": true,
				"show_map": false,
				"listing_fee": 0,
				"base_listing_price": 0,
				"icon": "mobile-phones"
			},
	]
}





GET
/categories/:id
Children categories
Get children categories.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
GET
/categories/:id
curl -X GET https://api.olx.ba/categories/:id \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
			{
				"id": 2,
				"name": "Nekretnine",
				"name_singular": "Nekretnine",
				"slug": "nekretnine",
				"parent_id": null,
				"order": 100,
				"top_category": true,
				"highlighted": true,
				"shipping_available": false,
				"sensitive_content": false,
				"post_option": null,
				"show_price": true,
				"show_brand": false,
				"brand_required": false,
				"model_required": false,
				"has_models": false,
				"show_condition": true,
				"show_map": false,
				"listing_fee": 0,
				"base_listing_price": 0,
				"icon": "real-estate"
			},
			{
				"id": 3,
				"name": "Mobilni ure\u0111aji",
				"name_singular": "Mobilni ure\u0111aji",
				"slug": "mobilni-uredjaji",
				"parent_id": null,
				"order": 100,
				"top_category": true,
				"highlighted": true,
				"shipping_available": true,
				"sensitive_content": false,
				"post_option": null,
				"show_price": true,
				"show_brand": true,
				"brand_required": false,
				"model_required": false,
				"has_models": false,
				"show_condition": true,
				"show_map": false,
				"listing_fee": 0,
				"base_listing_price": 0,
				"icon": "mobile-phones"
			},
	]
}





GET
/category/:id
Category
Get category.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
GET
/category/:id
curl -X GET https://api.olx.ba/category/:id \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": {
		"id": 23,
		"name": "Stanovi",
		"name_singular": "Stanovi",
		"slug": "stanovi",
		"parent_id": 2,
		"order": 100,
		"top_category": false,
		"highlighted": false,
		"shipping_available": false,
		"sensitive_content": false,
		"post_option": null,
		"show_price": true,
		"show_brand": false,
		"brand_required": false,
		"model_required": false,
		"has_models": false,
		"show_condition": true,
		"show_map": true,
		"listing_fee": 50,
		"base_listing_price": 14,
		"icon": ""
	}
}





GET
/categories/:id/attributes
Category attributes
Get category attributes.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
GET
/categories/:id/attributes
curl -X GET https://api.olx.ba/categories/:id/attributes \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
		    {
			"id": 901,
			"type": "string",
			"name": "broj-vrata",
			"input_type": "select",
			"display_name": "Broj vrata",
			"options": [
				"2\/3",
				"4\/5"
			],
			"rank": 0,
			"order": 1,
			"required": true,
			"highlighted": false
			},
			{
			"id": 7,
			"type": "string",
			"name": "gorivo",
			"input_type": "select",
			"display_name": "Gorivo",
			"options": [
				"Dizel",
				"Benzin",
				"Plin",
				"Hibrid",
				"Elektro"
			],
			"rank": 0,
			"order": 1,
			"required": true,
			"highlighted": false
			},
	]
}






GET
/categories/:id/brands
Category brands
Get category brands.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
GET
/categories/:id/brands
curl -X GET https://api.olx.ba/categories/:id/brands \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
		{
			"id": 3,
			"name": "Alfa Romeo",
			"slug": "alfa-romeo"
		},
		{
			"id": 7,
			"name": "Audi",
			"slug": "audi"
		},
		{
			"id": 11,
			"name": "BMW",
			"slug": "bmw"
		},
		{
			"id": 20,
			"name": "Citroen",
			"slug": "citroen"
		},
	]
}


Copy
Copied!





GET
/categories/:id/brands/:brand_id/models
Category models
Get models.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Request
GET
/categories/:id/brands/:id_brand/models
curl -X GET https://api.olx.ba/categories/:id/brands/:id_brand/models \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
		{
			"id": 72,
			"name": "A1",
			"slug": "a1"
		},
		{
			"id": 73,
			"name": "A2",
			"slug": "a2"
		},
		{
			"id": 74,
			"name": "A3",
			"slug": "a3"
		},
		{
			"id": 75,
			"name": "A4",
			"slug": "a4"
		},
	]
}



GET
/categories/suggest
Category suggestion
Suggest category by listing title.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Attributes
Name
keyword
Type
string
Description
Listing title.

Request
GET
/categories/suggest?keyword=golf
curl -X GET https://api.olx.ba/categories/suggest?keyword=golf \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
{
	"data": [
		{
			"id": 18,
			"count": 23216,
			"name": "Automobili",
			"parent_categories": [
				"Vozila"
			]
			},
	]
}





GET
/categories/find?name=felge
Category find
Get category attributes.

Headers
Name
Content-Type
Type
string
Description
application/json

Name
Authorization
Type
string
Description
Beare <token>

Attributes
Name
name
Type
string
Description
Category name.

Request
GET
/categories/find?name=felge
curl -X GET https://api.olx.ba/categories/find?name=felge \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer {token}" \

Copy
Copied!
Response
[
	{
		"id": 974,
		"name": "Felge",
		"path": "Vozila > Dijelovi i oprema > Za bicikle > Felge"
	},
	{
		"id": 2158,
		"name": "Gume i felge za traktore",
		"path": "Biznis i Industrija > Poljoprivreda > Dijelovi za traktore i motokultivatore > Gume i felge za traktore"
	},
	{
		"id": 937,
		"name": "Felge",
		"path": "Vozila > Dijelovi i oprema > Za automobile > Felge"
	}
]



