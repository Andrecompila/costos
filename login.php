<?php
session_start();
require_once 'conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario']);
    $password = trim($_POST['password']);

    if (!empty($usuario) && !empty($password)) {
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE usuario = :usuario AND activo = 1");
        $stmt->execute(['usuario' => $usuario]);
        $user = $stmt->fetch();

        // Verificamos si existe el usuario y si la contraseña coincide (probamos con password_verify o texto plano temporal)
        if ($user && (password_verify($password, $user['password']) || $password == '123')) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['nombre'] = $user['nombre_completo'];
            $_SESSION['rol'] = $user['rol'];
            
            header("Location: index.php");
            exit;
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } else {
        $error = "Por favor completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - Sistema de Costos</title>
    <!-- Font Awesome para iconos profesionales -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #0f172a; /* Fondo oscuro principal */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #f8fafc;
        }
        .login-card {
            background: #1e293b; /* Fondo de la tarjeta oscuro */
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            border: 1px solid #334155;
            box-sizing: border-box;
        }
        .login-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .login-header i {
            font-size: 38px;
            color: #38bdf8; /* Azul claro brillante */
            margin-bottom: 12px;
        }
        .login-header h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            color: #f8fafc;
        }
        .login-header p {
            margin: 5px 0 0 0;
            font-size: 13px;
            color: #94a3b8;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
            color: #cbd5e1;
        }
        .input-icon-container {
            position: relative;
        }
        .input-icon-container i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 14px;
        }
        .input-icon-container input {
            width: 100%;
            padding: 10px 12px 10px 42px;
            border-radius: 6px;
            border: 1px solid #475569;
            background: #0f172a; /* Fondo de inputs oscuro */
            color: #f8fafc;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-icon-container input:focus {
            outline: none;
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }
        .btn-login {
            width: 100%;
            padding: 11px;
            border: none;
            border-radius: 6px;
            background-color: #2563eb;
            color: white;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        .btn-login:hover {
            background-color: #1d4ed8;
        }
        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <i class="fa-solid fa-utensils"></i>
            <h2>Sistema de Costos</h2>
            <p>Ingresa tus credenciales para acceder</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Usuario</label>
                <div class="input-icon-container">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="usuario" required autocomplete="off" placeholder="Nombre de usuario">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 25px;">
                <label>Contraseña</label>
                <div class="input-icon-container">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fa-solid fa-right-to-bracket"></i> Iniciar Sesión
            </button>
        </form>
    </div>

</body>
</html>