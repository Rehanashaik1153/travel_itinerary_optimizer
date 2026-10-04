<?php

/*
=====================================================
TRIPNEST - SELECT DESTINATION
=====================================================
*/

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["geocode_results"])) {
    header("Location: destination.php");
    exit();
}

$results =
    $_SESSION["geocode_results"];

$selectedPlace = null;

/*
=====================================================
RECOVER EDIT TRIP ID
=====================================================
*/

$edit_trip_id = 0;

if (
    isset($_POST["trip_id"]) &&
    (int)$_POST["trip_id"] > 0
) {
    $edit_trip_id =
        (int)$_POST["trip_id"];
}

if (
    $edit_trip_id <= 0 &&
    isset($_SESSION["destination_edit_trip_id"]) &&
    (int)$_SESSION["destination_edit_trip_id"] > 0
) {
    $edit_trip_id =
        (int)$_SESSION["destination_edit_trip_id"];
}

/*
=====================================================
PROCESS DESTINATION SELECTION
=====================================================
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["selected_place"])) {

        $index =
            (int)$_POST["selected_place"];

        if (isset($results[$index])) {

            $selectedPlace =
                $results[$index];

            /*
            -----------------------------------------
            ENGLISH DESTINATION NAME
            -----------------------------------------
            */

            $displayName =
                trim(
                    (string)(
                        $selectedPlace["english_display_name"]
                        ?? $selectedPlace["english_name"]
                        ?? $selectedPlace["display_name"]
                        ?? ""
                    )
                );

            if ($displayName === "") {

                $displayName =
                    "Selected Destination";
            }

            /*
            -----------------------------------------
            COORDINATES
            -----------------------------------------
            */

            $latitude =
                isset($selectedPlace["lat"])
                    ? (float)$selectedPlace["lat"]
                    : 0;

            $longitude =
                isset($selectedPlace["lon"])
                    ? (float)$selectedPlace["lon"]
                    : 0;

            if (
                $latitude == 0 &&
                $longitude == 0
            ) {

                header(
                    "Location: destination.php"
                );

                exit();
            }

            /*
            -----------------------------------------
            SAVE DESTINATION IN SESSION
            -----------------------------------------
            */

            $_SESSION["selected_destination"] =
                $displayName;

            $_SESSION["destination_latitude"] =
                $latitude;

            $_SESSION["destination_longitude"] =
                $longitude;

            /*
            -----------------------------------------
            SAVE COMPLETE PLACE
            -----------------------------------------
            */

            $_SESSION["selected_destination_place"] = [
                "name" =>
                    $displayName,

                "display_name" =>
                    $displayName,

                "latitude" =>
                    $latitude,

                "longitude" =>
                    $longitude,

                "address" =>
                    $selectedPlace["address"]
                    ?? [],

                "type" =>
                    $selectedPlace["type"]
                    ?? "",

                "class" =>
                    $selectedPlace["class"]
                    ?? "",

                "osm_type" =>
                    $selectedPlace["osm_type"]
                    ?? "",

                "osm_id" =>
                    $selectedPlace["osm_id"]
                    ?? ""
            ];

            /*
            -----------------------------------------
            PRESERVE EDIT TRIP
            -----------------------------------------
            */

            if ($edit_trip_id > 0) {

                $_SESSION["destination_edit_trip_id"] =
                    $edit_trip_id;

            } else {

                unset(
                    $_SESSION["destination_edit_trip_id"]
                );
            }
        }
    }
}

/*
=====================================================
NO SELECTION
=====================================================
*/

if ($selectedPlace === null) {

    /*
    If the selection has already been stored,
    recover it instead of losing the destination.
    */

    if (
        isset($_SESSION["selected_destination"]) &&
        isset($_SESSION["destination_latitude"]) &&
        isset($_SESSION["destination_longitude"])
    ) {

        $selectedPlace = [
            "display_name" =>
                $_SESSION["selected_destination"],

            "lat" =>
                $_SESSION["destination_latitude"],

            "lon" =>
                $_SESSION["destination_longitude"]
        ];

    } else {

        header(
            "Location: destination.php"
        );

        exit();
    }
}

/*
=====================================================
DISPLAY VALUES
=====================================================
*/

$displayName =
    $_SESSION["selected_destination"]
    ?? $selectedPlace["english_display_name"]
    ?? $selectedPlace["display_name"]
    ?? "Selected Destination";

$latitude =
    (float)(
        $_SESSION["destination_latitude"]
        ?? $selectedPlace["lat"]
        ?? 0
    );

$longitude =
    (float)(
        $_SESSION["destination_longitude"]
        ?? $selectedPlace["lon"]
        ?? 0
    );

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        TripNest - Destination Selected
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background:
                #f5f7fb;
            color:
                #1f2937;
        }

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }

        .card {
            width: 100%;
            max-width: 760px;
            background: #ffffff;
            border-radius: 18px;
            padding: 40px;
            box-shadow:
                0 12px 35px rgba(
                    15,
                    23,
                    42,
                    0.10
                );
        }

        .title {
            margin: 0 0 10px;
            font-size: 30px;
            font-weight: 700;
        }

        .subtitle {
            margin: 0;
            color: #64748b;
            font-size: 15px;
        }

        .destination-card {
            margin-top: 30px;
            padding: 25px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .destination-name {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            line-height: 1.4;
        }

        .coordinates {
            margin-top: 12px;
            font-size: 13px;
            color: #64748b;
            line-height: 1.7;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            border-radius: 9px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
        }

        .primary {
            background: #2563eb;
            color: #ffffff;
        }

        .secondary {
            background: #e2e8f0;
            color: #1e293b;
        }

        .primary:hover {
            background: #1d4ed8;
        }

        .secondary:hover {
            background: #cbd5e1;
        }

    </style>

</head>

<body>

<div class="page">

    <div class="card">

        <h1 class="title">
            Destination Selected
        </h1>

        <p class="subtitle">
            Your destination has been selected successfully.
        </p>

        <div class="destination-card">

            <div class="destination-name">
                <?= htmlspecialchars(
                    $displayName,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

            <div class="coordinates">

                Latitude:
                <?= htmlspecialchars(
                    (string)$latitude,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

                <br>

                Longitude:
                <?= htmlspecialchars(
                    (string)$longitude,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        </div>

        <div class="actions">

            <a
                href="destination.php"
                class="button secondary"
            >
                Change Destination
            </a>

            <a
                href="plan_trip.php<?= $edit_trip_id > 0
                    ? "?trip_id=" . $edit_trip_id
                    : "" ?>"
                class="button primary"
            >
                Continue to Trip Planning
            </a>

        </div>

    </div>

</div>

</body>

</html>