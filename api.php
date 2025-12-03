<?php
/**
 * API для збереження та отримання глітч-ефектів
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataFile = __DIR__ . '/glitch_data.json';
$maxDataSize = 1048576;

function validateData($data) {
    if (!isset($data['text']) || !isset($data['css'])) {
        return ['valid' => false, 'error' => 'Відсутні обов\'язкові поля'];
    }
    
    if (!is_string($data['text']) || !is_string($data['css'])) {
        return ['valid' => false, 'error' => 'Невірний тип даних'];
    }
    
    if (strlen($data['text']) > 1000) {
        return ['valid' => false, 'error' => 'Текст занадто довгий'];
    }
    
    if (strlen($data['css']) > 50000) {
        return ['valid' => false, 'error' => 'CSS занадто великий'];
    }
    
    $data['text'] = htmlspecialchars($data['text'], ENT_QUOTES, 'UTF-8');
    
    return ['valid' => true, 'data' => $data];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $rawInput = file_get_contents('php://input');
        
        if (empty($rawInput)) {
            throw new Exception('Порожній запит');
        }
        
        if (strlen($rawInput) > $maxDataSize) {
            throw new Exception('Дані занадто великі');
        }
        
        $inputData = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Невалідний JSON');
        }
        
        $validation = validateData($inputData);
        if (!$validation['valid']) {
            throw new Exception($validation['error']);
        }
        
        $dataToSave = $validation['data'];
        $dataToSave['timestamp'] = time();
        $dataToSave['updated_at'] = date('Y-m-d H:i:s');
        
        $jsonData = json_encode($dataToSave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($jsonData === false) {
            throw new Exception('Помилка кодування JSON');
        }
        
        $tempFile = $dataFile . '.tmp';
        if (file_put_contents($tempFile, $jsonData, LOCK_EX) === false) {
            throw new Exception('Не вдалося записати дані');
        }
        
        if (!rename($tempFile, $dataFile)) {
            @unlink($tempFile);
            throw new Exception('Не вдалося завершити збереження');
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Дані успішно збережено',
            'timestamp' => $dataToSave['timestamp']
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (!file_exists($dataFile)) {
            $defaultData = [
                'text' => 'GLITCH',
                'css' => '.my-glitch { font-size: 3em; color: #fff; font-weight: bold; }',
                'timestamp' => time(),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            echo json_encode($defaultData, JSON_UNESCAPED_UNICODE);
            exit();
        }
        
        $content = @file_get_contents($dataFile);
        
        if ($content === false) {
            throw new Exception('Не вдалося прочитати файл');
        }
        
        if (empty($content)) {
            throw new Exception('Файл порожній');
        }
        
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Пошкоджені дані');
        }
        
        if (!isset($data['text']) || !isset($data['css'])) {
            throw new Exception('Невалідна структура даних');
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'text' => 'ERROR',
            'css' => '.my-glitch { color: red; font-size: 2em; }'
        ], JSON_UNESCAPED_UNICODE);
    }
}

else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Метод не підтримується'
    ], JSON_UNESCAPED_UNICODE);
}
?>
