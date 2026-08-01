# Kusina — Secured Filipino Cookbook API

A secured REST API for exploring Filipino foods — their categories, regional
origins, ingredients, and preparation instructions — built with the Slim
Framework and MySQL. Includes a working frontend web app that consumes the
API end to end.

Built as a laboratory activity for DMMMSU-MLUC, demonstrating token-based
API security with the Slim Framework.

## Features

- Retrieve all Filipino foods, or one food by ID
- Search foods by name
- Retrieve all categories, origins (via foods), and ingredients
- Add a new food with a JSON request, including its linked ingredients
- Every `/api` route protected by Bearer token authentication
- A working frontend ("Kusina") that browses, searches, filters, and adds
  recipes through the API — no raw JSON required to demo it

## Tech Stack

- **PHP 8 + Slim Framework 4** — routing and middleware
- **MySQL + PDO** — database layer, using prepared statements throughout
- **Vanilla HTML/CSS/JavaScript** — the frontend, no build step required

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
| GET | `/api/categories` | Yes | List all categories |
| GET | `/api/ingredients` | Yes | List all ingredients |
| POST | `/api/foods` | Yes | Add a new food record |

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

Endpoints to test:
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

**Missing/invalid token test** — remove the header or change the token value,
you should get:
```json
{
  "status": "error",
  "message": "Unauthorized access. Valid API token is required."
}
```
with a `401` status code.

### 6. The frontend ("Kusina")
A working web app lives in `web/index.html` that consumes this API directly —
browse/search/filter dishes, view full details and ingredients in a modal, and
add new recipes through a form that POSTs to `/api/foods`.

**How to run it:**
1. Keep your Slim server running (`php -S localhost:8000 -t public`, or via
   XAMPP/Apache as described above)
2. Open `web/index.html` in your browser — no build step needed, it's plain
   HTML/CSS/JS
3. If your API runs on a different host/port, edit the `API_BASE` constant near
   the top of the `<script>` in `index.html`

Everything testable in Thunder Client can also be done visually here: search
foods, filter by category, click a dish to see its full recipe, and submit new
recipes with checkboxes for ingredients.

## Notes on Design Choices

- `food_id` in the SQL script is a plain `INT PRIMARY KEY` (not
  `AUTO_INCREMENT`), so the `POST /api/foods` handler calculates the next ID
  manually (`MAX(food_id) + 1`) inside a transaction before inserting.
- The token check middleware (`$authMiddleware`) is attached only to the
  `/api` route group, so `/` stays public.
- All food-returning endpoints reuse `buildFoodPayload()` and
  `getIngredientsForFood()` so the ingredient list is always pulled fresh from
  the `food_ingredients` junction table.
- CORS headers are added so the frontend can call the API from the browser,
  and the app auto-detects its base path so it works whether run standalone
  or deployed inside a subfolder under Apache.
