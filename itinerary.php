<?php

/* =====================================================
   WANDERAI - DYNAMIC AI ITINERARY PAGE
   ===================================================== */

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";
require_once "places.php";
require_once "recommend_places.php";
require_once "generate_itinerary.php";


$username = htmlspecialchars(
    $_SESSION["username"] ?? "User"
);

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
    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    "ii",
    $trip_id,
    $user_id
);

$stmt->execute();

$result =
    $stmt->get_result();

if (
    !$result ||
    $result->num_rows !== 1
) {

    $stmt->close();

    header("Location: dashboard.php");
    exit();
}

$trip =
    $result->fetch_assoc();

$stmt->close();


/* =====================================================
   TRIP DATA
   ===================================================== */

$destinationRaw =
    trim(
        $trip["destination"] ?? ""
    );

$destination =
    htmlspecialchars(
        $destinationRaw
    );


$startDateRaw =
    $trip["start_date"] ?? "";

$start_date = "";

if (!empty($startDateRaw)) {

    $timestamp =
        strtotime($startDateRaw);

    if ($timestamp !== false) {

        $start_date =
            date(
                "d M Y",
                $timestamp
            );
    }
}


$number_of_days =
    max(
        1,
        (int)(
            $trip[
                "number_of_days"
            ] ?? 1
        )
    );


$budget =
    number_format(
        (float)(
            $trip["budget"] ?? 0
        ),
        2
    );


$travelers =
    max(
        1,
        (int)(
            $trip["travelers"] ?? 1
        )
    );


$interests =
    htmlspecialchars(
        $trip["interests"] ?? ""
    );


$transportRaw =
    trim(
        $trip[
            "transport_preference"
        ] ?? ""
    );

$transport =
    htmlspecialchars(
        $transportRaw
    );


$latitude =
    (float)(
        $trip["latitude"] ?? 0
    );

$longitude =
    (float)(
        $trip["longitude"] ?? 0
    );


/* =====================================================
   REGENERATION
   ===================================================== */

$regenerate =
    isset($_GET["regenerate"]) &&
    $_GET["regenerate"] === "1";


/* =====================================================
   VARIABLES
   ===================================================== */

$allPlaces = [];

$recommendedPlaces = [];

$itineraryPlaces = [];

$generatedItinerary = [];

$selectedAccommodation = null;

$placesMessage = "";

$placesDiscoveredCount = 0;

$savedPlacesCount = 0;


/* =====================================================
   LOAD SAVED ITINERARY
   ===================================================== */

$savedItinerary = [];

if (
    !empty(
        $trip["generated_itinerary"]
    )
) {

    $decoded =
        json_decode(
            $trip[
                "generated_itinerary"
            ],
            true
        );

    if (
        is_array($decoded) &&
        !empty($decoded)
    ) {

        $savedItinerary =
            $decoded;
    }
}


/* =====================================================
   GET SAVED ACCOMMODATION
   ===================================================== */

$savedAccommodation = null;

if (
    isset(
        $savedItinerary[
            "_accommodation"
        ]
    ) &&
    is_array(
        $savedItinerary[
            "_accommodation"
        ]
    )
) {

    $savedAccommodation =
        $savedItinerary[
            "_accommodation"
        ];

    unset(
        $savedItinerary[
            "_accommodation"
        ]
    );
}


/* =====================================================
   GET SAVED RECOMMENDED PLACES
   ===================================================== */

$savedPlaces = [];

if (
    isset(
        $savedItinerary[
            "_recommended_places"
        ]
    ) &&
    is_array(
        $savedItinerary[
            "_recommended_places"
        ]
    )
) {

    $savedPlaces =
        $savedItinerary[
            "_recommended_places"
        ];

    unset(
        $savedItinerary[
            "_recommended_places"
        ]
    );
}


/* =====================================================
   GET SAVED DISCOVERED COUNT
   ===================================================== */

if (
    isset(
        $savedItinerary[
            "_places_count"
        ]
    )
) {

    $savedPlacesCount =
        (int)$savedItinerary[
            "_places_count"
        ];

    unset(
        $savedItinerary[
            "_places_count"
        ]
    );
}


/* =====================================================
   ACCOMMODATION DETECTOR
   ===================================================== */

function isAccommodationPlace(
    $place
) {

    if (!is_array($place)) {
        return false;
    }

    $name =
        strtolower(
            trim(
                $place["name"] ?? ""
            )
        );

    $category =
        strtolower(
            trim(
                $place["category"] ?? ""
            )
        );

    $accommodationWords = [

        "accommodation",
        "hotel",
        "hostel",
        "guest house",
        "guest_house",
        "guesthouse",
        "resort",
        "motel",
        "apartment",
        "chalet",
        "camp site",
        "camp_site",
        "campsite",
        "alpine hut",
        "homestay",
        "lodging"
    ];

    foreach (
        $accommodationWords
        as $word
    ) {

        if (
            strpos(
                $category,
                $word
            ) !== false ||
            strpos(
                $name,
                $word
            ) !== false
        ) {

            return true;
        }
    }

    return false;
}


/* =====================================================
   DISPLAY CATEGORY DETECTOR
   ===================================================== */

function getDisplayCategory(
    $place
) {

    if (!is_array($place)) {
        return "Tourist Attraction";
    }

    $name =
        strtolower(
            trim(
                $place["name"] ?? ""
            )
        );

    $category =
        strtolower(
            trim(
                $place["category"] ?? ""
            )
        );


    /* Accommodation */

    if (
        isAccommodationPlace(
            $place
        )
    ) {
        return "Accommodation";
    }


    /* Waterfalls */

    if (
        strpos(
            $name,
            "waterfall"
        ) !== false ||
        strpos(
            $name,
            "waterfalls"
        ) !== false ||
        strpos(
            $name,
            "falls"
        ) !== false ||
        strpos(
            $category,
            "waterfall"
        ) !== false
    ) {

        return "Nature & Scenic";
    }


    /* Wildlife */

    if (
        strpos(
            $name,
            "wildlife"
        ) !== false ||
        strpos(
            $name,
            "sanctuary"
        ) !== false ||
        strpos(
            $name,
            "national park"
        ) !== false ||
        strpos(
            $category,
            "wildlife"
        ) !== false
    ) {

        return "Nature & Scenic";
    }


    /* Beaches */

    if (
        strpos(
            $name,
            "beach"
        ) !== false ||
        strpos(
            $name,
            "coast"
        ) !== false ||
        strpos(
            $category,
            "beach"
        ) !== false
    ) {

        return "Beaches";
    }


    /* Historical / Cultural */

    if (
        strpos(
            $name,
            "fort"
        ) !== false ||
        strpos(
            $name,
            "palace"
        ) !== false ||
        strpos(
            $name,
            "museum"
        ) !== false ||
        strpos(
            $name,
            "monument"
        ) !== false ||
        strpos(
            $name,
            "heritage"
        ) !== false ||
        strpos(
            $name,
            "archaeological"
        ) !== false ||
        strpos(
            $category,
            "historical"
        ) !== false ||
        strpos(
            $category,
            "historic"
        ) !== false ||
        strpos(
            $category,
            "cultural"
        ) !== false ||
        strpos(
            $category,
            "heritage"
        ) !== false
    ) {

        return "Historical & Cultural";
    }


    /* Religious */

    if (
        strpos(
            $name,
            "temple"
        ) !== false ||
        strpos(
            $name,
            "church"
        ) !== false ||
        strpos(
            $name,
            "mosque"
        ) !== false ||
        strpos(
            $name,
            "chapel"
        ) !== false ||
        strpos(
            $name,
            "shrine"
        ) !== false ||
        strpos(
            $name,
            "gurudwara"
        ) !== false ||
        strpos(
            $name,
            "monastery"
        ) !== false ||
        strpos(
            $category,
            "religious"
        ) !== false ||
        strpos(
            $category,
            "worship"
        ) !== false
    ) {

        return "Religious";
    }


    /* Shopping */

    if (
        strpos(
            $name,
            "market"
        ) !== false ||
        strpos(
            $name,
            "mall"
        ) !== false ||
        strpos(
            $name,
            "shopping"
        ) !== false ||
        strpos(
            $category,
            "shopping"
        ) !== false
    ) {

        return "Shopping";
    }


    /* Food */

    if (
        strpos(
            $category,
            "food"
        ) !== false ||
        strpos(
            $category,
            "restaurant"
        ) !== false ||
        strpos(
            $name,
            "restaurant"
        ) !== false ||
        strpos(
            $name,
            "cafe"
        ) !== false
    ) {

        return "Food";
    }


    /* Entertainment */

    if (
        strpos(
            $name,
            "water park"
        ) !== false ||
        strpos(
            $name,
            "waterpark"
        ) !== false ||
        strpos(
            $name,
            "amusement"
        ) !== false ||
        strpos(
            $name,
            "theme park"
        ) !== false ||
        strpos(
            $name,
            "aquarium"
        ) !== false ||
        strpos(
            $name,
            "zoo"
        ) !== false ||
        strpos(
            $name,
            "cinema"
        ) !== false ||
        strpos(
            $name,
            "theatre"
        ) !== false ||
        strpos(
            $name,
            "theater"
        ) !== false ||
        strpos(
            $category,
            "entertainment"
        ) !== false
    ) {

        return "Entertainment";
    }


    /* Nature */

    if (
        strpos(
            $name,
            "lake"
        ) !== false ||
        strpos(
            $name,
            "river"
        ) !== false ||
        strpos(
            $name,
            "hill"
        ) !== false ||
        strpos(
            $name,
            "mountain"
        ) !== false ||
        strpos(
            $name,
            "peak"
        ) !== false ||
        strpos(
            $name,
            "valley"
        ) !== false ||
        strpos(
            $name,
            "viewpoint"
        ) !== false ||
        strpos(
            $name,
            "view point"
        ) !== false ||
        strpos(
            $name,
            "forest"
        ) !== false ||
        strpos(
            $name,
            "garden"
        ) !== false ||
        strpos(
            $category,
            "nature"
        ) !== false ||
        strpos(
            $category,
            "scenic"
        ) !== false
    ) {

        return "Nature & Scenic";
    }


    /* Parks */

    if (
        strpos(
            $category,
            "park"
        ) !== false ||
        strpos(
            $name,
            "park"
        ) !== false ||
        strpos(
            $category,
            "garden"
        ) !== false
    ) {

        return "Parks";
    }


    /* Generic API category */

    if (
        $category !== ""
    ) {

        return ucwords(
            str_replace(
                "_",
                " ",
                $category
            )
        );
    }


    return "Tourist Attraction";
}


/* =====================================================
   DETERMINE WHETHER GENERATION IS REQUIRED
   ===================================================== */

$needsGeneration =
    $regenerate ||
    empty($savedItinerary);


/* =====================================================
   FRESH GENERATION / REGENERATION
   ===================================================== */

if ($needsGeneration) {


    /* =================================================
       VALIDATE DESTINATION COORDINATES
       ================================================= */

    if (
        $latitude == 0 ||
        $longitude == 0
    ) {

        $placesMessage =
            "Invalid destination coordinates. Please edit the trip and select the destination again.";

        /*
         * If an old itinerary exists,
         * display it instead.
         */

        if (
            !empty($savedItinerary)
        ) {

            $generatedItinerary =
                $savedItinerary;

            $selectedAccommodation =
                $savedAccommodation;

            $itineraryPlaces =
                $savedPlaces;

            $placesDiscoveredCount =
                $savedPlacesCount;
        }

    } else {


        /* =================================================
           FETCH DYNAMIC PLACES
           ================================================= */

        $placesResult =
            getNearbyPlaces(
                $latitude,
                $longitude,
                10000,
                $destinationRaw
            );


        /* =================================================
           API SUCCESS
           ================================================= */

        if (
            isset(
                $placesResult["success"]
            ) &&
            $placesResult[
                "success"
            ] === true
        ) {

            $allPlaces =
                $placesResult[
                    "places"
                ] ?? [];


            /* ---------------------------------------------
               DISCOVERED PLACE COUNT
               --------------------------------------------- */

            $placesDiscoveredCount =
                count(
                    $allPlaces
                );


            /* ---------------------------------------------
               RECOMMEND PLACES
               --------------------------------------------- */

            if (
                !empty($allPlaces)
            ) {

                $recommendedPlaces =
                    recommendPlaces(
                        $allPlaces,
                        $trip[
                            "interests"
                        ] ?? "",
                        $number_of_days
                    );
            }


            /* ---------------------------------------------
               FIND ACCOMMODATION
               --------------------------------------------- */

            foreach (
                $allPlaces as $place
            ) {

                if (
                    isAccommodationPlace(
                        $place
                    )
                ) {

                    $selectedAccommodation =
                        $place;

                    break;
                }
            }


            /* ---------------------------------------------
               FALLBACK TO SAVED ACCOMMODATION
               --------------------------------------------- */

            if (
                $selectedAccommodation === null &&
                $savedAccommodation !== null
            ) {

                $selectedAccommodation =
                    $savedAccommodation;
            }


            /* ---------------------------------------------
               BUILD SIGHTSEEING LIST
               --------------------------------------------- */

            foreach (
                $recommendedPlaces
                as $place
            ) {

                if (
                    !isAccommodationPlace(
                        $place
                    )
                ) {

                    $itineraryPlaces[] =
                        $place;
                }
            }


            /* =================================================
               REMOVE DUPLICATES
               ================================================= */

            $uniquePlaces = [];

            $seenNames = [];

            foreach (
                $itineraryPlaces
                as $place
            ) {

                $currentName =
                    strtolower(
                        trim(
                            $place[
                                "name"
                            ] ?? ""
                        )
                    );

                if (
                    $currentName === ""
                ) {
                    continue;
                }

                if (
                    isset(
                        $seenNames[
                            $currentName
                        ]
                    )
                ) {
                    continue;
                }

                $seenNames[
                    $currentName
                ] = true;

                $uniquePlaces[] =
                    $place;
            }

            $itineraryPlaces =
                $uniquePlaces;


            /* =================================================
               GENERATE ITINERARY
               ================================================= */

            if (
                !empty(
                    $itineraryPlaces
                )
            ) {

                $accommodationLatitude =
                    null;

                $accommodationLongitude =
                    null;


                if (
                    $selectedAccommodation !== null
                ) {

                    $accommodationLatitude =
                        $selectedAccommodation[
                            "latitude"
                        ] ?? null;

                    $accommodationLongitude =
                        $selectedAccommodation[
                            "longitude"
                        ] ?? null;
                }


                $generatedItinerary =
                    generateItinerary(

                        $itineraryPlaces,

                        $number_of_days,

                        $transportRaw,

                        $latitude,

                        $longitude,

                        $accommodationLatitude,

                        $accommodationLongitude
                    );
            }


            /* =================================================
               SAVE GENERATED ITINERARY
               ================================================= */

            if (
                !empty(
                    $generatedItinerary
                )
            ) {


                /* Save accommodation */

                if (
                    $selectedAccommodation !== null
                ) {

                    $generatedItinerary[
                        "_accommodation"
                    ] =
                        $selectedAccommodation;
                }


                /* Save selected places */

                if (
                    !empty(
                        $itineraryPlaces
                    )
                ) {

                    $generatedItinerary[
                        "_recommended_places"
                    ] =
                        $itineraryPlaces;
                }


                /* Save discovered count */

                $generatedItinerary[
                    "_places_count"
                ] =
                    $placesDiscoveredCount;


                /* Convert to JSON */

                $itineraryJson =
                    json_encode(
                        $generatedItinerary,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    );


                /* Save to database */

                if (
                    $itineraryJson !== false
                ) {

                    $saveStmt =
                        $conn->prepare(
                            "UPDATE trips
                             SET generated_itinerary = ?
                             WHERE trip_id = ?
                             AND user_id = ?"
                        );


                    if (
                        $saveStmt
                    ) {

                        $saveStmt->bind_param(
                            "sii",
                            $itineraryJson,
                            $trip_id,
                            $user_id
                        );

                        $saveStmt->execute();

                        $saveStmt->close();
                    }
                }


                /* Remove metadata before display */

                unset(
                    $generatedItinerary[
                        "_accommodation"
                    ]
                );

                unset(
                    $generatedItinerary[
                        "_recommended_places"
                    ]
                );

                unset(
                    $generatedItinerary[
                        "_places_count"
                    ]
                );
            }


        } else {


            /* =================================================
               API FAILURE
               ================================================= */

            $placesMessage =
                $placesResult[
                    "message"
                ] ??
                "Unable to fetch fresh places right now.";


            /*
             * Use previous itinerary if available.
             */

            if (
                !empty(
                    $savedItinerary
                )
            ) {

                $generatedItinerary =
                    $savedItinerary;

                $selectedAccommodation =
                    $savedAccommodation;

                $itineraryPlaces =
                    $savedPlaces;

                $placesDiscoveredCount =
                    $savedPlacesCount;

                $placesMessage =
                    "Fresh place data is temporarily unavailable. Your previously saved itinerary is being displayed.";
            }
        }
    }


} else {


    /* =====================================================
       USE SAVED ITINERARY
       ===================================================== */

    $generatedItinerary =
        $savedItinerary;

    $selectedAccommodation =
        $savedAccommodation;

    $itineraryPlaces =
        $savedPlaces;

    $placesDiscoveredCount =
        $savedPlacesCount;
}


/* =====================================================
   FALLBACK - EXTRACT PLACES FROM ITINERARY
   ===================================================== */

if (
    empty($itineraryPlaces) &&
    !empty($generatedItinerary)
) {

    $seenNames = [];

    foreach (
        $generatedItinerary
        as $dayData
    ) {

        if (
            !is_array($dayData) ||
            !isset(
                $dayData["day"]
            ) ||
            !isset(
                $dayData["places"]
            ) ||
            !is_array(
                $dayData["places"]
            )
        ) {
            continue;
        }


        foreach (
            $dayData["places"]
            as $place
        ) {

            /*
             * Do not show lunch as a recommendation.
             */

            if (
                !empty(
                    $place["is_break"]
                )
            ) {
                continue;
            }


            $placeName =
                strtolower(
                    trim(
                        $place[
                            "name"
                        ] ?? ""
                    )
                );

            if (
                $placeName === ""
            ) {
                continue;
            }


            if (
                isset(
                    $seenNames[
                        $placeName
                    ]
                )
            ) {
                continue;
            }


            $seenNames[
                $placeName
            ] = true;


            $itineraryPlaces[] =
                $place;
        }
    }
}


/* =====================================================
   FALLBACK PLACE COUNT
   ===================================================== */

if (
    $placesDiscoveredCount <= 0 &&
    !empty($itineraryPlaces)
) {

    $placesDiscoveredCount =
        count(
            $itineraryPlaces
        );
}


/* =====================================================
   PAGE TITLE
   ===================================================== */

$page_title =
    "My Itinerary | WanderAI";

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
        <?php
        echo $page_title;
        ?>
    </title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body class="dashboard-body">


<!-- =================================================
     NAVIGATION
     ================================================= -->

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

            $avatarName =
                $_SESSION[
                    "username"
                ] ?? "U";

            echo strtoupper(
                substr(
                    $avatarName,
                    0,
                    1
                )
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


<main class="itinerary-main">


<!-- =================================================
     HEADER
     ================================================= -->

<section class="itinerary-header">

    <div>

        <p class="dashboard-small-title">
            YOUR AI TRAVEL PLAN
        </p>

        <h1>

            Your Trip to

            <span>
                <?php
                echo $destination;
                ?>
            </span>

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
            href="budget.php?trip_id=<?php echo $trip_id; ?>"
            class="itinerary-action-btn"
>
            💰 Trip Budget
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


<!-- =================================================
     TRIP SUMMARY
     ================================================= -->

<section class="trip-summary-grid">

    <div class="trip-summary-card">

        <div class="summary-icon">
            📍
        </div>

        <div>

            <span>
                Destination
            </span>

            <strong>
                <?php
                echo $destination;
                ?>
            </strong>

        </div>

    </div>


    <div class="trip-summary-card">

        <div class="summary-icon">
            📅
        </div>

        <div>

            <span>
                Start Date
            </span>

            <strong>
                <?php
                echo htmlspecialchars(
                    $start_date
                );
                ?>
            </strong>

        </div>

    </div>


    <div class="trip-summary-card">

        <div class="summary-icon">
            🗓️
        </div>

        <div>

            <span>
                Duration
            </span>

            <strong>
                <?php
                echo $number_of_days;
                ?>
                Days
            </strong>

        </div>

    </div>


    <div class="trip-summary-card">

        <div class="summary-icon">
            💰
        </div>

        <div>

            <span>
                Budget
            </span>

            <strong>
                ₹<?php
                echo $budget;
                ?>
            </strong>

        </div>

    </div>


    <div class="trip-summary-card">

        <div class="summary-icon">
            👥
        </div>

        <div>

            <span>
                Travelers
            </span>

            <strong>
                <?php
                echo $travelers;
                ?>
            </strong>

        </div>

    </div>

</section>


<!-- =================================================
     PREFERENCES
     ================================================= -->

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


<!-- =================================================
     AI ENGINE
     ================================================= -->

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
            and arranged them into a dynamic day-wise
            schedule using travel distance, estimated
            visit duration and opening hours.
        </p>

    </div>

</section>


<!-- =================================================
     ACCOMMODATION
     ================================================= -->

<section class="accommodation-section">

    <p class="dashboard-small-title">
        YOUR STAY
    </p>

    <h2>
        Accommodation for Your Entire Trip
    </h2>


    <?php if (
        $selectedAccommodation !== null
    ): ?>

        <?php

        $accommodationLatitude =
            $selectedAccommodation[
                "latitude"
            ] ?? "";

        $accommodationLongitude =
            $selectedAccommodation[
                "longitude"
            ] ?? "";

        $accommodationMapQuery =
            urlencode(
                $accommodationLatitude .
                "," .
                $accommodationLongitude
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
                        $selectedAccommodation[
                            "name"
                        ] ?? "Accommodation"
                    );
                    ?>

                </h2>

                <p>

                    This accommodation is selected as
                    your stay for the complete
                    <?php
                    echo $number_of_days;
                    ?>
                    -day trip.

                </p>

                <span class="place-category">
                    Accommodation
                </span>

                <br><br>

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


<!-- =================================================
     RECOMMENDED PLACES
     ================================================= -->

<section class="recommended-places-section">

    <p class="dashboard-small-title">
        AI RECOMMENDATIONS
    </p>

    <h2>
        Recommended Places to Visit
    </h2>


    <?php if (
        !empty($placesMessage)
    ): ?>

        <div class="message error">

            <?php
            echo htmlspecialchars(
                $placesMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <?php if (
        empty($itineraryPlaces)
    ): ?>

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

            🌍

            <?php
            echo $placesDiscoveredCount;
            ?>

            places discovered

            <span>•</span>

            ⭐

            <?php
            echo count(
                $itineraryPlaces
            );
            ?>

            places selected for your itinerary

        </p>


        <div class="recommended-places-grid">


            <?php foreach (
                $itineraryPlaces
                as $place
            ): ?>

                <?php

                if (
                    !is_array($place) ||
                    !empty(
                        $place["is_break"]
                    )
                ) {
                    continue;
                }

                $displayCategory =
                    getDisplayCategory(
                        $place
                    );

                ?>

                <div
                    class="recommended-place-card"
                >

                    <h3>

                        📍

                        <?php
                        echo htmlspecialchars(
                            $place[
                                "name"
                            ] ??
                            "Unnamed Place"
                        );
                        ?>

                    </h3>


                    <span class="place-category">

                        <?php
                        echo htmlspecialchars(
                            $displayCategory
                        );
                        ?>

                    </span>


                    <p>

                        ⭐ Match Score:

                        <strong>

                            <?php
                            echo (int)(
                                $place[
                                    "recommendation_score"
                                ] ?? 0
                            );
                            ?>

                        </strong>

                    </p>


                    <?php if (
                        !empty(
                            $place[
                                "opening_hours"
                            ]
                        )
                    ): ?>

                        <p>

                            🕐

                            <?php
                            echo htmlspecialchars(
                                $place[
                                    "opening_hours"
                                ]
                            );
                            ?>

                        </p>

                    <?php endif; ?>


                    <?php if (
                        !empty(
                            $place[
                                "description"
                            ]
                        )
                    ): ?>

                        <p>

                            <?php
                            echo htmlspecialchars(
                                $place[
                                    "description"
                                ]
                            );
                            ?>

                        </p>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>


<!-- =================================================
     DAY-WISE ITINERARY
     ================================================= -->

<section class="day-itinerary-section">

    <p class="dashboard-small-title">
        DAY-WISE SCHEDULE
    </p>

    <h2>
        Your Personalized Travel Schedule
    </h2>

    <p>
        Places are arranged dynamically using estimated
        distance, travel time, visit duration and opening
        hours. Your selected accommodation is used as
        the daily base whenever coordinates are available.
    </p>


    <?php if (
        empty($generatedItinerary)
    ): ?>

        <div class="ai-itinerary-card">

            <div class="ai-itinerary-icon">
                🗓️
            </div>

            <div>

                <h2>
                    Itinerary could not be generated
                </h2>

                <p>
                    No suitable recommended places are
                    currently available for this trip.
                </p>

            </div>

        </div>

    <?php else: ?>


        <?php foreach (
            $generatedItinerary
            as $dayData
        ): ?>


            <?php

            /*
             * Only actual day objects are displayed.
             */

            if (
                !is_array($dayData) ||
                !isset(
                    $dayData["day"]
                ) ||
                !isset(
                    $dayData["places"]
                ) ||
                !is_array(
                    $dayData["places"]
                )
            ) {
                continue;
            }

            ?>

            <div class="day-card">


                <div class="day-title">

                    <div class="day-number">

                        <?php
                        echo (int)(
                            $dayData[
                                "day"
                            ]
                        );
                        ?>

                    </div>


                    <div>

                        <h2>

                            Day

                            <?php
                            echo (int)(
                                $dayData[
                                    "day"
                                ]
                            );
                            ?>

                        </h2>

                        <p>
                            Personalized schedule based on
                            recommended places.
                        </p>

                    </div>

                </div>


                <?php if (
                    empty(
                        $dayData[
                            "places"
                        ]
                    )
                ): ?>

                    <div
                        class="ai-itinerary-card"
                    >

                        <div
                            class="ai-itinerary-icon"
                        >
                            🌿
                        </div>

                        <div>

                            <h3>
                                Free Day
                            </h3>

                            <p>
                                No additional places fit
                                naturally into this day's
                                available travel time.
                            </p>

                        </div>

                    </div>


                <?php else: ?>


                    <div class="timeline">


                        <?php foreach (
                            $dayData[
                                "places"
                            ] as $schedulePlace
                        ): ?>


                            <?php

                            if (
                                !is_array(
                                    $schedulePlace
                                )
                            ) {
                                continue;
                            }


                            $mapLatitude =
                                $schedulePlace[
                                    "latitude"
                                ] ?? "";

                            $mapLongitude =
                                $schedulePlace[
                                    "longitude"
                                ] ?? "";


                            $mapQuery =
                                urlencode(
                                    $mapLatitude .
                                    "," .
                                    $mapLongitude
                                );


                            $isBreak =
                                !empty(
                                    $schedulePlace[
                                        "is_break"
                                    ]
                                );


                            $scheduleCategory =
                                $isBreak
                                ? "Food"
                                : getDisplayCategory(
                                    $schedulePlace
                                );

                            ?>


                            <div
                                class="timeline-item <?php echo $isBreak ? 'timeline-break' : ''; ?>"
                            >


                                <div class="timeline-dot">
                                </div>


                                <div class="timeline-time">

                                    🕐

                                    <?php
                                    echo htmlspecialchars(
                                        $schedulePlace[
                                            "start_time"
                                        ] ?? ""
                                    );
                                    ?>

                                    -

                                    <?php
                                    echo htmlspecialchars(
                                        $schedulePlace[
                                            "end_time"
                                        ] ?? ""
                                    );
                                    ?>

                                </div>


                                <div class="timeline-place">

                                    <?php
                                    echo $isBreak
                                        ? "🍴"
                                        : "📍";
                                    ?>

                                    <?php
                                    echo htmlspecialchars(
                                        $schedulePlace[
                                            "name"
                                        ] ??
                                        "Unnamed Place"
                                    );
                                    ?>

                                </div>


                                <span
                                    class="timeline-category"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $scheduleCategory
                                    );
                                    ?>

                                </span>


                                <div
                                    class="timeline-details"
                                >

                                    <?php if (
                                        $isBreak
                                    ): ?>

                                        🍴 Lunch break

                                    <?php else: ?>

                                        ⏱️ Visit:

                                        <?php
                                        echo (int)(
                                            $schedulePlace[
                                                "visit_minutes"
                                            ] ?? 0
                                        );
                                        ?>

                                        minutes

                                        <br>

                                        🚗 Estimated travel:

                                        <?php
                                        echo (int)(
                                            $schedulePlace[
                                                "travel_minutes"
                                            ] ?? 0
                                        );
                                        ?>

                                        minutes

                                        <br>

                                        📏 Distance:

                                        <?php
                                        echo htmlspecialchars(
                                            $schedulePlace[
                                                "distance_km"
                                            ] ?? 0
                                        );
                                        ?>

                                        km


                                        <?php if (
                                            !empty(
                                                $schedulePlace[
                                                    "opening_hours"
                                                ]
                                            )
                                        ): ?>

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

                                    <?php endif; ?>

                                </div>


                                <?php if (
                                    !$isBreak &&
                                    $mapLatitude !== "" &&
                                    $mapLongitude !== ""
                                ): ?>

                                    <a
                                        class="timeline-map"
                                        href="https://www.google.com/maps/search/?api=1&query=<?php echo $mapQuery; ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >

                                        🗺️ Open in Google Maps

                                    </a>

                                <?php endif; ?>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php endif; ?>


            </div>


        <?php endforeach; ?>


    <?php endif; ?>


</section>


</main>


<!-- =================================================
     FOOTER
     ================================================= -->

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