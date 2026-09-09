<?php

/* =====================================================
   WANDERAI - EXCLUDE / RESTORE A PLACE FROM A TRIP
   =====================================================

   Lets a user say "don't recommend this place" (🚫) or
   undo that later. The excluded list is stored per trip
   as a JSON array of place names in trips.excluded_places,
   and itinerary.php filters these out before scoring or
   scheduling ever sees them.
   ===================================================== */

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";

$user_id = (int)$_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my_trips.php");
    exit();
}

$trip_id = (int)($_POST["trip_id"] ?? 0);
$placeName = trim((string)($_POST["place_name"] ?? ""));
$action = $_POST["action"] ?? "exclude";

if ($trip_id <= 0 || $placeName === "") {
    header("Location: my_trips.php");
    exit();
}


/* =============================================
   LOAD TRIP (must belong to this user)
   ============================================= */

$stmt = $conn->prepare(
    "SELECT excluded_places
     FROM trips
     WHERE trip_id = ?
     AND user_id = ?"
);

if (!$stmt) {

    /*
       Most likely cause: the "excluded_places" column
       has not been added yet. See DATABASE_UPDATE_REQUIRED.sql
    */

    header(
        "Location: itinerary.php?trip_id=" .
        $trip_id .
        "&exclude_error=1"
    );
    exit();
}

$stmt->bind_param("ii", $trip_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
$trip = $result->fetch_assoc();

$stmt->close();

if (!$trip) {
    header("Location: my_trips.php");
    exit();
}


/* =============================================
   UPDATE EXCLUSION LIST
   ============================================= */

$excludedPlaces = [];

if (!empty($trip["excluded_places"])) {
    $decoded = json_decode($trip["excluded_places"], true);
    if (is_array($decoded)) {
        $excludedPlaces = $decoded;
    }
}

$normalizedTarget = strtolower($placeName);

$excludedPlaces = array_values(
    array_filter(
        $excludedPlaces,
        function ($name) use ($normalizedTarget) {
            return strtolower(trim((string)$name)) !== $normalizedTarget;
        }
    )
);

if ($action === "exclude") {
    $excludedPlaces[] = $placeName;
}


/* =============================================
   SAVE + REGENERATE
   ============================================= */

$updateStmt = $conn->prepare(
    "UPDATE trips
     SET excluded_places = ?
     WHERE trip_id = ?
     AND user_id = ?"
);

if (!$updateStmt) {

    header(
        "Location: itinerary.php?trip_id=" .
        $trip_id .
        "&exclude_error=1"
    );
    exit();
}

$encodedExclusions = json_encode($excludedPlaces);

$updateStmt->bind_param(
    "sii",
    $encodedExclusions,
    $trip_id,
    $user_id
);

$updateStmt->execute();
$updateStmt->close();

header(
    "Location: itinerary.php?trip_id=" .
    $trip_id .
    "&regenerate=1"
);
exit();

?>
