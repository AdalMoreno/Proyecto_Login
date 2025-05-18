<?php
session_start();
include 'db.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo'] != 'Doctor') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit();
}

// Obtener datos del cuerpo de la solicitud
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['id_cita']) || !isset($data['estado'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit();
}

$id_cita = $data['id_cita'];
$estado = $data['estado'];
$id_doctor = $_SESSION['id_usuario'];

// Validar estados permitidos
$estadosPermitidos = ['Pendiente', 'Confirmada', 'Cancelada', 'Completada'];
if (!in_array($estado, $estadosPermitidos)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Estado no válido']);
    exit();
}

try {
    // Verificar que la cita pertenece al doctor
    $sqlVerificar = "SELECT id_cita FROM Citas WHERE id_cita = :id_cita AND id_doctor = :id_doctor";
    $stmtVerificar = $conexion->prepare($sqlVerificar);
    $stmtVerificar->bindParam(':id_cita', $id_cita, PDO::PARAM_INT);
    $stmtVerificar->bindParam(':id_doctor', $id_doctor, PDO::PARAM_INT);
    $stmtVerificar->execute();
    
    if ($stmtVerificar->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No tienes permiso para modificar esta cita']);
        exit();
    }

    // Actualizar el estado
    $sqlActualizar = "UPDATE Citas SET estado = :estado WHERE id_cita = :id_cita";
    $stmtActualizar = $conexion->prepare($sqlActualizar);
    $stmtActualizar->bindParam(':estado', $estado, PDO::PARAM_STR);
    $stmtActualizar->bindParam(':id_cita', $id_cita, PDO::PARAM_INT);
    
    if ($stmtActualizar->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Estado actualizado correctamente']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el estado']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>