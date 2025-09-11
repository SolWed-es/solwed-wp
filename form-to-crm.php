<?php
// Cargar el entorno de WordPress para acceder a get_option()
$wp_load_paths = [
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php',
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
    (isset($_SERVER['DOCUMENT_ROOT']) && file_exists(dirname($_SERVER['DOCUMENT_ROOT']) . '/public_html/wp-load.php')) ? dirname($_SERVER['DOCUMENT_ROOT']) . '/public_html/wp-load.php' : null,
];
$wp_load_paths = array_filter($wp_load_paths);


$wp_load_path_found = false;
foreach ($wp_load_paths as $path) {
    if ($path && file_exists($path)) {
        require_once($path);
        $wp_load_path_found = true;
        break;
    }
}

if (!$wp_load_path_found) {
    if (isset($_SERVER['SCRIPT_FILENAME'])) {
        $script_path = dirname($_SERVER['SCRIPT_FILENAME']);
        for ($i = 0; $i < 7; $i++) { 
            if (file_exists($script_path . '/wp-load.php')) {
                require_once($script_path . '/wp-load.php');
                $wp_load_path_found = true;
                break;
            }
            if (file_exists($script_path . '/wp-config.php') ) {
                if(file_exists($script_path . '/wp-load.php')) {
                    require_once($script_path . '/wp-load.php');
                     $wp_load_path_found = true;
                } else if (file_exists(dirname($script_path) . '/wp-load.php')) {
                    require_once(dirname($script_path) . '/wp-load.php');
                     $wp_load_path_found = true;
                }
                if ($wp_load_path_found) break;
            }
            if ($script_path === dirname($script_path)) break; 
            $script_path = dirname($script_path);
        }
    }
}


if (!$wp_load_path_found) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'Error crítico: No se pudo cargar el entorno de WordPress (wp-load.php). Verifique la ruta en form-to-crm.php.'
    ]);
    error_log('SOLWED CRITICAL: wp-load.php not found from form-to-crm.php');
    exit;
}

$log_file = __DIR__ . '/log.txt';

function solwed_log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    if (is_array($message) || is_object($message)) {
        $message_str = print_r($message, true);
    } else {
        $message_str = (string) $message;
    }
    $formatted_message = "[{$timestamp}] {$message_str}" . PHP_EOL;
    file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
}

solwed_log_message('==============================================');
solwed_log_message('Llamada recibida a form-to-crm.php (WP Solwed)');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    solwed_log_message('Petición no es POST. Abortando.');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido. Solo se aceptan peticiones POST.']);
    exit;
}

solwed_log_message('Datos $_POST recibidos: ' . print_r($_POST, true));

$solwed_settings = get_option('solwed_crm_settings');

$default_api_url = 'https://tu-dominio-erp.com/api/3/contactos';
$default_api_token = 'TU_TOKEN_DE_EJEMPLO_AQUI_12345';

$api_url   = isset($solwed_settings['solwed_api_url']) && !empty($solwed_settings['solwed_api_url']) ? $solwed_settings['solwed_api_url'] : $default_api_url;
$api_token = isset($solwed_settings['solwed_api_token']) && !empty($solwed_settings['solwed_api_token']) ? $solwed_settings['solwed_api_token'] : $default_api_token;

if ( empty($api_url) || $api_url === $default_api_url || empty($api_token) || $api_token === $default_api_token ) {
    $error_message = 'Error: La URL de la API del CRM o el Token no están configurados correctamente en "Ajustes > Configuración CRM Solwed" o se están usando los valores de ejemplo. Por favor, configure el plugin.';
    solwed_log_message($error_message);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $error_message]);
    exit;
}

solwed_log_message('API URL (CRM): ' . $api_url);

$form_data = $_POST; 

$data = [
    'nombre'        => isset($form_data['Nombre']) ? sanitize_text_field($form_data['Nombre']) : (isset($form_data['nombre']) ? sanitize_text_field($form_data['nombre']) : ''),
    'apellidos'     => isset($form_data['Apellidos']) ? sanitize_text_field($form_data['Apellidos']) : (isset($form_data['apellidos']) ? sanitize_text_field($form_data['apellidos']) : ''),
    'email'         => isset($form_data['Correo_electronico']) ? sanitize_email($form_data['Correo_electronico']) : (isset($form_data['email']) ? sanitize_email($form_data['email']) : ''),
    'telefono1'     => isset($form_data['Telefono']) ? sanitize_text_field($form_data['Telefono']) : (isset($form_data['telefono1']) ? sanitize_text_field($form_data['telefono1']) : (isset($form_data['phone']) ? sanitize_text_field($form_data['phone']) : '')),
    'observaciones' => isset($form_data['Mensaje']) ? sanitize_textarea_field($form_data['Mensaje']) : (isset($form_data['mensaje']) ? sanitize_textarea_field($form_data['mensaje']) : (isset($form_data['message']) ? sanitize_textarea_field($form_data['message']) : '')),
];

if (empty($data['email'])) {
    solwed_log_message('Error: El campo de correo electrónico está vacío o no se recibió con un nombre esperado.');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'El campo de correo electrónico es obligatorio y no se encontró.']);
    exit;
}

$body = http_build_query($data);
solwed_log_message('Body para cURL (urlencode): ' . $body);

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Token: ' . $api_token,
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); 
curl_setopt($ch, CURLOPT_TIMEOUT, 45);        

$response_body = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error_number = curl_errno($ch);
$curl_error_message = curl_error($ch);
curl_close($ch);

solwed_log_message('Respuesta CRM (HTTP Code: ' . $http_code . '): ' . $response_body);
if ($curl_error_number) {
    solwed_log_message('Error cURL (' . $curl_error_number . '): ' . $curl_error_message);
}

header('Content-Type: application/json; charset=utf-8');
if ($http_code >= 200 && $http_code < 300) {
    $decoded_response = json_decode($response_body, true);
    if (json_last_error() === JSON_ERROR_NONE) {
         solwed_log_message('Respuesta del CRM (JSON decodificado): ' . print_r($decoded_response, true));
    }
    echo json_encode([
        'status' => 'ok',
        'message' => 'Datos recibidos y procesados correctamente por el webhook.',
        'crm_http_code' => $http_code,
        'crm_response' => $decoded_response ?? $response_body
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al contactar con la API del CRM.',
        'http_code' => $http_code,
        'crm_response' => $response_body,
        'curl_errno' => $curl_error_number,
        'curl_error' => $curl_error_message
    ]);
}
exit;
?>
