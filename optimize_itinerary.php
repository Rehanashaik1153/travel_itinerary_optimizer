<?php
/* ============================================================
   WANDERAI - APPLY BUDGET OPTIMIZATION
   ------------------------------------------------------------
   This file:
   1. Loads the user's saved itinerary
   2. Finds free/low-cost alternatives
   3. Replaces expensive itinerary attractions where possible
   4. Applies cheaper accommodation metadata
   5. Applies cheaper food/transport estimates
   6. Saves the optimized itinerary
   7. Redirects back to itinerary.php
   ============================================================ */

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";

$user_id = (int)$_SESSION["user_id"];


/* ============================================================
   CHECK TRIP ID
   ============================================================ */

if (!isset($_GET["trip_id"])) {
    header("Location: dashboard.php");
    exit();
}

$trip_id = (int)$_GET["trip_id"];

if ($trip_id <= 0) {
    header("Location: dashboard.php");
    exit();
}


/* ============================================================
   GET TRIP
   ============================================================ */

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


/* ============================================================
   TRIP DETAILS
   ============================================================ */

$numberOfDays = max(
    1,
    (int)($trip["number_of_days"] ?? 1)
);

$travelers = max(
    1,
    (int)($trip["travelers"] ?? 1)
);

$userBudget = (float)(
    $trip["budget"] ?? 0
);


/* ============================================================
   LOAD GENERATED ITINERARY
   ============================================================ */

if (empty($trip["generated_itinerary"])) {

    die(
        "No generated itinerary was found for this trip."
    );
}

$itinerary = json_decode(
    $trip["generated_itinerary"],
    true
);

if (!is_array($itinerary)) {

    die(
        "The saved itinerary data is invalid."
    );
}


/* ============================================================
   HELPER FUNCTIONS
   ============================================================ */

/*
 * Get first available value from a place.
 */
function getValue($array, $keys, $default = "")
{
    if (!is_array($array)) {
        return $default;
    }

    foreach ($keys as $key) {

        if (
            isset($array[$key]) &&
            $array[$key] !== ""
        ) {
            return $array[$key];
        }
    }

    return $default;
}


/*
 * Convert a value to a numeric cost.
 */
function getPlaceCost($place)
{
    $possibleCost = getValue(
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

    if (
        is_numeric($possibleCost)
    ) {

        return max(
            0,
            (float)$possibleCost
        );
    }


    /*
     * Check textual information.
     */
    $text = strtolower(
        (string)getValue(
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
        strpos($text, "no entry") !== false ||
        strpos($text, "free entry") !== false
    ) {

        return 0;
    }


    /*
     * Categories which are normally
     * free or very low cost.
     */
    $category = strtolower(
        (string)getValue(
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
        strpos($category, "scenic") !== false ||
        strpos($category, "beach") !== false ||
        strpos($category, "public") !== false
    ) {

        return 0;
    }


    /*
     * If no price is available,
     * use the same estimated activity
     * cost used by budget.php.
     */
    return 250;
}


/*
 * Determine whether a place is
 * free or inexpensive.
 */
function isCheapPlace($place)
{
    $cost = getPlaceCost($place);

    if ($cost <= 150) {
        return true;
    }

    $category = strtolower(
        (string)getValue(
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
        strpos($category, "scenic") !== false ||
        strpos($category, "beach") !== false ||
        strpos($category, "public") !== false ||
        strpos($category, "cultural") !== false
    );
}


/*
 * Get place name.
 */
function getPlaceName($place)
{
    return (string)getValue(
        $place,
        [
            "name",
            "place_name",
            "title"
        ],
        "Recommended Place"
    );
}


/*
 * Get place category.
 */
function getPlaceCategory($place)
{
    return (string)getValue(
        $place,
        [
            "category",
            "type",
            "place_type"
        ],
        "Attraction"
    );
}


/*
 * Check whether two places have
 * essentially the same name.
 */
function samePlace($placeA, $placeB)
{
    $nameA = strtolower(
        trim(getPlaceName($placeA))
    );

    $nameB = strtolower(
        trim(getPlaceName($placeB))
    );

    return $nameA === $nameB;
}


/* ============================================================
   FIND ACCOMMODATION
   ============================================================ */

$oldAccommodation = null;

if (
    isset($itinerary["_accommodation"]) &&
    is_array($itinerary["_accommodation"])
) {

    $oldAccommodation =
        $itinerary["_accommodation"];
}


/* ============================================================
   CREATE CHEAPER ACCOMMODATION
   ============================================================ */

/*
 * We do NOT invent a real hotel booking.
 *
 * We mark the itinerary as using a
 * budget accommodation option.
 *
 * The actual price remains an estimate.
 */

$optimizedAccommodation = [];

if ($oldAccommodation !== null) {

    $oldAccommodationName =
        getValue(
            $oldAccommodation,
            [
                "name",
                "hotel_name",
                "title"
            ],
            "Selected accommodation"
        );

    $oldAccommodationCategory =
        getValue(
            $oldAccommodation,
            [
                "category",
                "type"
            ],
            "Accommodation"
        );


    /*
     * Preserve useful location information
     * from the original accommodation.
     */
    $optimizedAccommodation =
        $oldAccommodation;

} else {

    $optimizedAccommodation = [
        "name" =>
            "Budget Accommodation",

        "category" =>
            "Hostel / Guesthouse"
    ];
}


/*
 * Estimated cheaper stay.
 *
 * This matches the budget page's
 * optimization calculation.
 */
$optimizedAccommodation[
    "optimized"
] = true;

$optimizedAccommodation[
    "optimization_type"
] = "budget_alternative";

$optimizedAccommodation[
    "name"
] =
    "Budget Accommodation";

$optimizedAccommodation[
    "category"
] =
    "Hostel / Guesthouse";

$optimizedAccommodation[
    "estimated_cost_per_night"
] =
    700;

$optimizedAccommodation[
    "price_per_night"
] =
    700;

$optimizedAccommodation[
    "nights"
] =
    max(
        1,
        $numberOfDays - 1
    );

$optimizedAccommodation[
    "estimated_total_cost"
] =
    700 *
    max(
        1,
        $numberOfDays - 1
    );


/*
 * Save optimized accommodation.
 */
$itinerary["_accommodation"] =
    $optimizedAccommodation;


/* ============================================================
   FOOD OPTIMIZATION
   ============================================================ */

/*
 * Original budget.php:
 *
 * ₹700/person/day
 *
 * Optimized:
 *
 * ₹500/person/day
 */

$itinerary["_budget_optimization"] = [
    "applied" => true,

    "food" => [
        "optimized" => true,

        "plan" =>
            "Local / budget meals",

        "cost_per_person_per_day" =>
            500,

        "travelers" =>
            $travelers,

        "days" =>
            $numberOfDays,

        "estimated_cost" =>
            500 *
            $travelers *
            $numberOfDays
    ],

    "transport" => [
        "optimized" => true,

        "plan" =>
            "Public transport / walking where practical",

        "estimated_daily_cost_per_person" =>
            350,

        "travelers" =>
            $travelers,

        "days" =>
            $numberOfDays,

        "estimated_cost" =>
            350 *
            $travelers *
            $numberOfDays
    ]
];


/* ============================================================
   COLLECT ALL PLACES
   ============================================================ */

$allPlaces = [];

$usedPlaceNames = [];


foreach (
    $itinerary as $dayKey => $dayData
) {

    /*
     * Ignore metadata.
     */
    if (
        strpos(
            (string)$dayKey,
            "_"
        ) === 0
    ) {
        continue;
    }


    if (
        !is_array($dayData)
    ) {
        continue;
    }


    if (
        !isset($dayData["places"]) ||
        !is_array($dayData["places"])
    ) {
        continue;
    }


    foreach (
        $dayData["places"] as $place
    ) {

        if (
            !is_array($place)
        ) {
            continue;
        }


        /*
         * Do not treat breaks as attractions.
         */
        if (
            !empty($place["is_break"])
        ) {
            continue;
        }


        $allPlaces[] =
            $place;


        $placeName =
            strtolower(
                trim(
                    getPlaceName(
                        $place
                    )
                )
            );


        if ($placeName !== "") {

            $usedPlaceNames[
                $placeName
            ] = true;
        }
    }
}


/* ============================================================
   FIND CHEAP ALTERNATIVES
   ============================================================ */

$cheapPlaces = [];

$paidPlaces = [];


foreach (
    $allPlaces as $place
) {

    $cost =
        getPlaceCost(
            $place
        );


    if (
        isCheapPlace(
            $place
        )
    ) {

        $cheapPlaces[] =
            $place;

    } else {

        $paidPlaces[] =
            $place;
    }
}


/* ============================================================
   SORT PAID PLACES
   MOST EXPENSIVE FIRST
   ============================================================ */

usort(
    $paidPlaces,
    function($a, $b) {

        return
            getPlaceCost($b)
            <=>
            getPlaceCost($a);
    }
);


/* ============================================================
   SORT CHEAP PLACES
   FREE FIRST
   ============================================================ */

usort(
    $cheapPlaces,
    function($a, $b) {

        return
            getPlaceCost($a)
            <=>
            getPlaceCost($b);
    }
);


/* ============================================================
   DETERMINE CURRENT ACTIVITY COST
   ============================================================ */

$currentActivityCost = 0;

foreach (
    $allPlaces as $place
) {

    $currentActivityCost +=
        250 *
        $travelers;
}


/* ============================================================
   DETERMINE INITIAL OPTIMIZED COST
   ============================================================ */

$optimizedActivityCost =
    $currentActivityCost;


/* ============================================================
   ACTIVITY REPLACEMENTS
   ============================================================ */

$replacementMap = [];

$replacementCount = 0;


/*
 * We replace paid itinerary places
 * with cheap/free places already
 * returned by the itinerary engine.
 *
 * We never invent an attraction.
 */

foreach (
    $paidPlaces as $paidPlace
) {

    if (
        empty($cheapPlaces)
    ) {
        break;
    }


    /*
     * Find a cheap place which:
     * - is not already used
     * - preferably has a similar category
     */
    $selectedAlternative = null;

    $paidCategory =
        strtolower(
            getPlaceCategory(
                $paidPlace
            )
        );


    foreach (
        $cheapPlaces as $index => $cheapPlace
    ) {

        $cheapName =
            strtolower(
                trim(
                    getPlaceName(
                        $cheapPlace
                    )
                )
            );


        /*
         * Do not duplicate a place
         * already present in itinerary.
         */
        if (
            isset(
                $usedPlaceNames[
                    $cheapName
                ]
            )
        ) {
            continue;
        }


        $cheapCategory =
            strtolower(
                getPlaceCategory(
                    $cheapPlace
                )
            );


        /*
         * Prefer category similarity.
         */
        if (
            $paidCategory !== "" &&
            $cheapCategory !== "" &&
            (
                strpos(
                    $cheapCategory,
                    $paidCategory
                ) !== false ||
                strpos(
                    $paidCategory,
                    $cheapCategory
                ) !== false
            )
        ) {

            $selectedAlternative =
                $cheapPlace;

            unset(
                $cheapPlaces[$index]
            );

            $cheapPlaces =
                array_values(
                    $cheapPlaces
                );

            break;
        }
    }


    /*
     * If no category match exists,
     * select the cheapest available
     * unused place.
     */
    if (
        $selectedAlternative === null
    ) {

        foreach (
            $cheapPlaces as $index => $cheapPlace
        ) {

            $cheapName =
                strtolower(
                    trim(
                        getPlaceName(
                            $cheapPlace
                        )
                    )
                );


            if (
                isset(
                    $usedPlaceNames[
                        $cheapName
                    ]
                )
            ) {
                continue;
            }


            $selectedAlternative =
                $cheapPlace;


            unset(
                $cheapPlaces[$index]
            );


            $cheapPlaces =
                array_values(
                    $cheapPlaces
                );

            break;
        }
    }


    /*
     * No alternative found.
     */
    if (
        $selectedAlternative === null
    ) {
        continue;
    }


    $paidCost =
        getPlaceCost(
            $paidPlace
        );


    $alternativeCost =
        getPlaceCost(
            $selectedAlternative
        );


    /*
     * Only replace if the alternative
     * is actually cheaper.
     */
    if (
        $alternativeCost >=
        $paidCost
    ) {
        continue;
    }


    $savingPerTraveler =
        max(
            0,
            $paidCost -
            $alternativeCost
        );


    /*
     * Update estimated activity
     * allocation.
     */
    $oldAllocation =
        250 *
        $travelers;


    $newAllocation =
        $alternativeCost *
        $travelers;


    $actualSaving =
        max(
            0,
            $oldAllocation -
            $newAllocation
        );


    /*
     * Store replacement information.
     */
    $replacementMap[] = [

        "original" =>
            getPlaceName(
                $paidPlace
            ),

        "alternative" =>
            getPlaceName(
                $selectedAlternative
            ),

        "original_cost" =>
            $paidCost,

        "alternative_cost" =>
            $alternativeCost,

        "saving" =>
            $actualSaving
    ];


    $replacementCount++;
}


/* ============================================================
   APPLY REPLACEMENTS TO DAY-WISE ITINERARY
   ============================================================ */

$replacementIndex = 0;


foreach (
    $itinerary as $dayKey => &$dayData
) {

    /*
     * Ignore metadata.
     */
    if (
        strpos(
            (string)$dayKey,
            "_"
        ) === 0
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
        $dayData["places"] as $placeIndex => &$place
    ) {

        if (
            !is_array($place)
        ) {
            continue;
        }


        if (
            !empty(
                $place["is_break"]
            )
        ) {
            continue;
        }


        if (
            $replacementIndex >=
            count(
                $replacementMap
            )
        ) {
            break;
        }


        /*
         * Only replace if this place
         * matches the selected original.
         */
        $currentName =
            getPlaceName(
                $place
            );


        $target =
            $replacementMap[
                $replacementIndex
            ];


        if (
            strcasecmp(
                trim($currentName),
                trim($target["original"])
            ) !== 0
        ) {
            continue;
        }


        /*
         * Keep the existing schedule
         * information.
         */
        $oldTime =
            getValue(
                $place,
                [
                    "time",
                    "start_time",
                    "visit_time"
                ],
                ""
            );


        $oldDuration =
            getValue(
                $place,
                [
                    "duration",
                    "visit_duration",
                    "estimated_duration"
                ],
                ""
            );


        $oldTravelTime =
            getValue(
                $place,
                [
                    "travel_time",
                    "estimated_travel_time"
                ],
                ""
            );


        /*
         * Replace the attraction with
         * the alternative place.
         *
         * IMPORTANT:
         * We start with the alternative's
         * original API data so coordinates,
         * maps and location information are
         * preserved.
         */
        $newPlace =
            $cheapPlaces; // temporary no-op
        

        /*
         * Find the actual alternative
         * from replacement map.
         *
         * Search all original itinerary
         * places for matching name.
         */
        $alternativeData =
            null;


        foreach (
            $allPlaces as $candidate
        ) {

            if (
                strcasecmp(
                    trim(
                        getPlaceName(
                            $candidate
                        )
                    ),
                    trim(
                        $target["alternative"]
                    )
                ) === 0
            ) {

                $alternativeData =
                    $candidate;

                break;
            }
        }


        /*
         * If alternative data exists,
         * use it.
         */
        if (
            is_array(
                $alternativeData
            )
        ) {

            /*
             * Preserve existing schedule.
             */
            if (
                $oldTime !== ""
            ) {

                if (
                    isset(
                        $alternativeData["time"]
                    )
                ) {

                    $alternativeData[
                        "time"
                    ] =
                        $oldTime;
                }

                if (
                    isset(
                        $alternativeData[
                            "start_time"
                        ]
                    )
                ) {

                    $alternativeData[
                        "start_time"
                    ] =
                        $oldTime;
                }

                if (
                    isset(
                        $alternativeData[
                            "visit_time"
                        ]
                    )
                ) {

                    $alternativeData[
                        "visit_time"
                    ] =
                        $oldTime;
                }
            }


            /*
             * Preserve visit duration
             * when available.
             */
            if (
                $oldDuration !== ""
            ) {

                if (
                    isset(
                        $alternativeData[
                            "duration"
                        ]
                    )
                ) {

                    $alternativeData[
                        "duration"
                    ] =
                        $oldDuration;
                }
            }


            /*
             * Mark this place as a
             * budget optimization.
             */
            $alternativeData[
                "budget_optimized"
            ] = true;

            $alternativeData[
                "replaced_place"
            ] =
                $target["original"];

            $alternativeData[
                "estimated_original_cost"
            ] =
                $target["original_cost"];

            $alternativeData[
                "estimated_optimized_cost"
            ] =
                $target["alternative_cost"];


            /*
             * Replace the place.
             */
            $place =
                $alternativeData;


            $replacementIndex++;
        }
    }
}

unset(
    $dayData,
    $place
);


/* ============================================================
   SAVE OPTIMIZATION DETAILS
   ============================================================ */

$itinerary["_budget_optimization"][
    "activity_replacements"
] =
    $replacementMap;

$itinerary["_budget_optimization"][
    "activity_replacement_count"
] =
    $replacementCount;


/* ============================================================
   MARK ITINERARY AS OPTIMIZED
   ============================================================ */

$itinerary["_optimization_applied"] = true;

$itinerary["_optimization_date"] =
    date("Y-m-d H:i:s");

$itinerary["_optimization_message"] =
    "This itinerary was regenerated using lower-cost alternatives based on the available trip budget.";


/* ============================================================
   CALCULATE OPTIMIZED ESTIMATES
   ============================================================ */

$optimizedAccommodationCost =
    700 *
    max(
        1,
        $numberOfDays - 1
    );

$optimizedFoodCost =
    500 *
    $travelers *
    $numberOfDays;

$optimizedTransportCost =
    350 *
    $travelers *
    $numberOfDays;


/*
 * Activity cost:
 *
 * Start with original allocation
 * of ₹250 per itinerary place.
 *
 * Then apply actual replacement
 * costs.
 */
$originalActivityCost =
    count(
        $allPlaces
    ) *
    250 *
    $travelers;


$optimizedActivityCost =
    $originalActivityCost;


foreach (
    $replacementMap as $replacement
) {

    $originalAllocation =
        250 *
        $travelers;

    $alternativeAllocation =
        $replacement[
            "alternative_cost"
        ] *
        $travelers;


    $optimizedActivityCost -=
        max(
            0,
            $originalAllocation -
            $alternativeAllocation
        );
}


$optimizedActivityCost =
    max(
        0,
        $optimizedActivityCost
    );


/*
 * Miscellaneous minimum buffer.
 */
$optimizedMiscellaneousCost =
    200 *
    $travelers;


$optimizedTotalCost =
    $optimizedAccommodationCost +
    $optimizedFoodCost +
    $optimizedTransportCost +
    $optimizedActivityCost +
    $optimizedMiscellaneousCost;


$totalOriginalEstimatedCost =
    (
        1800 *
        max(
            1,
            $numberOfDays - 1
        )
    ) +
    (
        700 *
        $travelers *
        $numberOfDays
    ) +
    (
        350 *
        $travelers *
        $numberOfDays
    ) +
    $originalActivityCost +
    (
        300 *
        $travelers
    );


$totalSaving =
    max(
        0,
        $totalOriginalEstimatedCost -
        $optimizedTotalCost
    );


/* ============================================================
   SAVE FINAL OPTIMIZED BUDGET INFORMATION
   ============================================================ */

$itinerary["_budget_optimization"][
    "original_estimated_cost"
] =
    $totalOriginalEstimatedCost;

$itinerary["_budget_optimization"][
    "optimized_estimated_cost"
] =
    $optimizedTotalCost;

$itinerary["_budget_optimization"][
    "potential_saving"
] =
    $totalSaving;

$itinerary["_budget_optimization"][
    "budget"
] =
    $userBudget;

$itinerary["_budget_optimization"][
    "remaining_budget"
] =
    $userBudget -
    $optimizedTotalCost;


/* ============================================================
   SAVE JSON
   ============================================================ */

$optimizedJson =
    json_encode(
        $itinerary,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );


if (
    $optimizedJson === false
) {

    die(
        "Unable to encode optimized itinerary."
    );
}


/* ============================================================
   UPDATE TRIP
   ============================================================ */

$updateStmt = $conn->prepare(
    "UPDATE trips
     SET generated_itinerary = ?
     WHERE trip_id = ?
     AND user_id = ?"
);


if (!$updateStmt) {

    die(
        "Database update error: " .
        $conn->error
    );
}


$updateStmt->bind_param(
    "sii",
    $optimizedJson,
    $trip_id,
    $user_id
);


if (
    !$updateStmt->execute()
) {

    $updateStmt->close();

    die(
        "Unable to save optimized itinerary: " .
        $conn->error
    );
}


$updateStmt->close();


/* ============================================================
   REDIRECT TO ITINERARY
   ============================================================ */

/*
 * The itinerary page will now load
 * the optimized generated_itinerary.
 */
header(
    "Location: itinerary.php?trip_id=" .
    $trip_id .
    "&optimized=1"
);

exit();

?>