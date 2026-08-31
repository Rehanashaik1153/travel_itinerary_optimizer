<?php

session_start();

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");
    exit();

}


/* =====================================================
   CHECK WHETHER SEARCH RESULTS EXIST
   ===================================================== */

if (!isset($_SESSION["geocode_results"])) {

    header("Location: destination.php");
    exit();

}


$results = $_SESSION["geocode_results"];

$selectedPlace = null;


/* =====================================================
   GET EDIT TRIP ID
   ===================================================== */

$edit_trip_id = isset($_POST["trip_id"])
    ? (int) $_POST["trip_id"]
    : 0;


/* =====================================================
   CHECK SELECTED LOCATION
   ===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    if (isset($_POST["selected_place"])) {


        $index = (int) $_POST["selected_place"];


        /* Check whether selected index exists */

        if (isset($results[$index])) {


            $selectedPlace =
                $results[$index];


            /* =============================================
               SAVE SELECTED DESTINATION IN SESSION
               ============================================= */

            $_SESSION["selected_destination"] =
                $selectedPlace["display_name"];


            $_SESSION["destination_latitude"] =
                (float) $selectedPlace["lat"];


            $_SESSION["destination_longitude"] =
                (float) $selectedPlace["lon"];


            /* Save edit trip ID if editing */

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


/* =====================================================
   INVALID SELECTION
   ===================================================== */

if ($selectedPlace === null) {

    header("Location: destination.php");
    exit();

}

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
        Destination Selected | WanderAI
    </title>


    <link
        rel="stylesheet"
        href="style.css"
    >


    <style>


        .selected-container {

            max-width: 750px;
            margin: 70px auto;
            padding: 30px;

        }


        .selected-card {

            background: white;
            padding: 35px;
            border-radius: 16px;

            box-shadow:
                0 5px 25px rgba(0,0,0,0.08);

            text-align: center;

        }


        .selected-icon {

            font-size: 55px;
            margin-bottom: 15px;

        }


        .selected-card h1 {

            margin-bottom: 15px;

        }


        .selected-place {

            font-size: 20px;
            font-weight: bold;
            margin: 20px 0;

        }


        .coordinates {

            background: #f5f7fa;
            padding: 18px;
            border-radius: 10px;
            margin-top: 20px;

        }


        .coordinate-row {

            margin: 8px 0;

        }


        .continue-btn {

            display: inline-block;
            margin-top: 25px;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;

        }


        .change-btn {

            display: inline-block;
            margin-top: 15px;
            text-decoration: none;

        }


    </style>

</head>


<body class="dashboard-body">


<!-- =====================================================
     NAVIGATION
     ===================================================== -->

<header class="dashboard-navbar">


    <a
        href="dashboard.php"
        class="logo"
    >


        <span class="logo-icon">
            ✈
        </span>


        <span>
            Wander<span>AI</span>
        </span>


    </a>



    <nav class="dashboard-nav">


        <a href="dashboard.php">
            Dashboard
        </a>


        <a
            href="plan_trip.php"
            class="active"
        >
            Plan Trip
        </a>


        <a href="my_trips.php">
            My Trips
        </a>


    </nav>



    <div class="user-menu">


        <span class="user-name">


            <?php

            echo htmlspecialchars(
                $_SESSION["username"]
            );

            ?>


        </span>



        <a
            href="logout.php"
            class="logout-btn"
        >

            Logout

        </a>


    </div>


</header>



<!-- =====================================================
     MAIN CONTENT
     ===================================================== -->

<main class="selected-container">


    <div class="selected-card">


        <!-- SUCCESS ICON -->

        <div class="selected-icon">

            ✅

        </div>



        <p class="dashboard-small-title">

            DESTINATION SELECTED

        </p>



        <h1>

            Perfect! We found your destination.

        </h1>



        <!-- SELECTED LOCATION -->

        <div class="selected-place">


            <?php

            echo htmlspecialchars(
                $selectedPlace["display_name"]
            );

            ?>


        </div>



        <!-- COORDINATES -->

        <div class="coordinates">


            <div class="coordinate-row">


                <strong>
                    Latitude:
                </strong>


                <?php

                echo htmlspecialchars(
                    $selectedPlace["lat"]
                );

                ?>


            </div>



            <div class="coordinate-row">


                <strong>
                    Longitude:
                </strong>


                <?php

                echo htmlspecialchars(
                    $selectedPlace["lon"]
                );

                ?>


            </div>


        </div>



        <p>


            📍 Your location has been identified successfully.
            You don't need to enter the coordinates manually.


        </p>



        <!-- CONTINUE TO PLAN TRIP -->

        <a
            href="plan_trip.php<?php
                echo $edit_trip_id > 0
                    ? '?trip_id=' . $edit_trip_id
                    : '';
            ?>"
            class="dashboard-primary-btn continue-btn"
        >


            Continue to Trip Planning →


        </a>



        <br>



        <!-- CHANGE DESTINATION -->

        <a
            href="destination.php<?php
                echo $edit_trip_id > 0
                    ? '?trip_id=' . $edit_trip_id
                    : '';
            ?>"
            class="change-btn"
        >


            ← Choose a Different Destination


        </a>


    </div>


</main>


</body>

</html>