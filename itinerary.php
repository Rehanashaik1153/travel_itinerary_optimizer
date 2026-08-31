<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";
require_once "places.php";
require_once "recommend_places.php";
require_once "generate_itinerary.php";

$username = htmlspecialchars($_SESSION["username"]);
$user_id = (int) $_SESSION["user_id"];


/* =====================================================
   CHECK TRIP ID
   ===================================================== */

if (!isset($_GET["trip_id"])) {
    header("Location: dashboard.php");
    exit();
}

$trip_id = (int) $_GET["trip_id"];


/* =====================================================
   GET TRIP DETAILS
   ===================================================== */

$stmt = $conn->prepare(
    "SELECT * FROM trips
     WHERE trip_id = ? AND user_id = ?"
);

$stmt->bind_param(
    "ii",
    $trip_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();

    header("Location: dashboard.php");
    exit();
}

$trip = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   FORMAT TRIP DATA
   ===================================================== */

$destination = htmlspecialchars(
    $trip["destination"] ?? ""
);

$start_date = !empty($trip["start_date"])
    ? date(
        "d M Y",
        strtotime($trip["start_date"])
    )
    : "";

$number_of_days = max(
    1,
    (int) ($trip["number_of_days"] ?? 1)
);

$budget = number_format(
    (float) ($trip["budget"] ?? 0),
    2
);

$travelers = max(
    1,
    (int) ($trip["travelers"] ?? 1)
);

$interests = htmlspecialchars(
    $trip["interests"] ?? ""
);

$transport = htmlspecialchars(
    $trip["transport_preference"] ?? ""
);

$latitude = (float) (
    $trip["latitude"] ?? 0
);

$longitude = (float) (
    $trip["longitude"] ?? 0
);


/* =====================================================
   REGENERATE ITINERARY
   ===================================================== */

$regenerate = isset($_GET["regenerate"])
    && $_GET["regenerate"] == "1";


/* =====================================================
   FETCH PLACES
   ===================================================== */

$placesResult = getNearbyPlaces(
    $latitude,
    $longitude,
    10000
);

$allPlaces = [];
$placesMessage = "";

if (
    isset($placesResult["success"]) &&
    $placesResult["success"] === true
) {

    $allPlaces = $placesResult["places"] ?? [];

} else {

    $placesMessage =
        $placesResult["message"]
        ?? "Unable to fetch places at the moment.";
}


/* =====================================================
   RECOMMEND PLACES
   ===================================================== */

$recommendedPlaces = [];

if (!empty($allPlaces)) {

    $recommendedPlaces = recommendPlaces(
        $allPlaces,
        $trip["interests"] ?? "",
        $number_of_days
    );
}


/* =====================================================
   SELECT ONE ACCOMMODATION
   ===================================================== */

$selectedAccommodation = null;

foreach ($allPlaces as $place) {

    if (
        isset($place["category"]) &&
        strtolower(
            trim($place["category"])
        ) === "accommodation"
    ) {

        $selectedAccommodation = $place;
        break;
    }
}


/* =====================================================
   REMOVE ACCOMMODATION FROM DAILY ITINERARY
   ===================================================== */

$itineraryPlaces = [];

foreach ($recommendedPlaces as $place) {

    if (
        !isset($place["category"]) ||
        strtolower(
            trim($place["category"])
        ) !== "accommodation"
    ) {

        $itineraryPlaces[] = $place;
    }
}


/* =====================================================
   GENERATE DAY-WISE ITINERARY
   ===================================================== */

$generatedItinerary = [];

if (!empty($itineraryPlaces)) {

    $generatedItinerary = generateItinerary(
        $itineraryPlaces,
        $number_of_days,
        $trip["transport_preference"] ?? "",
        $latitude,
        $longitude
    );
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

    <title>My Itinerary | WanderAI</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body class="dashboard-body">


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
            <?php echo $username; ?>
        </span>


        <a
            href="logout.php"
            class="logout-btn"
        >
            Logout
        </a>

    </div>

</header>



<main class="itinerary-main">


    <section class="itinerary-header">

        <div>

            <p class="dashboard-small-title">
                YOUR AI TRAVEL PLAN
            </p>


            <h1>
                Your Trip to
                <span><?php echo $destination; ?></span>
                ✈️
            </h1>


            <p>
                WanderAI analyzed your destination,
                interests and travel preferences to create
                a personalized itinerary.
            </p>

        </div>


        <div class="itinerary-actions">

            <a
                href="plan_trip.php?trip_id=<?php echo $trip_id; ?>"
                class="itinerary-action-btn edit-trip-action"
            >
                ✏️ Edit Trip
            </a>


            <a
                href="itinerary.php?trip_id=<?php echo $trip_id; ?>&regenerate=1"
                class="itinerary-action-btn regenerate-action"
                onclick="return confirm('Regenerate your itinerary with fresh recommendations?');"
            >
                🔄 Regenerate
            </a>


            <a
                href="plan_trip.php"
                class="itinerary-action-btn new-trip-action"
            >
                ✈️ Plan Another Trip
            </a>

        </div>

    </section>



    <section class="trip-summary-grid">


        <div class="trip-summary-card">

            <div class="summary-icon">
                📍
            </div>

            <div>

                <span>Destination</span>

                <strong>
                    <?php echo $destination; ?>
                </strong>

            </div>

        </div>


        <div class="trip-summary-card">

            <div class="summary-icon">
                📅
            </div>

            <div>

                <span>Start Date</span>

                <strong>
                    <?php echo $start_date; ?>
                </strong>

            </div>

        </div>


        <div class="trip-summary-card">

            <div class="summary-icon">
                🗓️
            </div>

            <div>

                <span>Duration</span>

                <strong>
                    <?php echo $number_of_days; ?> Days
                </strong>

            </div>

        </div>


        <div class="trip-summary-card">

            <div class="summary-icon">
                💰
            </div>

            <div>

                <span>Budget</span>

                <strong>
                    ₹<?php echo $budget; ?>
                </strong>

            </div>

        </div>


        <div class="trip-summary-card">

            <div class="summary-icon">
                👥
            </div>

            <div>

                <span>Travelers</span>

                <strong>
                    <?php echo $travelers; ?>
                </strong>

            </div>

        </div>

    </section>



    <section class="itinerary-preferences">

        <h2>
            Your Travel Preferences
        </h2>


        <div class="preference-details">

            <div>

                <span>
                    ❤️ Interests
                </span>

                <strong>

                    <?php
                    echo $interests
                        ?: "No interests selected";
                    ?>

                </strong>

            </div>


            <div>

                <span>
                    🚗 Preferred Transport
                </span>

                <strong>

                    <?php
                    echo $transport
                        ?: "Not specified";
                    ?>

                </strong>

            </div>

        </div>

    </section>



    <section class="ai-itinerary-card">

        <div class="ai-itinerary-icon">
            🤖
        </div>


        <div>

            <p class="dashboard-small-title">
                AI ITINERARY ENGINE
            </p>

            <h2>
                Your personalized itinerary is ready!
            </h2>

            <p>
                WanderAI discovered places near your
                destination, matched them with your interests
                and arranged them into a day-wise schedule.
            </p>

        </div>

    </section>



    <section class="accommodation-section">

        <p class="dashboard-small-title">
            YOUR STAY
        </p>

        <h2>
            Accommodation for Your Entire Trip
        </h2>


        <?php if ($selectedAccommodation !== null): ?>


            <?php

            $accommodationMapQuery = urlencode(
                ($selectedAccommodation["latitude"] ?? "")
                . ","
                . ($selectedAccommodation["longitude"] ?? "")
            );

            ?>


            <div class="accommodation-card">

                <div class="accommodation-icon">
                    🏨
                </div>


                <div>

                    <h2>

                        <?php
                        echo htmlspecialchars(
                            $selectedAccommodation["name"] ?? ""
                        );
                        ?>

                    </h2>


                    <p>

                        This accommodation is selected as
                        your stay for the complete
                        <?php echo $number_of_days; ?>-day trip.

                    </p>


                    <span class="place-category">
                        Accommodation
                    </span>


                    <br>


                    <a
                        class="timeline-map"
                        href="https://www.google.com/maps/search/?api=1&query=<?php echo $accommodationMapQuery; ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        🗺️ Open Accommodation in Google Maps
                    </a>

                </div>

            </div>


        <?php else: ?>


            <div class="ai-itinerary-card">

                <div class="ai-itinerary-icon">
                    🏨
                </div>

                <div>

                    <h2>
                        Accommodation not found
                    </h2>

                    <p>
                        No suitable accommodation was found
                        in the current place results.
                    </p>

                </div>

            </div>


        <?php endif; ?>

    </section>



    <section class="recommended-places-section">

        <p class="dashboard-small-title">
            AI RECOMMENDATIONS
        </p>

        <h2>
            Recommended Places to Visit
        </h2>


        <?php if (!empty($placesMessage)): ?>


            <div class="message error">

                <?php
                echo htmlspecialchars($placesMessage);
                ?>

            </div>


        <?php elseif (empty($itineraryPlaces)): ?>


            <div class="ai-itinerary-card">

                <div class="ai-itinerary-icon">
                    🔍
                </div>

                <div>

                    <h2>
                        No matching places found
                    </h2>

                    <p>
                        Try selecting different interests
                        or another destination.
                    </p>

                </div>

            </div>


        <?php else: ?>


            <p class="places-count">

                🌍 <?php echo count($allPlaces); ?>
                places discovered

                <span>•</span>

                ⭐ <?php echo count($itineraryPlaces); ?>
                places selected for your itinerary

            </p>


            <div class="recommended-places-grid">


                <?php foreach ($itineraryPlaces as $place): ?>


                    <div class="recommended-place-card">

                        <h3>

                            📍

                            <?php
                            echo htmlspecialchars(
                                $place["name"] ?? ""
                            );
                            ?>

                        </h3>


                        <span class="place-category">

                            <?php
                            echo htmlspecialchars(
                                $place["category"] ?? ""
                            );
                            ?>

                        </span>


                        <p>

                            ⭐ Match Score:

                            <strong>

                                <?php
                                echo
                                    $place["recommendation_score"]
                                    ?? 0;
                                ?>

                            </strong>

                        </p>


                        <?php if (!empty($place["opening_hours"])): ?>


                            <p>

                                🕐

                                <?php
                                echo htmlspecialchars(
                                    $place["opening_hours"]
                                );
                                ?>

                            </p>


                        <?php endif; ?>

                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>

    </section>



    <section class="day-itinerary-section">

        <p class="dashboard-small-title">
            DAY-WISE SCHEDULE
        </p>

        <h2>
            Your Personalized Travel Schedule
        </h2>

        <p>
            Places are arranged dynamically using
            estimated distance and your preferred transport.
            Your selected accommodation remains the same
            for the entire trip.
        </p>


        <?php if (empty($generatedItinerary)): ?>


            <div class="ai-itinerary-card">

                <div class="ai-itinerary-icon">
                    🗓️
                </div>

                <div>

                    <h2>
                        Itinerary could not be generated
                    </h2>

                    <p>
                        No recommended places are currently
                        available for this trip.
                    </p>

                </div>

            </div>


        <?php else: ?>


            <?php foreach ($generatedItinerary as $dayData): ?>


                <?php

                if (
                    !isset($dayData["day"]) ||
                    !isset($dayData["places"]) ||
                    !is_array($dayData["places"])
                ) {
                    continue;
                }

                ?>


                <div class="day-card">


                    <div class="day-title">

                        <div class="day-number">

                            <?php
                            echo $dayData["day"];
                            ?>

                        </div>


                        <div>

                            <h2>
                                Day <?php echo $dayData["day"]; ?>
                            </h2>

                            <p>
                                Personalized schedule based on
                                recommended places.
                            </p>

                        </div>

                    </div>



                    <div class="timeline">


                        <?php foreach (
                            $dayData["places"]
                            as $schedulePlace
                        ): ?>


                            <?php

                            $mapQuery = urlencode(
                                ($schedulePlace["latitude"] ?? "")
                                . ","
                                . (
                                    $schedulePlace["longitude"]
                                    ?? ""
                                )
                            );

                            ?>


                            <div class="timeline-item">

                                <div class="timeline-dot"></div>


                                <div class="timeline-time">

                                    🕐

                                    <?php
                                    echo
                                        $schedulePlace["start_time"]
                                        ?? "";
                                    ?>

                                    -

                                    <?php
                                    echo
                                        $schedulePlace["end_time"]
                                        ?? "";
                                    ?>

                                </div>


                                <div class="timeline-place">

                                    📍

                                    <?php
                                    echo htmlspecialchars(
                                        $schedulePlace["name"]
                                        ?? ""
                                    );
                                    ?>

                                </div>


                                <span class="timeline-category">

                                    <?php
                                    echo htmlspecialchars(
                                        $schedulePlace["category"]
                                        ?? ""
                                    );
                                    ?>

                                </span>


                                <div class="timeline-details">

                                    ⏱️ Visit:
                                    <?php
                                    echo
                                        $schedulePlace["visit_minutes"]
                                        ?? 0;
                                    ?>
                                    minutes

                                    <br>

                                    🚗 Estimated travel:
                                    <?php
                                    echo
                                        $schedulePlace["travel_minutes"]
                                        ?? 0;
                                    ?>
                                    minutes

                                    <br>

                                    📏 Distance:
                                    <?php
                                    echo
                                        $schedulePlace["distance_km"]
                                        ?? 0;
                                    ?>
                                    km


                                    <?php
                                    if (
                                        !empty(
                                            $schedulePlace[
                                                "opening_hours"
                                            ]
                                        )
                                    ):
                                    ?>

                                        <br>

                                        🕐 Opening hours:

                                        <?php
                                        echo htmlspecialchars(
                                            $schedulePlace[
                                                "opening_hours"
                                            ]
                                        );
                                        ?>

                                    <?php endif; ?>

                                </div>


                                <a
                                    class="timeline-map"
                                    href="https://www.google.com/maps/search/?api=1&query=<?php echo $mapQuery; ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    🗺️ Open in Google Maps
                                </a>

                            </div>


                        <?php endforeach; ?>


                    </div>

                </div>


            <?php endforeach; ?>


        <?php endif; ?>

    </section>

</main>



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