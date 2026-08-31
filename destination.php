<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "geocode.php";

$results = [];
$message = "";
$searchPerformed = false;


/* =====================================================
   CHECK IF THIS IS FOR EDITING A TRIP
   ===================================================== */

$edit_trip_id = isset($_GET["trip_id"])
    ? (int) $_GET["trip_id"]
    : 0;


/* =====================================================
   SEARCH DESTINATION
   ===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $destination = trim($_POST["destination"] ?? "");

    /* Preserve trip ID during edit */

    $edit_trip_id = isset($_POST["trip_id"])
        ? (int) $_POST["trip_id"]
        : 0;


    if ($destination === "") {

        $message = "Please enter a destination.";

    } else {

        $result = geocodeDestination($destination);

        $searchPerformed = true;


        if ($result["success"]) {

            $results = $result["results"];

            /* Save search results temporarily */

            $_SESSION["geocode_results"] = $results;


            /* Save edit trip ID temporarily */

            if ($edit_trip_id > 0) {

                $_SESSION["destination_edit_trip_id"] =
                    $edit_trip_id;
            }

        } else {

            $message = $result["message"];
        }
    }
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
        Choose Destination | WanderAI
    </title>


    <link
        rel="stylesheet"
        href="style.css"
    >


    <style>

        .destination-container {
            max-width: 900px;
            margin: 50px auto;
            padding: 30px;
        }


        .destination-header {
            text-align: center;
            margin-bottom: 30px;
        }


        .destination-search {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
        }


        .destination-search input {
            width: 70%;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }


        .destination-search button {
            padding: 14px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin-left: 8px;
        }


        .destination-result {
            background: white;
            margin-top: 20px;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #ddd;
            transition: 0.2s;
        }


        .destination-result:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }


        .destination-result label {
            display: block;
            cursor: pointer;
        }


        .destination-result input[type="radio"] {
            margin-right: 10px;
        }


        .coordinates {
            margin-top: 10px;
            font-size: 14px;
        }


        .select-button {
            margin-top: 25px;
            text-align: center;
        }


        .select-button button {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }


        .error-message {
            margin-top: 20px;
            padding: 15px;
            background: #ffecec;
            color: #c00;
            border-radius: 8px;
        }


        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
        }


        @media (max-width: 600px) {

            .destination-search input {
                width: 100%;
                margin-bottom: 10px;
            }


            .destination-search button {
                width: 100%;
                margin-left: 0;
            }

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

<main class="destination-container">


    <!-- HEADER -->

    <section class="destination-header">

        <p class="dashboard-small-title">

            GLOBAL DESTINATION SEARCH

        </p>


        <h1>

            🌍 Choose Your Destination

        </h1>


        <p>

            Enter any country, state, city, town or village
            anywhere in the world.

        </p>

    </section>



    <!-- =================================================
         SEARCH FORM
         ================================================= -->

    <section class="destination-search">


        <form method="POST">


            <!-- Preserve edit trip ID -->

            <input
                type="hidden"
                name="trip_id"
                value="<?php
                    echo $edit_trip_id;
                ?>"
            >


            <input
                type="text"
                name="destination"
                placeholder="Example: Coonoor, London, Tokyo, Guntur..."
                required
            >


            <button type="submit">

                🔍 Search

            </button>


        </form>

    </section>



    <!-- ERROR MESSAGE -->

    <?php if ($message !== ""): ?>


        <div class="error-message">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>


    <?php endif; ?>



    <!-- =================================================
         SEARCH RESULTS
         ================================================= -->

    <?php if (
        $searchPerformed &&
        count($results) > 0
    ): ?>


        <section>


            <h2
                style="
                    text-align:center;
                    margin-top:35px;
                "
            >

                Select the Correct Location

            </h2>



            <form
                method="POST"
                action="select_destination.php"
            >


                <!-- Preserve edit trip ID -->

                <input
                    type="hidden"
                    name="trip_id"
                    value="<?php
                        echo $edit_trip_id;
                    ?>"
                >


                <?php
                foreach (
                    $results as $index => $place
                ):
                ?>


                    <div class="destination-result">


                        <label>


                            <input
                                type="radio"
                                name="selected_place"
                                value="<?php
                                    echo $index;
                                ?>"
                                required
                            >


                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $place["display_name"]
                                );

                                ?>

                            </strong>


                        </label>



                        <div class="coordinates">


                            <strong>
                                Latitude:
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $place["lat"]
                            );

                            ?>


                            <br>


                            <strong>
                                Longitude:
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $place["lon"]
                            );

                            ?>


                        </div>


                    </div>


                <?php endforeach; ?>



                <div class="select-button">


                    <button type="submit">

                        Continue with Selected Destination →

                    </button>


                </div>


            </form>


        </section>


    <?php endif; ?>



    <!-- BACK BUTTON -->

    <div style="text-align:center;">


        <a
            href="plan_trip.php<?php
                echo $edit_trip_id > 0
                    ? '?trip_id=' . $edit_trip_id
                    : '';
            ?>"
            class="back-link"
        >

            ← Back to Trip Planning

        </a>


    </div>


</main>


</body>

</html>