# Secured Filipino Cookbook API

A secured REST API for exploring Filipino foods — their categories, regional
origins, ingredients, and preparation instructions — built with the Slim
Framework and MySQL.

Built as a laboratory activity for DMMMSU-MLUC, demonstrating token-based
API security with the Slim Framework.

## Features

- Full CRUD on foods: create, read, update, and delete recipes
- Retrieve all Filipino foods, or one food by ID
- Search foods by name
- Filter foods by category or by origin
- Get a random food ("surprise me")
- Retrieve all categories, origins, and ingredients
- Add a new food with a JSON request, including its linked ingredients
- Every `/api` route protected by Bearer token authentication

## Tech Stack

- **PHP 8 + Slim Framework 4** — routing and middleware
- **MySQL + PDO** — database layer, using prepared statements throughout

## Database Structure

5 tables: `categories`, `origins`, `foods`, `ingredients`, `food_ingredients`.

A food belongs to one category and one origin. A food can have many
ingredients, and an ingredient can belong to many foods — that many-to-many
relationship is handled through the `food_ingredients` junction table.

## API Endpoints

| Method | Endpoint | Auth required | Description |
|---|---|---|---|
| GET | `/` | No | Welcome message |
| GET | `/api/foods` | Yes | List all foods with category, origin, and ingredients |
| GET | `/api/foods/{id}` | Yes | Get one food by ID |
| GET | `/api/foods/search/{name}` | Yes | Search foods by name |
| GET | `/api/foods/random` | Yes | Get one random food |
| GET | `/api/foods/category/{id}` | Yes | List all foods in a given category |
| GET | `/api/foods/origin/{id}` | Yes | List all foods from a given origin |
| GET | `/api/categories` | Yes | List all categories |
| GET | `/api/origins` | Yes | List all origins |
| GET | `/api/ingredients` | Yes | List all ingredients |
| POST | `/api/foods` | Yes | Add a new food record |
| PUT | `/api/foods/{id}` | Yes | Update an existing food record |
| DELETE | `/api/foods/{id}` | Yes | Delete a food record |

Secured routes require this header:
```
Authorization: Bearer dmmmsu-cookbook-token-2026
```

---

## Setup Guide

### 1. Import the database
1. Open phpMyAdmin (or MySQL CLI).
2. Import `filipino_foods_relational.sql`. It creates the `filipino_cookbook_api`
   database and its 5 tables (`categories`, `origins`, `foods`, `ingredients`,
   `food_ingredients`) and seeds all the sample data.

### 2. Install dependencies
Inside the `filipino-cookbook-api` folder, run:
```
composer install
```
(This reads `composer.json` and installs `slim/slim` + `slim/psr7` into `vendor/`.)

### 3. Configure DB credentials
Open `public/index.php` and check the `getDbConnection()` function near the top.
Default is XAMPP-style: host `127.0.0.1`, user `root`, empty password. Adjust if
your MySQL setup is different.

### 4. Run the server
From the project root:
```
php -S localhost:8000 -t public
```
Your API is now live at `http://localhost:8000`.

Or, if deploying under XAMPP/Apache instead, place the project inside
`htdocs` and visit `http://localhost/filipino-cookbook-api/public/` —
the app auto-detects its own base path, so both setups work without
code changes.

### 5. Test with Thunder Client (or Postman)

**Public route (no token needed):**
```
GET http://localhost:8000/
```

**Secured routes — add header:**
```
Authorization: Bearer dmmmsu-cookbook-token-2026
```

Core endpoints to test:
- `GET /api/foods` — all foods with category/origin/ingredients
- `GET /api/foods/1` — Adobo by ID
- `GET /api/foods/99` — should return 404 "Food not found"
- `GET /api/foods/search/adobo` — search by name
- `GET /api/categories` — all categories
- `GET /api/ingredients` — all ingredients
- `POST /api/foods` — add new food, body:
```json
{
  "food_name": "Dinengdeng",
  "category_id": 3,
  "origin_id": 4,
  "instructions": "Boil vegetables with bagoong-based broth and add grilled fish before serving.",
  "ingredient_ids": [10, 15, 22]
}
```

Newer endpoints to test:
- `GET /api/origins` — all origins
- `GET /api/foods/random` — a random food each time you send it
- `GET /api/foods/category/4` — all Main Dish foods
- `GET /api/foods/origin/3` — all foods from the Ilocos Region
- `PUT /api/foods/1` — update a food, body:
```json
{
  "food_name": "Chicken Adobo",
  "category_id": 4,
  "origin_id": 4,
  "instructions": "Updated instructions here.",
  "ingredient_ids": [26, 54, 64]
}
```
- `DELETE /api/foods/15` — deletes a food (test on a throwaway record, not one you need)

**Missing/invalid token test** — remove the header or change the token value,
you should get:
```json
{
  "status": "error",
  "message": "Unauthorized access. Valid API token is required."
}
```
with a `401` status code.

## Notes on Design Choices

- `food_id` in the SQL script is a plain `INT PRIMARY KEY` (not
  `AUTO_INCREMENT`), so the `POST /api/foods` handler calculates the next ID
  manually (`MAX(food_id) + 1`) inside a transaction before inserting.
- The token check middleware (`$authMiddleware`) is attached only to the
  `/api` route group, so `/` stays public.
- All food-returning endpoints reuse `buildFoodPayload()` and
  `getIngredientsForFood()` so the ingredient list is always pulled fresh from
  the `food_ingredients` junction table.
- CORS headers are added so the API can be called from a browser-based
  client, and the app auto-detects its own base path so it works whether run
  standalone or deployed inside a subfolder under Apache. The allowed CORS
  methods list includes `PUT` and `DELETE` to support the newer endpoints.
- `PUT /api/foods/{id}` only replaces a food's ingredient links if
  `ingredient_ids` is included in the request body — omitting the field
  leaves existing ingredient links untouched, while sending an empty array
  clears them.
- `DELETE /api/foods/{id}` relies on the `ON DELETE CASCADE` constraint on
  `food_ingredients.food_id` from the original schema, so deleting a food
  automatically cleans up its ingredient links without a separate query.

  ## Developer Information
- **Name:** ROCAPOR, Jean Mark Baldemor
- **Course and Section:** BS Information Technology 4B
- **GitHub Username:** jeansyntax
- **Repository Link:** (https://github.com/jeansyntax/filipino_cookbook.git)
- **Date Completed:** July 2026
