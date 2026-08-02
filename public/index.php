<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';


// APP TOKEN (Part 3-C)

define('API_TOKEN', 'dmmmsu-cookbook-token-2026');


// DATABASE CONNECTION (Part 3-A) - PDO

function getDbConnection(): PDO
{
    $host = '127.0.0.1';
    $db   = 'filipino_cookbook_api';
    $user = 'root';
    $pass = ''; 
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    return new PDO($dsn, $user, $pass, $options);
}


// HELPER: standard JSON response (Part 3-B)

function jsonResponse(Response $response, $data, int $status = 200): Response
{
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

$app = AppFactory::create();


$scriptName = $_SERVER['SCRIPT_NAME']; // e.g. /filipino-cookbook-api/public/index.php
$basePath = str_replace('\\', '/', dirname($scriptName));
if ($basePath !== '/' && $basePath !== '\\') {
    $app->setBasePath($basePath);
}

//catch PHP errors as JSON instead of blank page
$app->addErrorMiddleware(true, true, true);


$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});


$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});


// TOKEN MIDDLEWARE (Part 3-C)

$authMiddleware = function (Request $request, $handler) {
    $authHeader = $request->getHeaderLine('Authorization');

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        $response = new \Slim\Psr7\Response();
        return jsonResponse($response, [
            'status'  => 'error',
            'message' => 'Unauthorized access. Valid API token is required.'
        ], 401);
    }

    $token = trim(str_replace('Bearer', '', $authHeader));

    if ($token !== API_TOKEN) {
        $response = new \Slim\Psr7\Response();
        return jsonResponse($response, [
            'status'  => 'error',
            'message' => 'Unauthorized access. Valid API token is required.'
        ], 401);
    }

    return $handler->handle($request);
};


// Part 4.1: PUBLIC WELCOME ROUTE (no token required)

$app->get('/', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'message' => 'Welcome to the Secured Filipino Cookbook API',
        'note'    => 'Use a valid Bearer token to access /api endpoints.'
    ]);
});


// Helper: fetch ingredient names for one food_id

function getIngredientsForFood(PDO $db, int $foodId): array
{
    $stmt = $db->prepare(
        "SELECT i.ingredient_name
         FROM food_ingredients fi
         JOIN ingredients i ON i.ingredient_id = fi.ingredient_id
         WHERE fi.food_id = :food_id
         ORDER BY i.ingredient_name ASC"
    );
    $stmt->execute(['food_id' => $foodId]);
    return array_column($stmt->fetchAll(), 'ingredient_name');
}

// Helper: build one food array (with category/origin/ingredients) from a row
function buildFoodPayload(PDO $db, array $row): array
{
    return [
        'food_id'      => (int)$row['food_id'],
        'food_name'    => $row['food_name'],
        'category_name'=> $row['category_name'],
        'origin_name'  => $row['origin_name'],
        'instructions' => $row['instructions'],
        'ingredients'  => getIngredientsForFood($db, (int)$row['food_id']),
    ];
}


// /api routes group - all require the Bearer token

$app->group('/api', function ($group) {

    // Part 4.2: GET ALL FOODS
    $group->get('/foods', function (Request $request, Response $response) {
        $db = getDbConnection();

        $stmt = $db->query(
            "SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
             FROM foods f
             JOIN categories c ON c.category_id = f.category_id
             JOIN origins o ON o.origin_id = f.origin_id
             ORDER BY f.food_id ASC"
        );
        $rows = $stmt->fetchAll();

        $foods = array_map(fn($row) => buildFoodPayload($db, $row), $rows);

        return jsonResponse($response, $foods);
    });

    // Part 4.3: GET FOOD BY ID
    $group->get('/foods/{id}', function (Request $request, Response $response, array $args) {
        $db = getDbConnection();
        $id = (int)$args['id'];

        $stmt = $db->prepare(
            "SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
             FROM foods f
             JOIN categories c ON c.category_id = f.category_id
             JOIN origins o ON o.origin_id = f.origin_id
             WHERE f.food_id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Food not found'
            ], 404);
        }

        return jsonResponse($response, buildFoodPayload($db, $row));
    });

    // Part 4.4: SEARCH FOOD BY NAME
    $group->get('/foods/search/{name}', function (Request $request, Response $response, array $args) {
        $db = getDbConnection();
        $name = $args['name'];

        $stmt = $db->prepare(
            "SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
             FROM foods f
             JOIN categories c ON c.category_id = f.category_id
             JOIN origins o ON o.origin_id = f.origin_id
             WHERE f.food_name LIKE :name
             ORDER BY f.food_id ASC"
        );
        $stmt->execute(['name' => '%' . $name . '%']);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'No foods matched your search'
            ], 404);
        }

        $foods = array_map(fn($row) => buildFoodPayload($db, $row), $rows);

        return jsonResponse($response, $foods);
    });

    // Part 4.5: GET ALL CATEGORIES
    $group->get('/categories', function (Request $request, Response $response) {
        $db = getDbConnection();
        $stmt = $db->query("SELECT category_id, category_name FROM categories ORDER BY category_id ASC");
        return jsonResponse($response, $stmt->fetchAll());
    });

    // Part 4.6: GET ALL INGREDIENTS
    $group->get('/ingredients', function (Request $request, Response $response) {
        $db = getDbConnection();
        $stmt = $db->query("SELECT ingredient_id, ingredient_name FROM ingredients ORDER BY ingredient_id ASC");
        return jsonResponse($response, $stmt->fetchAll());
    });

    // NEW FEATURE 1: GET ALL ORIGINS
    // The lab spec never exposed this, even though foods reference an origin_id.
    $group->get('/origins', function (Request $request, Response $response) {
        $db = getDbConnection();
        $stmt = $db->query("SELECT origin_id, origin_name FROM origins ORDER BY origin_id ASC");
        return jsonResponse($response, $stmt->fetchAll());
    });

    // NEW FEATURE 2: GET A RANDOM FOOD
    // Useful for a "surprise me" / "what should I cook today" button on the frontend.
    $group->get('/foods/random', function (Request $request, Response $response) {
        $db = getDbConnection();

        $stmt = $db->query(
            "SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
             FROM foods f
             JOIN categories c ON c.category_id = f.category_id
             JOIN origins o ON o.origin_id = f.origin_id
             ORDER BY RAND()
             LIMIT 1"
        );
        $row = $stmt->fetch();

        if (!$row) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'No foods available'
            ], 404);
        }

        return jsonResponse($response, buildFoodPayload($db, $row));
    });

    // NEW FEATURE 3: FILTER FOODS BY CATEGORY
    $group->get('/foods/category/{id}', function (Request $request, Response $response, array $args) {
        $db = getDbConnection();
        $categoryId = (int)$args['id'];

        $stmt = $db->prepare(
            "SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
             FROM foods f
             JOIN categories c ON c.category_id = f.category_id
             JOIN origins o ON o.origin_id = f.origin_id
             WHERE f.category_id = :category_id
             ORDER BY f.food_id ASC"
        );
        $stmt->execute(['category_id' => $categoryId]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'No foods found for that category'
            ], 404);
        }

        $foods = array_map(fn($row) => buildFoodPayload($db, $row), $rows);
        return jsonResponse($response, $foods);
    });

    // NEW FEATURE 4: FILTER FOODS BY ORIGIN
    $group->get('/foods/origin/{id}', function (Request $request, Response $response, array $args) {
        $db = getDbConnection();
        $originId = (int)$args['id'];

        $stmt = $db->prepare(
            "SELECT f.food_id, f.food_name, c.category_name, o.origin_name, f.instructions
             FROM foods f
             JOIN categories c ON c.category_id = f.category_id
             JOIN origins o ON o.origin_id = f.origin_id
             WHERE f.origin_id = :origin_id
             ORDER BY f.food_id ASC"
        );
        $stmt->execute(['origin_id' => $originId]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'No foods found for that origin'
            ], 404);
        }

        $foods = array_map(fn($row) => buildFoodPayload($db, $row), $rows);
        return jsonResponse($response, $foods);
    });

    // NEW FEATURE 5: UPDATE AN EXISTING FOOD
    // Completes CRUD: Create (POST /foods) already existed, this adds Update.
    $group->put('/foods/{id}', function (Request $request, Response $response, array $args) {
        $db = getDbConnection();
        $id = (int)$args['id'];
        $data = json_decode((string)$request->getBody(), true);

        // Confirm the food exists first
        $checkStmt = $db->prepare("SELECT food_id FROM foods WHERE food_id = :id");
        $checkStmt->execute(['id' => $id]);
        if (!$checkStmt->fetch()) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Food not found'
            ], 404);
        }

        $required = ['food_name', 'category_id', 'origin_id', 'instructions'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return jsonResponse($response, [
                    'status'  => 'error',
                    'message' => "Missing required field: $field"
                ], 400);
            }
        }

        $ingredientIds = $data['ingredient_ids'] ?? null;

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                "UPDATE foods
                 SET food_name = :food_name, category_id = :category_id,
                     origin_id = :origin_id, instructions = :instructions
                 WHERE food_id = :id"
            );
            $stmt->execute([
                'food_name'    => $data['food_name'],
                'category_id'  => (int)$data['category_id'],
                'origin_id'    => (int)$data['origin_id'],
                'instructions' => $data['instructions'],
                'id'           => $id,
            ]);

            // Only touch ingredient links if the request actually included ingredient_ids
            if (is_array($ingredientIds)) {
                $deleteStmt = $db->prepare("DELETE FROM food_ingredients WHERE food_id = :id");
                $deleteStmt->execute(['id' => $id]);

                if (count($ingredientIds) > 0) {
                    $ingStmt = $db->prepare(
                        "INSERT INTO food_ingredients (food_id, ingredient_id) VALUES (:food_id, :ingredient_id)"
                    );
                    foreach ($ingredientIds as $ingredientId) {
                        $ingStmt->execute([
                            'food_id'       => $id,
                            'ingredient_id' => (int)$ingredientId,
                        ]);
                    }
                }
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Failed to update food: ' . $e->getMessage()
            ], 500);
        }

        return jsonResponse($response, [
            'status'  => 'success',
            'message' => 'Food updated successfully.'
        ], 200);
    });

    // NEW FEATURE 6: DELETE A FOOD
    // Completes CRUD: Delete. food_ingredients rows for this food are removed
    // automatically by the ON DELETE CASCADE constraint from the original schema.
    $group->delete('/foods/{id}', function (Request $request, Response $response, array $args) {
        $db = getDbConnection();
        $id = (int)$args['id'];

        $checkStmt = $db->prepare("SELECT food_id FROM foods WHERE food_id = :id");
        $checkStmt->execute(['id' => $id]);
        if (!$checkStmt->fetch()) {
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Food not found'
            ], 404);
        }

        $stmt = $db->prepare("DELETE FROM foods WHERE food_id = :id");
        $stmt->execute(['id' => $id]);

        return jsonResponse($response, [
            'status'  => 'success',
            'message' => 'Food deleted successfully.'
        ], 200);
    });

    // Part 4.7: ADD NEW FOOD
    $group->post('/foods', function (Request $request, Response $response) {
        $db = getDbConnection();
        $data = json_decode((string)$request->getBody(), true);

        // Basic validation
        $required = ['food_name', 'category_id', 'origin_id', 'instructions'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return jsonResponse($response, [
                    'status'  => 'error',
                    'message' => "Missing required field: $field"
                ], 400);
            }
        }

        $ingredientIds = $data['ingredient_ids'] ?? [];

        try {
            $db->beginTransaction();

            // Get the next food_id (table uses manual INT PK, not AUTO_INCREMENT)
            $maxIdStmt = $db->query("SELECT COALESCE(MAX(food_id), 0) + 1 AS next_id FROM foods");
            $newId = (int)$maxIdStmt->fetch()['next_id'];

            $stmt = $db->prepare(
                "INSERT INTO foods (food_id, food_name, category_id, origin_id, instructions)
                 VALUES (:food_id, :food_name, :category_id, :origin_id, :instructions)"
            );
            $stmt->execute([
                'food_id'      => $newId,
                'food_name'    => $data['food_name'],
                'category_id'  => (int)$data['category_id'],
                'origin_id'    => (int)$data['origin_id'],
                'instructions' => $data['instructions'],
            ]);

            if (is_array($ingredientIds) && count($ingredientIds) > 0) {
                $ingStmt = $db->prepare(
                    "INSERT INTO food_ingredients (food_id, ingredient_id) VALUES (:food_id, :ingredient_id)"
                );
                foreach ($ingredientIds as $ingredientId) {
                    $ingStmt->execute([
                        'food_id'       => $newId,
                        'ingredient_id' => (int)$ingredientId,
                    ]);
                }
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            return jsonResponse($response, [
                'status'  => 'error',
                'message' => 'Failed to add food: ' . $e->getMessage()
            ], 500);
        }

        return jsonResponse($response, [
            'status'  => 'success',
            'message' => 'Food added successfully.'
        ], 201);
    });

})->add($authMiddleware);

$app->run();
