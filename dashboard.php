<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";

$user_id = $_SESSION["user_id"];
$username = htmlspecialchars($_SESSION["username"]);


/* =====================================================
   DASHBOARD STATISTICS
===================================================== */

$totalTrips = 0;
$totalBudget = 0;
$itinerariesCreated = 0;


$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total_trips,
        COALESCE(SUM(budget), 0) AS total_budget
     FROM trips
     WHERE user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $totalTrips = (int)$row["total_trips"];
    $totalBudget = (float)$row["total_budget"];
}

$stmt->close();


$itinerariesCreated = $totalTrips;


/* =====================================================
   GET RECENT TRIPS
===================================================== */

$recentTrips = [];

$stmt = $conn->prepare(
    "SELECT
        trip_id,
        destination,
        start_date,
        number_of_days,
        budget,
        interests
     FROM trips
     WHERE user_id = ?
     ORDER BY trip_id DESC
     LIMIT 3"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recentTrips[] = $row;
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | WanderAI</title>

    <link rel="stylesheet" href="style.css">

</head>


<body class="wander-dashboard-page">


<!-- =========================
     NAVIGATION
========================= -->

<header class="wander-dashboard-navbar">

    <a href="dashboard.php" class="wander-dashboard-logo">

        <span class="wander-dashboard-logo-icon">✈</span>

        <span>
            Wander<span>AI</span>
        </span>

    </a>


    <nav class="wander-dashboard-nav">

        <a href="dashboard.php" class="active">
            Dashboard
        </a>

        <a href="plan_trip.php">
            Plan Trip
        </a>

        <a href="my_trips.php">
            My Trips
        </a>

    </nav>


    <div class="wander-user-menu">

        <div class="wander-user-avatar">

            <?php
            echo strtoupper(substr($username, 0, 1));
            ?>

        </div>


        <span class="wander-user-name">

            <?php
            echo $username;
            ?>

        </span>


        <a href="logout.php" class="wander-logout-btn">
            Logout
        </a>

    </div>

</header>



<!-- =========================
     MAIN CONTENT
========================= -->

<main class="wander-dashboard-main">


    <!-- =========================
         WELCOME SECTION
    ========================= -->

    <section class="wander-dashboard-welcome">


        <div class="wander-welcome-content">

            <div class="wander-section-label">
                <span>✦</span>
                YOUR TRAVEL DASHBOARD
            </div>


            <h1>

                Welcome back,

                <span>
                    <?php echo $username; ?>
                </span>

                👋

            </h1>


            <p>

                Ready for your next adventure? Create a smart,
                personalized itinerary and let WanderAI help you
                plan every part of your journey.

            </p>


            <a
                href="plan_trip.php"
                class="wander-dashboard-primary-btn"
            >

                <span>✨</span>
                Plan a New Trip
                <strong>→</strong>

            </a>

        </div>



        <!-- Travel Illustration -->

        <div class="wander-welcome-visual">

            <div class="wander-visual-circle"></div>

            <div class="wander-visual-plane">
                ✈️
            </div>

            <div class="wander-visual-pin wander-pin-one">
                📍
            </div>

            <div class="wander-visual-pin wander-pin-two">
                📍
            </div>

            <div class="wander-visual-route"></div>

            <div class="wander-visual-card">

                <span>✦</span>

                <div>

                    <strong>Your next adventure</strong>

                    <small>
                        Starts with a plan
                    </small>

                </div>

            </div>

        </div>


    </section>



    <!-- =========================
         STATISTICS
    ========================= -->

    <section class="wander-dashboard-stats">


        <div class="wander-stat-card">

            <div class="wander-stat-icon">
                🧳
            </div>

            <div>

                <span>Total Trips</span>

                <strong>
                    <?php echo $totalTrips; ?>
                </strong>

                <small>
                    Your saved travel plans
                </small>

            </div>

        </div>



        <div class="wander-stat-card">

            <div class="wander-stat-icon">
                🗺️
            </div>

            <div>

                <span>Itineraries Created</span>

                <strong>
                    <?php echo $itinerariesCreated; ?>
                </strong>

                <small>
                    Your AI travel plans
                </small>

            </div>

        </div>



        <div class="wander-stat-card">

            <div class="wander-stat-icon">
                💰
            </div>

            <div>

                <span>Total Budget</span>

                <strong>

                    ₹<?php
                    echo number_format(
                        $totalBudget,
                        0
                    );
                    ?>

                </strong>

                <small>
                    Combined budget of all trips
                </small>

            </div>

        </div>


    </section>



    <!-- =========================
         QUICK ACTIONS
    ========================= -->

    <section class="wander-dashboard-section">


        <div class="wander-dashboard-section-header">

            <div>

                <div class="wander-section-label">
                    <span>✦</span>
                    QUICK ACTIONS
                </div>

                <h2>
                    What would you like to do?
                </h2>

            </div>

        </div>



        <div class="wander-quick-actions-grid">


            <a
                href="plan_trip.php"
                class="wander-quick-action-card"
            >

                <div class="wander-quick-action-icon">
                    ✨
                </div>

                <h3>
                    Plan a New Trip
                </h3>

                <p>

                    Enter your destination, budget,
                    dates and travel preferences.

                </p>

                <span>
                    Start Planning →
                </span>

            </a>



            <a
                href="my_trips.php"
                class="wander-quick-action-card"
            >

                <div class="wander-quick-action-icon">
                    📂
                </div>

                <h3>
                    My Saved Trips
                </h3>

                <p>

                    View your previously created trips
                    and itineraries.

                </p>

                <span>
                    View Trips →
                </span>

            </a>



            <a
                href="explore_places.php"
                class="wander-quick-action-card"
            >

                <div class="wander-quick-action-icon">
                    📍
                </div>

                <h3>
                    Explore Places
                </h3>

                <p>

                    Discover interesting places and
                    attractions for your journey.

                </p>

                <span>
                    Explore Places →
                </span>

            </a>



            <a
                href="plan_trip.php"
                class="wander-quick-action-card"
            >

                <div class="wander-quick-action-icon">
                    🤖
                </div>

                <h3>
                    AI Trip Planner
                </h3>

                <p>

                    Let WanderAI create a personalized
                    day-wise travel itinerary.

                </p>

                <span>
                    Generate Itinerary →
                </span>

            </a>


        </div>

    </section>



    <!-- =========================
         RECENT TRIPS
    ========================= -->

    <section class="wander-dashboard-section wander-recent-trips-section">


        <div class="wander-dashboard-section-header">

            <div>

                <div class="wander-section-label">
                    <span>✦</span>
                    YOUR JOURNEYS
                </div>

                <h2>
                    Recent Trips
                </h2>

            </div>


            <a
                href="my_trips.php"
                class="wander-view-all-btn"
            >

                View All
                <span>→</span>

            </a>

        </div>



        <?php if (empty($recentTrips)): ?>


            <!-- EMPTY STATE -->

            <div class="wander-empty-trips">

                <div class="wander-empty-trip-icon">
                    🧳
                </div>

                <h3>
                    No trips planned yet
                </h3>

                <p>

                    Your adventures are waiting! Start by
                    creating your first personalized trip.

                </p>

                <a
                    href="plan_trip.php"
                    class="wander-dashboard-secondary-btn"
                >

                    Plan Your First Trip
                    <span>→</span>

                </a>

            </div>


        <?php else: ?>


            <!-- RECENT TRIPS -->

            <div class="wander-recent-trips-grid">


                <?php foreach ($recentTrips as $recentTrip): ?>


                    <div class="wander-recent-trip-card">


                        <div class="wander-trip-card-top">

                            <div class="wander-trip-location-icon">
                                📍
                            </div>

                            <span>
                                Recent Trip
                            </span>

                        </div>


                        <h3>

                            <?php
                            echo htmlspecialchars(
                                $recentTrip["destination"]
                            );
                            ?>

                        </h3>



                        <div class="wander-trip-details">


                            <p>

                                <span>📅</span>

                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $recentTrip["start_date"]
                                    )
                                );
                                ?>

                            </p>



                            <p>

                                <span>🗓️</span>

                                <?php
                                echo (int)
                                    $recentTrip["number_of_days"];
                                ?>

                                Days

                            </p>



                            <p>

                                <span>💰</span>

                                ₹<?php
                                echo number_format(
                                    (float)$recentTrip["budget"],
                                    0
                                );
                                ?>

                            </p>


                        </div>



                        <div class="wander-trip-interests">

                            ❤️

                            <?php
                            echo htmlspecialchars(
                                $recentTrip["interests"]
                                ?: "No interests selected"
                            );
                            ?>

                        </div>



                        <a
                            href="itinerary.php?trip_id=<?php
                            echo (int)$recentTrip["trip_id"];
                            ?>"
                            class="wander-trip-view-btn"
                        >

                            View Itinerary
                            <span>→</span>

                        </a>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </section>


</main>



<!-- =========================
     FOOTER
========================= -->

<footer class="wander-dashboard-footer">


    <div class="wander-dashboard-footer-logo">

        <span>✈</span>

        Wander<span>AI</span>

    </div>


    <p>
        Your intelligent travel planning companion.
    </p>


    <div class="wander-dashboard-copyright">
        © 2026 WanderAI — AI Travel Itinerary Optimizer
    </div>


</footer>


</body>
</html>