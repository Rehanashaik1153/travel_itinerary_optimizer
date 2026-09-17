<?php

/* =====================================================
   WANDERAI - PLACES CACHE PREWARM
   =====================================================

   The single slowest part of "Generate Itinerary" is the
   live Overpass (OpenStreetMap) request. Everything after
   it - scoring, routing, scheduling - is pure PHP and takes
   milliseconds.

   This endpoint does that slow network call EARLY, in the
   background, while the user is still filling in the trip
   form. By the time they press "Generate", places.php finds
   the area already in its local cache and the itinerary is
   built almost instantly.

   It is called with fetch() from plan_trip.php and from the
   itinerary page, and it deliberately returns immediately-
   ignorable JSON. Nothing on the page depends on it, so if
   it fails the normal (slower) path still works exactly as
   before.
   ===================================================== */

session_start();

header("Content-Type: application/json");

/* Keep running even if the browser navigates away - that is
   the whole point of a prewarm. */

@ignore_user_abort(true);
@set_time_limit(60);

if (!isset($_SESSION["user_id"])) {

    echo json_encode([
        "success" => false,
        "message" => "Not signed in."
    ]);

    exit();
}

require_once "places.php";


/* =====================================================
   COORDINATES
   =====================================================

   Prefer explicit parameters, then fall back to whatever
   destination the user just selected in this session.
   ===================================================== */

$latitude = isset($_GET["lat"])
    ? (float)$_GET["lat"]
    : (float)($_SESSION["destination_latitude"] ?? 0);

$longitude = isset($_GET["lng"])
    ? (float)$_GET["lng"]
    : (float)($_SESSION["destination_longitude"] ?? 0);

$destination = trim(
    (string)(
        $_GET["destination"]
        ?? $_SESSION["selected_destination"]
        ?? ""
    )
);


/* -----------------------------------------------------
   RELEASE THE SESSION LOCK IMMEDIATELY
   -----------------------------------------------------

   PHP's default file-based sessions lock the session file
   for the whole request. Without this line, this slow
   background request would block every other page the user
   opens - the exact opposite of what a prewarm is for.
   Everything needed from the session has already been read
   above.
   ----------------------------------------------------- */

session_write_close();


if ($latitude == 0 || $longitude == 0) {

    echo json_encode([
        "success" => false,
        "message" => "No destination coordinates to prewarm."
    ]);

    exit();
}


/* =====================================================
   WARM THE CACHE
   =====================================================

   getNearbyPlaces() writes its raw Overpass response into
   /cache/places, which is exactly what the itinerary page
   reads later.
   ===================================================== */

$startedAt = microtime(true);

$result = getNearbyPlaces(
    $latitude,
    $longitude,
    10000,
    $destination
);

$elapsed = round(microtime(true) - $startedAt, 2);

echo json_encode([

    "success" =>
        !empty($result["success"]),

    "places" =>
        isset($result["places"])
            ? count($result["places"])
            : 0,

    "seconds" =>
        $elapsed

]);
