<?php

require 'config.php';

header('Content-Type: application/json');

$base_path = '/diet-api';
$request_uri = str_replace($base_path, '', $_SERVER['REQUEST_URI']);
$request_uri = parse_url($request_uri, PHP_URL_PATH);

$method = $_SERVER['REQUEST_METHOD'];

class CalorieCounter {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function handleRequest($method, $uri) {
        if ($method === 'POST' && $uri === '/patients') {
            $this->createPatient();
        } elseif ($method === 'GET' && $uri === '/patients') {
            $this->getPatients();
        } elseif ($method === 'POST' && $uri === '/foods') {
            $this->addFood();
        } elseif ($method === 'DELETE' && strpos($uri, '/foods/') === 0) {
            $food_id = explode('/', $uri)[2];
            $this->removeFood($food_id);
        } elseif ($method === 'POST' && $uri === '/logs') {
            $this->addLog();
        } elseif ($method === 'PUT' && strpos($uri, '/logs/') === 0) {
            $log_id = explode('/', $uri)[2];
            $this->updateLog($log_id);
        } elseif ($method === 'DELETE' && strpos($uri, '/logs/') === 0) {
            $log_id = explode('/', $uri)[2];
            $this->deleteLog($log_id);
        } elseif ($method === 'GET' && strpos($uri, '/patients/') === 0 && strpos($uri, '/calories') !== false) {
            $patient_id = explode('/', $uri)[2];
            $this->getTotalCalories($patient_id);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'What are you looking for? This endpoint doesn\'t exist.']);
        }
    }

    // Create a patient
    private function createPatient() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['first_name']) || empty($input['last_name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'First name and last name are required']);
            return;
        }

        $stmt = $this->pdo->prepare("INSERT INTO patients (first_name, last_name) VALUES (?, ?)");
        if ($stmt->execute([$input['first_name'], $input['last_name']])) {
            http_response_code(201);
            echo json_encode(['id' => $this->pdo->lastInsertId()]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Something went wrong while creating the patient, only one good knows what']);
        }
    }

    // Get all patients
    private function getPatients() {
        $stmt = $this->pdo->query("SELECT * FROM patients");
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($patients);
    }

    // Add a food item
    private function addFood() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['name']) || empty($input['calories_per_100g'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Food name and calories per 100g are required!']);
            return;
        }

        $stmt = $this->pdo->prepare("INSERT INTO foods (name, calories_per_100g) VALUES (?, ?)");
        if ($stmt->execute([$input['name'], $input['calories_per_100g']])) {
            http_response_code(201);
            echo json_encode(['id' => $this->pdo->lastInsertId()]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add the food item']);
        }
    }

    // Remove a food item (soft delete)
    private function removeFood($food_id) {
        // Here check if the food exists, otherwise it always return successs
        $stmt = $this->pdo->prepare("SELECT id FROM foods WHERE id = ? AND is_deleted = FALSE");
        $stmt->execute([$food_id]);
        $food = $stmt->fetch();
    
        if (!$food) {
            http_response_code(404);
            echo json_encode(['error' => 'Food item not found or already deleted']);
            return;
        }
    
        // Proceed with deletion (soft delete)
        $stmt = $this->pdo->prepare("UPDATE foods SET is_deleted = TRUE WHERE id = ?");
        if ($stmt->execute([$food_id])) {
            echo json_encode(['message' => 'Food item marked as deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete the food item']);
        }
    }
    

    // Add a food log
    private function addLog() {
        $input = json_decode(file_get_contents('php://input'), true);

        $today = date("Y-m-d");        

        if (empty($input['patient_id']) || empty($input['food_id']) || empty($input['quantity']) || empty($input['consumed_at'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing some important stuff, check the params and try again.. or FAIL gracefully !?']);
            return;
        }
        
        // Check if input date is bigger than today, because a person cannot know how much he will eat in the future
        if($input['consumed_at'] > $today ) {
            http_response_code(400);
            echo json_encode(['error' => 'Inavlid date, the closest date is today!']);
            return;
        }

        // Check if the food exists and is not deleted
        $stmt = $this->pdo->prepare("SELECT calories_per_100g FROM foods WHERE id = ? AND is_deleted = FALSE");
        $stmt->execute([$input['food_id']]);
        $food = $stmt->fetch();

        if (!$food) {
            http_response_code(400);
            echo json_encode(['error' => 'Food not found or is deleted']);
            return;
        }

        // Check if the patient exists
        $stmt = $this->pdo->prepare("SELECT id FROM patients WHERE id = ?");
        $stmt->execute([$input['patient_id']]);
        $patient = $stmt->fetch();

        if (!$patient) {
            http_response_code(400);
            echo json_encode(['error' => 'Patient not found']);
            return;
        }

        // Insert food log
        $stmt = $this->pdo->prepare("INSERT INTO food_logs (patient_id, food_id, quantity, consumed_at, calories_per_100g) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$input['patient_id'], $input['food_id'], $input['quantity'], $input['consumed_at'], $food['calories_per_100g']])) {
            http_response_code(201);
            echo json_encode(['id' => $this->pdo->lastInsertId()]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add the food log']);
        }
    }

    // Update a food log
    private function updateLog($log_id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $today = date("Y-m-d");        

        $updates = [];
        $params = [];

        if (isset($input['quantity'])) {
            $updates[] = 'quantity = ?';
            $params[] = $input['quantity'];
        }

        if (isset($input['consumed_at'])) {
            $updates[] = 'consumed_at = ?';
            $params[] = $input['consumed_at'];
        }

        if($input['consumed_at'] > $today ) {
            http_response_code(400);
            echo json_encode(['error' => 'Inavlid date, the closest date is today!']);
            return;
        }

        if (isset($input['food_id'])) {
            // Check if the new food exists and is not deleted
            $stmt = $this->pdo->prepare("SELECT id, calories_per_100g FROM foods WHERE id = ? AND is_deleted = FALSE");
            $stmt->execute([$input['food_id']]);
            $new_food = $stmt->fetch();

            if (!$new_food) {
                http_response_code(400);
                echo json_encode(['error' => 'New food not found or it\'s deleted']);
                return;
            }

            $updates[] = 'food_id = ?';
            $params[] = $new_food['id'];
            $updates[] = 'calories_per_100g = ?';
            $params[] = $new_food['calories_per_100g'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nothing to update']);
            return;
        }

        $params[] = $log_id;
        $query = "UPDATE food_logs SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($query);
        if ($stmt->execute($params)) {
            echo json_encode(['message' => 'Log updated']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update the log']);
        }
    }

    // Delete a food log
    private function deleteLog($log_id) {
        // Check if the log exists
        $stmt = $this->pdo->prepare("SELECT id FROM food_logs WHERE id = ?");
        $stmt->execute([$log_id]);
        $log = $stmt->fetch();
    
        if (!$log) {
            http_response_code(404);
            echo json_encode(['error' => 'Log not found, might have been already deleted!']);
            return;
        }
    
        // Proceed with deletion
        $stmt = $this->pdo->prepare("DELETE FROM food_logs WHERE id = ?");
        $stmt->execute([$log_id]);
    
        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Log deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete the log']);
        }
    }
    

    // Get total calories consumed by a patient on a specific day
    private function getTotalCalories($patient_id) {
        $date = $_GET['date'] ?? date('Y-m-d'); // Default to today if no date is provided
        $today = date('Y-m-d'); // today because we want to limit the user to put year 3023 etc etc

        // First, check if the patient exists
        $stmt = $this->pdo->prepare("SELECT id FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch();
    
        if (!$patient) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Patient not found.'
            ]);
            return;
        }

        if($date > $today ) {
            http_response_code(400);
            echo json_encode(['error' => 'Inavlid date, the closest date is today!']);
            return;
        }
    
        // Now, check if there are any logs for this patient on the given date
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS log_count FROM food_logs WHERE patient_id = ? AND consumed_at = ?");
        $stmt->execute([$patient_id, $date]);
        $logCount = $stmt->fetch(PDO::FETCH_ASSOC)['log_count'];
    
        if ($logCount == 0) {
            http_response_code(404);
            echo json_encode([
                'error' => 'No logs for this patient on this date.'
            ]);
            return;
        }
    
        // Fetch total calories if logs exist
        $stmt = $this->pdo->prepare("
            SELECT SUM(quantity * calories_per_100g / 100) AS total_calories
            FROM food_logs
            WHERE patient_id = ? AND consumed_at = ?
        ");
        if ($stmt->execute([$patient_id, $date])) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'patient_id' => $patient_id,
                'date' => $date,
                'total_calories' => $result['total_calories'] ?? 0
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch calories']);
        }
    }
    
    
}

$calorieCounter = new CalorieCounter($pdo);
$calorieCounter->handleRequest($method, $request_uri);
?>