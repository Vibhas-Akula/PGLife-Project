<?php
session_start();

// Set CORS headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

require "../includes/database_connect.php";

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
$city_name = isset($_GET["city"]) ? $_GET["city"] : NULL;

// Validate city parameter
if (empty($city_name)) {
    http_response_code(400); // Bad Request
    echo json_encode(["error" => "City parameter is required."]);
    exit;
}

// Fetch city details
$sql_1 = "SELECT * FROM cities WHERE name = ?";
$stmt_1 = $conn->prepare($sql_1);
$stmt_1->bind_param("s", $city_name);
$stmt_1->execute();
$result_1 = $stmt_1->get_result();

if ($result_1->num_rows === 0) {
    http_response_code(404); // Not Found
    echo json_encode(["error" => "Sorry! We do not have any PG listed in this city."]);
    exit;
}

$city = $result_1->fetch_assoc();
$city_id = $city['id'];

// Fetch properties in the city
$sql_2 = "SELECT * FROM properties WHERE city_id = ?";
$stmt_2 = $conn->prepare($sql_2);
$stmt_2->bind_param("i", $city_id);
$stmt_2->execute();
$result_2 = $stmt_2->get_result();

$properties = $result_2->fetch_all(MYSQLI_ASSOC);

// Fetch interested users for properties in the city
$sql_3 = "SELECT * 
          FROM interested_users_properties iup
          INNER JOIN properties p ON iup.property_id = p.id
          WHERE p.city_id = ?";
$stmt_3 = $conn->prepare($sql_3);
$stmt_3->bind_param("i", $city_id);
$stmt_3->execute();
$result_3 = $stmt_3->get_result();

$interested_users_properties = $result_3->fetch_all(MYSQLI_ASSOC);

// Process property data
$new_properties = array();
foreach ($properties as $property) {
    $property_images = glob("../img/properties/" . $property['id'] . "/*");
    $property_image = $property_images ? "img/properties/" . $property['id'] . "/" . basename($property_images[0]) : "";

    $interested_users_count = 0;
    $is_interested = false;
    foreach ($interested_users_properties as $interested_user_property) {
        if ($interested_user_property['property_id'] == $property['id']) {
            $interested_users_count++;
            if ($interested_user_property['user_id'] == $user_id) {
                $is_interested = true;
            }
        }
    }

    $property['interested_users_count'] = $interested_users_count;
    $property['is_interested'] = $is_interested;
    $property['image'] = $property_image;
    $new_properties[] = $property;
}

echo json_encode($new_properties);