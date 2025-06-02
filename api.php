<?php
// api.php

// --- Конфигурация Базы Данных ---
$db_path = __DIR__ . '/db/habits.sqlite';
$pdo = null;

try {
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// --- Создание таблиц, если их нет ---
$pdo->exec("CREATE TABLE IF NOT EXISTS habits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS habit_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    habit_id INTEGER NOT NULL,
    entry_date DATE NOT NULL,
    is_completed INTEGER DEFAULT 0, -- 0 for false, 1 for true
    FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE,
    UNIQUE(habit_id, entry_date) -- Одна запись на привычку в день
)");


// --- Роутинг запросов ---
$action = $_GET['action'] ?? ''; // Для GET запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Для POST, получаем action из тела JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

header('Content-Type: application/json'); // Всегда возвращаем JSON

switch ($action) {
    case 'get_habits':
        getHabits($pdo);
        break;
    case 'add_habit':
        addHabit($pdo, $input);
        break;
    case 'toggle_habit':
        toggleHabit($pdo, $input);
        break;
    case 'delete_habit':
        deleteHabit($pdo, $input);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

// --- Функции обработчики ---

function getHabits($pdo) {
    $today = date('Y-m-d');
    // Получаем все привычки и информацию о том, выполнена ли она сегодня
    $stmt = $pdo->query("
        SELECT h.id, h.name, COALESCE(he.is_completed, 0) as is_completed_today
        FROM habits h
        LEFT JOIN habit_entries he ON h.id = he.habit_id AND he.entry_date = '{$today}'
        ORDER BY h.created_at DESC
    ");
    $habits = $stmt->fetchAll();
    echo json_encode($habits);
}

function addHabit($pdo, $data) {
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Habit name is required']);
        return;
    }
    $name = htmlspecialchars(trim($data['name'])); // Базовая защита

    try {
        $stmt = $pdo->prepare("INSERT INTO habits (name) VALUES (:name)");
        $stmt->bindParam(':name', $name);
        $stmt->execute();
        $newHabitId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newHabitId, 'name' => $name, 'is_completed_today' => 0]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add habit: ' . $e->getMessage()]);
    }
}

function toggleHabit($pdo, $data) {
    $habit_id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
    $is_completed_new_status = filter_var($data['completed'] ?? 0, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0;
    $today = date('Y-m-d');

    if (!$habit_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid habit ID']);
        return;
    }

    try {
        // Проверяем, есть ли уже запись за сегодня
        $stmt_check = $pdo->prepare("SELECT id FROM habit_entries WHERE habit_id = :habit_id AND entry_date = :entry_date");
        $stmt_check->execute([':habit_id' => $habit_id, ':entry_date' => $today]);
        $entry = $stmt_check->fetch();

        if ($entry) {
            // Обновляем существующую
            $stmt = $pdo->prepare("UPDATE habit_entries SET is_completed = :is_completed WHERE id = :id");
            $stmt->execute([':is_completed' => $is_completed_new_status, ':id' => $entry['id']]);
        } else {
            // Вставляем новую
            $stmt = $pdo->prepare("INSERT INTO habit_entries (habit_id, entry_date, is_completed) VALUES (:habit_id, :entry_date, :is_completed)");
            $stmt->execute([':habit_id' => $habit_id, ':entry_date' => $today, ':is_completed' => $is_completed_new_status]);
        }
        echo json_encode(['success' => true, 'id' => $habit_id, 'completed' => $is_completed_new_status]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to toggle habit: ' . $e->getMessage()]);
    }
}

function deleteHabit($pdo, $data) {
    $habit_id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$habit_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid habit ID']);
        return;
    }

    try {
        // Удаление из habits автоматически удалит связанные записи из habit_entries из-за ON DELETE CASCADE
        $stmt = $pdo->prepare("DELETE FROM habits WHERE id = :id");
        $stmt->bindParam(':id', $habit_id);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'id' => $habit_id]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Habit not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete habit: ' . $e->getMessage()]);
    }
}

?>