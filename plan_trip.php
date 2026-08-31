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
   CHECK WHETHER THIS IS AN EDIT TRIP
   ===================================================== */

$edit_trip_id = isset($_GET["trip_id"])
    ? (int) $_GET["trip_id"]
    : 0;


/* =====================================================
   DEFAULT VALUES
   ===================================================== */

$trip_data = [
    "destination" => "",
    "selected_location" => "",
    "latitude" => "",
    "longitude" => "",
    "start_date" => "",
    "number_of_days" => "",
    "budget" => "",
    "travelers" => "",
    "interests" => "",
    "transport_preference" => ""
];


/* =====================================================
   LOAD EXISTING TRIP FOR EDITING
   ===================================================== */

if ($edit_trip_id > 0) {

    $stmt = $conn->prepare(
        "SELECT
            trip_id,
            destination,
            selected_location,
            latitude,
            longitude,
            start_date,
            number_of_days,
            budget,
            travelers,
            interests,
            transport_preference
         FROM trips
         WHERE trip_id = ?
         AND user_id = ?"
    );

    $stmt->bind_param(
        "ii",
        $edit_trip_id,
        $user_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $trip_data = $result->fetch_assoc();

    } else {

        $stmt->close();

        header("Location: my_trips.php");
        exit();
    }

    $stmt->close();
}


/* =====================================================
   GET SELECTED DESTINATION
   ===================================================== */

$selected_destination =
    $_SESSION["selected_destination"]
    ?? $trip_data["destination"];

$latitude =
    $_SESSION["destination_latitude"]
    ?? $trip_data["latitude"];

$longitude =
    $_SESSION["destination_longitude"]
    ?? $trip_data["longitude"];


/* =====================================================
   SAVE OR UPDATE TRIP
   ===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $destination = trim(
        $_POST["destination"] ?? ""
    );

    $start_date =
        $_POST["start_date"] ?? "";

    $days = (int)(
        $_POST["days"] ?? 0
    );

    $budget = (float)(
        $_POST["budget"] ?? 0
    );

    $travelers = (int)(
        $_POST["travelers"] ?? 0
    );

    $interests = isset($_POST["interests"])
        ? implode(", ", $_POST["interests"])
        : "";

    $transport =
        $_POST["transport"] ?? "";


    /* Use destination selected from destination.php */

    if (
        isset($_SESSION["selected_destination"]) &&
        $_SESSION["selected_destination"] !== ""
    ) {

        $destination =
            $_SESSION["selected_destination"];

        $selected_destination =
            $_SESSION["selected_destination"];

        $latitude =
            $_SESSION["destination_latitude"];

        $longitude =
            $_SESSION["destination_longitude"];
    }


    /* VALIDATION */

    if (
        empty($destination) ||
        empty($start_date) ||
        $days < 1 ||
        $budget <= 0 ||
        $travelers < 1 ||
        $latitude === "" ||
        $longitude === ""
    ) {

        $message =
            "Please select a valid destination and fill in all required trip details.";

        $messageType = "error";

    } else {


        /* UPDATE EXISTING TRIP */

        if ($edit_trip_id > 0) {

            $stmt = $conn->prepare(
                "UPDATE trips
                 SET
                    destination = ?,
                    selected_location = ?,
                    latitude = ?,
                    longitude = ?,
                    start_date = ?,
                    number_of_days = ?,
                    budget = ?,
                    travelers = ?,
                    interests = ?,
                    transport_preference = ?
                 WHERE trip_id = ?
                 AND user_id = ?"
            );

            if (!$stmt) {

                $message =
                    "Database error: " .
                    $conn->error;

                $messageType = "error";

            } else {

                $selected_location =
                    $selected_destination;

                $stmt->bind_param(
                    "ssddsidissii",
                    $destination,
                    $selected_location,
                    $latitude,
                    $longitude,
                    $start_date,
                    $days,
                    $budget,
                    $travelers,
                    $interests,
                    $transport,
                    $edit_trip_id,
                    $user_id
                );

                if ($stmt->execute()) {

                    $stmt->close();

                    unset(
                        $_SESSION["selected_destination"],
                        $_SESSION["destination_latitude"],
                        $_SESSION["destination_longitude"],
                        $_SESSION["geocode_results"]
                    );

                    header(
                        "Location: itinerary.php?trip_id=" .
                        $edit_trip_id .
                        "&regenerate=1"
                    );

                    exit();

                } else {

                    $message =
                        "Unable to update your trip. Database error: " .
                        $stmt->error;

                    $messageType = "error";

                    $stmt->close();
                }
            }


        } else {


            /* CREATE NEW TRIP */

            $stmt = $conn->prepare(
                "INSERT INTO trips
                (
                    user_id,
                    destination,
                    selected_location,
                    latitude,
                    longitude,
                    start_date,
                    number_of_days,
                    budget,
                    travelers,
                    interests,
                    transport_preference
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );


            if (!$stmt) {

                $message =
                    "Database error: " .
                    $conn->error;

                $messageType = "error";

            } else {

                $selected_location =
                    $selected_destination;

                $stmt->bind_param(
                    "issddsidiss",
                    $user_id,
                    $destination,
                    $selected_location,
                    $latitude,
                    $longitude,
                    $start_date,
                    $days,
                    $budget,
                    $travelers,
                    $interests,
                    $transport
                );


                if ($stmt->execute()) {

                    $trip_id =
                        $conn->insert_id;

                    $stmt->close();

                    unset(
                        $_SESSION["selected_destination"],
                        $_SESSION["destination_latitude"],
                        $_SESSION["destination_longitude"],
                        $_SESSION["geocode_results"]
                    );

                    header(
                        "Location: itinerary.php?trip_id=" .
                        $trip_id
                    );

                    exit();

                } else {

                    $message =
                        "Unable to save your trip. Database error: " .
                        $stmt->error;

                    $messageType = "error";

                    $stmt->close();
                }
            }
        }
    }
}


/* =====================================================
   PREPARE SELECTED INTERESTS
   ===================================================== */

$selected_interests = [];

if (!empty($trip_data["interests"])) {

    $selected_interests =
        array_map(
            "trim",
            explode(
                ",",
                $trip_data["interests"]
            )
        );
}


/* =====================================================
   PAGE TITLE
   ===================================================== */

$page_title =
    $edit_trip_id > 0
    ? "Edit Trip"
    : "Plan Your Perfect Trip";

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
        <?php echo $page_title; ?> | WanderAI
    </title>

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



<main class="plan-trip-main">


    <section class="plan-trip-header">

        <p class="dashboard-small-title">

            <?php
            echo $edit_trip_id > 0
                ? "UPDATE YOUR JOURNEY"
                : "CREATE YOUR JOURNEY";
            ?>

        </p>


        <h1>

            <?php if ($edit_trip_id > 0): ?>

                Edit Your <span>Trip</span> ✏️

            <?php else: ?>

                Plan Your <span>Perfect Trip</span> ✨

            <?php endif; ?>

        </h1>


        <p>

            <?php
            echo $edit_trip_id > 0
                ? "Update your travel preferences and regenerate your personalized itinerary."
                : "Tell us about your travel preferences and WanderAI will create a personalized itinerary for you.";
            ?>

        </p>

    </section>



    <?php if (!empty($message)): ?>

        <div
            class="message <?php echo $messageType; ?>"
        >
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>



    <section class="trip-form-card">

        <form
            method="POST"
            action="plan_trip.php<?php
                echo $edit_trip_id > 0
                    ? '?trip_id=' . $edit_trip_id
                    : '';
            ?>"
        >


            <div class="form-section-title">

                <div class="form-section-icon">
                    🗺️
                </div>

                <div>

                    <h2>Trip Details</h2>

                    <p>
                        Where and when would you like to travel?
                    </p>

                </div>

            </div>


            <div class="trip-form-grid">


                <div class="trip-form-group full-width">

                    <label for="destination">
                        📍 Destination
                    </label>


                    <input
                        type="text"
                        id="destination"
                        name="destination"
                        value="<?php echo htmlspecialchars($selected_destination); ?>"
                        placeholder="Select your destination first..."
                        readonly
                        required
                    >


                    <?php if ($selected_destination !== ""): ?>

                        <p class="input-help">
                            ✅ Destination selected successfully.
                        </p>

                    <?php else: ?>

                        <p class="input-help">
                            Please select your destination before planning the trip.
                        </p>

                    <?php endif; ?>


                    <a
                        href="destination.php<?php
                            echo $edit_trip_id > 0
                                ? '?trip_id=' . $edit_trip_id
                                : '';
                        ?>"
                        class="dashboard-secondary-btn destination-change-btn"
                    >
                        🌍 Choose / Change Destination
                    </a>

                </div>


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
                                $trip_data["start_date"]
                            );
                        ?>"
                        required
                    >

                </div>


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
                            echo htmlspecialchars(
                                $trip_data["number_of_days"]
                            );
                        ?>"
                        placeholder="Example: 3"
                        required
                    >

                </div>

            </div>



            <div class="form-section-title preferences-title">

                <div class="form-section-icon">
                    🎯
                </div>

                <div>

                    <h2>Travel Preferences</h2>

                    <p>
                        Help our AI understand what you enjoy.
                    </p>

                </div>

            </div>



            <div class="trip-form-grid">


                <div class="trip-form-group">

                    <label for="budget">
                        💰 Total Budget (₹)
                    </label>

                    <input
                        type="number"
                        id="budget"
                        name="budget"
                        min="1000"
                        value="<?php
                            echo htmlspecialchars(
                                $trip_data["budget"]
                            );
                        ?>"
                        placeholder="Example: 15000"
                        required
                    >

                </div>


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
                            echo htmlspecialchars(
                                $trip_data["travelers"]
                            );
                        ?>"
                        placeholder="Example: 2"
                        required
                    >

                </div>

            </div>



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
                                $selected_interests
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
                                $selected_interests
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
                                $selected_interests
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
                                $selected_interests
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
                                $selected_interests
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
                                $selected_interests
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
                            echo $trip_data["transport_preference"] === "Car"
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>🚗 Car</span>

                    </label>


                    <label class="transport-option">

                        <input
                            type="radio"
                            name="transport"
                            value="Public Transport"
                            <?php
                            echo $trip_data["transport_preference"] === "Public Transport"
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>🚌 Public Transport</span>

                    </label>


                    <label class="transport-option">

                        <input
                            type="radio"
                            name="transport"
                            value="Walking"
                            <?php
                            echo $trip_data["transport_preference"] === "Walking"
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>🚶 Walking</span>

                    </label>


                    <label class="transport-option">

                        <input
                            type="radio"
                            name="transport"
                            value="Bike"
                            <?php
                            echo $trip_data["transport_preference"] === "Bike"
                                ? "checked"
                                : "";
                            ?>
                        >

                        <span>🏍️ Bike</span>

                    </label>

                </div>

            </div>



            <div class="trip-form-submit">

                <button type="submit">

                    <?php if ($edit_trip_id > 0): ?>

                        🔄 Update & Regenerate Itinerary

                    <?php else: ?>

                        🤖 Generate My AI Itinerary

                    <?php endif; ?>

                </button>


                <p>

                    <?php
                    echo $edit_trip_id > 0
                        ? "Your trip details will be updated and WanderAI will regenerate your itinerary."
                        : "WanderAI will analyze your destination, preferences, budget and available travel time.";
                    ?>

                </p>

            </div>

        </form>

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