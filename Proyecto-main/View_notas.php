<?php
session_start();
include 'db.php'; 

// Verificar si el usuario es un doctor autenticado
if (!isset($_SESSION['id_usuario']) || $_SESSION['tipo'] != 'Doctor') {
    header("Location: login.php");
    exit();
}

$id_doctor = $_SESSION['id_usuario'];

try {
    // Obtener nombre del doctor
    $sql_doctor = "SELECT nombre FROM Usuario WHERE id_usuario = :id_doctor";
    $stmt_doctor = $conexion->prepare($sql_doctor);
    $stmt_doctor->bindParam(':id_doctor', $id_doctor, PDO::PARAM_INT);
    $stmt_doctor->execute();
    $doctor = $stmt_doctor->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        throw new Exception("No se encontró información del doctor");
    }

    // Obtener lista de pacientes
    $sql_pacientes = "SELECT id_usuario, nombre FROM Usuario WHERE tipo = 'Paciente' ORDER BY nombre";
    $stmt_pacientes = $conexion->prepare($sql_pacientes);
    $stmt_pacientes->execute();
    $pacientes = $stmt_pacientes->fetchAll(PDO::FETCH_ASSOC);

    // Obtener notas médicas existentes
    $sql_notas = "SELECT h.id_historial, h.id_paciente, 
                 p.nombre as paciente, h.diagnostico, 
                 h.tratamiento, h.fecha_registro as fecha,
                 d.nombre as nombre_doctor
                 FROM HistorialMedico h
                 JOIN Usuario p ON h.id_paciente = p.id_usuario
                 JOIN Usuario d ON h.id_doctor = d.id_usuario
                 WHERE h.id_doctor = :id_doctor
                 ORDER BY h.fecha_registro DESC";
    
    $stmt = $conexion->prepare($sql_notas);
    $stmt->bindParam(':id_doctor', $_SESSION['id_usuario'], PDO::PARAM_INT);
    $stmt->execute();
    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_notas = "Error al cargar datos: " . $e->getMessage();
    error_log($error_notas);
} catch (Exception $e) {
    $error_notas = $e->getMessage();
    error_log($error_notas);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas Médicas - Portal del Doctor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fas fa-user-md text-blue-500 text-2xl"></i>
                <h1 class="text-xl font-bold text-gray-800">Dr. <?php echo htmlspecialchars($doctor['nombre']); ?></h1>
            </div>
            <nav class="hidden md:flex space-x-6">
                <a href="View_doctor.php" class="text-gray-600 hover:text-blue-500">Inicio</a>
                <a href="View_horario.php" class="text-gray-600 hover:text-blue-500">Agendar</a>
                <a href="View_citas.php" class="text-gray-600 hover:text-blue-500">Citas</a>
                <a href="View_notas.php" class="text-blue-500 font-medium">Notas</a>
                <form action="cerrar.php" method="post">
                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Cerrar Sesión</span>
                    </button>
                </form>
            </nav>
            <button class="md:hidden text-gray-600">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Registro de Notas Médicas</h2>

            <!-- Formulario de Notas -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form id="formNotas" class="space-y-4">
                    <div>
                        <label for="id_paciente" class="block text-sm font-medium text-gray-700 mb-1">Paciente:</label>
                        <select id="id_paciente" name="id_paciente" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Seleccione un paciente</option>
                            <?php foreach ($pacientes as $paciente): ?>
                                <option value="<?= htmlspecialchars($paciente['id_usuario']) ?>">
                                    <?= htmlspecialchars($paciente['nombre']) ?> (ID: <?= htmlspecialchars($paciente['id_usuario']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="diagnostico" class="block text-sm font-medium text-gray-700 mb-1">Diagnóstico:</label>
                        <textarea id="diagnostico" name="diagnostico" rows="4" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div>
                        <label for="tratamiento" class="block text-sm font-medium text-gray-700 mb-1">Tratamiento:</label>
                        <textarea id="tratamiento" name="tratamiento" rows="4" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="button" id="btnGuardar" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Guardar Nota
                        </button>
                    </div>
                </form>
            </div>

            
            <!-- Lista de Notas Existentes -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Notas Anteriores</h3>
                <div class="space-y-4">
                    <?php if (empty($notas)): ?>
                        <p class="text-gray-500">No hay notas registradas.</p>
                    <?php else: ?>
                        <?php foreach ($notas as $nota): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-semibold text-gray-800">
                                    Paciente: <?= htmlspecialchars($nota['paciente']) ?> (ID: <?= htmlspecialchars($nota['id_paciente']) ?>)
                                </h4>
                                <span class="text-sm text-gray-500"><?= htmlspecialchars($nota['fecha']) ?></span>
                            </div>
                            <div class="mb-2">
                                <h5 class="font-medium text-gray-700">Diagnóstico:</h5>
                                <p class="text-gray-600"><?= nl2br(htmlspecialchars($nota['diagnostico'])) ?></p>
                            </div>
                            <div>
                                <h5 class="font-medium text-gray-700">Tratamiento:</h5>
                                <p class="text-gray-600"><?= nl2br(htmlspecialchars($nota['tratamiento'])) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 max-w-sm w-full">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                    <i class="fas fa-check text-green-600"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mt-3">¡Éxito!</h3>
                <div class="mt-2">
                    <p class="text-sm text-gray-500" id="modalMessage"></p>
                </div>
                <div class="mt-4">
                    <button type="button" onclick="document.getElementById('successModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                        Aceptar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 max-w-sm w-full">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-times text-red-600"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mt-3">Error</h3>
                <div class="mt-2">
                    <p class="text-sm text-gray-500" id="errorModalMessage"></p>
                </div>
                <div class="mt-4">
                    <button type="button" onclick="document.getElementById('errorModal').classList.add('hidden')" 
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                        Aceptar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Asignar evento al botón
            document.getElementById('btnGuardar').addEventListener('click', guardarNota);
        });

        async function guardarNota() {
            const form = document.getElementById('formNotas');
            const boton = document.getElementById('btnGuardar');
            const id_paciente = document.getElementById('id_paciente').value;
            const diagnostico = document.getElementById('diagnostico').value;
            const tratamiento = document.getElementById('tratamiento').value;

            console.log("Intentando guardar nota..."); // Debug

            // Validación básica
            if (!id_paciente || !diagnostico || !tratamiento) {
                mostrarMensajeError('Todos los campos son obligatorios');
                return;
            }

            // Mostrar estado de carga
            const textoOriginal = boton.innerHTML;
            boton.disabled = true;
            boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            try {
                console.log("Enviando datos al servidor..."); // Debug
                
                const response = await fetch('guardar_historial.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        id_paciente: id_paciente,
                        diagnostico: diagnostico,
                        tratamiento: tratamiento
                    })
                });

                console.log("Respuesta recibida:", response); // Debug

                const data = await response.json();
                console.log("Datos de respuesta:", data); // Debug

                if (!response.ok) {
                    throw new Error(data.message || 'Error en la solicitud');
                }

                if (data.status === 'success') {
                    mostrarMensajeExito('Nota médica guardada correctamente');
                    form.reset();
                    // Recargar la página después de 1.5 segundos
                    setTimeout(() => location.reload(), 1500);
                } else {
                    throw new Error(data.message || 'Error al guardar la nota');
                }
            } catch (error) {
                console.error('Error al guardar:', error);
                mostrarMensajeError(error.message || 'Error al conectar con el servidor');
            } finally {
                boton.disabled = false;
                boton.innerHTML = textoOriginal;
            }
        }

        function mostrarMensajeExito(mensaje) {
            console.log("Éxito:", mensaje); // Debug
            const modal = document.getElementById('successModal');
            const modalMessage = document.getElementById('modalMessage');
            modalMessage.textContent = mensaje;
            modal.classList.remove('hidden');
        }

        function mostrarMensajeError(mensaje) {
            console.error("Error:", mensaje); // Debug
            const modal = document.getElementById('errorModal');
            const modalMessage = document.getElementById('errorModalMessage');
            modalMessage.textContent = mensaje;
            modal.classList.remove('hidden');
        }
    </script>
</body>

</html>