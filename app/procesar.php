<?php
$nombre = $_POST["nombre"] ?? "desconocido";
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Resultado</title>
</head>

<body>

    <h2>Hola, <?php echo htmlspecialchars($nombre); ?> 💙</h2>
    <a href="index.php">Volver</a>

</body>

</html>