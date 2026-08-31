<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";

$user_id = (int) $_SESSION["user_id"];

/* ==========================================
   CHECK TRIP ID
   ========================================== */

if (!isset($_GET["trip_id"])) {
    header("Location: my_trips.php");
    exit();
}

$trip_id = (int) $_GET["trip_id"];

if ($trip_id <= 0) {
    header("Location: my_trips.php");
    exit();
}


/* ==========================================
   DELETE ONLY THE LOGGED-IN USER'S TRIP
   ========================================== */

$stmt = $conn->prepare(
    "DELETE FROM trips
     WHERE trip_id = ?
     AND user_id = ?"
);

$stmt->bind_param(
    "ii",
    $trip_id,
    $user_id
);

$stmt->execute();

$stmt->close();

$conn->close();


/* ==========================================
   RETURN TO MY TRIPS
   ========================================== */

header("Location: my_trips.php");
exit();

?>