<?php

/* =====================================================
   WANDERAI - REORDER A DAY'S SCHEDULE
   Called via fetch() from itinerary.php after a
   drag-and-drop reorder. Recomputes travel time and
   start/end times for that day only, then saves.
   ===================================================== */

session_start();

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not logged in."]);
    exit();
}

require_once "db.php";
require_once "itinerary_helpers.php";

$user_id = (int)$_SESSION["user_id"];

$input = json_decode(file_get_contents("php://input"), true);

$trip_id = isset($input["trip_id"]) ? (int)$input["trip_id"] : 0;
$dayNumber = isset($input["day"]) ? (int)$input["day"] : 0;
$order = isset($input["order"]) && is_array($input["order"]) ? $input["order"] : null;

if ($trip_id <= 0 || $dayNumber <= 0 || $order === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing or invalid parameters."]);
    exit();
}

/* =====================================================
   LOAD TRIP
   ===================================================== */

$stmt = $conn->prepare(
    "SELECT *
     FROM trips
     WHERE trip_id = ?
     AND user_id = ?"
);

$stmt->bind_param("ii", $trip_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    $stmt->close();
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Trip not found."]);
    exit();
}

$trip = $result->fetch_assoc();
$stmt->close();

$generatedItinerary = json_decode($trip["generated_itinerary"] ?? "", true);

if (!is_array($generatedItinerary)) {
    http_response_code(422);
    echo json_encode(["success" => false, "error" => "This trip has no itinerary yet."]);
    exit();
}

/* =====================================================
   FIND THE TARGET DAY
   ===================================================== */

$dayFound = false;

foreach ($generatedItinerary as &$dayData) {

    if (
        !is_array($dayData) ||
        (int)($dayData["day"] ?? 0) !== $dayNumber
    ) {
        continue;
    }

    $dayFound = true;

    $originalPlaces = is_array($dayData["places"] ?? null) ? $dayData["places"] : [];

    // Validate the requested order: must be a permutation of
    // valid indices into the original places array.
    $validIndices = range(0, count($originalPlaces) - 1);
    $sortedOrder = $order;
    sort($sortedOrder);

    if (
        count($originalPlaces) === 0 ||
        $sortedOrder !== $validIndices
    ) {
        http_response_code(422);
        echo json_encode(["success" => false, "error" => "Invalid order for this day."]);
        exit();
    }

    $reorderedPlaces = [];

    foreach ($order as $originalIndex) {
        $reorderedPlaces[] = $originalPlaces[(int)$originalIndex];
    }

    $accommodationLatitude = $trip["latitude"] ?? null;
    $accommodationLongitude = $trip["longitude"] ?? null;

    $dayData["places"] = wanderRecomputeDaySchedule(
        $reorderedPlaces,
        $accommodationLatitude,
        $accommodationLongitude,
        $trip["transport_preference"] ?? "walking"
    );

    break;
}
unset($dayData);

if (!$dayFound) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Day not found in this trip."]);
    exit();
}

/* =====================================================
   SAVE
   ===================================================== */

$updateStmt = $conn->prepare(
    "UPDATE trips
     SET generated_itinerary = ?
     WHERE trip_id = ?
     AND user_id = ?"
);

$encoded = json_encode($generatedItinerary);

$updateStmt->bind_param("sii", $encoded, $trip_id, $user_id);

if (!$updateStmt->execute()) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Could not save the new order."]);
    exit();
}

$updateStmt->close();

// Return the recomputed day so the frontend can update
// times/badges without a full page reload.
$updatedDay = null;

foreach ($generatedItinerary as $dayData) {
    if ((int)($dayData["day"] ?? 0) === $dayNumber) {
        $updatedDay = $dayData;
        break;
    }
}

echo json_encode(["success" => true, "day" => $updatedDay]);

?>
