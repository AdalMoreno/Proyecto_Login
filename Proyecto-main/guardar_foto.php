<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

// Validar autenticación y tipo de usuario
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo'] != 'Paciente') {
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado']);
    exit;
}

$id_paciente = $_SESSION['id_usuario'];

// Validar método POST y archivo subido
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['foto'])) {
    echo json_encode(['success' => false, 'error' => 'Solicitud inválida']);
    exit;
}

// Configuración de la carpeta y validación
$uploadDir = 'fotos_pacientes/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif, image/jpg'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Validar tipo y tamaño del archivo
if (!in_array($_FILES['foto']['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Formato no permitido (solo JPEG, PNG, GIF)']);
    exit;
}

if ($_FILES['foto']['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'La imagen supera el límite de 5MB']);
    exit;
}

// Crear carpeta si no existe
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generar nombre único para el archivo (ej: paciente_5_20240518.jpg)
$fileExt = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
$fileName = 'paciente_' . $id_paciente . '_' . date('YmdHis') . '.' . $fileExt;
$uploadPath = $uploadDir . $fileName;

try {
    // Mover el archivo subido a la carpeta de destino
    if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadPath)) {
        // Actualizar la base de datos con el nombre del archivo
        $sql = "UPDATE Usuario SET foto = :foto WHERE id_usuario = :id_paciente";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':foto', $fileName, PDO::PARAM_STR);
        $stmt->bindParam(':id_paciente', $id_paciente, PDO::PARAM_INT);
        $stmt->execute();

        // Actualizar la sesión (opcional)
        $_SESSION['foto_paciente'] = $fileName;

        // Respuesta JSON con la URL accesible desde el navegador
        echo json_encode([
            'success' => true,
            'url' => $uploadPath,  // Ruta física (puedes usar solo $fileName si prefieres)
            'fileName' => $fileName
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al mover el archivo']);
    }
} catch (PDOException $e) {
    // Eliminar la foto si falla la base de datos
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>