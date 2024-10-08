<?php
session_start();

if (!isset($_SESSION["nom_usuario"])) {
    header("Location: ../../views/iniciarSesion.php");
    exit();
}

include '../../class/database.php';

$db = new database();
$db->conectarDB();

$nom_usuario = $_POST['nom_usuario_quitar'];
$nombre_rol = $_POST['nombre_rol_quitar'];

try {
    // Obtener ID del usuario
    $stmt = $db->getPDO()->prepare("SELECT id_usuario FROM usuarios WHERE nom_usuario = ?");
    $stmt->execute([$nom_usuario]);
    $usuario = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$usuario) {
        echo "<script>alert('Usuario no encontrado.'); window.location.href = '../../views/administrador/vista_admin_darRol.php';</script>";
        exit();
    }

    // Verificar que no se esté quitando el rol de administrador a sí mismo
    if ($usuario->id_usuario == $_SESSION['id_usuario'] && $nombre_rol == 'administrador') {
        echo "<script>alert('No puedes quitarte el rol de administrador a ti mismo.'); window.location.href = '../../views/administrador/vista_admin_darRol.php';</script>";
        exit();
    }

    // Obtener ID del rol
    $stmt = $db->getPDO()->prepare("SELECT id_rol FROM roles WHERE nombre_rol = ?");
    $stmt->execute([$nombre_rol]);
    $rol = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$rol) {
        echo "<script>alert('Rol no encontrado o no permitido.'); window.location.href = '../../views/administrador/vista_admin_darRol.php';</script>";
        exit();
    }

    // Verificar si el usuario tiene el rol asignado
    $stmt = $db->getPDO()->prepare("SELECT * FROM rol_usuario WHERE usuario = ? AND rol = ?");
    $stmt->execute([$usuario->id_usuario, $rol->id_rol]);
    $rol_usuario = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$rol_usuario) {
        echo "<script>alert('El usuario no tiene este rol asignado.'); window.location.href = '../../views/administrador/vista_admin_darRol.php';</script>";
        exit();
    }

    // Quitar el rol al usuario
    $stmt = $db->getPDO()->prepare("DELETE FROM rol_usuario WHERE usuario = ? AND rol = ?");
    $stmt->execute([$usuario->id_usuario, $rol->id_rol]);

    // Si se está quitando el rol de instalador, marcarlo como inactivo
    if ($nombre_rol == 'instalador') {
        $stmt = $db->getPDO()->prepare("UPDATE instalador SET estatus = 'inactivo' WHERE persona = ?");
        $stmt->execute([$usuario->id_usuario]);
    }

    echo "<script>alert('Rol eliminado exitosamente.'); window.location.href = '../../views/administrador/vista_admin_darRol.php';</script>";
} catch (Exception $e) {
    error_log("Error al eliminar el rol: " . $e->getMessage());
    echo "<script>alert('Error al eliminar el rol.'); window.location.href = '../../views/administrador/vista_admin_darRol.php';</script>";
}
