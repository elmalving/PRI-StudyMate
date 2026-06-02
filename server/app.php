<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:4173');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$mongoHost = getenv('MONGODB_HOST') ?: 'localhost';
$mongoPort = 27017;
$mongoDb = 'db';

try {
    $mongo = new MongoDB\Client("mongodb://{$mongoHost}:{$mongoPort}");
    $db = $mongo->selectDatabase($mongoDb);
    $usersCollection = $db->selectCollection('users');
    $assignmentsCollection = $db->selectCollection('assignments');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

switch ($requestUri) {
    case '/@me':
        getCurrentUser($usersCollection);
        break;
    case '/@assignment':
        getAssignment($usersCollection, $assignmentsCollection);
        break;
    case '/@all_email':
        getAllEmail($usersCollection);
        break;
    case '/register':
        if ($method === 'POST') {
            registerUser($usersCollection);
        }
        break;
    case '/login':
        if ($method === 'POST') {
            loginUser($usersCollection);
        }
        break;
    case '/logout':
        if ($method === 'POST') {
            logoutUser();
        }
        break;
    case '/add_assignment':
        if ($method === 'POST') {
            addAssignment($usersCollection, $assignmentsCollection);
        }
        break;
    case '/add_answer':
        if ($method === 'POST') {
            addAnswer($usersCollection, $assignmentsCollection);
        }
        break;
    case '/add_grade':
        if ($method === 'POST') {
            addGrade($usersCollection, $assignmentsCollection);
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        break;
}

function getCurrentUser($usersCollection)
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    $user = $usersCollection->findOne(['_id' => $_SESSION['user_id']]);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    echo json_encode([
        'id' => (string) $user['_id'],
        'email' => $user['email'],
        'name' => $user['name']
    ]);
}

function getAssignment($usersCollection, $assignmentsCollection)
{
    $email = $_GET['email'] ?? null;

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    $user = $usersCollection->findOne(['_id' => $_SESSION['user_id']]);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    if ($user['email'] === 'test@gmail.com' && $email) {
        $user = $usersCollection->findOne(['email' => $email]);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }
    }

    $assignments = [];
    $assignmentIds = $user['assignments'] ?? [];

    foreach ($assignmentIds as $assignmentId) {
        $assignment = $assignmentsCollection->findOne(['_id' => $assignmentId]);
        if ($assignment) {
            $assignments[] = [
                'id' => (string) $assignment['_id'],
                'subject' => $assignment['subject'] ?? null,
                'difficulty' => $assignment['difficulty'] ?? null,
                'text' => $assignment['text'] ?? null,
                'url' => $assignment['url'] ?? null,
                'assigned_date' => $assignment['assigned_date'] ?? null,
                'rect' => $assignment['rect'] ?? null,
                'answer' => $assignment['answer'] ?? null,
                'comment' => $assignment['comment'] ?? null,
                'grade' => $assignment['grade'] ?? null
            ];
        }
    }

    echo json_encode(['assignments' => $assignments]);
}

function getAllEmail($usersCollection)
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    $user = $usersCollection->findOne(['_id' => $_SESSION['user_id']]);

    if (!$user || $user['email'] !== 'test@gmail.com') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied!']);
        return;
    }

    $users = $usersCollection->find([], ['projection' => ['email' => 1, '_id' => 0]]);
    $emails = [];
    foreach ($users as $u) {
        $emails[] = ['email' => $u['email']];
    }

    echo json_encode($emails);
}

function registerUser($usersCollection)
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;
    $name = $data['firstName'] ?? null;
    $surname = $data['lastName'] ?? null;
    $degree = $data['degree'] ?? null;

    if (!$email || !$password || !$name || !$surname || !$degree) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    $existingUser = $usersCollection->findOne(['email' => $email]);
    if ($existingUser) {
        http_response_code(409);
        echo json_encode(['error' => 'Email is already used']);
        return;
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $userId = bin2hex(random_bytes(16));

    $newUser = [
        '_id' => $userId,
        'email' => $email,
        'password' => $hashedPassword,
        'name' => $name,
        'surname' => $surname,
        'degree' => $degree,
        'assignments' => []
    ];

    $usersCollection->insertOne($newUser);

    $_SESSION['user_id'] = $userId;

    echo json_encode([
        'id' => $userId,
        'email' => $email
    ]);
}

function loginUser($usersCollection)
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing email or password']);
        return;
    }

    $user = $usersCollection->findOne(['email' => $email]);

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    $_SESSION['user_id'] = (string) $user['_id'];

    echo json_encode([
        'id' => (string) $user['_id'],
        'email' => $user['email']
    ]);
}

function logoutUser()
{
    unset($_SESSION['user_id']);
    session_destroy();
    http_response_code(200);
    echo json_encode(['message' => 'Logged out']);
}

function addAssignment($usersCollection, $assignmentsCollection)
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => "Account doesn't exist"]);
        return;
    }

    $subject = $data['subject'] ?? null;
    $difficulty = $data['difficulty'] ?? null;
    $text = $data['task'] ?? null;
    $url = $data['url'] ?? null;
    $email = $data['email'] ?? null;
    $rect = $data['rectId'] ?? null;

    $user = $usersCollection->findOne(['email' => $email]);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }

    $assignmentIds = $user['assignments'] ?? [];
    foreach ($assignmentIds as $assignmentId) {
        $existing = $assignmentsCollection->findOne(['_id' => $assignmentId]);
        if ($existing && isset($existing['rect']) && $existing['rect'] === $rect) {
            http_response_code(409);
            echo json_encode(['error' => 'Rect is already occupied']);
            return;
        }
    }

    $assignmentId = bin2hex(random_bytes(16));
    $newAssignment = [
        '_id' => $assignmentId,
        'subject' => $subject,
        'difficulty' => $difficulty,
        'text' => $text,
        'url' => $url,
        'assigned_date' => new MongoDB\BSON\UTCDateTime(),
        'rect' => $rect,
        'answer' => null,
        'comment' => null,
        'grade' => null
    ];

    $assignmentsCollection->insertOne($newAssignment);

    $usersCollection->updateOne(
        ['email' => $email],
        ['$push' => ['assignments' => $assignmentId]]
    );

    echo json_encode(['success' => 'Assignment added successfully']);
}

function addAnswer($usersCollection, $assignmentsCollection)
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    $answer = $data['answer'] ?? null;
    $rect = $data['rectId'] ?? null;

    if ($answer === null || $rect === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'User not logged in']);
        return;
    }

    $user = $usersCollection->findOne(['_id' => $_SESSION['user_id']]);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }

    $assignmentIds = $user['assignments'] ?? [];
    foreach ($assignmentIds as $assignmentId) {
        $assignment = $assignmentsCollection->findOne(['_id' => $assignmentId]);
        if ($assignment && isset($assignment['rect']) && $assignment['rect'] === $rect) {
            $assignmentsCollection->updateOne(
                ['_id' => $assignmentId],
                ['$set' => ['answer' => $answer]]
            );
            echo json_encode(['success' => 'Answer added successfully']);
            return;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Assignment not found']);
}

function addGrade($usersCollection, $assignmentsCollection)
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    $comment = $data['comment'] ?? null;
    $grade = $data['grade'] ?? null;
    $email = $data['email'] ?? null;
    $rect = $data['rectId'] ?? null;

    if ($comment === null || $grade === null || $email === null || $rect === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'User not logged in']);
        return;
    }

    $user = $usersCollection->findOne(['email' => $email]);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }

    $assignmentIds = $user['assignments'] ?? [];
    foreach ($assignmentIds as $assignmentId) {
        $assignment = $assignmentsCollection->findOne(['_id' => $assignmentId]);
        if ($assignment && isset($assignment['rect']) && $assignment['rect'] === $rect) {
            $assignmentsCollection->updateOne(
                ['_id' => $assignmentId],
                ['$set' => [
                    'comment' => $comment,
                    'grade' => $grade,
                    'rect' => null
                ]]
            );
            echo json_encode(['success' => 'Grade added successfully']);
            return;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Assignment not found']);
}
