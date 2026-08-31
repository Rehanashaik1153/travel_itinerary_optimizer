<?php

/* =====================================================
   WANDERAI - DYNAMIC ITINERARY GENERATOR
   ===================================================== */


/* =====================================================
   CALCULATE APPROXIMATE DISTANCE
   ===================================================== */

function calculateDistance(
    $latitude1,
    $longitude1,
    $latitude2,
    $longitude2
) {

    $earthRadius = 6371;

    $latitudeDifference =
        deg2rad($latitude2 - $latitude1);

    $longitudeDifference =
        deg2rad($longitude2 - $longitude1);

    $a =
        sin($latitudeDifference / 2)
        *
        sin($latitudeDifference / 2)

        +

        cos(deg2rad($latitude1))
        *
        cos(deg2rad($latitude2))

        *

        sin($longitudeDifference / 2)
        *
        sin($longitudeDifference / 2);

    $c =
        2
        *
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

    $transport =
        strtolower(trim($transport));

    $speed = 30;

    if (
        strpos($transport, "walking") !== false
    ) {

        $speed = 5;

    } elseif (
        strpos($transport, "bike") !== false
    ) {

        $speed = 35;

    } elseif (
        strpos($transport, "public") !== false
    ) {

        $speed = 25;

    } elseif (
        strpos($transport, "car") !== false
    ) {

        $speed = 40;

    }


    $minutes =
        ($distanceKm / $speed) * 60;


    return max(
        10,
        (int)round($minutes)
    );

}


/* =====================================================
   ESTIMATE VISIT DURATION
   ===================================================== */

function estimateVisitDuration(
    $place
) {

    $category =
        strtolower(
            $place["category"] ?? ""
        );

    $name =
        strtolower(
            $place["name"] ?? ""
        );


    /* Museums and galleries */

    if (
        strpos($category, "museum") !== false
        ||
        strpos($category, "gallery") !== false
    ) {

        return 120;

    }


    /* Entertainment */

    if (
        strpos($category, "entertainment") !== false
        ||
        strpos($category, "zoo") !== false
        ||
        strpos($category, "theme") !== false
    ) {

        return 180;

    }


    /* Parks and gardens */

    if (
        strpos($category, "park") !== false
        ||
        strpos($name, "park") !== false
        ||
        strpos($name, "garden") !== false
    ) {

        return 120;

    }


    /* Nature */

    if (
        strpos($category, "nature") !== false
        ||
        strpos($category, "scenic") !== false
    ) {

        return 120;

    }


    /* Historical */

    if (
        strpos($category, "historic") !== false
        ||
        strpos($category, "cultural") !== false
    ) {

        return 120;

    }


    /* Food */

    if (
        strpos($category, "food") !== false
    ) {

        return 90;

    }


    /* Shopping */

    if (
        strpos($category, "shopping") !== false
    ) {

        return 120;

    }


    /* Religious */

    if (
        strpos($category, "religious") !== false
    ) {

        return 60;

    }


    /* Default */

    return 90;

}


/* =====================================================
   FORMAT TIME
   ===================================================== */

function formatItineraryTime(
    $minutes
) {

    $hours =
        floor($minutes / 60);

    $remainingMinutes =
        $minutes % 60;


    return sprintf(
        "%02d:%02d",
        $hours,
        $remainingMinutes
    );

}


/* =====================================================
   FIND NEAREST PLACE
   ===================================================== */

function findNearestPlaceIndex(
    $places,
    $currentLatitude,
    $currentLongitude
) {

    $nearestIndex = null;

    $shortestDistance =
        PHP_FLOAT_MAX;


    foreach (
        $places as $index => $place
    ) {

        $placeLatitude =
            (float)(
                $place["latitude"] ?? 0
            );

        $placeLongitude =
            (float)(
                $place["longitude"] ?? 0
            );


        $distance =
            calculateDistance(
                $currentLatitude,
                $currentLongitude,
                $placeLatitude,
                $placeLongitude
            );


        if (
            $distance < $shortestDistance
        ) {

            $shortestDistance =
                $distance;

            $nearestIndex =
                $index;

        }

    }


    return [

        "index" => $nearestIndex,

        "distance" => $shortestDistance

    ];

}


/* =====================================================
   GENERATE DAY-WISE ITINERARY
   ===================================================== */

function generateItinerary(
    $places,
    $numberOfDays,
    $transport,
    $startLatitude,
    $startLongitude
) {

    $numberOfDays =
        max(
            1,
            (int)$numberOfDays
        );


    if (
        empty($places)
        ||
        !is_array($places)
    ) {

        return [];

    }


    /*
       Keep all recommended places.

       Do NOT limit to:
       numberOfDays × 3
    */

    $remainingPlaces =
        array_values($places);


    $itinerary = [];


    /*
       Day starts at 9:00 AM.
    */

    $dayStartTime =
        9 * 60;


    /*
       Maximum end time = 8:00 PM.
    */

    $dayEndLimit =
        20 * 60;


    /* =================================================
       CREATE EACH DAY
       ================================================= */

    for (
        $day = 1;
        $day <= $numberOfDays;
        $day++
    ) {


        $daySchedule = [];


        $currentTime =
            $dayStartTime;


        /*
           Start each day from destination area.
        */

        $currentLatitude =
            (float)$startLatitude;


        $currentLongitude =
            (float)$startLongitude;


        /*
           Number of days still available,
           including today.
        */

        $daysRemaining =
            $numberOfDays - $day + 1;


        /*
           If no places remain, still create
           an empty day.
        */

        if (empty($remainingPlaces)) {

            $itinerary[] = [

                "day" => $day,

                "places" => []

            ];

            continue;

        }


        /*
           Calculate approximately how many places
           should be reserved for each remaining day.

           This prevents Day 1 from taking all places.
        */

        $targetPlacesForToday =
            (int)ceil(
                count($remainingPlaces)
                /
                $daysRemaining
            );


        $placesToday = 0;


        /* =============================================
           ADD PLACES WHILE TIME IS AVAILABLE
           ============================================= */

        while (
            !empty($remainingPlaces)
        ) {


            /*
               Find nearest available place.
            */

            $nearestResult =
                findNearestPlaceIndex(
                    $remainingPlaces,
                    $currentLatitude,
                    $currentLongitude
                );


            $nearestIndex =
                $nearestResult["index"];


            $distance =
                $nearestResult["distance"];


            if (
                $nearestIndex === null
            ) {

                break;

            }


            $place =
                $remainingPlaces[
                    $nearestIndex
                ];


            /*
               Calculate travel time.
            */

            $estimatedTravelMinutes =
                estimateTravelMinutes(
                    $distance,
                    $transport
                );


            /*
               No travel time before first place
               in the displayed schedule.
            */

            $actualTravelMinutes = 0;


            if ($placesToday > 0) {

                $actualTravelMinutes =
                    $estimatedTravelMinutes;

            }


            /*
               Estimate visit duration.
            */

            $visitDuration =
                estimateVisitDuration($place);


            /*
               Calculate schedule.
            */

            $startTime =
                $currentTime
                +
                $actualTravelMinutes;


            $endTime =
                $startTime
                +
                $visitDuration;


            /*
               Do not schedule anything after 8 PM.
            */

            if (
                $endTime > $dayEndLimit
            ) {

                /*
                   Stop this day's schedule.

                   Remaining places will be used
                   on the next day.
                */

                break;

            }


            /* =========================================
               ADD PLACE
               ========================================= */

            $daySchedule[] = [

                "name" =>
                    $place["name"]
                    ?? "Unnamed Place",

                "category" =>
                    $place["category"]
                    ?? "Tourist Attraction",

                "latitude" =>
                    $place["latitude"]
                    ?? 0,

                "longitude" =>
                    $place["longitude"]
                    ?? 0,

                "recommendation_score" =>
                    $place[
                        "recommendation_score"
                    ]
                    ?? 0,

                "opening_hours" =>
                    $place[
                        "opening_hours"
                    ]
                    ?? "",

                "distance_km" =>
                    round(
                        $distance,
                        2
                    ),

                "travel_minutes" =>
                    $actualTravelMinutes,

                "visit_minutes" =>
                    $visitDuration,

                "start_time" =>
                    formatItineraryTime(
                        $startTime
                    ),

                "end_time" =>
                    formatItineraryTime(
                        $endTime
                    )

            ];


            /*
               Remove successfully scheduled place.
            */

            array_splice(
                $remainingPlaces,
                $nearestIndex,
                1
            );


            /*
               Update current location.
            */

            $currentLatitude =
                (float)(
                    $place["latitude"] ?? 0
                );


            $currentLongitude =
                (float)(
                    $place["longitude"] ?? 0
                );


            /*
               Update current time.
            */

            $currentTime =
                $endTime;


            $placesToday++;


            /*
               ------------------------------------------------
               BALANCED DISTRIBUTION
               ------------------------------------------------

               Once today's approximate target is reached,
               check whether enough places remain for future
               days. If yes, stop this day and preserve them.

               This prevents Day 1 from using all places while
               still allowing more than 3 places per day.
            */

            $placesLeft =
                count($remainingPlaces);


            $futureDays =
                $numberOfDays - $day;


            if (
                $futureDays > 0
                &&
                $placesToday >= $targetPlacesForToday
                &&
                $placesLeft >= $futureDays
            ) {

                break;

            }

        }


        /*
           Add day to final itinerary.
        */

        $itinerary[] = [

            "day" => $day,

            "places" => $daySchedule

        ];

    }


    return $itinerary;

}

?>