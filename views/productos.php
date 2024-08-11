<?php
session_start();
include '../class/database.php';

$id_usuario = 0;
$notificaciones = [];
$productos_por_pagina = 8; // Mostrar 8 productos por vez

if (isset($_SESSION["nom_usuario"])) {
    $user = $_SESSION["nom_usuario"];

    // Crear conexión a la base de datos
    $conexion = new database();
    $conexion->conectarDB();

    // Consulta para obtener el rol del usuario basado en el nombre de usuario
    $consulta_rol = "CALL roles_usuario(?)";
    $params_rol = [$user];
    $resultado_rol = $conexion->seleccionar($consulta_rol, $params_rol);

    if ($resultado_rol && !empty($resultado_rol)) {
        $nombre_rol = $resultado_rol[0]->nombre_rol;

        // Consulta para obtener los IDs del cliente e instalador basado en el nombre de usuario
        $consulta_ids = "CALL obtener_id_por_nombre_usuario(?)";
        $params_ids = [$user];
        $resultado_ids = $conexion->seleccionar($consulta_ids, $params_ids);

        if ($resultado_ids && !empty($resultado_ids)) {
            $fila = $resultado_ids[0];

            if ($nombre_rol == 'cliente' && isset($fila->id_cliente)) {
                $id_cliente = $fila->id_cliente;
                $id_usuario = $id_cliente;

                $consultaNotificaciones = "SELECT notificacion, fecha FROM notificaciones_cliente WHERE cliente = ?";
                $paramsNotificaciones = [$id_cliente];
                $notificaciones = $conexion->seleccionar($consultaNotificaciones, $paramsNotificaciones);

            } elseif ($nombre_rol == 'instalador' && isset($fila->id_instalador)) {
                $id_instalador = $fila->id_instalador;
                $id_usuario = $id_instalador;

                $consultaNotificaciones = "SELECT notificacion, fecha FROM notificaciones_instalador WHERE instalador = ?";
                $paramsNotificaciones = [$id_instalador];
                $notificaciones = $conexion->seleccionar($consultaNotificaciones, $paramsNotificaciones);
            }
        }
    }
}

$conexion = new database();
$conexion->conectarDB();

$productos_espera = [];

// Cargar los primeros 8 productos
$consulta_productos = "
    SELECT p.id_producto, p.nombre, p.precio, i.imagen
    FROM productos p
    LEFT JOIN imagen i ON p.id_producto = i.producto
    WHERE p.estatus = 'activo'
    LIMIT $productos_por_pagina
";
$productos_espera = $conexion->seleccionar($consulta_productos);

$total_productos = count($productos_espera);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Glass Store</title>
  <link rel="shortcut icon" href="../img/index/logoVarianteSmall.png" type="image/x-icon">
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/bootstrap-5.3.3-dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../css/normalized.css">
  <style>
    .card-img-left {
        border-radius: 10px;
    }

    .card {
        border-radius: 10px;
        margin-bottom: 20px;
    }
  </style>
</head>
<body>
    <!-- whatsapp flotante -->
  <div id="wa-button">
    <a href="https://api.whatsapp.com/send?phone=528717843809" target="_blank">
      <img src="../img/index/whatsappFloat.svg" alt="Contáctanos por WhatsApp">
    </a>
  </div>
    <!-- barra superior -->
    <div class="container blue">
      <div class="navbar-top">
          <div class="social-link">
              <a href="https://api.whatsapp.com/send?phone=528717843809" target="_blank" ><img src="../img/index/whatsapp.svg" alt="" width="30px"></a>
              <a href="https://www.facebook.com/profile.php?id=100064181314031" target="_blank"><img src="../img/index/facebook.svg" alt="" width="30px"></a>
              <a href="https://www.instagram.com/glassstoretrc?igsh=MXVhdHh1MDVhOGxzeA==" target="_blank"><img src="../img/index/instagram.svg" alt="" width="30px"></a>
          </div>

          <div class="logo">
              <img src="../img/index/GLASS.png" alt="" class="logo">
          </div>
          <div class="icons">
                <a href="../../"><img src="../img/index/inicio.svg" alt="" width="25px"></a>
                <button class="botonMostrarFavoritos" data-bs-toggle="modal" data-bs-target="#favoritosModal"><img src="../img/index/favorites.svg" alt="" width="25px"></button>

                <a id="carrito" data-bs-toggle="modal" data-bs-target="#carritoModal"><img src="../img/index/clip.svg" alt="" width="25px"></a>

                <div class="dropdown">
                    <a href="#" id="user-icon" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="../img/index/user.svg" alt="" width="25px" style="cursor: pointer">
                    </a>
                    <?php
                    
                    if (isset($_SESSION["nom_usuario"])) {
                        echo '<ul class="dropdown-menu" aria-labelledby="user-icon">';
                        echo '<li><a class="dropdown-item" href="./cliente/perfil.php">Perfil</a></li>';
                        echo '<li><a class="dropdown-item" href="#" id="notification-icon" data-bs-toggle="modal" data-bs-target="#notificationModal">Notificaciones</a></li>';
                        $user = $_SESSION["nom_usuario"];
                        $consulta = "CALL roles_usuario(?)";
                        $params = [$user];
                        $roles = $conexion->seleccionar($consulta, $params);
                        if ($roles) {
                            foreach ($roles as $rol) {
                                if ($rol->nombre_rol == 'administrador') {
                                    echo '<li><a class="dropdown-item" href="../views/administrador/vista_admin.php">Administrador</a></li>';
                                } elseif ($rol->nombre_rol == 'instalador') {
                                    echo '<li><a class="dropdown-item" href="../views/instalador/index_Instalador.php">Buzón</a></li>';
                                }
                            }
                        }
                        echo '<li><hr class="dropdown-divider"></li>';
                        echo '<li><a class="dropdown-item" href="../scripts/cerrarSesion.php">Cerrar Sesión</a></li>';
                        echo '</ul>';
                    } else {
                        echo '<ul class="dropdown-menu" aria-labelledby="user-icon">';
                        echo '<li><a class="dropdown-item" href="../views/iniciarSesion.php">Iniciar Sesión</a></li>';
                        echo '</ul>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

  <!-- segunda barra -->
  <nav class="navbar sticky-top navbar-expand-md" id="navbar-color">
    <div class="container-fluid">
        <!-- menú hamburguesa -->
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
            <span><i><img src="../img/index/menu.svg" alt="Menu" width="30px"></i></span>
        </button>
        <div class="offcanvas offcanvas-start" id="offcanvasNavbar">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title">Menú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link"  href="/">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-left" href="https://api.whatsapp.com/send?phone=8717843809" target="_blank">Contacto</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-left" href="/views/citas.php">Agendar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-left" href="/#about-us">Nosotros</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- banner -->
<main>
    <div class="main-content-products">
        <div class="content-products">
            <h1>" TRANSFORMA TU ESPACIO CON ESTILO Y DISTINCIÓN "</h1><br>
            <div class="busqueda mx-auto">
                <input type="text" placeholder="Buscar" class="buscar-input" id="search-input" autocomplete="off" name="nombre_producto" style="display: flex; align-items: center; width: 100%;">
                <img src="../img/productos/search.svg" alt="Buscar" id="search-button" style="cursor: pointer; margin-left: 10px;">
            </div>
        </div>
    </div>
</main>

<!-- aquí se cargan los productos con imágenes -->
<div class="container">
    <div class="row" style="margin-top: 50px;" id="product-list">
        <?php
        if (!empty($productos_espera)) {
            foreach ($productos_espera as $producto) {
                $imagen = $producto->imagen ? '../img/index/' . $producto->imagen : '../img/index/default.png';
                $id_producto = $producto->id_producto;

                // Verificar si el producto es favorito para el usuario actual
                $esFavorito = false;
                if ($id_usuario != 0) { // Verificar solo si el usuario está autenticado
                    $esFavorito = $conexion->esFavorito($id_producto, $id_usuario);
                }

                // Determinar el icono a mostrar
                $iconoFavorito = $esFavorito ? '../img/index/heartCover.svg' : '../img/index/addFavorites.svg';
                echo "
                <div class='col-md-3 mt-3 py-3 py-md-0 product-item' data-name='{$producto->nombre}'>
                    <div class='card shadow' id='c'>
                        <a href='./perfilProducto.php?id={$id_producto}' style='text-decoration: none; color: inherit;'>
                            <img src='{$imagen}' alt='{$producto->nombre}' class='card image-top pad'>
                        </a>
                        
                        <div class='icon-overlay-container' onclick='changeIcon(this, {$id_producto})'>
                            <img src='{$iconoFavorito}' alt='Favorite Icon' class='icon-overlay'>
                        </div>
                        <div class='card-body'>
                            <h3 class='card-title text-center title-card-new'>{$producto->nombre}</h3>
                            <p class='card-text text-center card-price'>\${$producto->precio}</p>
                        </div>
                    </div>
                </div>
                ";
            }
        } else {
            echo "<div class='col-12'><p class='text-center'>No se encontraron productos.</p></div>";
        }
        ?>
    </div>
    <div class="row">
        <div class="col-12 text-center mt-3">
            <button id="load-more" class="btn btn-primary">Ver más</button>
        </div>
    </div>
</div>

<!-- detalles producto en el carrito -->
<div class="modal fade" id="carritoModal" tabindex="-1" aria-labelledby="carritoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="carritoModalLabel">Cotizaciones</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="carrito-list" class="row">
                    <!-- Aquí se cargarán los detalles del carrito -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="aceptar-btn" class="btn btn-primary">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<!--Footer-->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3">
                <h5>Misión</h5>
                <p>Transformar espacios con soluciones innovadoras y elegantes para el diseño de interiores, creando hogares y negocios funcionales, acogedores y que reflejen el estilo único de cada cliente.</p>
            </div>
            <div class="col-md-4 mb-3">
                <h5>Links</h5>
                <ul class="list-unstyled">
                    <li><a href="https://api.whatsapp.com/send?phone=528717843809" target="_blank" class="text-white">Contacto</a></li>
                    <li><a href="/views/productos.php" class="text-white">Productos</a></li>
                    <li><a href="/views/citas.php" class="text-white">Agendar</a></li>
                    <li><a href="#about-us" id="link-nosotros" class="text-white">Nosotros</a></li>
                </ul>
            </div>
            <div class="col-md-4 mb-3">
                <h5>Contáctanos</h5>
                <p><i class="bi bi-geo-alt"></i>Torreón Coahuila, México</p>
                <p><i class="bi bi-envelope"></i> glassstore@gmail.com</p>
                <p><i class="bi bi-phone"></i> +52 123 4564 456</p>
            </div>
        </div>
    </div>
    <div class="copy text-center py-3 w-100">
        <p class="mb-0">&copy; 2024 Glass Store. All rights reserved.</p>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../css/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var currentPage = 1;
    var productosPorPagina = <?php echo $productos_por_pagina; ?>;

    $('#load-more').on('click', function() {
        currentPage++;
        cargarMasProductos(currentPage);
    });

    $('#search-input').on('input', function() {
        currentPage = 1; // Resetear la página actual a 1
        ejecutarBusqueda();
    });

    $('#search-button').on('click', function() {
        currentPage = 1; // Resetear la página actual a 1
        ejecutarBusqueda();
    });

    $('#search-input').on('keydown', function(event) {
        if (event.keyCode === 13) {
            event.preventDefault();
            currentPage = 1; // Resetear la página actual a 1
            ejecutarBusqueda();
        }
    });

    function ejecutarBusqueda() {
        var searchValue = $('#search-input').val().toLowerCase();
        $.ajax({
            url: '../scripts/cargar_productos.php',
            method: 'GET',
            data: {
                search: searchValue,
                page: currentPage,
                productos_por_pagina: productosPorPagina
            },
            dataType: 'json',
            success: function(response) {
                $('#product-list').empty();
                if (response.productos.length > 0) {
                    response.productos.forEach(function(producto) {
                        var imagen = producto.imagen ? '../img/index/' + producto.imagen : '../img/index/default.png';
                        var iconoFavorito = producto.es_favorito ? '../img/index/heartCover.svg' : '../img/index/addFavorites.svg';

                        var productoHtml = `
                            <div class='col-md-3 mt-3 py-3 py-md-0 product-item' data-name='${producto.nombre}'>
                                <div class='card shadow' id='c'>
                                    <a href='./perfilProducto.php?id=${producto.id_producto}' style='text-decoration: none; color: inherit;'>
                                        <img src='${imagen}' alt='${producto.nombre}' class='card image-top pad'>
                                    </a>
                                    <div class='icon-overlay-container' onclick='changeIcon(this, ${producto.id_producto})'>
                                        <img src='${iconoFavorito}' alt='Favorite Icon' class='icon-overlay'>
                                    </div>
                                    <div class='card-body'>
                                        <h3 class='card-title text-center title-card-new'>${producto.nombre}</h3>
                                        <p class='card-text text-center card-price'>\$${producto.precio}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('#product-list').append(productoHtml);
                    });

                    $('#load-more').show(); // Mostrar el botón "Ver más" después de la búsqueda
                } else {
                    $('#product-list').append("<div class='col-12'><p class='text-center'>No se encontraron productos.</p></div>");
                    $('#load-more').hide();
                }
            },
            error: function(error) {
                console.error('Error al buscar productos:', error);
            }
        });
    }

    function cargarMasProductos(pagina) {
        var searchValue = $('#search-input').val().toLowerCase();
        $.ajax({
            url: '../scripts/cargar_productos.php',
            method: 'GET',
            data: {
                search: searchValue,
                page: pagina,
                productos_por_pagina: productosPorPagina
            },
            dataType: 'json',
            success: function(response) {
                if (response.productos.length > 0) {
                    response.productos.forEach(function(producto) {
                        var imagen = producto.imagen ? '../img/index/' + producto.imagen : '../img/index/default.png';
                        var iconoFavorito = producto.es_favorito ? '../img/index/heartCover.svg' : '../img/index/addFavorites.svg';

                        var productoHtml = `
                            <div class='col-md-3 mt-3 py-3 py-md-0 product-item' data-name='${producto.nombre}'>
                                <div class='card shadow' id='c'>
                                    <a href='./perfilProducto.php?id=${producto.id_producto}' style='text-decoration: none; color: inherit;'>
                                        <img src='${imagen}' alt='${producto.nombre}' class='card image-top pad'>
                                    </a>
                                    <div class='icon-overlay-container' onclick='changeIcon(this, ${producto.id_producto})'>
                                        <img src='${iconoFavorito}' alt='Favorite Icon' class='icon-overlay'>
                                    </div>
                                    <div class='card-body'>
                                        <h3 class='card-title text-center title-card-new'>${producto.nombre}</h3>
                                        <p class='card-text text-center card-price'>\$${producto.precio}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('#product-list').append(productoHtml);
                    });
                } else {
                    $('#load-more').hide(); // Ocultar el botón si no hay más productos
                }
            },
            error: function(error) {
                console.error('Error al cargar más productos:', error);
            }
        });
    }
});
</script>



</body>
</html>
