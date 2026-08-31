<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";

$username = htmlspecialchars($_SESSION["username"]);
$user_id = (int) $_SESSION["user_id"];


/* ==========================================
   GET USER'S SAVED TRIPS
   ========================================== */

$stmt = $conn->prepare(
    "SELECT
        trip_id,
        destination,
        start_date,
        number_of_days,
        budget,
        travelers,
        interests,
        transport_preference
     FROM trips
     WHERE user_id = ?
     ORDER BY trip_id DESC"
);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$trips = [];

while ($row = $result->fetch_assoc()) {
    $trips[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Trips | WanderAI</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body class="dashboard-body">


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

        <a
            href="my_trips.php"
            class="active"
        >
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



<main class="my-trips-main">


    <section class="my-trips-header">

        <div>

            <p class="dashboard-small-title">
                YOUR TRAVEL HISTORY
            </p>


            <h1>
                My <span>Trips</span> ✈️
            </h1>


            <p>
                View all your saved trips and open
                their personalized AI itineraries.
            </p>

        </div>


        <a
            href="plan_trip.php"
            class="dashboard-secondary-btn new-trip-btn"
        >
            + Plan New Trip
        </a>

    </section>



    <?php if (empty($trips)): ?>


        <section class="empty-trips">

            <div class="empty-trips-icon">
                🧳
            </div>


            <h2>
                No trips planned yet
            </h2>


            <p>
                Start planning your first journey and
                WanderAI will create a personalized
                travel itinerary for you.
            </p>


            <a
                href="plan_trip.php"
                class="dashboard-secondary-btn"
            >
                ✈️ Plan My First Trip
            </a>

        </section>


    <?php else: ?>


        <section class="trips-grid">


            <?php foreach ($trips as $trip): ?>


                <article class="trip-card">


                    <div class="trip-card-header">

                        <h2>

                            📍

                            <?php
                            echo htmlspecialchars(
                                $trip["destination"]
                            );
                            ?>

                        </h2>


                        <span class="trip-id">

                            Trip #
                            <?php echo $trip["trip_id"]; ?>

                        </span>

                    </div>



                    <div class="trip-info">


                        <div class="trip-info-item">

                            <span>
                                📅 Start Date
                            </span>

                            <strong>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $trip["start_date"]
                                    )
                                );
                                ?>

                            </strong>

                        </div>



                        <div class="trip-info-item">

                            <span>
                                🗓️ Duration
                            </span>

                            <strong>

                                <?php
                                echo (int)
                                    $trip["number_of_days"];
                                ?>

                                Days

                            </strong>

                        </div>



                        <div class="trip-info-item">

                            <span>
                                💰 Budget
                            </span>

                            <strong>

                                ₹<?php
                                echo number_format(
                                    (float)$trip["budget"],
                                    2
                                );
                                ?>

                            </strong>

                        </div>



                        <div class="trip-info-item">

                            <span>
                                👥 Travelers
                            </span>

                            <strong>

                                <?php
                                echo (int)
                                    $trip["travelers"];
                                ?>

                            </strong>

                        </div>



                        <div class="trip-info-item trip-interests">

                            <span>
                                ❤️ Interests
                            </span>

                            <strong>

                                <?php
                                echo !empty(
                                    $trip["interests"]
                                )
                                    ? htmlspecialchars(
                                        $trip["interests"]
                                    )
                                    : "Not specified";
                                ?>

                            </strong>

                        </div>

                    </div>



                    <div class="trip-actions">


                        <a
                            href="itinerary.php?trip_id=<?php echo $trip["trip_id"]; ?>"
                            class="view-itinerary-btn"
                        >
                            🤖 View AI Itinerary
                        </a>


                        <a
                            href="delete_trip.php?trip_id=<?php echo $trip["trip_id"]; ?>"
                            class="delete-trip-btn"
                            onclick="return confirm('Are you sure you want to delete this trip?');"
                        >
                            🗑 Delete
                        </a>

                    </div>

                </article>


            <?php endforeach; ?>


        </section>


    <?php endif; ?>


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