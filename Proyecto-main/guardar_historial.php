<?php
session_start();
include 'db.php';

// Habilitar visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit();
}

// Verificar autenticación
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo'] !== 'Doctor') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

try {
    // Obtener datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
    }

    // Validar campos requeridos
    $required = ['id_paciente', 'diagnostico', 'tratamiento'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            throw new Exception("El campo $field es requerido");
        }
    }

    // Sanitizar y validar datos
    $id_paciente = (int)$data['id_paciente'];
    $diagnostico = trim($data['diagnostico']);
    $tratamiento = trim($data['tratamiento']);

    if ($id_paciente <= 0) {
        throw new Exception("ID de paciente inválido");
    }

    // Verificar conexión
    if (!$conexion) {
        throw new Exception("No hay conexión a la base de datos");
    }

    // Iniciar transacción
    $conexion->beginTransaction();

    // Verificar que el paciente existe
    $stmt = $conexion->prepare("SELECT id_usuario FROM Usuario WHERE id_usuario = ? AND tipo = 'Paciente'");
    $stmt->execute([$id_paciente]);
    
    if (!$stmt->fetch()) {
        throw new Exception("No existe un paciente con ID $id_paciente");
    }

    // Insertar el historial médico
    $sql = "INSERT INTO HistorialMedico 
            (id_paciente, id_doctor, diagnostico, tratamiento) 
            VALUES 
            (?, ?, ?, ?)";
    
    $stmt = $conexion->prepare($sql);
    $success = $stmt->execute([
        $id_paciente,
        $_SESSION['id_usuario'],
        $diagnostico,
        $tratamiento
    ]);

    if (!$success) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception("Error al guardar: " . $errorInfo[2]);
    }

    $conexion->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Nota médica guardada correctamente',
        'id_historial' => $conexion->lastInsertId()
    ]);

} catch (PDOException $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log("Error PDO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error en la base de datos',
        'error_details' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log("Error general: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>