<?php
/**
 * forms/contact.php
 * Backend para procesamiento de formulario de contacto
 * Destinatario: logiasanjuan@gmail.com
 */

// Configuración
$recipient_email = "rlsj2000n197@gmail.com";
$subject_prefix = "[Nuevo Mensaje desde el sitio Web] ";

// Verificar método de solicitud
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método no permitido"]);
    exit;
}

// Función para sanitizar entradas
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Función para validar email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Recoger y sanitizar datos del formulario
$name = isset($_POST["name"]) ? sanitize_input($_POST["name"]) : "";
$email = isset($_POST["email"]) ? sanitize_input($_POST["email"]) : "";
$subject = isset($_POST["subject"]) ? sanitize_input($_POST["subject"]) : "";
$message = isset($_POST["message"]) ? sanitize_input($_POST["message"]) : "";

// Validaciones requeridas
$errors = [];

if (empty($name) || strlen($name) < 2) {
    $errors[] = "El nombre es requerido y debe tener al menos 2 caracteres";
}

if (empty($email) || !validate_email($email)) {
    $errors[] = "Por favor ingrese un correo electrónico válido";
}

if (empty($subject) || strlen($subject) < 5) {
    $errors[] = "El asunto es requerido y debe tener al menos 5 caracteres";
}

if (empty($message) || strlen($message) < 10) {
    $errors[] = "El mensaje es requerido y debe tener al menos 10 caracteres";
}

// Si hay errores, devolver respuesta JSON
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        "status" => "error", 
        "message" => implode(". ", $errors)
    ]);
    exit;
}

// Preparar contenido del correo
$email_subject = $subject_prefix . $subject;
$email_body = "
<html>
<head>
    <meta charset='UTF-8'>
    <title>Nuevo mensaje de contacto</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;'>
        <h2 style='color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>
            Nuevo Mensaje de Contacto
        </h2>
        
        <table style='width: 100%; margin-top: 20px;'>
            <tr>
                <td style='padding: 8px 0; font-weight: bold; width: 120px;'>Nombre:</td>
                <td style='padding: 8px 0;'>{$name}</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; font-weight: bold;'>Email:</td>
                <td style='padding: 8px 0;'>
                    <a href='mailto:{$email}' style='color: #3498db; text-decoration: none;'>{$email}</a>
                </td>
            </tr>
            <tr>
                <td style='padding: 8px 0; font-weight: bold;'>Asunto:</td>
                <td style='padding: 8px 0;'>{$subject}</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; font-weight: bold; vertical-align: top;'>Mensaje:</td>
                <td style='padding: 8px 0;'>
                    <div style='background: #f8f9fa; padding: 12px; border-left: 3px solid #3498db; border-radius: 3px;'>
                        " . nl2br($message) . "
                    </div>
                </td>
            </tr>
            <tr>
                <td style='padding: 8px 0; font-weight: bold;'>Fecha:</td>
                <td style='padding: 8px 0;'>" . date("d/m/Y H:i:s") . "</td>
            </tr>
            <tr>
                <td style='padding: 8px 0; font-weight: bold;'>IP:</td>
                <td style='padding: 8px 0;'>" . $_SERVER['REMOTE_ADDR'] . "</td>
            </tr>
        </table>
        
        <hr style='margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;'>
        <p style='font-size: 12px; color: #7f8c8d; text-align: center;'>
            Este mensaje fue enviado desde el formulario de contacto del sitio web.
        </p>
    </div>
</body>
</html>
";

// Headers para correo HTML
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
$headers .= "From: {$name} <noreply@{$_SERVER['HTTP_HOST']}>" . "\r\n";
$headers .= "Reply-To: {$email}" . "\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Headers adicionales para mejor entrega
$headers .= "X-Priority: 3" . "\r\n";
$headers .= "X-MSMail-Priority: Normal" . "\r\n";

// Intentar enviar el correo
if (mail($recipient_email, $email_subject, $email_body, $headers)) {
    // Opcional: Guardar registro en archivo log
    $log_entry = date("Y-m-d H:i:s") . " - Mensaje de {$name} ({$email}) - Asunto: {$subject}\n";
    file_put_contents("contact_log.txt", $log_entry, FILE_APPEND | LOCK_EX);
    
    echo json_encode([
        "status" => "success", 
        "message" => "Mensaje enviado exitosamente"
    ]);
} else {
    // Registrar error en log
    error_log("Error al enviar correo desde formulario: " . print_r($_POST, true));
    
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Error interno al procesar el mensaje. Por favor intente más tarde."
    ]);
}
?>