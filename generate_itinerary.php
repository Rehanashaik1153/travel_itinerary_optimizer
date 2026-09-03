<?php

/* =====================================================
   WANDERAI - DYNAMIC AI ITINERARY GENERATOR
   ===================================================== */


/* =====================================================
   DISTANCE CALCULATION
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

    $transport =
        strtolower(
            trim(
                (string)$transport
            )
        );

    $speed = 35;

    if (
        strpos($transport, "walking") !== false
    ) {

        $speed = 5;

    } elseif (
        strpos($transport, "bike") !== false ||
        strpos($transport, "bicycle") !== false
    ) {

        $speed = 30;

    } elseif (
        strpos($transport, "public") !== false ||
        strpos($transport, "bus") !== false ||
        strpos($transport, "train") !== false
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

    $category =
        strtolower(
            trim(
                $place["category"] ?? ""
            )
        );

    $name =
        strtolower(
            trim(
                $place["name"] ?? ""
            )
        );


    /* ---------------------------------------------
       Waterfalls
       --------------------------------------------- */

    if (
        strpos($name, "waterfall") !== false ||
        strpos($name, "waterfalls") !== false ||
        preg_match(
            "/\bfalls\b/i",
            $name
        )
    ) {

        return 120;
    }


    /* ---------------------------------------------
       Wildlife
       --------------------------------------------- */

    if (
        strpos($name, "wildlife") !== false ||
        strpos($name, "sanctuary") !== false ||
        strpos($name, "national park") !== false ||
        strpos($category, "wildlife") !== false
    ) {

        return 180;
    }


    /* ---------------------------------------------
       Beach
       --------------------------------------------- */

    if (
        strpos($name, "beach") !== false ||
        strpos($category, "beach") !== false
    ) {

        return 150;
    }


    /* ---------------------------------------------
       Museum / Gallery
       --------------------------------------------- */

    if (
        strpos($category, "museum") !== false ||
        strpos($category, "gallery") !== false ||
        strpos($name, "museum") !== false ||
        strpos($name, "gallery") !== false
    ) {

        return 120;
    }


    /* ---------------------------------------------
       Entertainment
       --------------------------------------------- */

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


    /* ---------------------------------------------
       Historical / Cultural
       --------------------------------------------- */

    if (
        strpos($category, "historical") !== false ||
        strpos($category, "historic") !== false ||
        strpos($category, "cultural") !== false ||
        strpos($category, "heritage") !== false ||
        strpos($name, "fort") !== false ||
        strpos($name, "palace") !== false ||
        strpos($name, "monument") !== false ||
        strpos($name, "heritage") !== false ||
        strpos($name, "museum") !== false
    ) {

        return 120;
    }


    /* ---------------------------------------------
       Religious
       --------------------------------------------- */

    if (
        strpos($category, "religious") !== false ||
        strpos($category, "worship") !== false ||
        strpos($name, "temple") !== false ||
        strpos($name, "church") !== false ||
        strpos($name, "mosque") !== false ||
        strpos($name, "chapel") !== false ||
        strpos($name, "shrine") !== false ||
        strpos($name, "monastery") !== false
    ) {

        return 60;
    }


    /* ---------------------------------------------
       Nature / Scenic
       --------------------------------------------- */

    if (
        strpos($category, "nature") !== false ||
        strpos($category, "scenic") !== false ||
        strpos($name, "viewpoint") !== false ||
        strpos($name, "view point") !== false ||
        strpos($name, "lookout") !== false ||
        strpos($name, "lake") !== false ||
        strpos($name, "river") !== false ||
        strpos($name, "mountain") !== false ||
        strpos($name, "valley") !== false ||
        strpos($name, "forest") !== false ||
        strpos($name, "garden") !== false
    ) {

        return 120;
    }


    /* ---------------------------------------------
       Parks
       --------------------------------------------- */

    if (
        strpos($category, "park") !== false ||
        strpos($name, "park") !== false
    ) {

        return 90;
    }


    /* ---------------------------------------------
       General tourist attraction
       --------------------------------------------- */

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

    $minutes =
        max(
            0,
            (int)$minutes
        );

    $hours =
        floor(
            $minutes / 60
        );

    $remainingMinutes =
        $minutes % 60;

    $hours =
        $hours % 24;

    return sprintf(
        "%02d:%02d",
        $hours,
        $remainingMinutes
    );
}


/* =====================================================
   PARSE OPENING HOURS
   ===================================================== */

function getOpeningTimeRange(
    $openingHours
) {

    $openingHours =
        trim(
            (string)$openingHours
        );

    if (
        $openingHours === ""
    ) {

        return null;
    }


    preg_match_all(
        '/([01]\d|2[0-3]):([0-5]\d)\s*-\s*([01]\d|2[0-3]):([0-5]\d)/',
        $openingHours,
        $matches,
        PREG_SET_ORDER
    );


    if (
        empty($matches)
    ) {

        return null;
    }


    $ranges = [];


    foreach (
        $matches as $match
    ) {

        $startHour =
            (int)$match[1];

        $startMinute =
            (int)$match[2];

        $endHour =
            (int)$match[3];

        $endMinute =
            (int)$match[4];


        $start =
            ($startHour * 60)
            +
            $startMinute;

        $end =
            ($endHour * 60)
            +
            $endMinute;


        if (
            $end <= $start
        ) {

            continue;
        }


        $ranges[] = [
            "start" => $start,
            "end" => $end
        ];
    }


    if (
        empty($ranges)
    ) {

        return null;
    }


    usort(
        $ranges,
        function ($a, $b) {

            return
                $a["start"]
                <=>
                $b["start"];
        }
    );


    return [
        "open" =>
            $ranges[0]["start"],

        "close" =>
            max(
                array_column(
                    $ranges,
                    "end"
                )
            ),

        "ranges" =>
            $ranges
    ];
}


/* =====================================================
   FIND VALID OPENING PERIOD
   ===================================================== */

function findValidOpeningStart(
    $startTime,
    $visitDuration,
    $openingHours
) {

    $range =
        getOpeningTimeRange(
            $openingHours
        );


    /*
     * Unknown opening hours.
     */

    if (
        $range === null
    ) {

        return $startTime;
    }


    foreach (
        $range["ranges"] as $timeRange
    ) {

        $candidate =
            max(
                $startTime,
                $timeRange["start"]
            );


        if (
            $candidate
            +
            $visitDuration
            <=
            $timeRange["end"]
        ) {

            return $candidate;
        }
    }


    return -1;
}


/* =====================================================
   CHECK LUNCH CONFLICT
   ===================================================== */

function itineraryHasLunchConflict(
    $startTime,
    $endTime,
    $lunchStart,
    $lunchEnd
) {

    if (
        $endTime <= $lunchStart
    ) {

        return false;
    }


    if (
        $startTime >= $lunchEnd
    ) {

        return false;
    }


    return true;
}


/* =====================================================
   CREATE LUNCH BREAK
   ===================================================== */

function createLunchBreak(
    $latitude,
    $longitude,
    $startTime,
    $endTime
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
            $endTime - $startTime,

        "start_time" =>
            formatItineraryTime(
                $startTime
            ),

        "end_time" =>
            formatItineraryTime(
                $endTime
            ),

        "is_break" =>
            true
    ];
}


/* =====================================================
   PLACE COORDINATE VALIDATION
   ===================================================== */

function itineraryValidCoordinates(
    $place
) {

    if (
        !is_array($place)
    ) {

        return false;
    }


    $latitude =
        (float)(
            $place["latitude"]
            ?? 0
        );

    $longitude =
        (float)(
            $place["longitude"]
            ?? 0
        );


    if (
        $latitude == 0 &&
        $longitude == 0
    ) {

        return false;
    }


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


    return true;
}


/* =====================================================
   PLACE QUALITY SCORE
   ===================================================== */

function itineraryPlaceQualityScore(
    $place
) {

    $score = 0;


    if (
        !empty(
            $place["recommendation_score"]
        )
    ) {

        $score +=
            (float)(
                $place[
                    "recommendation_score"
                ]
            )
            *
            0.10;
    }


    if (
        !empty(
            $place["description"]
        )
    ) {

        $score += 3;
    }


    if (
        !empty(
            $place["opening_hours"]
        )
    ) {

        $score += 3;
    }


    if (
        itineraryValidCoordinates(
            $place
        )
    ) {

        $score += 5;
    }


    return $score;
}


/* =====================================================
   FIND BEST NEXT PLACE
   ===================================================== */

function findBestNextPlace(
    $places,
    $currentLatitude,
    $currentLongitude,
    $currentTime,
    $dayEndLimit,
    $transport,
    $placesToday,
    $targetPlaces,
    $lunchStart,
    $lunchEnd
) {

    $bestIndex = null;

    $bestScore =
        -PHP_FLOAT_MAX;

    $bestDistance = 0;

    $bestTravelMinutes = 0;

    $bestStartTime = 0;

    $bestEndTime = 0;

    $bestVisitDuration = 0;


    foreach (
        $places as $index => $place
    ) {

        if (
            !itineraryValidCoordinates(
                $place
            )
        ) {

            continue;
        }


        $latitude =
            (float)$place["latitude"];

        $longitude =
            (float)$place["longitude"];


        $distance =
            calculateDistance(
                $currentLatitude,
                $currentLongitude,
                $latitude,
                $longitude
            );


        $travelMinutes =
            estimateTravelMinutes(
                $distance,
                $transport
            );


        $visitDuration =
            estimateVisitDuration(
                $place
            );


        $startTime =
            $currentTime
            +
            $travelMinutes;


        /*
         * Respect opening hours.
         */

        $openingStart =
            findValidOpeningStart(
                $startTime,
                $visitDuration,
                $place["opening_hours"] ?? ""
            );


        if (
            $openingStart < 0
        ) {

            continue;
        }


        $startTime =
            $openingStart;


        $endTime =
            $startTime
            +
            $visitDuration;


        /*
         * Do not exceed the daily travel limit.
         */

        if (
            $endTime > $dayEndLimit
        ) {

            continue;
        }


        /*
         * Do not place sightseeing across lunch.
         */

        if (
            !$placesToday == 0
        ) {

            if (
                itineraryHasLunchConflict(
                    $startTime,
                    $endTime,
                    $lunchStart,
                    $lunchEnd
                )
            ) {

                continue;
            }
        }


        /*
         * Recommendation score.
         */

        $recommendationScore =
            (float)(
                $place[
                    "recommendation_score"
                ]
                ??
                0
            );


        /*
         * Distance preference.
         *
         * Nearby attractions are preferred.
         */

        $distancePenalty =
            min(
                30,
                $distance * 1.4
            );


        /*
         * Travel penalty.
         */

        $travelPenalty =
            min(
                15,
                $travelMinutes * 0.15
            );


        /*
         * Opening hours quality bonus.
         */

        $openingBonus =
            !empty(
                $place["opening_hours"]
            )
            ? 4
            : 0;


        /*
         * Place quality.
         */

        $qualityBonus =
            itineraryPlaceQualityScore(
                $place
            );


        /*
         * Distribution bonus.
         */

        $distributionBonus = 0;

        if (
            $placesToday <
            $targetPlaces
        ) {

            $distributionBonus = 8;
        }


        /*
         * Prefer reasonable duration.
         */

        $durationBonus = 0;

        if (
            $visitDuration >= 60 &&
            $visitDuration <= 180
        ) {

            $durationBonus = 3;
        }


        /*
         * Final score.
         */

        $selectionScore =
            $recommendationScore
            +
            $qualityBonus
            +
            $openingBonus
            +
            $distributionBonus
            +
            $durationBonus
            -
            $distancePenalty
            -
            $travelPenalty;


        /*
         * Small preference for places closer to the
         * current position.
         */

        $selectionScore -=
            min(
                10,
                $distance * 0.5
            );


        if (
            $selectionScore >
            $bestScore
        ) {

            $bestScore =
                $selectionScore;

            $bestIndex =
                $index;

            $bestDistance =
                $distance;

            $bestTravelMinutes =
                $travelMinutes;

            $bestStartTime =
                $startTime;

            $bestEndTime =
                $endTime;

            $bestVisitDuration =
                $visitDuration;
        }
    }


    return [

        "index" =>
            $bestIndex,

        "distance" =>
            $bestDistance,

        "travel_minutes" =>
            $bestTravelMinutes,

        "start_time" =>
            $bestStartTime,

        "end_time" =>
            $bestEndTime,

        "visit_minutes" =>
            $bestVisitDuration
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
    $startLongitude,
    $accommodationLatitude = null,
    $accommodationLongitude = null
) {

    $numberOfDays =
        max(1, (int)$numberOfDays);

    if (empty($places) || !is_array($places)) {
        return [];
    }

    $remainingPlaces = [];

    foreach ($places as $place) {
        if (itineraryValidCoordinates($place)) {
            $remainingPlaces[] = $place;
        }
    }

    if (empty($remainingPlaces)) {
        return [];
    }

    $transport = trim((string)$transport);

    if ($transport === '') {
        $transport = 'car';
    }

    $useAccommodation =
        $accommodationLatitude !== null &&
        $accommodationLongitude !== null &&
        (float)$accommodationLatitude != 0 &&
        (float)$accommodationLongitude != 0;

    if ($useAccommodation) {
        $baseLatitude = (float)$accommodationLatitude;
        $baseLongitude = (float)$accommodationLongitude;
    } else {
        $baseLatitude = (float)$startLatitude;
        $baseLongitude = (float)$startLongitude;
    }

    $dayStartTime = 9 * 60;
    $dayEndLimit = 20 * 60;
    $lunchStart = 13 * 60;
    $lunchEnd = 14 * 60;
    $maximumPlacesPerDay = 4;

    $itinerary = [];

    /*
     * We first divide the available recommendations between the days.
     * The target is recalculated every day so a two-day trip with eight
     * recommendations naturally aims for four places on each day,
     * while smaller or longer trips remain balanced.
     */
    for ($day = 1; $day <= $numberOfDays; $day++) {

        $daySchedule = [];
        $currentLatitude = $baseLatitude;
        $currentLongitude = $baseLongitude;
        $currentTime = $dayStartTime;
        $placesToday = 0;
        $lunchAdded = false;

        $daysRemaining =
            $numberOfDays - $day + 1;

        $remainingCount = count($remainingPlaces);

        if ($remainingCount <= 0) {
            $itinerary[] = [
                'day' => $day,
                'places' => []
            ];
            continue;
        }

        $targetPlacesForToday =
            (int)ceil(
                $remainingCount / $daysRemaining
            );

        $targetPlacesForToday =
            min(
                $maximumPlacesPerDay,
                max(1, $targetPlacesForToday)
            );

        /*
         * Keep selecting the best geographically sensible attraction
         * that can actually fit into the current day's time window.
         */
        while (!empty($remainingPlaces)) {

            if (
                !$lunchAdded &&
                $currentTime >= $lunchStart &&
                $currentTime < $lunchEnd
            ) {
                $daySchedule[] = createLunchBreak(
                    $currentLatitude,
                    $currentLongitude,
                    $lunchStart,
                    $lunchEnd
                );

                $currentTime = $lunchEnd;
                $lunchAdded = true;
                continue;
            }

            $bestIndex = null;
            $bestData = null;
            $bestScore = -INF;

            foreach ($remainingPlaces as $index => $place) {

                $distance = calculateDistance(
                    $currentLatitude,
                    $currentLongitude,
                    (float)$place['latitude'],
                    (float)$place['longitude']
                );

                $travelMinutes = estimateTravelMinutes(
                    $distance,
                    $transport
                );

                $visitMinutes = estimateVisitDuration($place);

                $candidateStart =
                    $currentTime + $travelMinutes;

                /*
                 * If the trip to the next place crosses lunch, pause at
                 * lunch first and then calculate the attraction again.
                 */
                if (
                    !$lunchAdded &&
                    $candidateStart < $lunchStart &&
                    $candidateStart + $visitMinutes > $lunchStart
                ) {
                    $candidateStart = $lunchEnd;
                }

                if (
                    !$lunchAdded &&
                    $candidateStart >= $lunchStart &&
                    $candidateStart < $lunchEnd
                ) {
                    $candidateStart = $lunchEnd;
                }

                $openingHours =
                    $place['opening_hours'] ?? '';

                $validStart = findValidOpeningStart(
                    $candidateStart,
                    $visitMinutes,
                    $openingHours
                );

                if ($validStart < 0) {
                    continue;
                }

                /* If the attraction starts after lunch, lunch must exist. */
                if (
                    !$lunchAdded &&
                    $validStart >= $lunchEnd
                ) {
                    $validStart = max(
                        $validStart,
                        $lunchEnd
                    );
                }

                $validEnd =
                    $validStart + $visitMinutes;

                if ($validEnd > $dayEndLimit) {
                    continue;
                }

                if (
                    !$lunchAdded &&
                    $validStart < $lunchEnd &&
                    $validEnd > $lunchStart
                ) {
                    continue;
                }

                /*
                 * Geographic route score:
                 * - prefer short travel now
                 * - prefer highly recommended places
                 * - if this is the last attraction of the day, prefer
                 *   places nearer the accommodation/base for the return
                 *   journey.
                 */
                $recommendationScore =
                    (float)(
                        $place['recommendation_score'] ?? 0
                    );

                $returnDistance = calculateDistance(
                    (float)$place['latitude'],
                    (float)$place['longitude'],
                    $baseLatitude,
                    $baseLongitude
                );

                $futureSlots =
                    $targetPlacesForToday - $placesToday - 1;

                $routePenalty =
                    $futureSlots <= 0
                    ? ($returnDistance * 5)
                    : ($returnDistance * 0.5);

                $score =
                    ($recommendationScore * 3) -
                    ($distance * 2) -
                    $routePenalty -
                    ($validStart - $currentTime) * 0.08;

                /*
                 * Before the daily target is reached, favour places that
                 * can be visited earlier rather than leaving empty time.
                 */
                if ($placesToday < $targetPlacesForToday) {
                    $score += 15;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $index;
                    $bestData = [
                        'distance' => $distance,
                        'travel_minutes' => $travelMinutes,
                        'start_time' => $validStart,
                        'end_time' => $validEnd,
                        'visit_minutes' => $visitMinutes
                    ];
                }
            }

            if ($bestIndex === null || $bestData === null) {
                break;
            }

            /*
             * If the chosen attraction begins after the lunch break,
             * insert lunch before adding it.
             */
            if (
                !$lunchAdded &&
                $bestData['start_time'] >= $lunchEnd
            ) {
                $daySchedule[] = createLunchBreak(
                    $currentLatitude,
                    $currentLongitude,
                    $lunchStart,
                    $lunchEnd
                );

                $currentTime = $lunchEnd;
                $lunchAdded = true;

                /* Re-evaluate from the new position/time. */
                continue;
            }

            $place = $remainingPlaces[$bestIndex];

            $schedulePlace = [
                'name' => $place['name'] ?? 'Unnamed Place',
                'category' => $place['category'] ?? 'Tourist Attraction',
                'latitude' => $place['latitude'] ?? 0,
                'longitude' => $place['longitude'] ?? 0,
                'recommendation_score' =>
                    $place['recommendation_score'] ?? 0,
                'opening_hours' =>
                    $place['opening_hours'] ?? '',
                'distance_km' =>
                    round($bestData['distance'], 2),
                'travel_minutes' =>
                    $bestData['travel_minutes'],
                'visit_minutes' =>
                    $bestData['visit_minutes'],
                'start_time' =>
                    formatItineraryTime(
                        $bestData['start_time']
                    ),
                'end_time' =>
                    formatItineraryTime(
                        $bestData['end_time']
                    ),
                'is_break' => false
            ];

            $daySchedule[] = $schedulePlace;

            array_splice(
                $remainingPlaces,
                $bestIndex,
                1
            );

            $currentLatitude =
                (float)$place['latitude'];

            $currentLongitude =
                (float)$place['longitude'];

            $currentTime =
                $bestData['end_time'];

            $placesToday++;

            if (
                !$lunchAdded &&
                $currentTime >= $lunchStart &&
                $currentTime < $lunchEnd
            ) {
                $daySchedule[] = createLunchBreak(
                    $currentLatitude,
                    $currentLongitude,
                    $lunchStart,
                    $lunchEnd
                );

                $currentTime = $lunchEnd;
                $lunchAdded = true;
            }

            /*
             * Once the balanced target is reached, leave enough places
             * for the remaining days whenever possible.
             */
            $placesLeft = count($remainingPlaces);
            $futureDays = $numberOfDays - $day;

            if (
                $placesToday >= $targetPlacesForToday &&
                $futureDays > 0 &&
                $placesLeft >= $futureDays
            ) {
                break;
            }

            if ($placesToday >= $maximumPlacesPerDay) {
                break;
            }

            if ($currentTime >= $dayEndLimit) {
                break;
            }
        }

        /*
         * Add lunch to a sightseeing day even if the last attraction
         * ended before 13:00. This keeps the visible itinerary complete.
         */
        if (
            !$lunchAdded &&
            $placesToday > 0 &&
            $lunchStart < $dayEndLimit
        ) {
            $daySchedule[] = createLunchBreak(
                $currentLatitude,
                $currentLongitude,
                $lunchStart,
                $lunchEnd
            );
        }

        $itinerary[] = [
            'day' => $day,
            'places' => $daySchedule
        ];
    }

    return $itinerary;
}

?>