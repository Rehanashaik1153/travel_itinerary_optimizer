<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";

$username = htmlspecialchars($_SESSION["username"]);
$user_id = (int) $_SESSION["user_id"];

$message = "";
$messageType = "";


/* =====================================================
   CHECK TRIP ID
   ===================================================== */

if (!isset($_GET["trip_id"])) {
    header("Location: my_trips.php");
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

    header("Location: my_trips.php");
    exit();
}

$trip = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   UPDATE TRIP
   ===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $start_date = $_POST["start_date"] ?? "";

    $days = (int)($_POST["days"] ?? 0);

    $budget = (float)($_POST["budget"] ?? 0);

    $travelers = (int)($_POST["travelers"] ?? 0);

    $interests = isset($_POST["interests"])
        ? implode(", ", $_POST["interests"])
        : "";

    $transport = $_POST["transport"] ?? "";


    /* VALIDATION */

    if (
        empty($start_date) ||
        $days < 1 ||
        $budget <= 0 ||
        $travelers < 1
    ) {

        $message =
            "Please fill in all required trip details correctly.";

        $messageType = "error";

    } else {

        /* UPDATE DATABASE */

        $updateStmt = $conn->prepare(
            "UPDATE trips
             SET
                start_date = ?,
                number_of_days = ?,
                budget = ?,
                travelers = ?,
                interests = ?,
                transport_preference = ?
             WHERE trip_id = ?
             AND user_id = ?"
        );


        if (!$updateStmt) {

            $message =
                "Database error: " . $conn->error;

            $messageType = "error";

        } else {

            $updateStmt->bind_param(
                "sidissii",
                $start_date,
                $days,
                $budget,
                $travelers,
                $interests,
                $transport,
                $trip_id,
                $user_id
            );


            if ($updateStmt->execute()) {

                $updateStmt->close();

                /*
                 Redirect back to itinerary.
                 Later we will connect this with
                 the saved itinerary regeneration system.
                */

                header(
                    "Location: itinerary.php?trip_id="
                    . $trip_id
                    . "&updated=1"
                );

                exit();

            } else {

                $message =
                    "Unable to update trip: "
                    . $updateStmt->error;

                $messageType = "error";

                $updateStmt->close();
            }
        }
    }
}


/* =====================================================
   PREPARE SELECTED INTERESTS
   ===================================================== */

$selectedInterests = [];

if (!empty($trip["interests"])) {

    $selectedInterests = array_map(
        "trim",
        explode(",", $trip["interests"])
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

    <title>Edit Trip | WanderAI</title>

    <link rel="stylesheet" href="style.css">

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

<main class="plan-trip-main">


    <!-- PAGE HEADER -->

    <section class="plan-trip-header">

        <p class="dashboard-small-title">

            UPDATE YOUR JOURNEY

        </p>


        <h1>

            Edit Your
            <span>Trip</span> ✏️

        </h1>


        <p>

            Update your travel preferences and
            trip details for
            <strong>

                <?php
                echo htmlspecialchars(
                    $trip["destination"]
                );
                ?>

            </strong>

        </p>

    </section>



    <!-- MESSAGE -->

    <?php if (!empty($message)): ?>

        <div
            class="message <?php echo $messageType; ?>"
        >

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>



    <!-- =================================================
         EDIT FORM
         ================================================= -->

    <section class="trip-form-card">


        <form method="POST">


            <!-- DESTINATION -->

            <div class="form-section-title">

                <div class="form-section-icon">
                    📍
                </div>


                <div>

                    <h2>
                        Trip Details
                    </h2>


                    <p>
                        Update your travel information.
                    </p>

                </div>

            </div>



            <div class="trip-form-grid">


                <!-- DESTINATION -->

                <div class="trip-form-group full-width">

                    <label>
                        📍 Destination
                    </label>


                    <input
                        type="text"
                        value="<?php
                            echo htmlspecialchars(
                                $trip["destination"]
                            );
                        ?>"
                        readonly
                    >


                    <p class="input-help">

                        Destination cannot be changed here.
                        Create a new trip for another destination.

                    </p>

                </div>



                <!-- START DATE -->

                <div class="trip-form-group">

                    <label for="start_date">

                        📅 Start Date

                    </label>


                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        value="<?php
                            echo htmlspecialchars(
                                $trip["start_date"]
                            );
                        ?>"
                        required
                    >

                </div>



                <!-- DAYS -->

                <div class="trip-form-group">

                    <label for="days">

                        🗓️ Number of Days

                    </label>


                    <input
                        type="number"
                        id="days"
                        name="days"
                        min="1"
                        max="30"
                        value="<?php
                            echo (int)(
                                $trip["number_of_days"]
                            );
                        ?>"
                        required
                    >

                </div>



                <!-- BUDGET -->

                <div class="trip-form-group">

                    <label for="budget">

                        💰 Total Budget (₹)

                    </label>


                    <input
                        type="number"
                        id="budget"
                        name="budget"
                        min="1000"
                        step="0.01"
                        value="<?php
                            echo htmlspecialchars(
                                $trip["budget"]
                            );
                        ?>"
                        required
                    >

                </div>



                <!-- TRAVELERS -->

                <div class="trip-form-group">

                    <label for="travelers">

                        👥 Number of Travelers

                    </label>


                    <input
                        type="number"
                        id="travelers"
                        name="travelers"
                        min="1"
                        max="20"
                        value="<?php
                            echo (int)(
                                $trip["travelers"]
                            );
                        ?>"
                        required
                    >

                </div>

            </div>



            <!-- =================================================
                 INTERESTS
                 ================================================= -->

            <div class="trip-form-group interest-group">


                <label>

                    ❤️ What are you interested in?

                </label>


                <p class="input-help">

                    Select one or more interests.

                </p>


                <div class="interests-options">


                    <label class="interest-option">

                        <input
                            type="checkbox"
                            name="interests[]"
                            value="Culture & History"
                            <?php
                            echo in_array(
                                "Culture & History",
                                $selectedInterests
                            )
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>
                            🏛️ Culture & History
                        </span>

                    </label>



                    <label class="interest-option">

                        <input
                            type="checkbox"
                            name="interests[]"
                            value="Nature"
                            <?php
                            echo in_array(
                                "Nature",
                                $selectedInterests
                            )
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>
                            🌿 Nature
                        </span>

                    </label>



                    <label class="interest-option">

                        <input
                            type="checkbox"
                            name="interests[]"
                            value="Adventure"
                            <?php
                            echo in_array(
                                "Adventure",
                                $selectedInterests
                            )
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>
                            🏔️ Adventure
                        </span>

                    </label>



                    <label class="interest-option">

                        <input
                            type="checkbox"
                            name="interests[]"
                            value="Food"
                            <?php
                            echo in_array(
                                "Food",
                                $selectedInterests
                            )
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>
                            🍜 Food
                        </span>

                    </label>



                    <label class="interest-option">

                        <input
                            type="checkbox"
                            name="interests[]"
                            value="Shopping"
                            <?php
                            echo in_array(
                                "Shopping",
                                $selectedInterests
                            )
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>
                            🛍️ Shopping
                        </span>

                    </label>



                    <label class="interest-option">

                        <input
                            type="checkbox"
                            name="interests[]"
                            value="Entertainment"
                            <?php
                            echo in_array(
                                "Entertainment",
                                $selectedInterests
                            )
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>
                            🎭 Entertainment
                        </span>

                    </label>


                </div>


            </div>



            <!-- =================================================
                 TRANSPORT
                 ================================================= -->

            <div class="trip-form-group transport-group">


                <label>

                    🚗 Preferred Transport

                </label>


                <div class="transport-options">


                    <label class="transport-option">

                        <input
                            type="radio"
                            name="transport"
                            value="Car"
                            <?php
                            echo (
                                $trip["transport_preference"]
                                === "Car"
                            )
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>
                            🚗 Car
                        </span>

                    </label>



                    <label class="transport-option">

                        <input
                            type="radio"
                            name="transport"
                            value="Public Transport"
                            <?php
                            echo (
                                $trip["transport_preference"]
                                === "Public Transport"
                            )
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>
                            🚌 Public Transport
                        </span>

                    </label>



                    <label class="transport-option">

                        <input
                            type="radio"
                            name="transport"
                            value="Walking"
                            <?php
                            echo (
                                $trip["transport_preference"]
                                === "Walking"
                            )
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>
                            🚶 Walking
                        </span>

                    </label>



                    <label class="transport-option">

                        <input
                            type="radio"
                            name="transport"
                            value="Bike"
                            <?php
                            echo (
                                $trip["transport_preference"]
                                === "Bike"
                            )
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>
                            🏍️ Bike
                        </span>

                    </label>


                </div>


            </div>



            <!-- =================================================
                 SUBMIT
                 ================================================= -->

            <div class="trip-form-submit">


                <button type="submit">

                    💾 Update Trip

                </button>


                <p>

                    Your trip details will be updated.
                    You can then regenerate the AI itinerary
                    using your new preferences.

                </p>


                <br>


                <a
                    href="itinerary.php?trip_id=<?php
                        echo $trip_id;
                    ?>"
                    class="dashboard-secondary-btn"
                >

                    ← Cancel

                </a>


            </div>


        </form>


    </section>


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