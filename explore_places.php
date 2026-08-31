
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";
require_once "places.php";

$username = htmlspecialchars($_SESSION["username"]);
$user_id = $_SESSION["user_id"];


/* Get destination from session first */

$latitude = $_SESSION["destination_latitude"] ?? "";
$longitude = $_SESSION["destination_longitude"] ?? "";
$destination = $_SESSION["selected_destination"] ?? "";


/* =====================================================
   IF SESSION DESTINATION IS NOT AVAILABLE,
   GET THE LATEST SAVED TRIP FROM DATABASE
   ===================================================== */

if (
    $latitude === "" ||
    $longitude === "" ||
    $destination === ""
) {

    $stmt = $conn->prepare(
        "SELECT selected_location, latitude, longitude
         FROM trips
         WHERE user_id = ?
         ORDER BY trip_id DESC
         LIMIT 1"
    );

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    $result = $stmt->get_result();


    if ($result->num_rows === 1) {

        $trip = $result->fetch_assoc();

        $destination =
            $trip["selected_location"] ?? "";

        $latitude =
            $trip["latitude"] ?? "";

        $longitude =
            $trip["longitude"] ?? "";


        /* Restore destination information to session */

        if ($destination !== "") {

            $_SESSION["selected_destination"] =
                $destination;

        }

        if ($latitude !== null && $latitude !== "") {

            $_SESSION["destination_latitude"] =
                (float)$latitude;

        }

        if ($longitude !== null && $longitude !== "") {

            $_SESSION["destination_longitude"] =
                (float)$longitude;

        }

    }

    $stmt->close();
}


/* =====================================================
   FIND NEARBY PLACES
   ===================================================== */

$places = [];
$message = "";


if (
    $latitude === "" ||
    $longitude === "" ||
    $destination === ""
) {

    $message =
        "Please select and save a destination first.";

} else {

    $result = getNearbyPlaces(
        (float)$latitude,
        (float)$longitude,
        10000
    );


    if ($result["success"]) {

        $places = $result["places"];

    } else {

        $message = $result["message"];

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
        Explore Places | WanderAI
    </title>

    <link rel="stylesheet" href="style.css">


    <style>

        .explore-main {

            max-width: 1100px;

            margin: 50px auto;

            padding: 20px;

        }


        .explore-header {

            text-align: center;

            margin-bottom: 35px;

        }


        .explore-header h1 {

            margin-bottom: 10px;

        }


        .explore-header h1 span {

            color: #007bff;

        }


        .destination-info {

            background: white;

            padding: 20px;

            border-radius: 14px;

            box-shadow:
                0 5px 20px rgba(0,0,0,0.08);

            margin-bottom: 30px;

            text-align: center;

        }


        .destination-info strong {

            font-size: 20px;

        }


        .places-count {

            text-align: center;

            margin: 25px 0;

        }


        .places-grid {

            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(280px, 1fr));

            gap: 20px;

        }


        .place-card {

            background: white;

            padding: 22px;

            border-radius: 14px;

            box-shadow:
                0 5px 20px rgba(0,0,0,0.08);

            border: 1px solid #eee;

        }


        .place-icon {

            font-size: 38px;

            margin-bottom: 10px;

        }


        .place-card h3 {

            margin: 8px 0 12px;

        }


        .place-category {

            display: inline-block;

            padding: 6px 10px;

            border-radius: 20px;

            background: #eef5ff;

            color: #007bff;

            font-size: 13px;

            margin-bottom: 15px;

        }


        .place-details {

            font-size: 14px;

            line-height: 1.7;

        }


        .place-details p {

            margin: 6px 0;

        }


        .place-actions {

            margin-top: 15px;

        }


        .place-actions a {

            text-decoration: none;

            font-size: 14px;

        }


        .error-message {

            background: #ffecec;

            color: #c00;

            padding: 18px;

            border-radius: 10px;

            text-align: center;

            margin-top: 20px;

        }


        .back-section {

            text-align: center;

            margin-top: 35px;

        }


        .back-section a {

            text-decoration: none;

            margin: 5px;

        }


        .no-places {

            background: white;

            padding: 35px;

            text-align: center;

            border-radius: 14px;

            box-shadow:
                0 5px 20px rgba(0,0,0,0.08);

        }

    </style>

</head>


<body class="dashboard-body">


<!-- =====================================================
     NAVIGATION
     ===================================================== -->

<header class="dashboard-navbar">


    <a href="dashboard.php" class="logo">

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

        <a href="plan_trip.php">
            Plan Trip
        </a>

        <a href="my_trips.php">
            My Trips
        </a>

    </nav>


    <div class="user-menu">


        <div class="user-avatar">

            <?php
            echo strtoupper(
                substr($username, 0, 1)
            );
            ?>

        </div>


        <span class="user-name">

            <?php
            echo $username;
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

<main class="explore-main">


    <!-- HEADER -->

    <section class="explore-header">


        <p class="dashboard-small-title">

            

            DISCOVER YOUR NEXT ADVENTURE



        </p>


        <h1>

            📍 Explore Places in
            <span>
                <?php
                echo htmlspecialchars(
                    $destination
                );
                ?>
            </span>

        </h1>


        <p>

            WanderAI is discovering tourist attractions
            near your selected destination.

        </p>


    </section>



    <!-- DESTINATION INFORMATION -->

    <?php if ($destination !== ""): ?>

        <div class="destination-info">

            <p>
                Selected Destination
            </p>

            <strong>

                <?php
                echo htmlspecialchars(
                    $destination
                );
                ?>

            </strong>


            <p>

                📍 Latitude:
                <?php
                echo htmlspecialchars(
                    $latitude
                );
                ?>

                &nbsp;&nbsp;

                📍 Longitude:
                <?php
                echo htmlspecialchars(
                    $longitude
                );
                ?>

            </p>

        </div>

    <?php endif; ?>



    <!-- ERROR -->

    <?php if ($message !== ""): ?>

        <div class="error-message">

            <?php
            echo htmlspecialchars(
                $message
            );
            ?>

            <br><br>

            <a
                href="destination.php"
                class="dashboard-secondary-btn"
            >

                Choose Destination

            </a>

        </div>

    <?php endif; ?>



    <!-- PLACES -->

    <?php if (count($places) > 0): ?>


        <div class="places-count">

            <h2>

                🎯
                <?php
                echo count($places);
                ?>
                Places Found

            </h2>


            <p>

                These places were dynamically discovered
                near your destination.

            </p>

        </div>



        <section class="places-grid">


            <?php foreach ($places as $place): ?>


                <div class="place-card">


                    <div class="place-icon">

                        <?php

                        $category =
                            strtolower(
                                $place["category"]
                            );


                        if (
                            strpos(
                                $category,
                                "museum"
                            ) !== false
                        ) {

                            echo "🏛️";

                        } elseif (
                            strpos(
                                $category,
                                "viewpoint"
                            ) !== false
                        ) {

                            echo "🌄";

                        } elseif (
                            strpos(
                                $category,
                                "gallery"
                            ) !== false
                        ) {

                            echo "🎨";

                        } elseif (
                            strpos(
                                $category,
                                "zoo"
                            ) !== false
                        ) {

                            echo "🦁";

                        } elseif (
                            strpos(
                                $category,
                                "theme"
                            ) !== false
                        ) {

                            echo "🎢";

                        } elseif (
                            strpos(
                                $category,
                                "historic"
                            ) !== false
                        ) {

                            echo "🏰";

                        } else {

                            echo "📍";

                        }

                        ?>

                    </div>



                    <h3>

                        <?php
                        echo htmlspecialchars(
                            $place["name"]
                        );
                        ?>

                    </h3>



                    <span class="place-category">

                        <?php
                        echo htmlspecialchars(
                            $place["category"]
                        );
                        ?>

                    </span>



                    <div class="place-details">


                        <p>

                            📍
                            <strong>
                                Location:
                            </strong>

                            <?php
                            echo htmlspecialchars(
                                $place["latitude"]
                            );
                            ?>,

                            <?php
                            echo htmlspecialchars(
                                $place["longitude"]
                            );
                            ?>

                        </p>



                        <?php if (
                            $place["opening_hours"] !== ""
                        ): ?>

                            <p>

                                🕐
                                <strong>
                                    Opening Hours:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    $place["opening_hours"]
                                );
                                ?>

                            </p>

                        <?php endif; ?>



                        <?php if (
                            $place["fee"] !== ""
                        ): ?>

                            <p>

                                💰
                                <strong>
                                    Fee:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    $place["fee"]
                                );
                                ?>

                            </p>

                        <?php endif; ?>



                        <?php if (
                            $place["website"] !== ""
                        ): ?>

                            <div class="place-actions">

                                <a
                                    href="<?php
                                    echo htmlspecialchars(
                                        $place["website"]
                                    );
                                    ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >

                                    🌐 Visit Website →

                                </a>

                            </div>

                        <?php endif; ?>


                    </div>


                </div>


            <?php endforeach; ?>


        </section>


    <?php elseif ($message === ""): ?>


        <div class="no-places">

            <div style="font-size:50px;">
                🔍
            </div>


            <h2>
                No Tourist Places Found
            </h2>


            <p>

                We couldn't find tourist places
                within 10 km of this destination.

            </p>

        </div>


    <?php endif; ?>



    <!-- =================================================
         NAVIGATION BUTTONS
         ================================================= -->

    <div class="back-section">


        <a
            href="plan_trip.php"
            class="dashboard-secondary-btn"
        >

            ← Back to Trip Planning

        </a>


        <a
            href="destination.php"
            class="dashboard-secondary-btn"
        >

            🌍 Change Destination

        </a>


    </div>


</main>



<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer class="dashboard-footer">


    <div class="footer-logo">

        ✈ Wander<span>AI</span>

    </div>


    <p>

        Your intelligent travel planning companion.

    </p>


    <div class="copyright">

        © 2026 WanderAI — AI Travel Itinerary Optimizer

    </div>


</footer>


</body>

</html>