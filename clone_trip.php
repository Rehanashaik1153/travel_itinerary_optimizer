<?php

/* =====================================================
   WANDERAI - CLONE A TRIP
   Duplicates a trip's settings (destination, dates,
   budget, travelers, interests, transport) as a new
   trip row, but leaves out the generated itinerary and
   excluded places so it can be freshly regenerated.
   Sends the person straight to Edit Trip so they can
   adjust the date before it's used.
   ===================================================== */

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";

$user_id = (int)$_SESSION["user_id"];
$trip_id = isset($_GET["trip_id"]) ? (int)$_GET["trip_id"] : 0;

if ($trip_id <= 0) {
    header("Location: my_trips.php");
    exit();
}

/* =====================================================
   LOAD ORIGINAL TRIP
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
    header("Location: my_trips.php?clone_error=1");
    exit();
}

$originalTrip = $result->fetch_assoc();
$stmt->close();

/* =====================================================
   BUILD THE CLONE
   Copy every column except the identity/derived ones.
   ===================================================== */

$excludedColumns = [
    "trip_id",
    "user_id",
    "generated_itinerary",
    "excluded_places",
];

$cloneData = [];

foreach ($originalTrip as $column => $value) {

    if (in_array($column, $excludedColumns, true)) {
        continue;
    }

    $cloneData[$column] = $value;
}

if (isset($cloneData["destination"])) {
    $cloneData["destination"] =
        $cloneData["destination"] . " (Copy)";
}

if (isset($cloneData["selected_location"])) {
    $cloneData["selected_location"] =
        $cloneData["selected_location"] . " (Copy)";
}

if (empty($cloneData)) {
    header("Location: my_trips.php?clone_error=1");
    exit();
}

$columns = array_keys($cloneData);

$placeholders = implode(", ", array_fill(0, count($columns) + 1, "?"));

$columnList =
    "user_id, " . implode(", ", $columns);

$insertStmt = $conn->prepare(
    "INSERT INTO trips ($columnList) VALUES ($placeholders)"
);

if (!$insertStmt) {
    header("Location: my_trips.php?clone_error=1");
    exit();
}

$types = "i" . str_repeat("s", count($columns));
$values = array_values($cloneData);

$bindParams = [];
$bindParams[] = $types;
$bindParams[] = &$user_id;

foreach ($values as $key => $value) {
    $bindParams[] = &$values[$key];
}

call_user_func_array([$insertStmt, "bind_param"], $bindParams);

if (!$insertStmt->execute()) {
    $insertStmt->close();
    header("Location: my_trips.php?clone_error=1");
    exit();
}

$newTripId = $insertStmt->insert_id;

$insertStmt->close();

header("Location: edit_trip.php?trip_id=" . $newTripId . "&cloned=1");
exit();

?>
