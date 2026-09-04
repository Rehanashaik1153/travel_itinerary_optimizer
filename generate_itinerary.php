<?php

/* =====================================================
   WANDERAI - DYNAMIC ITINERARY GENERATOR
   ===================================================== */


/* =====================================================
   CALCULATE DISTANCE - HAVERSINE
   ===================================================== */

function calculateDistance(
    $latitude1,
    $longitude1,
    $latitude2,
    $longitude2
) {
    $earthRadius = 6371;

    $latitudeDifference = deg2rad(
        $latitude2 - $latitude1
    );

    $longitudeDifference = deg2rad(
        $longitude2 - $longitude1
    );

    $a =
        sin($latitudeDifference / 2) *
        sin($latitudeDifference / 2) +
        cos(deg2rad($latitude1)) *
        cos(deg2rad($latitude2)) *
        sin($longitudeDifference / 2) *
        sin($longitudeDifference / 2);

    $a = min(1, max(0, $a));

    $c =
        2 *
        atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

    return $earthRadius * $c;
}


/* =====================================================
   ESTIMATE TRAVEL TIME
   ===================================================== */

function estimateTravelMinutes(
    $distanceKm,
    $transport
) {
    $transport = strtolower(
        trim((string)$transport)
    );

    $speed = 35;

    if (
        strpos($transport, "walking") !== false ||
        strpos($transport, "walk") !== false
    ) {
        $speed = 5;

    } elseif (
        strpos($transport, "bike") !== false ||
        strpos($transport, "bicycle") !== false
    ) {
        $speed = 25;

    } elseif (
        strpos($transport, "public") !== false ||
        strpos($transport, "bus") !== false ||
        strpos($transport, "train") !== false ||
        strpos($transport, "metro") !== false
    ) {
        $speed = 25;

    } elseif (
        strpos($transport, "car") !== false ||
        strpos($transport, "taxi") !== false ||
        strpos($transport, "cab") !== false
    ) {
        $speed = 40;
    }

    if ($distanceKm <= 0.05) {
        return 0;
    }

    $minutes =
        ($distanceKm / $speed) * 60;

    return max(
        5,
        (int)round($minutes)
    );
}


/* =====================================================
   ESTIMATE VISIT DURATION
   ===================================================== */

function estimateVisitDuration(
    $place
) {
    $category = strtolower(
        trim($place["category"] ?? "")
    );

    $name = strtolower(
        trim($place["name"] ?? "")
    );


    /* Waterfalls */

    if (
        strpos($name, "waterfall") !== false ||
        strpos($name, "waterfalls") !== false ||
        preg_match("/\bfalls\b/i", $name)
    ) {
        return 120;
    }


    /* Wildlife */

    if (
        strpos($name, "wildlife") !== false ||
        strpos($name, "sanctuary") !== false ||
        strpos($name, "national park") !== false ||
        strpos($category, "wildlife") !== false
    ) {
        return 180;
    }


    /* Beaches */

    if (
        strpos($name, "beach") !== false ||
        strpos($category, "beach") !== false
    ) {
        return 150;
    }


    /* Museum / Gallery */

    if (
        strpos($category, "museum") !== false ||
        strpos($category, "gallery") !== false ||
        strpos($name, "museum") !== false ||
        strpos($name, "gallery") !== false
    ) {
        return 120;
    }


    /* Entertainment */

    if (
        strpos($category, "entertainment") !== false ||
        strpos($name, "water park") !== false ||
        strpos($name, "waterpark") !== false ||
        strpos($name, "theme park") !== false ||
        strpos($name, "amusement") !== false ||
        strpos($name, "aquarium") !== false ||
        strpos($name, "zoo") !== false
    ) {
        return 180;
    }


    /* Historical / Cultural */

    if (
        strpos($category, "historical") !== false ||
        strpos($category, "historic") !== false ||
        strpos($category, "cultural") !== false ||
        strpos($category, "heritage") !== false ||
        strpos($name, "fort") !== false ||
        strpos($name, "palace") !== false ||
        strpos($name, "monument") !== false ||
        strpos($name, "heritage") !== false
    ) {
        return 120;
    }


    /* Religious */

    if (
        strpos($category, "religious") !== false ||
        strpos($category, "worship") !== false ||
        strpos($name, "temple") !== false ||
        strpos($name, "church") !== false ||
        strpos($name, "mosque") !== false ||
        strpos($name, "chapel") !== false ||
        strpos($name, "shrine") !== false ||
        strpos($name, "monastery") !== false ||
        strpos($name, "gurudwara") !== false
    ) {
        return 60;
    }


    /* Nature */

    if (
        strpos($category, "nature") !== false ||
        strpos($category, "scenic") !== false ||
        strpos($name, "viewpoint") !== false ||
        strpos($name, "view point") !== false ||
        strpos($name, "lookout") !== false ||
        strpos($name, "lake") !== false ||
        strpos($name, "river") !== false ||
        strpos($name, "mountain") !== false ||
        strpos($name, "hill") !== false ||
        strpos($name, "valley") !== false ||
        strpos($name, "forest") !== false ||
        strpos($name, "garden") !== false
    ) {
        return 120;
    }


    /* Parks */

    if (
        strpos($category, "park") !== false ||
        strpos($name, "park") !== false
    ) {
        return 90;
    }


    /* General tourist attraction */

    if (
        strpos($category, "tourist") !== false ||
        strpos($category, "attraction") !== false ||
        strpos($category, "tourism") !== false
    ) {
        return 90;
    }


    return 90;
}


/* =====================================================
   FORMAT TIME
   ===================================================== */

function formatItineraryTime(
    $minutes
) {
    $minutes = max(
        0,
        (int)$minutes
    );

    $hours = floor(
        $minutes / 60
    );

    $remainingMinutes =
        $minutes % 60;

    return sprintf(
        "%02d:%02d",
        $hours % 24,
        $remainingMinutes
    );
}


/* =====================================================
   PARSE OPENING HOURS
   Supports:
   09:00-17:00
   09:00 - 17:00
   Multiple ranges
   ===================================================== */

function getOpeningTimeRanges(
    $openingHours
) {
    $openingHours = trim(
        (string)$openingHours
    );

    if ($openingHours === "") {
        return [];
    }

    preg_match_all(
        '/([01]\d|2[0-3]):([0-5]\d)\s*-\s*([01]\d|2[0-3]):([0-5]\d)/',
        $openingHours,
        $matches,
        PREG_SET_ORDER
    );

    if (empty($matches)) {
        return [];
    }

    $ranges = [];

    foreach ($matches as $match) {

        $start =
            ((int)$match[1] * 60) +
            (int)$match[2];

        $end =
            ((int)$match[3] * 60) +
            (int)$match[4];

        if ($end <= $start) {
            continue;
        }

        $ranges[] = [
            "start" => $start,
            "end" => $end
        ];
    }

    usort(
        $ranges,
        function ($a, $b) {
            return $a["start"] <=> $b["start"];
        }
    );

    return $ranges;
}


/* =====================================================
   FIND VALID START TIME
   ===================================================== */

function findValidOpeningStart(
    $startTime,
    $visitDuration,
    $openingHours
) {
    $ranges = getOpeningTimeRanges(
        $openingHours
    );

    /*
     * No opening-hours information:
     * allow the attraction to be scheduled.
     */

    if (empty($ranges)) {
        return $startTime;
    }

    foreach ($ranges as $range) {

        $candidate = max(
            $startTime,
            $range["start"]
        );

        if (
            $candidate + $visitDuration
            <=
            $range["end"]
        ) {
            return $candidate;
        }
    }

    return -1;
}


/* =====================================================
   CHECK WHETHER ACTIVITY OVERLAPS LUNCH
   ===================================================== */

function overlapsLunch(
    $startTime,
    $endTime,
    $lunchStart,
    $lunchEnd
) {
    return (
        $startTime < $lunchEnd &&
        $endTime > $lunchStart
    );
}


/* =====================================================
   CREATE LUNCH BREAK
   ===================================================== */

function createLunchBreak(
    $latitude,
    $longitude
) {
    return [

        "name" =>
            "Lunch Break",

        "category" =>
            "Food",

        "latitude" =>
            $latitude,

        "longitude" =>
            $longitude,

        "recommendation_score" =>
            0,

        "opening_hours" =>
            "",

        "distance_km" =>
            0,

        "travel_minutes" =>
            0,

        "visit_minutes" =>
            60,

        "start_time" =>
            "13:00",

        "end_time" =>
            "14:00",

        "is_break" =>
            true
    ];
}


/* =====================================================
   VALIDATE COORDINATES
   ===================================================== */

function itineraryValidCoordinates(
    $place
) {
    if (!is_array($place)) {
        return false;
    }

    if (
        !isset($place["latitude"]) ||
        !isset($place["longitude"])
    ) {
        return false;
    }

    if (
        $place["latitude"] === "" ||
        $place["longitude"] === ""
    ) {
        return false;
    }

    $latitude =
        (float)$place["latitude"];

    $longitude =
        (float)$place["longitude"];

    if (
        $latitude < -90 ||
        $latitude > 90
    ) {
        return false;
    }

    if (
        $longitude < -180 ||
        $longitude > 180
    ) {
        return false;
    }

    if (
        $latitude == 0 &&
        $longitude == 0
    ) {
        return false;
    }

    return true;
}


/* =====================================================
   CHECK IF TWO TIME PERIODS OVERLAP
   ===================================================== */

function itineraryTimeOverlap(
    $start1,
    $end1,
    $start2,
    $end2
) {
    return (
        $start1 < $end2 &&
        $end1 > $start2
    );
}


/* =====================================================
   GENERATE DAY-WISE ITINERARY
   ===================================================== */

function generateItinerary(
    $places,
    $numberOfDays,
    $transport,
    $startLatitude,
    $startLongitude,
    $accommodationLatitude = null,
    $accommodationLongitude = null
) {

    $numberOfDays = max(
        1,
        (int)$numberOfDays
    );

    if (
        empty($places) ||
        !is_array($places)
    ) {
        return [];
    }


    /* -------------------------------------------------
       CLEAN PLACES
       ------------------------------------------------- */

    $remainingPlaces = [];

    $seenNames = [];

    foreach ($places as $place) {

        if (
            !itineraryValidCoordinates(
                $place
            )
        ) {
            continue;
        }

        $name = strtolower(
            trim(
                $place["name"] ?? ""
            )
        );

        if ($name === "") {
            continue;
        }

        if (
            isset($seenNames[$name])
        ) {
            continue;
        }

        $seenNames[$name] = true;

        $remainingPlaces[] = $place;
    }


    if (
        empty($remainingPlaces)
    ) {
        return [];
    }


    /* -------------------------------------------------
       TRANSPORT
       ------------------------------------------------- */

    $transport = trim(
        (string)$transport
    );

    if ($transport === "") {
        $transport = "car";
    }


    /* -------------------------------------------------
       BASE LOCATION
       -------------------------------------------------

       Accommodation is preferred as the daily base.
       Otherwise destination coordinates are used.
       ------------------------------------------------- */

    $useAccommodation =
        $accommodationLatitude !== null &&
        $accommodationLongitude !== null &&
        is_numeric($accommodationLatitude) &&
        is_numeric($accommodationLongitude) &&
        (float)$accommodationLatitude != 0 &&
        (float)$accommodationLongitude != 0;

    if ($useAccommodation) {

        $baseLatitude =
            (float)$accommodationLatitude;

        $baseLongitude =
            (float)$accommodationLongitude;

    } else {

        $baseLatitude =
            (float)$startLatitude;

        $baseLongitude =
            (float)$startLongitude;
    }


    /* -------------------------------------------------
       DAILY LIMITS
       ------------------------------------------------- */

    $dayStartTime = 9 * 60;       // 09:00
    $lunchStart = 13 * 60;        // 13:00
    $lunchEnd = 14 * 60;          // 14:00
    $dayEndLimit = 20 * 60;       // 20:00

    $maximumPlacesPerDay = 4;


    $itinerary = [];


    /* =================================================
       GENERATE EACH DAY
       ================================================= */

    for (
        $day = 1;
        $day <= $numberOfDays;
        $day++
    ) {

        $daySchedule = [];

        $currentLatitude =
            $baseLatitude;

        $currentLongitude =
            $baseLongitude;

        $currentTime =
            $dayStartTime;

        $placesToday = 0;

        $lunchAdded = false;


        $daysRemaining =
            $numberOfDays - $day + 1;

        $remainingCount =
            count($remainingPlaces);


        /* ---------------------------------------------
           No remaining places
           --------------------------------------------- */

        if (
            $remainingCount <= 0
        ) {

            $itinerary[] = [
                "day" => $day,
                "places" => []
            ];

            continue;
        }


        /* ---------------------------------------------
           Balanced target
           --------------------------------------------- */

        $targetPlaces =
            (int)ceil(
                $remainingCount /
                $daysRemaining
            );

        $targetPlaces =
            min(
                $maximumPlacesPerDay,
                max(1, $targetPlaces)
            );


        /* =================================================
           SELECT PLACES
           ================================================= */

        while (
            !empty($remainingPlaces) &&
            $placesToday < $maximumPlacesPerDay
        ) {

            /*
             * Stop if the available time has ended.
             */

            if (
                $currentTime >=
                $dayEndLimit
            ) {
                break;
            }


            /*
             * Add lunch once we reach 13:00.
             */

            if (
                !$lunchAdded &&
                $currentTime >= $lunchStart
            ) {

                $daySchedule[] =
                    createLunchBreak(
                        $currentLatitude,
                        $currentLongitude
                    );

                $currentTime =
                    $lunchEnd;

                $lunchAdded = true;

                continue;
            }


            $bestIndex = null;

            $bestCandidate = null;

            $bestScore = -INF;


            /* =================================================
               EVALUATE EVERY REMAINING PLACE
               ================================================= */

            foreach (
                $remainingPlaces as $index => $place
            ) {

                $placeLatitude =
                    (float)$place["latitude"];

                $placeLongitude =
                    (float)$place["longitude"];


                /* Distance from current location */

                $distance =
                    calculateDistance(
                        $currentLatitude,
                        $currentLongitude,
                        $placeLatitude,
                        $placeLongitude
                    );


                /* Travel time */

                $travelMinutes =
                    estimateTravelMinutes(
                        $distance,
                        $transport
                    );


                /* Visit duration */

                $visitMinutes =
                    estimateVisitDuration(
                        $place
                    );


                /*
                 * Earliest arrival.
                 */

                $candidateStart =
                    $currentTime +
                    $travelMinutes;


                /*
                 * If arrival occurs during lunch,
                 * move start to after lunch.
                 */

                if (
                    !$lunchAdded &&
                    $candidateStart >= $lunchStart &&
                    $candidateStart < $lunchEnd
                ) {

                    $candidateStart =
                        $lunchEnd;
                }


                /*
                 * If the activity would cross lunch,
                 * do not allow it.
                 */

                if (
                    !$lunchAdded &&
                    $candidateStart < $lunchStart &&
                    $candidateStart + $visitMinutes > $lunchStart
                ) {
                    continue;
                }


                /*
                 * Respect opening hours.
                 */

                $validStart =
                    findValidOpeningStart(
                        $candidateStart,
                        $visitMinutes,
                        $place["opening_hours"] ?? ""
                    );


                if (
                    $validStart < 0
                ) {
                    continue;
                }


                /*
                 * If valid start is during lunch,
                 * move to after lunch.
                 */

                if (
                    !$lunchAdded &&
                    $validStart >= $lunchStart &&
                    $validStart < $lunchEnd
                ) {

                    $validStart =
                        $lunchEnd;
                }


                /*
                 * Do not allow activity to overlap lunch.
                 */

                $validEnd =
                    $validStart +
                    $visitMinutes;


                if (
                    !$lunchAdded &&
                    overlapsLunch(
                        $validStart,
                        $validEnd,
                        $lunchStart,
                        $lunchEnd
                    )
                ) {
                    continue;
                }


                /*
                 * Daily closing time.
                 */

                if (
                    $validEnd >
                    $dayEndLimit
                ) {
                    continue;
                }


                /* ---------------------------------------------
                   Recommendation score
                   --------------------------------------------- */

                $recommendationScore =
                    (float)(
                        $place[
                            "recommendation_score"
                        ] ?? 0
                    );


                /*
                 * Return distance to accommodation/base.
                 */

                $returnDistance =
                    calculateDistance(
                        $placeLatitude,
                        $placeLongitude,
                        $baseLatitude,
                        $baseLongitude
                    );


                /*
                 * Prefer nearby attractions.
                 */

                $distancePenalty =
                    min(
                        40,
                        $distance * 2
                    );


                /*
                 * Travel-time penalty.
                 */

                $travelPenalty =
                    min(
                        20,
                        $travelMinutes * 0.20
                    );


                /*
                 * Prefer places with opening information.
                 */

                $openingBonus =
                    !empty(
                        $place["opening_hours"]
                    )
                    ? 5
                    : 0;


                /*
                 * Duration bonus.
                 */

                $durationBonus = 0;

                if (
                    $visitMinutes >= 60 &&
                    $visitMinutes <= 180
                ) {
                    $durationBonus = 5;
                }


                /*
                 * Quality bonus.
                 */

                $qualityBonus = 0;

                if (
                    !empty(
                        $place["description"]
                    )
                ) {
                    $qualityBonus += 3;
                }

                if (
                    itineraryValidCoordinates(
                        $place
                    )
                ) {
                    $qualityBonus += 5;
                }


                /*
                 * Return-route penalty becomes stronger
                 * near the end of the day's target.
                 */

                $futurePlaces =
                    $targetPlaces -
                    $placesToday -
                    1;

                if (
                    $futurePlaces <= 0
                ) {

                    $returnPenalty =
                        $returnDistance * 4;

                } else {

                    $returnPenalty =
                        $returnDistance * 0.5;
                }


                /*
                 * Earlier available places are preferred.
                 */

                $waitingPenalty =
                    max(
                        0,
                        $validStart -
                        $candidateStart
                    ) * 0.05;


                /*
                 * Balanced distribution bonus.
                 */

                $distributionBonus = 0;

                if (
                    $placesToday <
                    $targetPlaces
                ) {
                    $distributionBonus = 10;
                }


                /* ---------------------------------------------
                   FINAL SELECTION SCORE
                   --------------------------------------------- */

                $score =
                    ($recommendationScore * 3)
                    +
                    $openingBonus
                    +
                    $durationBonus
                    +
                    $qualityBonus
                    +
                    $distributionBonus
                    -
                    $distancePenalty
                    -
                    $travelPenalty
                    -
                    $returnPenalty
                    -
                    $waitingPenalty;


                if (
                    $score >
                    $bestScore
                ) {

                    $bestScore =
                        $score;

                    $bestIndex =
                        $index;

                    $bestCandidate = [

                        "distance" =>
                            $distance,

                        "travel_minutes" =>
                            $travelMinutes,

                        "visit_minutes" =>
                            $visitMinutes,

                        "start_time" =>
                            $validStart,

                        "end_time" =>
                            $validEnd
                    ];
                }
            }


            /* ---------------------------------------------
               No place can fit
               --------------------------------------------- */

            if (
                $bestIndex === null ||
                $bestCandidate === null
            ) {
                break;
            }


            /*
             * If the selected place starts after lunch,
             * insert lunch first and recalculate.
             */

            if (
                !$lunchAdded &&
                $bestCandidate["start_time"] >=
                $lunchEnd
            ) {

                $daySchedule[] =
                    createLunchBreak(
                        $currentLatitude,
                        $currentLongitude
                    );

                $currentTime =
                    $lunchEnd;

                $lunchAdded = true;

                continue;
            }


            /* ---------------------------------------------
               Add selected place
               --------------------------------------------- */

            $place =
                $remainingPlaces[
                    $bestIndex
                ];


            $daySchedule[] = [

                "name" =>
                    $place["name"]
                    ?? "Unnamed Place",

                "category" =>
                    $place["category"]
                    ?? "Tourist Attraction",

                "latitude" =>
                    (float)$place["latitude"],

                "longitude" =>
                    (float)$place["longitude"],

                "recommendation_score" =>
                    $place[
                        "recommendation_score"
                    ] ?? 0,

                "opening_hours" =>
                    $place[
                        "opening_hours"
                    ] ?? "",

                "description" =>
                    $place[
                        "description"
                    ] ?? "",

                "distance_km" =>
                    round(
                        $bestCandidate[
                            "distance"
                        ],
                        2
                    ),

                "travel_minutes" =>
                    $bestCandidate[
                        "travel_minutes"
                    ],

                "visit_minutes" =>
                    $bestCandidate[
                        "visit_minutes"
                    ],

                "start_time" =>
                    formatItineraryTime(
                        $bestCandidate[
                            "start_time"
                        ]
                    ),

                "end_time" =>
                    formatItineraryTime(
                        $bestCandidate[
                            "end_time"
                        ]
                    ),

                "is_break" =>
                    false
            ];


            /*
             * Remove selected place.
             */

            array_splice(
                $remainingPlaces,
                $bestIndex,
                1
            );


            /*
             * Update current position.
             */

            $currentLatitude =
                (float)$place[
                    "latitude"
                ];

            $currentLongitude =
                (float)$place[
                    "longitude"
                ];


            /*
             * Update current time.
             */

            $currentTime =
                $bestCandidate[
                    "end_time"
                ];


            $placesToday++;


            /*
             * Add lunch if activity ended at/after lunch start.
             */

            if (
                !$lunchAdded &&
                $currentTime >=
                $lunchStart
            ) {

                $daySchedule[] =
                    createLunchBreak(
                        $currentLatitude,
                        $currentLongitude
                    );

                $currentTime =
                    $lunchEnd;

                $lunchAdded = true;
            }


            /*
             * Leave enough places for future days.
             */

            $placesLeft =
                count($remainingPlaces);

            $futureDays =
                $numberOfDays - $day;


            if (
                $placesToday >=
                $targetPlaces &&
                $futureDays > 0 &&
                $placesLeft >= $futureDays
            ) {
                break;
            }
        }


        /* =================================================
           ADD LUNCH TO A DAY WITH ACTIVITIES
           ================================================= */

        if (
            !$lunchAdded &&
            $placesToday > 0
        ) {

            $daySchedule[] =
                createLunchBreak(
                    $currentLatitude,
                    $currentLongitude
                );
        }


        /* =================================================
           SAVE DAY
           ================================================= */

        $itinerary[] = [

            "day" =>
                $day,

            "places" =>
                $daySchedule
        ];
    }


    return $itinerary;
}

?>