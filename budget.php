<?php
/* =====================================================
   WANDERAI - DYNAMIC TRIP BUDGET + BUDGET OPTIMIZER
   ===================================================== */

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";

$username = htmlspecialchars($_SESSION["username"] ?? "User");
$user_id = (int)$_SESSION["user_id"];


/* =====================================================
   CHECK TRIP ID
   ===================================================== */

if (!isset($_GET["trip_id"])) {
    header("Location: dashboard.php");
    exit();
}

$trip_id = (int)$_GET["trip_id"];

if ($trip_id <= 0) {
    header("Location: dashboard.php");
    exit();
}


/* =====================================================
   GET TRIP
   ===================================================== */

$stmt = $conn->prepare(
    "SELECT *
     FROM trips
     WHERE trip_id = ?
     AND user_id = ?"
);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

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
   TRIP INFORMATION
   ===================================================== */

$destination =
    htmlspecialchars(
        $trip["destination"] ?? "Unknown"
    );

$numberOfDays =
    max(
        1,
        (int)($trip["number_of_days"] ?? 1)
    );

$travelers =
    max(
        1,
        (int)($trip["travelers"] ?? 1)
    );

$userBudget =
    (float)($trip["budget"] ?? 0);

$transportRaw =
    trim(
        strtolower(
            $trip["transport_preference"] ?? "car"
        )
    );

$transportDisplay =
    htmlspecialchars(
        $trip["transport_preference"] ?? "Not specified"
    );


/* =====================================================
   LOAD SAVED ITINERARY
   ===================================================== */

$generatedItinerary = [];

if (!empty($trip["generated_itinerary"])) {

    $decoded =
        json_decode(
            $trip["generated_itinerary"],
            true
        );

    if (is_array($decoded)) {
        $generatedItinerary = $decoded;
    }
}


/* =====================================================
   GET ACCOMMODATION
   ===================================================== */

$accommodation = null;

if (
    isset($generatedItinerary["_accommodation"]) &&
    is_array($generatedItinerary["_accommodation"])
) {

    $accommodation =
        $generatedItinerary["_accommodation"];
}


/* =====================================================
   COST ESTIMATION
   ===================================================== */

/*
 * These values are estimated.
 * They are not live market prices.
 */


/* =====================================================
   ACCOMMODATION
   ===================================================== */

$accommodationPerNight = 0;

if ($accommodation !== null) {

    $accommodationCategory =
        strtolower(
            $accommodation["category"] ?? ""
        );

    $accommodationName =
        strtolower(
            $accommodation["name"] ?? ""
        );


    if (
        strpos($accommodationCategory, "hostel") !== false ||
        strpos($accommodationName, "hostel") !== false
    ) {

        $accommodationPerNight = 700;

    } elseif (
        strpos($accommodationCategory, "guest") !== false ||
        strpos($accommodationName, "guest") !== false
    ) {

        $accommodationPerNight = 1200;

    } elseif (
        strpos($accommodationCategory, "resort") !== false ||
        strpos($accommodationName, "resort") !== false
    ) {

        $accommodationPerNight = 3000;

    } else {

        $accommodationPerNight = 1800;
    }
}

$nights =
    max(
        1,
        $numberOfDays - 1
    );

$accommodationCost =
    $accommodationPerNight
    *
    $nights;


/* =====================================================
   FOOD
   ===================================================== */

$foodPerPersonPerDay = 700;

$foodCost =
    $foodPerPersonPerDay
    *
    $travelers
    *
    $numberOfDays;


/* =====================================================
   TRANSPORT
   ===================================================== */

$transportPerDayPerTraveler = 0;

if (
    strpos($transportRaw, "walking") !== false
) {

    $transportPerDayPerTraveler = 100;

} elseif (
    strpos($transportRaw, "bike") !== false ||
    strpos($transportRaw, "bicycle") !== false
) {

    $transportPerDayPerTraveler = 300;

} elseif (
    strpos($transportRaw, "public") !== false ||
    strpos($transportRaw, "bus") !== false ||
    strpos($transportRaw, "train") !== false
) {

    $transportPerDayPerTraveler = 350;

} elseif (
    strpos($transportRaw, "taxi") !== false ||
    strpos($transportRaw, "cab") !== false
) {

    $transportPerDayPerTraveler = 1000;

} else {

    $transportPerDayPerTraveler = 800;
}


/*
 * Shared vehicle for car/taxi/cab.
 */

if (
    strpos($transportRaw, "car") !== false ||
    strpos($transportRaw, "taxi") !== false ||
    strpos($transportRaw, "cab") !== false
) {

    $transportCost =
        $transportPerDayPerTraveler
        *
        $numberOfDays;

} else {

    $transportCost =
        $transportPerDayPerTraveler
        *
        $travelers
        *
        $numberOfDays;
}


/* =====================================================
   ACTIVITY COUNT
   ===================================================== */

$activityPlaces = 0;

foreach (
    $generatedItinerary as $dayData
) {

    if (
        !is_array($dayData) ||
        !isset($dayData["places"]) ||
        !is_array($dayData["places"])
    ) {
        continue;
    }

    foreach (
        $dayData["places"] as $place
    ) {

        if (!is_array($place)) {
            continue;
        }

        if (!empty($place["is_break"])) {
            continue;
        }

        $activityPlaces++;
    }
}


/* =====================================================
   ACTIVITY COST
   ===================================================== */

$activityCostPerVisit = 250;

$activityCost =
    $activityPlaces
    *
    $activityCostPerVisit
    *
    $travelers;


/* =====================================================
   MISCELLANEOUS
   ===================================================== */

$miscellaneousCost =
    300
    *
    $travelers;


/* =====================================================
   TOTAL
   ===================================================== */

$totalEstimatedCost =
    $accommodationCost
    +
    $foodCost
    +
    $transportCost
    +
    $activityCost
    +
    $miscellaneousCost;


/* =====================================================
   BUDGET STATUS
   ===================================================== */

$remainingBudget =
    $userBudget
    -
    $totalEstimatedCost;

if ($userBudget <= 0) {

    $budgetStatus =
        "No budget specified";

    $budgetClass =
        "neutral";

} elseif ($remainingBudget >= 0) {

    $budgetStatus =
        "Your trip is within budget";

    $budgetClass =
        "success";

} else {

    $budgetStatus =
        "Estimated trip cost exceeds your budget";

    $budgetClass =
        "danger";
}


/* =====================================================
   BUDGET PERCENTAGE
   ===================================================== */

if ($userBudget > 0) {

    $budgetPercentage =
        ($totalEstimatedCost / $userBudget)
        * 100;

    $budgetPercentage =
        min(
            100,
            max(
                0,
                $budgetPercentage
            )
        );

} else {

    $budgetPercentage = 0;
}


/* =====================================================
   =====================================================
   BUDGET OPTIMIZATION ENGINE
   =====================================================
   ===================================================== */

/*
 * The optimizer creates a lower-cost scenario.
 *
 * IMPORTANT:
 * This does not change the user's saved itinerary.
 * It gives recommendations for reducing estimated cost.
 */


/* -----------------------------------------------------
   TARGET COST
   ----------------------------------------------------- */

$optimizedAccommodationCost =
    $accommodationCost;

$optimizedFoodCost =
    $foodCost;

$optimizedTransportCost =
    $transportCost;

$optimizedActivityCost =
    $activityCost;

$optimizedMiscellaneousCost =
    $miscellaneousCost;

$optimizationSuggestions = [];


/* =====================================================
   ACCOMMODATION OPTIMIZATION
   ===================================================== */

/*
 * Assume a budget-friendly accommodation target
 * of ₹700 per night.
 */

if (
    $accommodationCost > 0 &&
    $accommodationPerNight > 700
) {

    $optimizedAccommodationPerNight = 700;

    $optimizedAccommodationCost =
        $optimizedAccommodationPerNight
        *
        $nights;

    $accommodationSaving =
        $accommodationCost
        -
        $optimizedAccommodationCost;

    if ($accommodationSaving > 0) {

        $optimizationSuggestions[] = [

            "icon" => "🏨",

            "title" =>
                "Choose budget accommodation",

            "description" =>
                "Consider a hostel, budget hotel or guest house instead of a higher-priced stay.",

            "saving" =>
                $accommodationSaving
        ];
    }
}


/* =====================================================
   FOOD OPTIMIZATION
   ===================================================== */

/*
 * Reduce estimated food cost from ₹700
 * to ₹500 per person per day.
 */

$optimizedFoodPerPersonPerDay = 500;

$optimizedFoodCost =
    $optimizedFoodPerPersonPerDay
    *
    $travelers
    *
    $numberOfDays;

$foodSaving =
    $foodCost
    -
    $optimizedFoodCost;

if ($foodSaving > 0) {

    $optimizationSuggestions[] = [

        "icon" => "🍽️",

        "title" =>
            "Choose budget-friendly meals",

        "description" =>
            "Use local restaurants, affordable meals and reduce expensive dining options.",

        "saving" =>
            $foodSaving
    ];
}


/* =====================================================
   TRANSPORT OPTIMIZATION
   ===================================================== */

/*
 * Public transport is treated as the budget option.
 */

$optimizedTransportCost =
    $transportCost;

if (
    strpos($transportRaw, "walking") === false &&
    strpos($transportRaw, "public") === false &&
    strpos($transportRaw, "bus") === false &&
    strpos($transportRaw, "train") === false
) {

    $publicTransportDaily =
        350;

    $optimizedTransportCost =
        $publicTransportDaily
        *
        $travelers
        *
        $numberOfDays;

    /*
     * If only one traveler uses public transport,
     * keep the calculation reasonable.
     */

    if ($optimizedTransportCost < $transportCost) {

        $transportSaving =
            $transportCost
            -
            $optimizedTransportCost;

        $optimizationSuggestions[] = [

            "icon" => "🚌",

            "title" =>
                "Use public transport",

            "description" =>
                "Use buses or trains where practical instead of taxis or private transport.",

            "saving" =>
                $transportSaving
        ];
    }
}


/* =====================================================
   ACTIVITY OPTIMIZATION
   ===================================================== */

/*
 * If there are many paid activities,
 * suggest reducing some of them.
 */

$optimizedActivityPlaces =
    $activityPlaces;

if ($activityPlaces > 4) {

    $freeOrLowerCostPlaces =
        $activityPlaces - 4;

    $optimizedActivityPlaces = 4;

    $optimizedActivityCost =
        $optimizedActivityPlaces
        *
        $activityCostPerVisit
        *
        $travelers;

    $activitySaving =
        $activityCost
        -
        $optimizedActivityCost;

    if ($activitySaving > 0) {

        $optimizationSuggestions[] = [

            "icon" => "🎟️",

            "title" =>
                "Reduce paid activities",

            "description" =>
                "Replace some paid attractions with free parks, viewpoints, beaches, cultural areas or public places.",

            "saving" =>
                $activitySaving
        ];
    }
}


/* =====================================================
   MISCELLANEOUS OPTIMIZATION
   ===================================================== */

$optimizedMiscellaneousCost =
    max(
        200 * $travelers,
        0
    );

$miscellaneousSaving =
    $miscellaneousCost
    -
    $optimizedMiscellaneousCost;

if ($miscellaneousSaving > 0) {

    $optimizationSuggestions[] = [

        "icon" => "🧾",

        "title" =>
            "Control miscellaneous expenses",

        "description" =>
            "Keep a smaller emergency and small-expense allowance while maintaining a reasonable buffer.",

        "saving" =>
            $miscellaneousSaving
    ];
}


/* =====================================================
   OPTIMIZED TOTAL
   ===================================================== */

$optimizedTotalCost =
    $optimizedAccommodationCost
    +
    $optimizedFoodCost
    +
    $optimizedTransportCost
    +
    $optimizedActivityCost
    +
    $optimizedMiscellaneousCost;


/* =====================================================
   TOTAL POTENTIAL SAVING
   ===================================================== */

$totalPotentialSaving =
    $totalEstimatedCost
    -
    $optimizedTotalCost;

$totalPotentialSaving =
    max(
        0,
        $totalPotentialSaving
    );


/* =====================================================
   OPTIMIZED BUDGET STATUS
   ===================================================== */

$optimizedRemainingBudget =
    $userBudget
    -
    $optimizedTotalCost;

if ($userBudget <= 0) {

    $optimizedStatus =
        "Set a budget to check optimization.";

    $optimizedClass =
        "neutral";

} elseif ($optimizedRemainingBudget >= 0) {

    $optimizedStatus =
        "The optimized estimate fits your budget.";

    $optimizedClass =
        "success";

} else {

    $optimizedStatus =
        "The trip may still exceed your budget.";

    $optimizedClass =
        "danger";
}


/* =====================================================
   CURRENCY FORMAT
   ===================================================== */

function formatRupees($amount)
{
    return "₹" .
        number_format(
            (float)$amount,
            2
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

    <title>
        Trip Budget | WanderAI
    </title>

    <link
        rel="stylesheet"
        href="style.css"
    >

    <style>

        .budget-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 25px;
        }

        .budget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
            margin-bottom: 35px;
        }

        .budget-header h1 {
            margin-bottom: 10px;
        }

        .budget-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .budget-action-btn {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
        }

        .budget-summary {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(210px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .budget-card {
            background: white;
            border-radius: 18px;
            padding: 25px;
            box-shadow:
                0 8px 25px rgba(0,0,0,0.08);
        }

        .budget-card-icon {
            font-size: 30px;
            margin-bottom: 12px;
        }

        .budget-card span {
            display: block;
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .budget-card strong {
            font-size: 24px;
        }

        .budget-breakdown {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow:
                0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .budget-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 17px 0;
            border-bottom: 1px solid #eee;
        }

        .budget-row:last-child {
            border-bottom: none;
        }

        .budget-row-label {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .budget-row-label span {
            font-size: 25px;
        }

        .budget-row-label small {
            display: block;
            margin-top: 5px;
            color: #777;
        }

        .budget-total {
            font-size: 22px;
            font-weight: 800;
            padding-top: 25px;
        }

        .budget-status {
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .budget-status.success {
            background: #e9f8ef;
            border: 1px solid #b7e6c7;
        }

        .budget-status.danger {
            background: #fff0f0;
            border: 1px solid #f0bcbc;
        }

        .budget-status.neutral {
            background: #f3f3f3;
            border: 1px solid #ddd;
        }

        .budget-status h2 {
            margin-bottom: 8px;
        }

        .budget-progress {
            width: 100%;
            height: 14px;
            background: #eee;
            border-radius: 20px;
            overflow: hidden;
            margin-top: 18px;
        }

        .budget-progress-bar {
            height: 100%;
            width: <?php echo $budgetPercentage; ?>%;
            background: #5b7cff;
            border-radius: 20px;
        }

        .budget-note {
            background: #fff9e8;
            border: 1px solid #f0dfaa;
            border-radius: 15px;
            padding: 20px;
            color: #66551a;
            margin-bottom: 30px;
        }


        /* =================================================
           BUDGET OPTIMIZER
           ================================================= */

        .budget-optimizer {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow:
                0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .optimizer-header {
            margin-bottom: 25px;
        }

        .optimizer-header h2 {
            margin-bottom: 8px;
        }

        .optimizer-summary {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(210px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .optimizer-card {
            border-radius: 15px;
            padding: 20px;
            background: #f7f8ff;
            border: 1px solid #e4e7ff;
        }

        .optimizer-card span {
            display: block;
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .optimizer-card strong {
            font-size: 22px;
        }

        .optimizer-status {
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .optimizer-status.success {
            background: #e9f8ef;
            border: 1px solid #b7e6c7;
        }

        .optimizer-status.danger {
            background: #fff0f0;
            border: 1px solid #f0bcbc;
        }

        .optimizer-status.neutral {
            background: #f3f3f3;
            border: 1px solid #ddd;
        }

        .optimization-list {
            display: grid;
            gap: 15px;
        }

        .optimization-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px;
            border: 1px solid #eee;
            border-radius: 14px;
            background: #fafafa;
        }

        .optimization-left {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .optimization-icon {
            font-size: 28px;
        }

        .optimization-item h3 {
            margin: 0 0 5px;
        }

        .optimization-item p {
            margin: 0;
            color: #666;
        }

        .saving-badge {
            white-space: nowrap;
            background: #e9f8ef;
            color: #18733d;
            border: 1px solid #b7e6c7;
            border-radius: 20px;
            padding: 8px 13px;
            font-weight: 700;
        }

        .no-optimization {
            padding: 20px;
            border-radius: 14px;
            background: #f5f5f5;
        }


        @media (max-width: 700px) {

            .budget-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .budget-row {
                gap: 15px;
            }

            .optimization-item {
                flex-direction: column;
                align-items: flex-start;
            }

        }

    </style>

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
                substr(
                    $_SESSION["username"] ?? "U",
                    0,
                    1
                )
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


<main class="budget-main">


    <!-- =================================================
         HEADER
         ================================================= -->

    <section class="budget-header">

        <div>

            <p class="dashboard-small-title">
                WANDERAI BUDGET MANAGEMENT
            </p>

            <h1>
                💰 Trip Budget
            </h1>

            <p>
                Estimated travel cost for your
                <?php echo $destination; ?> trip.
            </p>

        </div>


        <div class="budget-actions">

            <a
                href="itinerary.php?trip_id=<?php echo $trip_id; ?>"
                class="budget-action-btn"
            >
                🗓️ View Itinerary
            </a>

        </div>

    </section>


    <!-- =================================================
         SUMMARY
         ================================================= -->

    <section class="budget-summary">

        <div class="budget-card">

            <div class="budget-card-icon">
                💰
            </div>

            <span>
                Your Budget
            </span>

            <strong>
                <?php
                echo formatRupees($userBudget);
                ?>
            </strong>

        </div>


        <div class="budget-card">

            <div class="budget-card-icon">
                🧮
            </div>

            <span>
                Estimated Total
            </span>

            <strong>
                <?php
                echo formatRupees(
                    $totalEstimatedCost
                );
                ?>
            </strong>

        </div>


        <div class="budget-card">

            <div class="budget-card-icon">
                📅
            </div>

            <span>
                Trip Duration
            </span>

            <strong>
                <?php echo $numberOfDays; ?>
                Days
            </strong>

        </div>


        <div class="budget-card">

            <div class="budget-card-icon">
                👥
            </div>

            <span>
                Travelers
            </span>

            <strong>
                <?php echo $travelers; ?>
            </strong>

        </div>

    </section>


    <!-- =================================================
         STATUS
         ================================================= -->

    <section
        class="budget-status <?php echo $budgetClass; ?>"
    >

        <h2>
            <?php
            echo htmlspecialchars(
                $budgetStatus
            );
            ?>
        </h2>


        <?php if ($userBudget > 0): ?>

            <?php if ($remainingBudget >= 0): ?>

                <p>
                    Estimated remaining budget:

                    <strong>
                        <?php
                        echo formatRupees(
                            $remainingBudget
                        );
                        ?>
                    </strong>
                </p>

            <?php else: ?>

                <p>
                    Estimated amount over budget:

                    <strong>
                        <?php
                        echo formatRupees(
                            abs($remainingBudget)
                        );
                        ?>
                    </strong>
                </p>

            <?php endif; ?>


            <div class="budget-progress">

                <div
                    class="budget-progress-bar"
                ></div>

            </div>

        <?php else: ?>

            <p>
                Add a trip budget to compare your
                estimated expenses.
            </p>

        <?php endif; ?>

    </section>


    <!-- =================================================
         ORIGINAL BREAKDOWN
         ================================================= -->

    <section class="budget-breakdown">

        <p class="dashboard-small-title">
            ESTIMATED EXPENSE BREAKDOWN
        </p>

        <h2>
            Where your money may go
        </h2>


        <div class="budget-row">

            <div class="budget-row-label">

                <span>🏨</span>

                <div>

                    <strong>
                        Accommodation
                    </strong>

                    <small>
                        <?php echo $nights; ?>
                        night(s)
                    </small>

                </div>

            </div>

            <strong>
                <?php
                echo formatRupees(
                    $accommodationCost
                );
                ?>
            </strong>

        </div>


        <div class="budget-row">

            <div class="budget-row-label">

                <span>🍽️</span>

                <div>

                    <strong>
                        Food
                    </strong>

                    <small>
                        Estimated for
                        <?php echo $travelers; ?>
                        traveler(s)
                    </small>

                </div>

            </div>

            <strong>
                <?php
                echo formatRupees(
                    $foodCost
                );
                ?>
            </strong>

        </div>


        <div class="budget-row">

            <div class="budget-row-label">

                <span>🚗</span>

                <div>

                    <strong>
                        Transportation
                    </strong>

                    <small>
                        <?php
                        echo $transportDisplay;
                        ?>
                    </small>

                </div>

            </div>

            <strong>
                <?php
                echo formatRupees(
                    $transportCost
                );
                ?>
            </strong>

        </div>


        <div class="budget-row">

            <div class="budget-row-label">

                <span>🎟️</span>

                <div>

                    <strong>
                        Activities / Entry
                    </strong>

                    <small>
                        <?php
                        echo $activityPlaces;
                        ?>
                        itinerary place(s)
                    </small>

                </div>

            </div>

            <strong>
                <?php
                echo formatRupees(
                    $activityCost
                );
                ?>
            </strong>

        </div>


        <div class="budget-row">

            <div class="budget-row-label">

                <span>🧾</span>

                <div>

                    <strong>
                        Miscellaneous
                    </strong>

                    <small>
                        Emergency / small expenses
                    </small>

                </div>

            </div>

            <strong>
                <?php
                echo formatRupees(
                    $miscellaneousCost
                );
                ?>
            </strong>

        </div>


        <div class="budget-row budget-total">

            <span>
                Estimated Total
            </span>

            <strong>
                <?php
                echo formatRupees(
                    $totalEstimatedCost
                );
                ?>
            </strong>

        </div>

    </section>


    <!-- =================================================
         BUDGET OPTIMIZATION
         ================================================= -->

    <?php if ($userBudget > 0): ?>

        <section class="budget-optimizer">

            <div class="optimizer-header">

                <p class="dashboard-small-title">
                    AI BUDGET OPTIMIZATION
                </p>

                <h2>
                    🤖 Ways to reduce your trip cost
                </h2>

                <p>
                    WanderAI analyzed your estimated
                    expenses and generated lower-cost
                    alternatives.
                </p>

            </div>


            <div class="optimizer-summary">

                <div class="optimizer-card">

                    <span>
                        Current Estimate
                    </span>

                    <strong>
                        <?php
                        echo formatRupees(
                            $totalEstimatedCost
                        );
                        ?>
                    </strong>

                </div>


                <div class="optimizer-card">

                    <span>
                        Potential Savings
                    </span>

                    <strong>
                        <?php
                        echo formatRupees(
                            $totalPotentialSaving
                        );
                        ?>
                    </strong>

                </div>


                <div class="optimizer-card">

                    <span>
                        Optimized Estimate
                    </span>

                    <strong>
                        <?php
                        echo formatRupees(
                            $optimizedTotalCost
                        );
                        ?>
                    </strong>

                </div>


                <div class="optimizer-card">

                    <span>
                        Optimized Balance
                    </span>

                    <strong>
                        <?php
                        echo formatRupees(
                            $optimizedRemainingBudget
                        );
                        ?>
                    </strong>

                </div>

            </div>


            <div
                class="optimizer-status <?php echo $optimizedClass; ?>"
            >

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $optimizedStatus
                    );
                    ?>
                </strong>

                <?php if ($optimizedRemainingBudget >= 0): ?>

                    <p>
                        You could potentially save
                        <?php
                        echo formatRupees(
                            $totalPotentialSaving
                        );
                        ?>
                        and stay within your budget.
                    </p>

                <?php else: ?>

                    <p>
                        Even after applying the
                        suggested savings, consider
                        reducing the trip cost further.
                    </p>

                <?php endif; ?>

            </div>


            <?php if (!empty($optimizationSuggestions)): ?>

                <div class="optimization-list">

                    <?php
                    foreach (
                        $optimizationSuggestions
                        as $suggestion
                    ):
                    ?>

                        <div class="optimization-item">

                            <div class="optimization-left">

                                <div class="optimization-icon">

                                    <?php
                                    echo $suggestion["icon"];
                                    ?>

                                </div>

                                <div>

                                    <h3>

                                        <?php
                                        echo htmlspecialchars(
                                            $suggestion["title"]
                                        );
                                        ?>

                                    </h3>

                                    <p>

                                        <?php
                                        echo htmlspecialchars(
                                            $suggestion["description"]
                                        );
                                        ?>

                                    </p>

                                </div>

                            </div>


                            <div class="saving-badge">

                                Save up to

                                <?php
                                echo formatRupees(
                                    $suggestion["saving"]
                                );
                                ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="no-optimization">

                    <strong>
                        ✅ Your current expense plan is
                        already relatively budget-friendly.
                    </strong>

                    <p>
                        No major cost-saving recommendation
                        is currently required.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>


    <!-- =================================================
         IMPORTANT NOTE
         ================================================= -->

    <section class="budget-note">

        <strong>
            ℹ️ Important:
        </strong>

        The amounts shown here are
        <strong>estimated values</strong>,
        not live prices.

        Actual hotel rates,
        transportation costs,
        food prices and attraction fees
        can vary depending on destination,
        season, provider and traveler choices.

        The budget optimization section provides
        estimated saving suggestions and does not
        automatically book or change your trip.

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

        © 2026 WanderAI —
        AI Travel Itinerary Optimizer

    </div>

</footer>


</body>
</html>