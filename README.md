# Secured Filipino Cookbook API

A REST API for exploring Filipino foods — their categories, regional origins,
ingredients, and preparation instructions. Built with the Slim Framework and
MySQL, protected with token-based authentication.

This document is for developers who want to **consume this API** in their own
application (website, mobile app, another system, etc.) — not for running the
API's own source code.

## Base URL

```
http://localhost/filipino-cookbook-api/public
```
(Replace with wherever the API owner has it hosted — ask them for the actual
base URL if you're consuming this from a different machine or network.)

## Authentication

Every route except `GET /` requires an API token sent as a Bearer token in
the request header:

```
Authorization: Bearer dmmmsu-cookbook-token-2026
```

Requests without a valid token receive:
```json
{
  "status": "error",
  "message": "Unauthorized access. Valid API token is required."
}
```
with HTTP status `401 Unauthorized`.

## Response Format

All responses are JSON. Successful responses return the requested data
directly (an object or array). Error responses follow this shape:
```json
{
  "status": "error",
  "message": "Description of what went wrong"
}
```

---

## Endpoints

### 1. Welcome Message
```
GET /
```
No authentication required.

**Example response — 200 OK:**
```json
{
  "message": "Welcome to the Secured Filipino Cookbook API",
  "note": "Use a valid Bearer token to access /api endpoints."
}
```

---

### 2. Get All Foods
```
GET /api/foods
```
Requires token. Returns every food record with its category, origin,
instructions, and full ingredient list.

**Example response — 200 OK:**
```json
[
  {
    "food_id": 1,
    "food_name": "Adobo",
    "category_name": "Main Dish",
    "origin_name": "Philippines",
    "instructions": "Marinate the meat with soy sauce, vinegar, garlic, bay leaves, and peppercorn. Simmer until the meat becomes tender and the sauce is reduced.",
    "ingredients": [
      "Bay leaves",
      "Chicken or pork",
      "Cooking oil",
      "Garlic",
      "Peppercorn",
      "Soy sauce",
      "Vinegar"
    ]
  }
]
```

---

### 3. Get Food by ID
```
GET /api/foods/{id}
```
Requires token. Returns a single food record.

**Example:** `GET /api/foods/1`

**Example response — 200 OK:** same shape as one item above.

**If the ID doesn't exist — 404 Not Found:**
```json
{
  "status": "error",
  "message": "Food not found"
}
```

---

### 4. Search Foods by Name
```
GET /api/foods/search/{name}
```
Requires token. Partial, case-insensitive match on food name.

**Example:** `GET /api/foods/search/adobo`

**Example response — 200 OK:** array of matching foods, same shape as
endpoint 2.

**If nothing matches — 404 Not Found:**
```json
{
  "status": "error",
  "message": "No foods matched your search"
}
```

---

### 5. Get All Categories
```
GET /api/categories
```
Requires token.

**Example response — 200 OK:**
```json
[
  { "category_id": 1, "category_name": "Appetizer" },
  { "category_id": 2, "category_name": "Dessert" },
  { "category_id": 3, "category_name": "Grilled Dish" },
  { "category_id": 4, "category_name": "Main Dish" },
  { "category_id": 5, "category_name": "Noodle Dish" },
  { "category_id": 6, "category_name": "Soup" },
  { "category_id": 7, "category_name": "Vegetable Dish" }
]
```

---

### 6. Get All Ingredients
```
GET /api/ingredients
```
Requires token.

**Example response — 200 OK:**
```json
[
  { "ingredient_id": 1, "ingredient_name": "Annatto oil" },
  { "ingredient_id": 2, "ingredient_name": "Bagoong" }
]
```
(Full list contains 64 ingredients.)

---

### 7. Add a New Food
```
POST /api/foods
```
Requires token. Adds a new food record and links it to existing ingredients.

**Request body:**
```json
{
  "food_name": "Dinengdeng",
  "category_id": 3,
  "origin_id": 4,
  "instructions": "Boil vegetables with bagoong-based broth and add grilled fish before serving.",
  "ingredient_ids": [10, 15, 22]
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `food_name` | string | Yes | |
| `category_id` | integer | Yes | Must match an existing `category_id` |
| `origin_id` | integer | Yes | Must match an existing `origin_id` (see Origins below) |
| `instructions` | string | Yes | |
| `ingredient_ids` | array of integers | No | Must match existing `ingredient_id` values |

**Example response — 201 Created:**
```json
{
  "status": "success",
  "message": "Food added successfully."
}
```

**If a required field is missing — 400 Bad Request:**
```json
{
  "status": "error",
  "message": "Missing required field: food_name"
}
```

---

## Reference Data

### Origins
There's no `/api/origins` endpoint, so use these IDs directly when adding
a food:

| origin_id | origin_name |
|---|---|
| 1 | Bacolod |
| 2 | Bicol Region |
| 3 | Ilocos Region |
| 4 | Philippines |

### Categories
Fetch live from `GET /api/categories`, or reference:

| category_id | category_name |
|---|---|
| 1 | Appetizer |
| 2 | Dessert |
| 3 | Grilled Dish |
| 4 | Main Dish |
| 5 | Noodle Dish |
| 6 | Soup |
| 7 | Vegetable Dish |

---

## Quick Start (example using fetch in JavaScript)

```js
const API_BASE = 'http://localhost/filipino-cookbook-api/public';
const TOKEN = 'dmmmsu-cookbook-token-2026';

fetch(`${API_BASE}/api/foods`, {
  headers: { 'Authorization': `Bearer ${TOKEN}` }
})
  .then(res => res.json())
  .then(data => console.log(data));
```

## Notes for Integrators

- `food_id`, `category_id`, `origin_id`, and `ingredient_id` are all plain
  integers and stable — safe to hardcode or cache in your own system.
- CORS is enabled (`Access-Control-Allow-Origin: *`), so this API can be
  called directly from a browser-based frontend on a different origin.
- There is no rate limiting or per-user token — this is a single shared
  token for the whole class/lab activity, not meant for production use.
