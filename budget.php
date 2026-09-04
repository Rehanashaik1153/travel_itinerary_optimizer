```php
<?php
/* =====================================================
   WANDERAI - DYNAMIC TRIP BUDGET + ALTERNATIVE OPTIMIZER
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

$stmt->bind_param("ii", $trip_id, $user_id);
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
   HELPER FUNCTIONS
   ===================================================== */

function formatRupees($amount)
{
    return "₹" .
        number_format(
            (float)$amount,
            2
        );
}


function getPlaceValue($place, $keys, $default = "")
{
    foreach ($keys as $key) {

        if (
            isset($place[$key]) &&
            $place[$key] !== ""
        ) {
            return $place[$key];
        }
    }

    return $default;
}


function estimatePlaceCost($place)
{
    $possibleCost =
        getPlaceValue(
            $place,
            [
                "estimated_cost",
                "cost",
                "entry_fee",
                "price",
                "estimated_price"
            ],
            null
        );

    if (is_numeric($possibleCost)) {

        return max(
            0,
            (float)$possibleCost
        );
    }


    $text =
        strtolower(
            (string)getPlaceValue(
                $place,
                [
                    "price",
                    "cost",
                    "entry_fee",
                    "description"
                ],
                ""
            )
        );


    if (
        strpos($text, "free") !== false ||
        strpos($text, "no entry") !== false
    ) {

        return 0;
    }


    $category =
        strtolower(
            (string)getPlaceValue(
                $place,
                [
                    "category",
                    "type",
                    "place_type"
                ],
                ""
            )
        );


    if (
        strpos($category, "park") !== false ||
        strpos($category, "viewpoint") !== false ||
        strpos($category, "nature") !== false ||
        strpos($category, "beach") !== false ||
        strpos($category, "scenic") !== false ||
        strpos($category, "public") !== false
    ) {

        return 0;
    }


    return 250;
}


function isFreeOrCheapPlace($place)
{
    $cost = estimatePlaceCost($place);

    if ($cost <= 150) {
        return true;
    }


    $category =
        strtolower(
            (string)getPlaceValue(
                $place,
                [
                    "category",
                    "type",
                    "place_type"
                ],
                ""
            )
        );


    return (
        strpos($category, "park") !== false ||
        strpos($category, "viewpoint") !== false ||
        strpos($category, "nature") !== false ||
        strpos($category, "beach") !== false ||
        strpos($category, "scenic") !== false ||
        strpos($category, "cultural") !== false ||
        strpos($category, "public") !== false
    );
}


/* =====================================================
   ACCOMMODATION COST
   ===================================================== */

$accommodationPerNight = 0;

$currentAccommodationName =
    "Selected accommodation";

$currentAccommodationCategory =
    "";


if ($accommodation !== null) {

    $accommodationCategory =
        strtolower(
            $accommodation["category"] ?? ""
        );

    $accommodationName =
        strtolower(
            $accommodation["name"] ?? ""
        );

    $currentAccommodationName =
        htmlspecialchars(
            $accommodation["name"] ??
            "Selected accommodation"
        );

    $currentAccommodationCategory =
        htmlspecialchars(
            $accommodation["category"] ??
            "Accommodation"
        );


    $storedAccommodationPrice =
        null;


    foreach (
        [
            "price_per_night",
            "estimated_cost_per_night",
            "nightly_price",
            "price",
            "cost_per_night"
        ] as $priceKey
    ) {

        if (
            isset($accommodation[$priceKey]) &&
            is_numeric(
                $accommodation[$priceKey]
            )
        ) {

            $storedAccommodationPrice =
                (float)$accommodation[$priceKey];

            break;
        }
    }


    if (
        $storedAccommodationPrice !== null &&
        $storedAccommodationPrice > 0
    ) {

        $accommodationPerNight =
            $storedAccommodationPrice;

    } elseif (
        strpos(
            $accommodationCategory,
            "hostel"
        ) !== false ||
        strpos(
            $accommodationName,
            "hostel"
        ) !== false
    ) {

        $accommodationPerNight = 700;

    } elseif (
        strpos(
            $accommodationCategory,
            "guest"
        ) !== false ||
        strpos(
            $accommodationName,
            "guest"
        ) !== false
    ) {

        $accommodationPerNight = 1200;

    } elseif (
        strpos(
            $accommodationCategory,
            "resort"
        ) !== false ||
        strpos(
            $accommodationName,
            "resort"
        ) !== false
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
    $accommodationPerNight *
    $nights;


/* =====================================================
   FOOD
   ===================================================== */

$foodPerPersonPerDay = 700;

$foodCost =
    $foodPerPersonPerDay *
    $travelers *
    $numberOfDays;


/* =====================================================
   TRANSPORTATION
   ===================================================== */

$transportPerDayPerTraveler = 0;


if (
    strpos(
        $transportRaw,
        "walking"
    ) !== false
) {

    $transportPerDayPerTraveler = 100;

} elseif (
    strpos(
        $transportRaw,
        "bike"
    ) !== false ||
    strpos(
        $transportRaw,
        "bicycle"
    ) !== false
) {

    $transportPerDayPerTraveler = 300;

} elseif (
    strpos(
        $transportRaw,
        "public"
    ) !== false ||
    strpos(
        $transportRaw,
        "bus"
    ) !== false ||
    strpos(
        $transportRaw,
        "train"
    ) !== false
) {

    $transportPerDayPerTraveler = 350;

} elseif (
    strpos(
        $transportRaw,
        "taxi"
    ) !== false ||
    strpos(
        $transportRaw,
        "cab"
    ) !== false
) {

    $transportPerDayPerTraveler = 1000;

} else {

    $transportPerDayPerTraveler = 800;
}


if (
    strpos(
        $transportRaw,
        "car"
    ) !== false ||
    strpos(
        $transportRaw,
        "taxi"
    ) !== false ||
    strpos(
        $transportRaw,
        "cab"
    ) !== false
) {

    $transportCost =
        $transportPerDayPerTraveler *
        $numberOfDays;

} else {

    $transportCost =
        $transportPerDayPerTraveler *
        $travelers *
        $numberOfDays;
}


/* =====================================================
   COLLECT ITINERARY PLACES
   ===================================================== */

$allPlaces = [];

$activityPlaces = 0;


foreach (
    $generatedItinerary as $dayKey => $dayData
) {

    if (
        $dayKey === "_accommodation"
    ) {
        continue;
    }


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


        if (
            !empty(
                $place["is_break"]
            )
        ) {
            continue;
        }


        $allPlaces[] =
            $place;

        $activityPlaces++;
    }
}


/* =====================================================
   ACTIVITY COST
   ===================================================== */

$activityCostPerVisit = 250;

$activityCost =
    $activityPlaces *
    $activityCostPerVisit *
    $travelers;


/* =====================================================
   MISCELLANEOUS
   ===================================================== */

$miscellaneousCost =
    300 *
    $travelers;


/* =====================================================
   CURRENT TOTAL
   ===================================================== */

$totalEstimatedCost =
    $accommodationCost +
    $foodCost +
    $transportCost +
    $activityCost +
    $miscellaneousCost;


/* =====================================================
   BUDGET STATUS
   ===================================================== */

$remainingBudget =
    $userBudget -
    $totalEstimatedCost;


if ($userBudget <= 0) {

    $budgetStatus =
        "No budget specified";

    $budgetClass =
        "neutral";

} elseif (
    $remainingBudget >= 0
) {

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
        (
            $totalEstimatedCost /
            $userBudget
        ) *
        100;

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
   ALTERNATIVE OPTIMIZATION ENGINE
   ===================================================== */

$optimizationSuggestions = [];


/* =====================================================
   1. ACCOMMODATION ALTERNATIVE
   ===================================================== */

$optimizedAccommodationCost =
    $accommodationCost;

$alternativeAccommodationName =
    "Budget accommodation";

$alternativeAccommodationCategory =
    "Hostel / Guesthouse";

$alternativeAccommodationPerNight =
    700;


if (
    $accommodationCost > 0 &&
    $accommodationPerNight >
    $alternativeAccommodationPerNight
) {

    $alternativeAccommodationCost =
        $alternativeAccommodationPerNight *
        $nights;


    $accommodationSaving =
        $accommodationCost -
        $alternativeAccommodationCost;


    if (
        $accommodationSaving > 0
    ) {

        $optimizedAccommodationCost =
            $alternativeAccommodationCost;


        $optimizationSuggestions[] = [

            "type" => "accommodation",

            "icon" => "🏨",

            "title" =>
                "Choose cheaper accommodation",

            "current" =>
                $currentAccommodationName,

            "alternative" =>
                $alternativeAccommodationName,

            "description" =>
                "Current estimated accommodation: " .
                formatRupees(
                    $accommodationCost
                ) .
                ". Estimated budget stay: " .
                formatRupees(
                    $alternativeAccommodationCost
                ) .
                " for " .
                $nights .
                " night(s).",

            "current_cost" =>
                $accommodationCost,

            "alternative_cost" =>
                $alternativeAccommodationCost,

            "saving" =>
                $accommodationSaving
        ];
    }
}


/* =====================================================
   2. FOOD ALTERNATIVE
   ===================================================== */

$optimizedFoodPerPersonPerDay =
    500;

$optimizedFoodCost =
    $optimizedFoodPerPersonPerDay *
    $travelers *
    $numberOfDays;


$foodSaving =
    $foodCost -
    $optimizedFoodCost;


if (
    $foodSaving > 0
) {

    $optimizationSuggestions[] = [

        "type" => "food",

        "icon" => "🍽️",

        "title" =>
            "Choose lower-cost meals",

        "current" =>
            "Current meal plan",

        "alternative" =>
            "Local / budget meals",

        "description" =>
            "Current estimated food cost: " .
            formatRupees(
                $foodCost
            ) .
            ". Estimated budget meal plan: " .
            formatRupees(
                $optimizedFoodCost
            ) .
            " for " .
            $travelers .
            " traveler(s) over " .
            $numberOfDays .
            " day(s).",

        "current_cost" =>
            $foodCost,

        "alternative_cost" =>
            $optimizedFoodCost,

        "saving" =>
            $foodSaving
    ];
}


/* =====================================================
   3. TRANSPORT ALTERNATIVE
   ===================================================== */

$optimizedTransportCost =
    $transportCost;

$alternativeTransport =
    "Public transport";

$alternativeTransportDaily =
    350;


if (
    strpos(
        $transportRaw,
        "walking"
    ) !== false
) {

    $alternativeTransport =
        "Walking where practical";

    $alternativeTransportCost =
        0;

} else {

    $alternativeTransportCost =
        $alternativeTransportDaily *
        $travelers *
        $numberOfDays;
}


if (
    strpos($transportRaw, "car") !== false ||
    strpos($transportRaw, "taxi") !== false ||
    strpos($transportRaw, "cab") !== false
) {

    $alternativeTransportCost =
        $alternativeTransportDaily *
        $travelers *
        $numberOfDays;
}


$transportSaving =
    $transportCost -
    $alternativeTransportCost;


if (
    $transportSaving > 0
) {

    $optimizedTransportCost =
        $alternativeTransportCost;


    $optimizationSuggestions[] = [

        "type" => "transport",

        "icon" => "🚌",

        "title" =>
            "Use a cheaper transport option",

        "current" =>
            $transportDisplay,

        "alternative" =>
            $alternativeTransport,

        "description" =>
            "Current estimated transportation: " .
            formatRupees(
                $transportCost
            ) .
            ". Estimated alternative transportation: " .
            formatRupees(
                $alternativeTransportCost
            ) .
            ".",

        "current_cost" =>
            $transportCost,

        "alternative_cost" =>
            $alternativeTransportCost,

        "saving" =>
            $transportSaving
    ];
}


/* =====================================================
   4. ACTIVITY ALTERNATIVES
   ===================================================== */

$paidActivities = [];

$freeActivities = [];


foreach (
    $allPlaces as $place
) {

    $placeName =
        getPlaceValue(
            $place,
            [
                "name",
                "place_name",
                "title"
            ],
            "Recommended place"
        );


    $placeCost =
        estimatePlaceCost(
            $place
        );


    if (
        isFreeOrCheapPlace(
            $place
        )
    ) {

        $freeActivities[] = [

            "name" =>
                $placeName,

            "cost" =>
                $placeCost,

            "category" =>
                getPlaceValue(
                    $place,
                    [
                        "category",
                        "type",
                        "place_type"
                    ],
                    "Attraction"
                )
        ];

    } else {

        $paidActivities[] = [

            "name" =>
                $placeName,

            "cost" =>
                $placeCost,

            "category" =>
                getPlaceValue(
                    $place,
                    [
                        "category",
                        "type",
                        "place_type"
                    ],
                    "Attraction"
                )
        ];
    }
}


/* =====================================================
   REPLACE MOST EXPENSIVE PAID ACTIVITY
   ===================================================== */

$activityOptimizedCost =
    $activityCost;

$activityAlternativeNames = [];


if (
    !empty($paidActivities) &&
    !empty($freeActivities)
) {

    usort(
        $paidActivities,
        function($a, $b) {
            return $b["cost"] <=> $a["cost"];
        }
    );


    $paidToReplace =
        $paidActivities[0];

    $alternativePlace =
        $freeActivities[0];


    $currentActivityPrice =
        $paidToReplace["cost"];

    $alternativeActivityPrice =
        $alternativePlace["cost"];


    if (
        $currentActivityPrice >
        $alternativeActivityPrice
    ) {

        $activitySavingPerTraveler =
            $currentActivityPrice -
            $alternativeActivityPrice;


        $activitySaving =
            $activitySavingPerTraveler *
            $travelers;


        $activitySaving =
            min(
                $activitySaving,
                $activityCost
            );


        $activityOptimizedCost =
            $activityCost -
            $activitySaving;


        $activityAlternativeNames[] =
            $alternativePlace["name"];


        $optimizationSuggestions[] = [

            "type" => "activity",

            "icon" => "🎟️",

            "title" =>
                "Replace a paid attraction",

            "current" =>
                $paidToReplace["name"],

            "alternative" =>
                $alternativePlace["name"],

            "description" =>
                "Replace " .
                $paidToReplace["name"] .
                " (estimated cost " .
                formatRupees(
                    $currentActivityPrice
                ) .
                ") with " .
                $alternativePlace["name"] .
                " (estimated cost " .
                formatRupees(
                    $alternativeActivityPrice
                ) .
                ").",

            "current_cost" =>
                $currentActivityPrice *
                $travelers,

            "alternative_cost" =>
                $alternativeActivityPrice *
                $travelers,

            "saving" =>
                $activitySaving
        ];
    }
}


/* =====================================================
   5. MORE FREE / CHEAP ACTIVITY OPTIONS
   ===================================================== */

$additionalActivityAlternatives = [];


foreach (
    $freeActivities as $freePlace
) {

    if (
        !in_array(
            $freePlace["name"],
            $activityAlternativeNames,
            true
        )
    ) {

        $additionalActivityAlternatives[] =
            $freePlace;
    }


    if (
        count(
            $additionalActivityAlternatives
        ) >= 4
    ) {
        break;
    }
}


/* =====================================================
   6. MISCELLANEOUS
   ===================================================== */

$optimizedMiscellaneousCost =
    max(
        200 *
        $travelers,
        0
    );


$miscellaneousSaving =
    $miscellaneousCost -
    $optimizedMiscellaneousCost;


if (
    $miscellaneousSaving > 0
) {

    $optimizationSuggestions[] = [

        "type" => "misc",

        "icon" => "🧾",

        "title" =>
            "Reduce miscellaneous expenses",

        "current" =>
            "Current expense buffer",

        "alternative" =>
            "Reasonable minimum buffer",

        "description" =>
            "Current miscellaneous allowance: " .
            formatRupees(
                $miscellaneousCost
            ) .
            ". Recommended minimum buffer: " .
            formatRupees(
                $optimizedMiscellaneousCost
            ) .
            ".",

        "current_cost" =>
            $miscellaneousCost,

        "alternative_cost" =>
            $optimizedMiscellaneousCost,

        "saving" =>
            $miscellaneousSaving
    ];
}


/* =====================================================
   OPTIMIZED TOTAL
   ===================================================== */

$optimizedTotalCost =
    $optimizedAccommodationCost +
    $optimizedFoodCost +
    $optimizedTransportCost +
    $activityOptimizedCost +
    $optimizedMiscellaneousCost;


/* =====================================================
   TOTAL POTENTIAL SAVING
   ===================================================== */

$totalPotentialSaving =
    $totalEstimatedCost -
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
    $userBudget -
    $optimizedTotalCost;


if ($userBudget <= 0) {

    $optimizedStatus =
        "Set a budget to check optimization.";

    $optimizedClass =
        "neutral";

} elseif (
    $optimizedRemainingBudget >= 0
) {

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
   OPTIMIZED PERCENTAGE
   ===================================================== */

if ($userBudget > 0) {

    $optimizedPercentage =
        (
            $optimizedTotalCost /
            $userBudget
        ) *
        100;


    $optimizedPercentage =
        min(
            100,
            max(
                0,
                $optimizedPercentage
            )
        );

} else {

    $optimizedPercentage = 0;
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

/* =====================================================
   BUDGET PAGE
   ===================================================== */

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

.budget-status.success,
.optimizer-status.success {
    background: #e9f8ef;
    border: 1px solid #b7e6c7;
}

.budget-status.danger,
.optimizer-status.danger {
    background: #fff0f0;
    border: 1px solid #f0bcbc;
}

.budget-status.neutral,
.optimizer-status.neutral {
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


/* =====================================================
   OPTIMIZER
   ===================================================== */

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

.optimizer-progress {
    width: 100%;
    height: 12px;
    background: #eee;
    border-radius: 20px;
    overflow: hidden;
    margin-top: 15px;
}

.optimizer-progress-bar {
    height: 100%;
    width: <?php echo $optimizedPercentage; ?>%;
    background: #4caf50;
    border-radius: 20px;
}


/* =====================================================
   RECOMMENDATIONS
   ===================================================== */

.optimization-list {
    display: grid;
    gap: 18px;
}

.optimization-item {
    border: 1px solid #e8e8e8;
    border-radius: 16px;
    padding: 20px;
    background: #fafafa;
}

.optimization-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}

.optimization-left {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.optimization-icon {
    font-size: 30px;
}

.optimization-item h3 {
    margin: 0 0 7px;
}

.optimization-item p {
    margin: 5px 0;
    color: #666;
    line-height: 1.6;
}

.recommendation-box {
    margin-top: 15px;
    padding: 15px;
    border-radius: 12px;
    background: white;
    border: 1px solid #eee;
}

.recommendation-box strong {
    display: block;
    margin-bottom: 5px;
}

.cost-comparison {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-top: 15px;
}

.cost-box {
    padding: 13px;
    border-radius: 10px;
    background: #f6f6f6;
}

.cost-box span {
    display: block;
    color: #777;
    font-size: 13px;
    margin-bottom: 5px;
}

.cost-box strong {
    font-size: 18px;
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


/* =====================================================
   FREE / CHEAP ALTERNATIVES
   ===================================================== */

.alternative-places {
    margin-top: 25px;
}

.alternative-places h3 {
    margin-bottom: 15px;
}

.alternative-place-grid {
    display: grid;
    grid-template-columns:
        repeat(auto-fit, minmax(220px, 1fr));
    gap: 15px;
}

.alternative-place {
    padding: 16px;
    border-radius: 14px;
    background: #f8f9ff;
    border: 1px solid #e1e4ff;
}

.alternative-place strong {
    display: block;
    margin-bottom: 5px;
}

.alternative-place small {
    color: #777;
}

.no-optimization {
    padding: 20px;
    border-radius: 14px;
    background: #f5f5f5;
}


/* =====================================================
   APPLY OPTIMIZATION BUTTON
   ===================================================== */

.apply-optimization-box {
    margin-top: 30px;
    padding: 25px;
    border-radius: 16px;
    background: #f7f8ff;
    border: 1px solid #dfe3ff;
    text-align: center;
}

.apply-optimization-box h3 {
    margin-bottom: 8px;
}

.apply-optimization-box p {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.6;
}

.apply-optimization-btn {
    display: inline-block;
    padding: 14px 22px;
    border-radius: 12px;
    background: #5b7cff;
    color: white;
    text-decoration: none;
    font-weight: 700;
    transition: 0.2s;
}

.apply-optimization-btn:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}


/* =====================================================
   MOBILE
   ===================================================== */

@media (max-width: 700px) {

    .budget-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .budget-row {
        gap: 15px;
    }

    .optimization-top {
        flex-direction: column;
    }

    .saving-badge {
        white-space: normal;
    }

    .apply-optimization-btn {
        width: 100%;
        box-sizing: border-box;
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
    echo formatRupees(
        $userBudget
    );
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
            abs(
                $remainingBudget
            )
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
    <?php echo $transportDisplay; ?>
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
     AI BUDGET OPTIMIZATION
     ================================================= -->

<?php if ($userBudget > 0): ?>

<section class="budget-optimizer">

<div class="optimizer-header">

<p class="dashboard-small-title">
    AI BUDGET OPTIMIZATION
</p>

<h2>
    🤖 Recommended lower-cost alternatives
</h2>

<p>
    WanderAI compares your current estimated
    expenses with lower-cost alternatives and
    calculates the potential savings.
</p>

</div>


<!-- =================================================
     OPTIMIZER SUMMARY
     ================================================= -->

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


<!-- =================================================
     OPTIMIZED STATUS
     ================================================= -->

<div
    class="optimizer-status
    <?php echo $optimizedClass; ?>"
>

<strong>

<?php
echo htmlspecialchars(
    $optimizedStatus
);
?>

</strong>


<?php if (
    $optimizedRemainingBudget >= 0
): ?>

<p>

After applying the recommended alternatives,
your estimated trip cost becomes

<strong>
    <?php
    echo formatRupees(
        $optimizedTotalCost
    );
    ?>
</strong>.

You could potentially save

<strong>
    <?php
    echo formatRupees(
        $totalPotentialSaving
    );
    ?>
</strong>.

</p>

<?php else: ?>

<p>

The recommended alternatives reduce the
estimated cost, but the trip may still exceed
the entered budget.

</p>

<?php endif; ?>


<div class="optimizer-progress">

<div
    class="optimizer-progress-bar"
></div>

</div>

</div>


<!-- =================================================
     RECOMMENDATIONS
     ================================================= -->

<?php if (
    !empty(
        $optimizationSuggestions
    )
): ?>

<div class="optimization-list">

<?php
foreach (
    $optimizationSuggestions
    as $suggestion
):
?>

<div class="optimization-item">

<div class="optimization-top">

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


<div class="recommendation-box">

<strong>
    Recommended alternative
</strong>

<?php
echo htmlspecialchars(
    $suggestion["alternative"]
);
?>

</div>


<div class="cost-comparison">

<div class="cost-box">

<span>
    Current
</span>

<strong>

<?php
echo formatRupees(
    $suggestion["current_cost"]
);
?>

</strong>

</div>


<div class="cost-box">

<span>
    Alternative
</span>

<strong>

<?php
echo formatRupees(
    $suggestion["alternative_cost"]
);
?>

</strong>

</div>


<div class="cost-box">

<span>
    Saving
</span>

<strong>

<?php
echo formatRupees(
    $suggestion["saving"]
);
?>

</strong>

</div>

</div>

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

</div>

<?php
endforeach;
?>

</div>

<?php else: ?>

<div class="no-optimization">

<strong>
    ✅ Your current trip is already relatively
    budget-friendly.
</strong>

<p>
    No significant lower-cost alternative was
    identified from the available itinerary data.
</p>

</div>

<?php endif; ?>


<!-- =================================================
     SPECIFIC FREE / CHEAP PLACES
     ================================================= -->

<?php if (
    !empty(
        $additionalActivityAlternatives
    )
): ?>

<div class="alternative-places">

<h3>
    🎟️ More free / low-cost places from your itinerary
</h3>

<p>
    These places can help reduce attraction
    expenses while keeping the trip experience
    aligned with the available itinerary.
</p>


<div class="alternative-place-grid">

<?php
foreach (
    $additionalActivityAlternatives
    as $place
):
?>

<div class="alternative-place">

<strong>

<?php
echo htmlspecialchars(
    $place["name"]
);
?>

</strong>

<small>

<?php
echo htmlspecialchars(
    $place["category"]
);
?>

•

<?php

if (
    $place["cost"] <= 0
) {

    echo "Free";

} else {

    echo formatRupees(
        $place["cost"]
    );

}

?>

</small>

</div>

<?php endforeach; ?>

</div>

</div>

<?php endif; ?>


<!-- =================================================
     APPLY BUDGET OPTIMIZATION
     ================================================= -->

<div class="apply-optimization-box">

<h3>
    🔄 Ready to reduce your trip cost?
</h3>

<p>
    Apply the recommended lower-cost alternatives
    and generate a new itinerary that stays within
    your available budget where possible.
</p>

<a
    href="optimize_itinerary.php?trip_id=<?php echo $trip_id; ?>"
    class="apply-optimization-btn"
    onclick="return confirm(
        'Apply the recommended budget alternatives and regenerate your itinerary?'
    );"
>
    🤖 Apply Alternatives & Regenerate Itinerary
</a>

</div>


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

The optimization section compares the
current estimated cost with estimated
lower-cost alternatives available from
the trip information.

It does not automatically change or
book the saved itinerary.

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
```
