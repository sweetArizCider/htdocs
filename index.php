<?php
session_start();
include './class/database.php';

$id_usuario = 0;
$notificaciones = [];
if (isset($_SESSION["nom_usuario"])) {
    $user = $_SESSION["nom_usuario"];


    $conexion = new Database();
    $conexion->conectarDB();

    // Consulta para obtener el rol del usuario basado en el nombre de usuario
    $consulta_rol = "CALL roles_usuario(:nombre_usuario)";
    $params_rol = [':nombre_usuario' => $user];
    $resultado_rol = $conexion->seleccionar($consulta_rol, $params_rol);

    if ($resultado_rol && !empty($resultado_rol)) {
        $nombre_rol = $resultado_rol[0]->nombre_rol;

        // Consulta para obtener los IDs del cliente e instalador basado en el nombre de usuario
        $consulta_ids = "CALL obtener_id_por_nombre_usuario(:nombre_usuario)";
        $params_ids = [':nombre_usuario' => $user];
        $resultado_ids = $conexion->seleccionar($consulta_ids, $params_ids);

        if ($resultado_ids && !empty($resultado_ids)) {
            $fila = $resultado_ids[0];

            if ($nombre_rol == 'cliente' && isset($fila->id_cliente)) {
                $id_cliente = $fila->id_cliente;
                $id_usuario = $id_cliente;
                
                // Cambiar el estado de todos los productos "en carrito" a "en espera" para el cliente actual asi le sallen cuando no concluye la cita
                $consulta_update = "UPDATE detalle_producto SET estatus = 'en espera' WHERE cliente = :id_cliente AND estatus = 'en carrito'";
                $params_update = [':id_cliente' => $id_cliente];
                $conexion->ejecutar1($consulta_update, $params_update);

                // Consulta de notificaciones del cliente
                $consultaNotificaciones = "SELECT notificacion, fecha FROM notificaciones_cliente WHERE cliente = :cliente order by fecha desc";
                $paramsNotificaciones = [':cliente' => $id_cliente];
                $notificaciones = $conexion->seleccionar($consultaNotificaciones, $paramsNotificaciones);

            } elseif ($nombre_rol == 'instalador' && isset($fila->id_instalador)) {
                $id_instalador = $fila->id_instalador;
                $id_usuario = $id_instalador;

              
                $consultaNotificaciones = "SELECT notificacion, fecha FROM notificaciones_instalador WHERE instalador = :instalador";
                $paramsNotificaciones = [':instalador' => $id_instalador];
                $notificaciones = $conexion->seleccionar($consultaNotificaciones, $paramsNotificaciones);
            }
        }
    }
}

$productos_espera = [];
if ($id_usuario != 0) {
    // solo los que estan en espera 
    $consulta_productos = "CALL carrito(?)";
    $params_productos = [$id_usuario];
    $productos_espera = $conexion->seleccionar($consulta_productos, $params_productos);
}

function esReciente($fecha){
    $fechaNotif = new DateTime($fecha);
    $fechaActual = new DateTime();
    $intervalo = $fechaActual->diff($fechaNotif);
    return ($intervalo->d < 30); // la agarra de los ultimos 30 dias 
}

$notificacionesRecientes = array_filter($notificaciones, function($notif) {
    return esReciente($notif->fecha);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Glass Store</title>
    <link rel="shortcut icon" href="./img/index/logoVarianteSmall.png" type="image/x-icon">
    <link rel="stylesheet" href="./css/normalized.css">
    <link rel="stylesheet" href="./css/styles.css">
    <link rel="stylesheet" href="./css/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    
    <style>
         .icon-overlay-container-fav {
    position: absolute;
    bottom: 10px; 
    right: 10px; 
    cursor: pointer;
    background-color: rgba(255, 255, 255, 0.8); 
    border-radius: 50%;
    padding: 5px;
}

.icon-overlay-fav {
    width: 25px;
    height: 25px;
}
    </style>
    

</head>
<body>
  
  <!-- whatsapp flotante -->
  <div id="wa-button">
    <a href="https://api.whatsapp.com/send?phone=528717843809" target="_blank">
      <img src="./img/index/whatsappFloat.svg" alt="Contáctanos por WhatsApp">
    </a>
  </div>
    <!-- barra superior -->
    <div class="container blue">
      <div class="navbar-top">
          <div class="social-link">
              <a href="https://api.whatsapp.com/send?phone=528717843809" target="_blank" ><img src="./img/index/whatsapp.svg" alt="" width="30px"></a>
              <a href="https://www.facebook.com/profile.php?id=100064181314031" target="_blank"><img src="./img/index/facebook.svg" alt="" width="30px"></a>
              <a href="https://www.instagram.com/glassstoretrc?igsh=MXVhdHh1MDVhOGxzeA==" target="_blank"><img src="./img/index/instagram.svg" alt="" width="30px"></a>
          </div>

          <div class="logo">
              <img src="./img/index/GLASS.png" alt="" class="logo">
          </div>
          <div class="icons">
                <a href="../views/productos.php"><img src="./img/index/search.svg" alt="" width="25px"></a>
                <button class="botonMostrarFavoritos" data-bs-toggle="modal" data-bs-target="#favoritosModal"><img src="./img/index/favorites.svg" alt="" width="25px"></button>
                <a id="carrito" data-bs-toggle="modal" data-bs-target="#carritoModal"><img src="./img/index/clip.svg" alt="" width="25px"></a>

                <div class="dropdown">
    <a href="#" id="user-icon" data-bs-toggle="dropdown" aria-expanded="false">
        <img src="./img/index/user.svg" alt="" width="25px" style="cursor: pointer">
    </a>
    <?php if (isset($_SESSION["nom_usuario"])): ?>
        <ul class="dropdown-menu" aria-labelledby="user-icon">
            <li class="dropdown-item" style="color: #6c757d; font-size: .8em; pointer-events: none; cursor: default;"> 
                <?php echo htmlspecialchars($_SESSION["nom_usuario"]); ?>
            </li>
            <li><a class="dropdown-item" href="./views/cliente/perfil.php">Perfil</a></li>
            <li><a class="dropdown-item" href="#" id="notification-icon" data-bs-toggle="modal" data-bs-target="#notificationModal">Notificaciones</a></li>
            <?php
            $user = $_SESSION["nom_usuario"];
            $consulta = "CALL roles_usuario(?)";
            $params = [$user];
            $roles = $conexion->seleccionar($consulta, $params);
            if ($roles) {
                foreach ($roles as $rol) {
                    if ($rol->nombre_rol == 'administrador') {
                        echo '<li><a class="dropdown-item" href="./views/administrador/vista_admin.php">Administrador</a></li>';
                    } elseif ($rol->nombre_rol == 'instalador') {
                        echo '<li><a class="dropdown-item" href="./views/instalador/index_Instalador.php">Buzón</a></li>';
                    }
                }
            }
            ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="scripts/cerrarSesion.php">Cerrar Sesión</a></li>
        </ul>
    <?php else: ?>
        <ul class="dropdown-menu" aria-labelledby="user-icon">
            <li><a class="dropdown-item" href="./views/iniciarSesion.php">Iniciar Sesión</a></li>
        </ul>
    <?php endif; ?>
</div>

            </div>
        </div>
    </div> 

                
    <!-- segunda barra -->
    <nav class="navbar sticky-top navbar-expand-md" id="navbar-color">
      <div class="container">
          <!-- menú hamburguesa -->
          <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
              <span><i><img src="./img/index/menu.svg" alt="Menu" width="30px"></i></span>
          </button>
  
          <div class="offcanvas offcanvas-start" id="offcanvasNavbar">
              <div class="offcanvas-header">
                  <h5 class="offcanvas-title">Menú</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
              </div>
              <div class="offcanvas-body">
                  <ul class="navbar-nav">
                      <li class="nav-item ">
                          <a class="nav-link" href="https://api.whatsapp.com/send?phone=528717843809" target="_blank">Contacto</a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link nav-left" href="./views/productos.php">Productos</a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link nav-left" href="./views/citas.php">Agendar</a>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link nav-left" href="#about-us" id="link-nosotros">Nosotros</a>
                      </li>
                  </ul>
              </div>
          </div>
      </div>
    </nav>


    <!-- banner -->
     <main>
      <div class="main-content">
        <div class="content">
          <h1>TRANSFORMA TU ESPACIO <br> CON
              ESTILO Y DISTINCIÓN
          </h1>
          <p>Dale un toque único a tu espacio con nuestros <br> productos de alta calidad. Soluciones elegantes y <br> personalizadas para tu hogar o negocio.</p>
          <div id="btn1" ><a href="./views/productos.php" ><button class="banner-boton" >Ver Productos</button></a></div>
        </div> 
      </div>
     </main>
    

    <!-- categorias -->
    <div class="container">
        <h3 class="text-center subtitle-main" style="padding-top: 30px;">Principales Categorias</h3>
        <div class="row" style="margin-top: 50px;">
            <div class="col-md-3 py-3 py-md-0 ">
                <div class="card shadow">
                    <img src="./img/index/PersianaCafeClaro.jpeg" alt="" class="card img-card" >
                    <div class="card-body">
                        <h5 class="card-titel text-center categoria-title">Persianas</h5>
                        <p class="text-center">Mejora tu hogar con nuestras elegantes persianas, disponibles en varios estilos y colores para un control óptimo de la luz y privacidad.</p>
                       
                    </div>
                </div>
            </div>
            <div class="col-md-3 py-3 py-md-0 ">
                <div class="card shadow">
                    <img src="./img/index/espejoCorteOndas.jpeg" alt="" class="card image-top img-card" height="200px">
                    <div class="card-body">
                        <h5 class="card-titel text-center">Vidrio Templado</h5>
                        <p class="text-center">Añade un toque moderno y seguro con nuestros productos de vidrio templado, ideales para espejos, canceles, repisas, mesas y ventanas.</p>
                       
                    </div>
                </div>
            </div>
            <div class="col-md-3 py-3 py-md-0 ">
                <div class="card shadow">
                    <img src="./img/index/tapizClaroSillonCafe.jpeg" alt="" class="card image-top img-card" height="200px">
                    <div class="card-body">
                        <h5 class="card-titel text-center">Papel Tapiz</h5>
                        <p class="text-center">Renueva tus espacios con nuestro papel tapiz, disponible en una variedad de diseños y colores vibrantes. Para todos los gustos.</p>
                        
                    </div>
                </div>
            </div>

            <div class="col-md-3 py-3 py-md-0 ">
              <div class="card shadow">
                  <img src="./img/index/pasamanosEscaleraCoralVidrio.png" alt="" class="card image-top img-card" height="200px">
                  <div class="card-body">
                      <h5 class="card-titel text-center">Herreria</h5>
                      <p class="text-center">Combina funcionalidad y estilo con nuestros productos de herrería, ideales para pasamanos y puertas. Perfectas para espacios sociales.</p>
                      
                  </div>
              </div>
          </div>
        </div>
    </div>

    <!-- Agendar cita -->
    <h1 class="text-center subtitle-main" style="margin-top: 50px;" >Pedidos personalizados</h1>
     <div class="background-cover">
      <div class="container">
        
        <div class="row alinear" style="margin-top: 50px;">
            <div class="col-md-6 py-3 py-md-0">
                <div class="d-flex justify-content-center">
                    <img src="./img/index/agendarOp1.jpeg" alt="" class="img-agendar">
                </div>
            </div>
            <div class="col-md-6 py-3 py-md-0">
                <p class="font-mont agendar-title">AGENDA AHORA !</p>
                <div class="row stars">
                    <div class="col-1">
                        <img src="./img/index/star.svg" alt="">
                    </div>
                    <div class="col-1">
                        <img src="./img/index/star.svg" alt="">
                    </div>
                    <div class="col-1">
                        <img src="./img/index/star.svg" alt="">
                    </div>
                    <div class="col-1">
                        <img src="./img/index/star.svg" alt="">
                    </div>
                    <div class="col-1">
                        <img src="./img/index/star.svg" alt="">
                    </div>
                </div>
                <p class="testimonyReview">Obtén asesoramiento personalizado para transformar tu espacio con nuestras soluciones en vidrio templado, persianas, papel tapiz y herrajes. ¡Reserva tu cita ahora!</p>
                <div id="btn4"><a href="./views/citas.php"><button class="agendar-boton">Agendar</button></a></div>
            </div>
        </div>
    </div>
     </div>
    
    <!-- sobre nosotros -->
    <div id="about-us" class="container nosotros">
        <h1 class="text-center title-nosotros" style="margin-top: 50px;">Sobre Nosotros</h1>
        <div class="row alinear" style="margin-top: 50px;">
            <div class="col-md-6 py-3 py-md-0">
                <div class="alinear d-flex justify-content-center">
                    <img src="./img/index/persianaPlantas.jpeg " class="img-nosotros" alt="">
                </div>
            </div>
            <div class="col-md-6 py-3 py-md-0">
                <p class="text-nosotros">En Glass Storee,<mark> nos especializamos en ofrecer soluciones de alta calidad </mark> para la decoración y renovación de hogares y negocios. <br><br>  <mark>Con años de experiencia en el sector </mark>, nuestro objetivo es transformar espacios con productos innovadores y elegantes como vidrio templado, persianas, papel tapiz y herrajes.</p>
                  <img src="./img/index/GLASS.png" alt="" class="logo">
            </div>
        </div>
    </div>

    <!-- Historias de clientes -->
    <h1 class="text-center subtitle-main" style="margin-top: 50px;" >Historias de Clientes</h1>
     <div class="container" style="width: 80%;">
              <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                  <div class="carousel-item active container-historias" data-id="1">
                    <div class="row">
                      <div class="col-md-8">
                        <div class="testimonyText">
                          <h2 class="subtitle">Familia González</h2>
                          <p class="testimonyReview">Las puertas corredizas y divisores de ambientes de Glass Store optimizaron el espacio del hogar de la familia González, creando un ambiente más funcional y versátil. ¡Ahora disfrutan de un hogar con mayor fluidez y amplitud!</p>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <figure class="testimonyPicture">
                          <img src="./img/index/barandalTerrazaBar.jpeg" alt="Barandal de vidrio" class="img-fluid testimonyImg">
                        </figure>
                      </div>
                    </div>
                  </div>
                  <div class="carousel-item container-historias" data-id="2">
                    <div class="row">
                      <div class="col-md-8">
                        <div class="testimonyText">
                          <h2 class="subtitle">Familia Pérez</h2> <br>
                          <p class="testimonyReview">La familia Pérez transformó su hogar con Glass Store, instalando una pared de vidrio templado, persianas motorizadas y papel tapiz texturizado. "Nuestra casa ahora es moderna y acogedora. <br> ¡Estamos encantados con el resultado!" <br> – Familia Pérez.</p>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <figure class="testimonyPicture">
                          <img src="./img/index/cancelMarcoDoradoLavaboPlanta.jpeg" alt="Cancel con marco dorado" class="img-fluid testimonyImg">
                        </figure>
                      </div>
                    </div>
                  </div>
                  <div class="carousel-item container-historias" data-id="3">
                    <div class="row">
                      <div class="col-md-8">
                        <div class="testimonyText">
                          <h2 class="subtitle">Familia López</h2> <br>
                          <p class="testimonyReview">Los López soñaban con un hogar moderno y acogedor. Glass Store lo hizo realidad con persianas motorizadas, papel tapiz texturizado, barandales de cristal, pasamanos ergonómicos, espejos y repisas funcionales. El resultado: un espacio transformado, lleno de luz, estilo y funcionalidad. La familia López está encantada y recomienda Glass Store.</p>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <figure class="testimonyPicture">
                          <img src="./img/index/barandalExteriorCasaBlanca.jpeg" alt="Barandal de cristal para terraza exterior" class="img-fluid testimonyImg">
                        </figure>
                      </div>
                    </div>
                  </div>
                  <div class="carousel-item container-historias" data-id="4">
                    <div class="row">
                      <div class="col-md-8">
                        <div class="testimonyText">
                          <h2 class="subtitle">Familia López</h2> <br>
                          <p class="testimonyReview">Las luminarias de Glass Store llenaron de luz y estilo el hogar de la familia Martínez, creando ambientes cálidos y acogedores. ¡Ahora disfrutan de un espacio iluminado y acogedor</p>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <figure class="testimonyPicture">
                          <img src="./img/index/persianaGimnasio.jpeg" alt="Persianas dentro de un gimnasio" class="img-fluid testimonyImg">
                        </figure>
                      </div>
                    </div>
                  </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                  <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
                  <span class="carousel-control-next-icon" aria-hidden="true"></span>
                  <span class="visually-hidden">Next</span>
                </button>
              </div>
            
          
     </div>

    <!-- footer -->
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
    <!-- modal notificaciones-->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationModalLabel">Notificaciones</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php
                if (!empty($notificacionesRecientes)) {
                    foreach ($notificacionesRecientes as $notif) {
                        echo '<div class="notification">';
                        echo '<p>' . htmlspecialchars($notif->notificacion) . '</p>';
                        echo '<small>' . htmlspecialchars($notif->fecha) . '</small>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>No tienes notificaciones recientes.</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

     <!-- modal cotizacion -->
    <div class="modal fade" id="quoteModal" tabindex="-1" aria-labelledby="quoteModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="quoteModalLabel">Cotizar Producto</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form>
              <div class="mb-3">
                <label for="productName" class="form-label">Producto</label>
                <input type="text" class="form-control" id="productName" readonly>
              </div>
              <div class="mb-3">
                <label for="squareMeters" class="form-label">Metros Cuadrados</label>
                <input type="number" class="form-control" id="squareMeters">
              </div>
              <div class="mb-3">
                <label for="additionalFeatures" class="form-label">Características Adicionales</label>
                <textarea class="form-control" id="additionalFeatures" rows="3"></textarea>
              </div>
              <button type="submit" class="boton-mini boton-modal">Enviar Cotización</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    

<!-- Modal de Favoritos -->
<div class="modal fade" id="favoritosModal" tabindex="-1" aria-labelledby="favoritosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="favoritosModalLabel">Mis Favoritos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($_SESSION["nom_usuario"])): ?>
                    <!-- Usuario logueado -->
                    <p class="text-center">Guarda tus productos favoritos y accede a ellos en cualquier momento.</p>
                    <div id="favoritos-list" class="row">
                        <!-- Aquí se cargarán los productos favoritos con lo de abjo -->
                    </div>
                <?php else: ?>
                    <!-- Usuario no logueado -->
                    <div class="text-center">
                        <p><a href="./views/iniciarSesion.php">Inicia sesión</a> para guardar tus productos favoritos y acceder a ellos cuando quieras. ¡ <a href="../views/register.php">Crea tu cuenta</a> y disfruta de una experiencia personalizada!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>




<!-- Modal de Cotizaciones -->
<div class="modal fade" id="carritoModal" tabindex="-1" aria-labelledby="carritoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="carritoModalLabel">Cotizaciones</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($_SESSION["nom_usuario"])): ?>
                    <!-- Usuario logueado -->
                    <?php if (!empty($productos_espera)): ?>
                        <div id="carrito-list" class="row">
                            <!-- Aquí se cargarán los detalles del carrito -->
                        </div>
                    <?php else: ?>
                       
                    <?php endif; ?>
                    <?php else: ?>
                    <!-- Usuario no logueado -->
                         <div class="text-center">
                            <p><a href="./views/iniciarSesion.php">Inicia sesión</a> para ver tus cotizaciones y acceder a ellas cuando quieras. ¡ <a href="../views/register.php">Crea tu cuenta</a> y disfruta de una experiencia personalizada!</p>
                        </div>
                    <?php endif; ?>
                </div>
            <div class="modal-footer">
            <?php if (isset($_SESSION["nom_usuario"]) && !empty($productos_espera)): ?>
                <button type="button" id="aceptar-btn" class="btn btn-primary">Aceptar</button>
                <button type="button" id="limpiar-btn" class="btn btn-danger">Limpiar</button>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>
 </body>
<script src="../css/bootstrap-5.3.3-dist/js/bootstrap.min.js"></script>
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.6/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/js/bootstrap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/js/bootstrap.min.js"></script>
<script>
     $('#favoritosModal').on('hidden.bs.modal', function () {
        location.reload(); // Recarga la página al cerrar el modal
    });

 
  document.getElementById('user-icon').addEventListener('click', function() {
            var loginForm = document.getElementById('login-form');
            if (loginForm.style.display === 'none' || loginForm.style.display === '') {
                loginForm.style.display = 'block';
            } else {
                loginForm.style.display = 'none';
            }
        });
        document.getElementById('link-nosotros').addEventListener('click', function(event) {
            event.preventDefault();
            document.querySelector('#about-us').scrollIntoView({
                behavior: 'smooth'
            });
        });

    document.querySelectorAll('.boton-mini').forEach(button => {
      button.addEventListener('click', function() {
        const productName = this.closest('.card-body').querySelector('.card-titel').innerText;
        document.getElementById('productName').value = productName;
      });
    });

    function closeForm() {
            document.getElementById('login-form').style.display = 'none';
        }

        function saveToFavorites(id_producto) {
    $.ajax({
        url: './scripts/guardar_favorito.php',
        method: 'POST',
        data: { id_producto: id_producto },
        success: function(response) {
            console.log(response);
        },
        error: function(error) {
            console.error('Error:', error);
        }
    });
}



        $('#favoritosModal').on('shown.bs.modal', function () {
        cargarFavoritos();
    });

    // Función para cargar favoritos
    function cargarFavoritos() {
    <?php if (isset($_SESSION["nom_usuario"])): ?>
        $.ajax({
            url: './scripts/obtener_favoritos.php',
            method: 'GET',
            dataType: 'json',
            success: function(favoritos) {
                var favoritosList = $('#favoritos-list');
                favoritosList.empty();
                if (favoritos.length > 0) {
                    favoritos.forEach(function(favorito) {
                        var imagen = favorito.imagen ? './img/disenos/' + favorito.imagen : './img/index/default.png';
                        var iconoFavorito = './img/index/heartCover.svg';

                        var favoritoHtml = `
                           <div class='col-md-3 mt-3 py-3 py-md-0 product-item' id='favorito-${favorito.id_producto}'>
                                <div class='card shadow'>
                                    <a href='./views/perfilProducto.php?id=${favorito.id_producto}' style='text-decoration: none; color: inherit;'>
                                        <img src='${imagen}' alt='${favorito.nombre}' class='card-img-top'>
                                        <div class='card-body'>
                                            <h5 class='card-title'>${favorito.nombre}</h5>
                                            <p class='card-text'>$${favorito.precio}</p>
                                        </div>
                                    </a>
                                    <div class='icon-overlay-container-fav' onclick='eliminarFavoritoDesdeModal(${favorito.id_producto})'>
                                        <img src='${iconoFavorito}' alt='Remove Favorite Icon' class='icon-overlay-fav'>
                                    </div>
                                </div>
                            </div>`;

                        favoritosList.append(favoritoHtml);
                    });
                } else {
                    favoritosList.append("<p class='text-center'>No tienes productos en favoritos.</p>");
                }
            },
            error: function(error) {
                console.error('Error al obtener los favoritos:', error);
                $('#favoritos-list').append("<p>Error al cargar los favoritos.</p>");
            }
        });
    <?php else: ?>
        var favoritosList = $('#favoritos-list');
        favoritosList.empty();
        favoritosList.append("<p>No tienes favoritos, por favor inicia sesión.</p>");
    <?php endif; ?>
}

   $(document).ready(function() {
    $('#limpiar-btn').on('click', function() {
        $('.producto-checkbox:checked').each(function() {
            var idDetalleProducto = $(this).val();
            var card = $(this).closest('.card'); // Guardar referencia al elemento de la tarjeta para eliminarlo

            $.ajax({
                url: './scripts/desactivar_cotizacion.php', // Archivo PHP que manejará la desactivación
                method: 'POST',
                data: { id_detalle_producto: idDetalleProducto },
                success: function(response) {
                    console.log('Producto desactivado:', response);
                    card.remove(); // Eliminar la tarjeta del DOM
                },
                error: function(error) {
                    console.error('Error al desactivar el producto:', error);
                }
            });
        });
    });
});

function eliminarFavoritoDesdeModal(id_producto) {
    $.ajax({
        url: './scripts/guardar_favorito.php',
        method: 'POST',
        data: { id_producto: id_producto },
        success: function(response) {
            if (response.mensaje === 'Producto eliminado de favoritos.') {
                // Eliminar el producto del modal de favoritos
                $('#favorito-' + id_producto).remove();

                // Opcional: Mostrar un mensaje si ya no hay más favoritos
                if ($('#favoritos-list').children().length === 0) {
                    $('#favoritos-list').append("<p class='text-center'>No tienes productos en favoritos.</p>");
                }
            } else if (response.error) {
                console.error('Error:', response.error);
            }
        },
        error: function(error) {
            console.error('Error:', error);
        }
    });
}



     // Cargar carrito cuando el modal es mostrado
     $(document).ready(function() {
        $('#carritoModal').on('shown.bs.modal', function () {
            cargarCarrito();
        });

        $('#aceptar-btn').on('click', function() {
            actualizarEstadoProductos();
        });

        function cargarCarrito() {
    $.ajax({
        url: './scripts/obtener_carrito.php',
        method: 'GET',
        dataType: 'json',
        success: function(carrito) {
            var carritoList = $('#carrito-list');
            carritoList.empty();
            if (carrito.length > 0) {
                carrito.forEach(function(item) {
                    var imagen = item.imagen_producto ? './img/disenos/' + item.imagen_producto : './img/disenos/default.png';

                    // Concatenar las propiedades en una sola línea
                    var descripcion = [];
                    if (item.alto) descripcion.push('Alto: ' + item.alto);
                    if (item.largo) descripcion.push('Largo: ' + item.largo);
                    if (item.cantidad) descripcion.push('Cantidad: ' + item.cantidad);
                    if (item.monto) descripcion.push('Monto: ' + item.monto);
                    if (item.grosor) descripcion.push('Grosor: ' + item.grosor);
                    if (item.codigo_diseno) descripcion.push('Diseño: ' + item.codigo_diseno);
                    if (item.marco) descripcion.push('Accesorios: ' + item.marco);
                    if (item.monto) descripcion.push('Monto: $' + item.monto);

                    
                    
                    var descripcionProducto = descripcion.join(', ');

                    var productoHtml = `
                        <div class='col-md-12 mt-3 py-3 py-md-0'>
                            <div class='card shadow' style='display: flex; flex-direction: row;padding:1em 1em;'>
                                <input type='checkbox' class='form-check-input align-self-center producto-checkbox' value='${item.id_detalle_producto}' style='margin-right: 9px;'>
                                <img src='${imagen}' alt='${item.nombre_producto}' class='card-img-left' style='width: 150px; height: 150px;'>
                                <div class='card-body'>
                                    <h5 class='card-title'>${item.nombre_producto}</h5>
                                    <p class='card-text'>${descripcionProducto}</p>
                                </div>
                            </div>
                        </div>`;
                    carritoList.append(productoHtml);
                });
            } else {
                carritoList.append(` <div class='text-center'>
                ¿Aún no has solicitado una cotización? <a href='./views/productos.php'style='color: #007bff;'>¡Cotiza ahora!</a> y transforma tu espacio con nuestros productos.
                </div>`);
            }
        },
        error: function(error) {
            console.error('Error al obtener los productos del carrito:', error);
            $('#carrito-list').append("<p>Error al cargar los productos del carrito.</p>");
        }
    });
}
        function actualizarEstadoProductos() {
            $('.producto-checkbox:checked').each(function() {
                var idDetalleProducto = $(this).val();
                $.ajax({
                    url: './scripts/actualizar_carrito.php',
                    method: 'POST',
                    data: { id_detalle_producto: idDetalleProducto },
                    success: function(response) {
                        console.log('Producto actualizado:', response);
                        window.location.href = './views/citas.php';
                    },
                    error: function(error) {
                        console.error('Error al actualizar el producto:', error);
                    }
                });
            });
        }
    });

</script>
<script src="../js/loginSuccess.js"></script>
<script src="./js/bootstrap.bundle.min.js"></script>
</html>