<?php
session_start();

// Si no ha iniciado sesión, lo devolvemos al login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'conexion.php';
$seccion = isset($_GET['seccion']) ? $_GET['seccion'] : 'inicio';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Costos - Restaurante</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="recetas.css">
    <link rel="stylesheet" href="insumos.css">
    <link rel="stylesheet" href="categorias.css">
    <link rel="stylesheet" href="parametros.css">
    <link rel="stylesheet" href="recetas_ver.css">
    <!-- Font Awesome para iconos profesionales -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- Menú Lateral -->
    <div class="sidebar">
        <h2><i class="fa-solid fa-utensils" style="margin-right: 6px;"></i> Restaurante</h2>
        <!-- Tarjeta de Usuario Moderna -->
        <div class="user-info" style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 8px; margin: 15px 10px; border: 1px solid rgba(255, 255, 255, 0.08);">
            <i class="fa-solid fa-circle-user" style="font-size: 32px; color: var(--primary-color, #60a5fa);"></i>
            <div style="overflow: hidden; line-height: 1.3;">
                <div style="font-size: 13px; font-weight: 600; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?= htmlspecialchars($_SESSION['nombre']) ?>
                </div>
                <div style="font-size: 11px; color: #94a3b8; text-transform: capitalize;">
                    <?= ucfirst($_SESSION['rol']) ?>
                </div>
            </div>
        </div>
<a href="index.php?seccion=inicio" class="<?= ($seccion == 'inicio') ? 'active' : '' ?>">
    <i class="fa-solid fa-house"></i> Inicio
</a>
<a href="index.php?seccion=categorias" class="<?= ($seccion == 'categorias') ? 'active' : '' ?>">
    <i class="fa-solid fa-tags"></i> Categorías
</a>
<a href="index.php?seccion=parametros" class="<?= ($seccion == 'parametros') ? 'active' : '' ?>">
    <i class="fa-solid fa-gears"></i> Parámetros
</a>
<a href="index.php?seccion=insumos" class="<?= ($seccion == 'insumos') ? 'active' : '' ?>">
    <i class="fa-solid fa-carrot"></i> Insumos
</a>
<a href="index.php?seccion=recetas" class="<?= ($seccion == 'recetas') ? 'active' : '' ?>">
    <i class="fa-solid fa-book-open"></i> Recetas
</a>

<!-- Botón de Modo Oscuro -->
<a href="#" id="toggle-theme" style="margin-top: 20px; text-align: center; background: rgba(255, 255, 255, 0.05); cursor: pointer;">
    <i id="theme-icon" class="fa-solid fa-moon"></i> <span id="theme-text">Modo Oscuro</span>
</a>

<!-- Cerrar Sesión -->
<a href="logout.php" class="btn-logout">
    <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
</a>
        
    </div>

    <!-- Contenido Principal -->
    <div class="content">
        <?php
        if ($seccion == 'inicio') {
            include 'inicio.php';
        }
        elseif ($seccion == 'parametros') {
            include 'parametros.php';
        } 
        elseif ($seccion == 'categorias') {
            include 'categorias.php';
        }
        elseif ($seccion == 'insumos') {
            include 'insumos.php';
        }
        elseif ($seccion == 'recetas') {
            include 'recetas.php';
        }
        elseif ($seccion == 'recetas_nuevo') {
            include 'recetas_nuevo.php';
        }
        elseif ($seccion == 'recetas_ver') {
            include 'recetas_ver.php';
        }
        else {
            echo "<h1>Página no encontrada</h1>";
        }
        ?>
    </div>

    <!-- Script para alternar y recordar el Modo Oscuro con Font Awesome -->
<script>
    const toggleBtn = document.getElementById('toggle-theme');
    const themeIcon = document.getElementById('theme-icon');
    const themeText = document.getElementById('theme-text');
    
    // 1. Cargar el tema guardado en localStorage o 'light' por defecto
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
    
    if (currentTheme === 'dark') {
        themeIcon.className = 'fa-solid fa-sun';
        themeText.textContent = 'Modo Claro';
    } else {
        themeIcon.className = 'fa-solid fa-moon';
        themeText.textContent = 'Modo Oscuro';
    }

    // 2. Alternar tema al hacer clic
    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault(); // Previene que la página recargue o haga scroll
        let theme = document.documentElement.getAttribute('data-theme');
        
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
            themeIcon.className = 'fa-solid fa-moon';
            themeText.textContent = 'Modo Oscuro';
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            themeIcon.className = 'fa-solid fa-sun';
            themeText.textContent = 'Modo Claro';
        }
    });
</script>

</body>
</html>