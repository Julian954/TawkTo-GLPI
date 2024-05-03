<?php
// Clave secreta del webhook
const WEBHOOK_SECRET = "WEBHOOK_KEY";

/**
 * Verifica la firma del webhook.
 *
 * @param string $body Cuerpo de la solicitud.
 * @param string $signature Firma recibida en la solicitud.
 * @return bool Devuelve true si la firma es válida, de lo contrario false.
 */
function verifySignature($body, $signature) {
    $digest = hash_hmac('sha1', $body, WEBHOOK_SECRET);
    return $signature === $digest;
}

// Verificar la firma del webhook
$request_body = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_TAWK_SIGNATURE'];

if (!verifySignature($request_body, $signature)) {
    error_log("Falla en la verificación de la firma del webhook");
    http_response_code(400); // Bad Request
    exit();
}

$data = json_decode($request_body, true);

// check if the new event is a creation of ticket
if ($data['event'] === 'ticket:create') {
    // Procesar los datos del ticket
    $fecha = $data['time'];
    $ticket = $data['ticket'];
    $requester = $data['requester'];

    $ticket_id = $ticket['id'];
    $human_id = $ticket['humanId'];
    $subject = $ticket['subject'];
    $message = $ticket['message'];
    $reqname = $requester['name'];
    $currentDateTime = date("Y-m-d H:i:s");

    $apimessage = 'Tawk Ticket Id: ' . $ticket_id . PHP_EOL . $message;

    // URL del punto final de la API REST de GLPI
    $GLPI_API_URL = "GLPI API DIRECTION"; //EXAMPLE https://host/apirest.php
    $GLPI_API_USER_TOKEN = "USER TOKEN"; //MAKE SURE TO HAVE ADMIN PRIVILEGES 
    $GLPI_API_APP_TOKEN = "APP TOKEN";  //YOU CAN FOUND IT IN THE API CLIENT SECTION JUST BELOW THE API URL

    $headers = array(
        "Content-Type: application/json"
    );

    // Petición a la API para el token de sesión
    $URLSESSION = $GLPI_API_URL . "/initSession?user_token=" . $GLPI_API_USER_TOKEN . "&app_token=" . $GLPI_API_APP_TOKEN;
    $sessionToken2 = file_get_contents($URLSESSION, false, stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'header' => $headers
        )
    )));
    $sessionToken2 = json_decode($sessionToken2, true);
    $sessionToken2 = $sessionToken2['session_token'];
    
    
    /**
     * Encuentra el ID de ubicación por nombre.
     *
     * @param array $locations Array de ubicaciones.
     * @param string $reqname Nombre de la ubicación a buscar.
     * @return int|null Devuelve el ID de la ubicación si hay coincidencia, de lo contrario null.
     */
    function findLocationIdByName($locations, $reqname) {
        foreach ($locations as $locat) {
            if (like_match('%'. $reqname .'%',$locat['name']) == 1) {
                return $locat['id'];
            }
        }
        return null; // Si no se encuentra la entidad por defecto
    }

    /**
     * Operador SQL Like en PHP.
     *
     * @param string $pattern Patrón de búsqueda.
     * @param string $subject Cadena a comparar.
     * @return bool Devuelve TRUE si hay coincidencia, de lo contrario FALSE.
     */
    function like_match($pattern, $subject)
    {
        $pattern = str_replace('%', '.*', preg_quote($pattern, '/'));
        return (bool) preg_match("/^{$pattern}$/i", $subject);
    }
    

 // Petición de ubicación
    $url =$GLPI_API_URL . "/Location?session_token=" . $sessionToken2 . "&app_token=" . $GLPI_API_APP_TOKEN . "&range=0-10000";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if ($response === false) {
        echo "Error en la solicitud cURL: " . curl_error($ch);
    } else {
        // Verificar si hubo algún error en la respuesta
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_status !== 200) {
            echo 'Error al realizar la solicitud: HTTP ' . $http_status;
        } else {
            // Decodificar la respuesta JSON
            $locations = json_decode($response, true);
            // Verificar si la decodificación fue exitosa
            if ($locations === null) {
                echo 'Error al decodificar la respuesta JSON';
            } else {
                // Search the location ID by name
                $UbiId = findLocationIdByName($locations,$reqname);
                if ($UbiId === null) {
                    echo 'No se encontró la entidad por el nombre especificado';
                }
            }
        }
    }

    curl_close($ch);


 // Petición de creación de ticket

    $data = array(
        "input" => array(
            "name" => $subject,         // Título
            "entitie" => "5",           // ID Entidad (Terceros)
            "date"=> $currentDateTime,  // Fecha
            "status" => "1",            // ID Estado (Nuevo)
            "content" => $apimessage,   // Descripción
            "urgency" => "3",           // ID Urgencia
            "impact" => "3",            // ID Impacto
            "priority" => "3",          // ID Prioridad
            "type" => "2",              // ID Tipo
            "requesttypes_id" => "9",       // ID Origen
            "itilcategory" => "25",     // ID Categoría
            "locations_id" => $UbiId    // ID Tercero
        )
    );

    $json = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_URL, $GLPI_API_URL . "/Ticket?session_token=" . $sessionToken2 . "&app_token=" . $GLPI_API_APP_TOKEN);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    curl_close($ch);
}

// Respondiendo con éxito
http_response_code(200);
?>
