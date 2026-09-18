<?php

/* =====================================================
   WANDERAI - EXPORT ITINERARY AS .ICS CALENDAR
   ===================================================== */

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";
require_once "itinerary_helpers.php";

$user_id = (int)$_SESSION["user_id"];
$trip_id = isset($_GET["trip_id"]) ? (int)$_GET["trip_id"] : 0;

if ($trip_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

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
    header("Location: dashboard.php");
    exit();
}

$trip = $result->fetch_assoc();
$stmt->close();

$generatedItinerary = json_decode($trip["generated_itinerary"] ?? "", true);

if (!is_array($generatedItinerary) || empty($generatedItinerary)) {
    header("Location: itinerary.php?trip_id=" . $trip_id);
    exit();
}

$startDate = $trip["start_date"] ?? date("Y-m-d");
$startDateIso = date("Y-m-d", strtotime($startDate));

$destination = $trip["destination"] ?? "Your Trip";

$icsContent = wanderBuildIcsCalendar(
    $generatedItinerary,
    $startDateIso,
    $destination,
    $trip_id
);

$filename =
    "wanderai-" .
    preg_replace("/[^a-z0-9]+/i", "-", strtolower($destination)) .
    "-itinerary.ics";

header("Content-Type: text/calendar; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Content-Length: " . strlen($icsContent));

echo $icsContent;
exit();

?>
