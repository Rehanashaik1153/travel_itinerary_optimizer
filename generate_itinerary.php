<?php

/*
=====================================================
TRIPNEST - DYNAMIC ITINERARY GENERATOR
=====================================================
*/

if (!function_exists("calculateDistance")) {

    function calculateDistance(
        $lat1,
        $lon1,
        $lat2,
        $lon2
    ) {

        $earthRadius = 6371;

        $lat1 = (float)$lat1;
        $lon1 = (float)$lon1;
        $lat2 = (float)$lat2;
        $lon2 = (float)$lon2;

        $dLat = deg2rad(
            $lat2 - $lat1
        );

        $dLon = deg2rad(
            $lon2 - $lon1
        );

        $a =
            sin($dLat / 2) *
            sin($dLat / 2)
            +
            cos(deg2rad($lat1)) *
            cos(deg2rad($lat2)) *
            sin($dLon / 2) *
            sin($dLon / 2);

        $c =
            2 *
            atan2(
                sqrt($a),
                sqrt(max(0, 1 - $a))
            );

        return $earthRadius * $c;
    }
}


/*
=====================================================
TRAVEL TIME
=====================================================
*/

if (!function_exists("estimateTravelMinutes")) {

    function estimateTravelMinutes(
        $distanceKm,
        $transport
    ) {

        $distanceKm =
            max(
                0,
                (float)$distanceKm
            );

        if ($distanceKm <= 0.05) {
            return 0;
        }

        $transport =
            mb_strtolower(
                trim(
                    (string)$transport
                )
            );

        switch ($transport) {

            case "walking":
            case "walk":
                $speed = 5;
                break;

            case "bike":
            case "bicycle":
            case "cycling":
                $speed = 25;
                break;

            case "public":
            case "public transport":
            case "bus":
            case "train":
            case "metro":
                $speed = 25;
                break;

            case "car":
            case "taxi":
            case "cab":
                $speed = 40;
                break;

            default:
                $speed = 35;
                break;
        }

        return max(
            5,
            (int)ceil(
                ($distanceKm / $speed) * 60
            )
        );
    }
}


/*
=====================================================
VISIT DURATION
=====================================================
*/

if (!function_exists("estimateVisitDuration")) {

    function estimateVisitDuration($place)
    {

        $text = mb_strtolower(
            trim(
                (
                    $place["category"] ?? ""
                )
                . " "
                .
                (
                    $place["name"] ?? ""
                )
            )
        );

        if (
            strpos($text, "waterfall") !== false
        ) {
            return 120;
        }

        if (
            strpos($text, "wildlife") !== false ||
            strpos($text, "sanctuary") !== false ||
            strpos($text, "national park") !== false
        ) {
            return 180;
        }

        if (
            strpos($text, "beach") !== false
        ) {
            return 150;
        }

        if (
            strpos($text, "museum") !== false ||
            strpos($text, "gallery") !== false
        ) {
            return 120;
        }

        if (
            strpos($text, "entertainment") !== false ||
            strpos($text, "theme park") !== false ||
            strpos($text, "amusement") !== false
        ) {
            return 180;
        }

        if (
            strpos($text, "historical") !== false ||
            strpos($text, "cultural") !== false ||
            strpos($text, "historic") !== false
        ) {
            return 120;
        }

        if (
            strpos($text, "religious") !== false ||
            strpos($text, "temple") !== false ||
            strpos($text, "church") !== false ||
            strpos($text, "mosque") !== false
        ) {
            return 60;
        }

        if (
            strpos($text, "nature") !== false ||
            strpos($text, "scenic") !== false
        ) {
            return 120;
        }

        if (
            strpos($text, "park") !== false
        ) {
            return 90;
        }

        if (
            strpos($text, "food") !== false ||
            strpos($text, "restaurant") !== false ||
            strpos($text, "cafe") !== false
        ) {
            return 60;
        }

        if (
            strpos($text, "shopping") !== false
        ) {
            return 120;
        }

        return 90;
    }
}


/*
=====================================================
TIME FORMAT
=====================================================
*/

if (!function_exists("formatItineraryTime")) {

    function formatItineraryTime($minutes)
    {

        $minutes =
            max(
                0,
                (int)$minutes
            );

        $hours =
            intdiv(
                $minutes,
                60
            );

        $mins =
            $minutes % 60;

        return sprintf(
            "%02d:%02d",
            $hours % 24,
            $mins
        );
    }
}


/*
=====================================================
OPENING HOURS PARSER
=====================================================
*/

if (!function_exists("getOpeningTimeRanges")) {

    function getOpeningTimeRanges(
        $openingHours
    ) {

        $openingHours =
            trim(
                (string)$openingHours
            );

        if ($openingHours === "") {
            return [];
        }

        /*
        Correct regex.

        Example:
        09:00-18:00
        10:00 - 20:00
        */

        preg_match_all(
            '/([01]\d|2[0-3]):([0-5]\d)\s*-\s*([01]\d|2[0-3]):([0-5]\d)/',
            $openingHours,
            $matches,
            PREG_SET_ORDER
        );

        $ranges = [];

        foreach ($matches as $match) {

            $start =
                ((int)$match[1] * 60)
                +
                (int)$match[2];

            $end =
                ((int)$match[3] * 60)
                +
                (int)$match[4];

            if ($end <= $start) {
                continue;
            }

            $ranges[] = [
                "start" => $start,
                "end" => $end
            ];
        }

        return $ranges;
    }
}


/*
=====================================================
OPENING-HOUR VALIDATION
=====================================================
*/

if (!function_exists("findValidOpeningStart")) {

    function findValidOpeningStart(
        $candidateStart,
        $visitMinutes,
        $openingHours
    ) {

        $candidateStart =
            (int)$candidateStart;

        $visitMinutes =
            max(
                1,
                (int)$visitMinutes
            );

        $openingHours =
            trim(
                (string)$openingHours
            );

        /*
        No opening-hours data:
        do not reject the place.
        */

        if ($openingHours === "") {
            return $candidateStart;
        }

        $ranges =
            getOpeningTimeRanges(
                $openingHours
            );

        /*
        Invalid/unparseable opening-hours data:
        don't let it destroy itinerary generation.
        */

        if (empty($ranges)) {
            return $candidateStart;
        }

        foreach ($ranges as $range) {

            $start =
                max(
                    $candidateStart,
                    $range["start"]
                );

            $end =
                $start + $visitMinutes;

            if (
                $end <= $range["end"]
            ) {
                return $start;
            }
        }

        return -1;
    }
}


/*
=====================================================
LUNCH OVERLAP
=====================================================
*/

if (!function_exists("overlapsLunch")) {

    function overlapsLunch(
        $start,
        $end
    ) {

        $lunchStart = 13 * 60;
        $lunchEnd = 14 * 60;

        return
            $start < $lunchEnd &&
            $end > $lunchStart;
    }
}


/*
=====================================================
VALID COORDINATES
=====================================================
*/

if (!function_exists("itineraryValidCoordinates")) {

    function itineraryValidCoordinates(
        $place
    ) {

        if (!is_array($place)) {
            return false;
        }

        /*
        Support both formats:
        latitude/longitude
        lat/lon
        */

        $latitude =
            $place["latitude"]
            ?? $place["lat"]
            ?? null;

        $longitude =
            $place["longitude"]
            ?? $place["lon"]
            ?? null;

        if (
            $latitude === null ||
            $longitude === null
        ) {
            return false;
        }

        if (
            !is_numeric($latitude) ||
            !is_numeric($longitude)
        ) {
            return false;
        }

        $latitude =
            (float)$latitude;

        $longitude =
            (float)$longitude;

        if (
            $latitude < -90 ||
            $latitude > 90 ||
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
}


/*
=====================================================
FIND LUNCH PLACE
=====================================================
*/

if (!function_exists("findLunchFoodPlace")) {

    function findLunchFoodPlace(
        &$places,
        $currentLatitude,
        $currentLongitude,
        $maxKm = 8
    ) {

        $bestIndex = null;
        $bestDistance = INF;

        foreach (
            $places as $index => $place
        ) {

            if (
                !is_array($place)
            ) {
                continue;
            }

            $category =
                mb_strtolower(
                    trim(
                        (string)(
                            $place["category"]
                            ?? ""
                        )
                    )
                );

            $name =
                mb_strtolower(
                    trim(
                        (string)(
                            $place["name"]
                            ?? ""
                        )
                    )
                );

            $isFood =
                $category === "food" ||
                strpos(
                    $category,
                    "food"
                ) !== false ||
                strpos(
                    $name,
                    "restaurant"
                ) !== false ||
                strpos(
                    $name,
                    "cafe"
                ) !== false;

            if (!$isFood) {
                continue;
            }

            if (
                !itineraryValidCoordinates(
                    $place
                )
            ) {
                continue;
            }

            $latitude =
                (float)(
                    $place["latitude"]
                    ?? $place["lat"]
                );

            $longitude =
                (float)(
                    $place["longitude"]
                    ?? $place["lon"]
                );

            $distance =
                calculateDistance(
                    $currentLatitude,
                    $currentLongitude,
                    $latitude,
                    $longitude
                );

            /*
            Lunch search limit.
            */

            if ($distance > $maxKm) {
                continue;
            }

            if (
                $distance < $bestDistance
            ) {

                $bestDistance =
                    $distance;

                $bestIndex =
                    $index;
            }
        }

        if ($bestIndex === null) {
            return null;
        }

        $foodPlace =
            $places[$bestIndex];

        /*
        Remove food place from attraction
        candidates so it isn't scheduled again.
        */

        array_splice(
            $places,
            $bestIndex,
            1
        );

        return $foodPlace;
    }
}


/*
=====================================================
CREATE LUNCH BREAK
=====================================================
*/

if (!function_exists("createLunchBreak")) {

    function createLunchBreak(
        $latitude,
        $longitude,
        $foodPlace = null,
        $nearLabel = ""
    ) {

        $nearLabel = trim((string)$nearLabel);

        /*
         * Used only when no restaurant at all could be found on the
         * map. It still tells the traveller what to do at 13:00.
         */
        $name = "Lunch at a local restaurant";

        $description =
            "Lunch break - try a well-reviewed local restaurant" .
            ($nearLabel !== "" ? " near " . $nearLabel : "") . ".";

        $category = "Food";

        $foodLatitude = (float)$latitude;
        $foodLongitude = (float)$longitude;

        $address = "";
        $website = "";
        $phone = "";
        $openingHours = "";

        if (is_array($foodPlace)) {

            $name = trim((string)($foodPlace["name"] ?? ""));

            if ($name === "") {
                $name = "Lunch at a local restaurant";
            }

            $description = trim((string)($foodPlace["description"] ?? ""));

            if ($description === "" && !empty($foodPlace["cuisine"])) {
                $description =
                    ucfirst(str_replace(";", ", ", (string)$foodPlace["cuisine"])) .
                    " cuisine";
            }

            if ($description === "") {
                $description = "Lunch stop";
            }

            $foodLatitude = (float)(
                $foodPlace["latitude"] ?? $foodPlace["lat"] ?? $latitude
            );
            $foodLongitude = (float)(
                $foodPlace["longitude"] ?? $foodPlace["lon"] ?? $longitude
            );

            $address = (string)($foodPlace["address"] ?? "");
            $website = (string)($foodPlace["website"] ?? "");
            $phone = (string)($foodPlace["phone"] ?? "");
            $openingHours = (string)($foodPlace["opening_hours"] ?? "");
        }

        return [
            "name" => $name,
            "category" => $category,
            "latitude" => $foodLatitude,
            "longitude" => $foodLongitude,
            "description" => $description,
            "address" => $address,
            "website" => $website,
            "phone" => $phone,
            "opening_hours" => $openingHours,
            "start_time" => "13:00",
            "end_time" => "14:00",
            "visit_minutes" => 60,
            "travel_minutes" => 0,
            "distance_km" => 0,
            "is_break" => true
        ];
    }
}


/*
=====================================================
PICK THE LUNCH PLACE
=====================================================

Looks for a real restaurant / cafe for the 13:00 - 14:00
slot. Sources, in order:
  1. food places kept aside just for lunch ($foodPool)
  2. food places inside the normal candidate list
Search radius widens (8 km, then 40 km). If every nearby
restaurant was already used on an earlier day, the nearest
one is reused rather than showing a nameless break.
*/

if (!function_exists("pickLunchPlace")) {

    function pickLunchPlace(
        &$remainingPlaces,
        &$foodPool,
        &$usedFood,
        $latitude,
        $longitude
    ) {

        foreach ([8, 40] as $limit) {

            $bestIndex = null;
            $bestValue = INF;

            foreach ($foodPool as $index => $food) {

                if (!itineraryValidCoordinates($food)) {
                    continue;
                }

                $distance = calculateDistance(
                    $latitude,
                    $longitude,
                    (float)($food["latitude"] ?? $food["lat"]),
                    (float)($food["longitude"] ?? $food["lon"])
                );

                if ($distance > $limit) {
                    continue;
                }

                /* Prefer places with real listing details. */
                $value = $distance;

                if (!empty($food["cuisine"]) || !empty($food["opening_hours"]) || !empty($food["website"])) {
                    $value -= 1.5;
                }

                if ($value < $bestValue) {
                    $bestValue = $value;
                    $bestIndex = $index;
                }
            }

            if ($bestIndex !== null) {

                $chosen = $foodPool[$bestIndex];

                array_splice($foodPool, $bestIndex, 1);

                $usedFood[] = $chosen;

                return $chosen;
            }

            $fromCandidates = findLunchFoodPlace(
                $remainingPlaces,
                $latitude,
                $longitude,
                $limit
            );

            if ($fromCandidates !== null) {

                $usedFood[] = $fromCandidates;

                return $fromCandidates;
            }
        }

        /* Reuse the nearest restaurant already used on another day. */
        $reuse = null;
        $reuseDistance = INF;

        foreach ($usedFood as $food) {

            if (!itineraryValidCoordinates($food)) {
                continue;
            }

            $distance = calculateDistance(
                $latitude,
                $longitude,
                (float)($food["latitude"] ?? $food["lat"]),
                (float)($food["longitude"] ?? $food["lon"])
            );

            if ($distance <= 40 && $distance < $reuseDistance) {
                $reuseDistance = $distance;
                $reuse = $food;
            }
        }

        return $reuse;
    }
}


/*
=====================================================
MAIN ITINERARY GENERATOR
=====================================================
*/

function generateItinerary(
    $places,
    $numberOfDays,
    $transport,
    $startLatitude,
    $startLongitude,
    $accommodationLatitude = null,
    $accommodationLongitude = null,
    $foodPlaces = []
) {

    $numberOfDays =
        max(
            1,
            (int)$numberOfDays
        );

    if (
        empty($places) ||
        !is_array($places)
    ) {
        return [];
    }

    /*
    ================================================
    NORMALIZE PLACES
    ================================================
    */

    $remainingPlaces = [];
    $seenNames = [];

    foreach (
        $places as $place
    ) {

        if (
            !is_array($place)
        ) {
            continue;
        }

        if (
            !itineraryValidCoordinates(
                $place
            )
        ) {
            continue;
        }

        $latitude =
            (float)(
                $place["latitude"]
                ?? $place["lat"]
            );

        $longitude =
            (float)(
                $place["longitude"]
                ?? $place["lon"]
            );

        $place["latitude"] =
            $latitude;

        $place["longitude"] =
            $longitude;

        $name =
            trim(
                (string)(
                    $place["name"]
                    ?? ""
                )
            );

        if ($name === "") {
            continue;
        }

        $nameKey =
            mb_strtolower(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $name
                )
            );

        if (
            isset($seenNames[$nameKey])
        ) {
            continue;
        }

        $seenNames[$nameKey] = true;

        $remainingPlaces[] =
            $place;
    }

    if (
        empty($remainingPlaces)
    ) {
        return [];
    }

    /*
    ================================================
    TRANSPORT
    ================================================
    */

    $transport =
        trim(
            (string)$transport
        );

    if ($transport === "") {
        $transport = "car";
    }

    /*
    ================================================
    BASE LOCATION
    ================================================
    */

    if (
        $accommodationLatitude !== null &&
        $accommodationLongitude !== null &&
        is_numeric($accommodationLatitude) &&
        is_numeric($accommodationLongitude) &&
        (float)$accommodationLatitude != 0 &&
        (float)$accommodationLongitude != 0
    ) {

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

    /*
    ================================================
    TIME SETTINGS
    ================================================
    */

    $dayStart =
        9 * 60;

    $lunchStart =
        13 * 60;

    $lunchEnd =
        14 * 60;

    $dayEnd =
        20 * 60;

    $maxPlacesPerDay =
        6;

    /*
     * Keep adding sights until the day reaches about 18:00
     * (hard stop 20:00 above).
     */
    $softDayEnd =
        18 * 60;

    $itinerary = [];

    /*
    ================================================
    LUNCH PLACES
    ================================================
    Restaurants that are not part of the sightseeing list
    are kept aside so every day can still get a real,
    named place for 13:00 - 14:00.
    */
    $foodPool = [];
    $usedFood = [];
    $lastPlaceName = "";

    $poolSeen = [];

    foreach ($remainingPlaces as $knownPlace) {
        $poolSeen[
            mb_strtolower(trim((string)($knownPlace["name"] ?? "")))
        ] = true;
    }

    if (is_array($foodPlaces)) {

        foreach ($foodPlaces as $foodCandidate) {

            if (
                !is_array($foodCandidate) ||
                !itineraryValidCoordinates($foodCandidate)
            ) {
                continue;
            }

            $foodName = mb_strtolower(
                trim((string)($foodCandidate["name"] ?? ""))
            );

            if ($foodName === "" || isset($poolSeen[$foodName])) {
                continue;
            }

            $poolSeen[$foodName] = true;

            $foodCandidate["latitude"] = (float)(
                $foodCandidate["latitude"] ?? $foodCandidate["lat"]
            );
            $foodCandidate["longitude"] = (float)(
                $foodCandidate["longitude"] ?? $foodCandidate["lon"]
            );

            $foodPool[] = $foodCandidate;
        }
    }

    /*
     * Minimum number of sights kept back for each remaining day,
     * so day 1 cannot use up everything and leave "Free Days".
     */
    $minPerDay = max(
        1,
        min(
            3,
            (int)floor(count($remainingPlaces) / max(1, $numberOfDays))
        )
    );

    $daySchedule = [];
    $currentLatitude = $baseLatitude;
    $currentLongitude = $baseLongitude;
    $currentTime = $dayStart;
    $lunchAdded = false;

    $insertLunch = function () use (
        &$daySchedule,
        &$remainingPlaces,
        &$foodPool,
        &$usedFood,
        &$currentLatitude,
        &$currentLongitude,
        &$currentTime,
        &$lunchAdded,
        &$lastPlaceName,
        $lunchEnd
    ) {

        $lunchPlace = pickLunchPlace(
            $remainingPlaces,
            $foodPool,
            $usedFood,
            $currentLatitude,
            $currentLongitude
        );

        $daySchedule[] = createLunchBreak(
            $currentLatitude,
            $currentLongitude,
            $lunchPlace,
            $lastPlaceName
        );

        if (
            $lunchPlace !== null &&
            itineraryValidCoordinates($lunchPlace)
        ) {
            $currentLatitude = (float)(
                $lunchPlace["latitude"] ?? $lunchPlace["lat"]
            );
            $currentLongitude = (float)(
                $lunchPlace["longitude"] ?? $lunchPlace["lon"]
            );
        }

        $currentTime = $lunchEnd;
        $lunchAdded = true;
    };


    /*
    ================================================
    GENERATE EACH DAY
    ================================================
    */

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
            $dayStart;

        $placesToday =
            0;
        $lunchAdded =
            false;
        $lastPlaceName = "";
        $daysAfterToday =
            $numberOfDays - $day;

        /*
        ---------------------------------------------
        Calculate a reasonable number of attractions
        for this day.
        ---------------------------------------------
        */

        $daysRemaining =
            $numberOfDays - $day + 1;

        $remainingCount =
            count($remainingPlaces);

        if ($remainingCount <= 0) {

            $itinerary[] = [
                "day" =>
                    $day,
                "places" =>
                    []
            ];

            continue;
        }

        $targetPlaces =
            (int)ceil(
                $remainingCount /
                max(1, $daysRemaining)
            );

        /*
         * When enough real places exist, aim for at least two
         * attractions per day. Never invent places just to satisfy
         * this target.
         */
        if (
            $remainingCount >=
            ($daysRemaining * 2)
        ) {
            $targetPlaces = max(
                2,
                $targetPlaces
            );
        }

        $targetPlaces =
            min(
                $maxPlacesPerDay,
                max(1, $targetPlaces)
            );

        /*
        =============================================
        DAY LOOP
        =============================================
        */

        while (
            !empty($remainingPlaces) &&
            $placesToday < $maxPlacesPerDay
        ) {

            /*
            Keep enough sights back for the remaining days.
            */
            if (
                $daysAfterToday > 0 &&
                $placesToday >= 2 &&
                count($remainingPlaces) <= $daysAfterToday * $minPerDay
            ) {
                break;
            }

            if (
                $currentTime >= $dayEnd
            ) {
                break;
            }

            /*
            -----------------------------------------
            LUNCH
            -----------------------------------------
            */

            if (
                !$lunchAdded &&
                $currentTime >= $lunchStart
            ) {

                $insertLunch();

                continue;
            }

            /*
            =========================================
            FIND BEST NORMAL CANDIDATE
            =========================================
            */

            $bestIndex =
                null;

            $bestData =
                null;

            $bestScore =
                -INF;

            foreach (
                $remainingPlaces as $index => $place
            ) {

                if (
                    !itineraryValidCoordinates(
                        $place
                    )
                ) {
                    continue;
                }

                $placeLatitude =
                    (float)$place["latitude"];

                $placeLongitude =
                    (float)$place["longitude"];

                $distance =
                    calculateDistance(
                        $currentLatitude,
                        $currentLongitude,
                        $placeLatitude,
                        $placeLongitude
                    );

                $travelMinutes =
                    estimateTravelMinutes(
                        $distance,
                        $transport
                    );

                $visitMinutes =
                    estimateVisitDuration(
                        $place
                    );

                $candidateStart =
                    $currentTime +
                    $travelMinutes;

                /*
                Move activity after lunch
                if arrival falls inside lunch.
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
                Opening-hours validation.
                */

                $validStart =
                    findValidOpeningStart(
                        $candidateStart,
                        $visitMinutes,
                        $place["opening_hours"]
                            ?? ""
                    );

                if (
                    $validStart < 0
                ) {
                    continue;
                }

                $validEnd =
                    $validStart +
                    $visitMinutes;

                /*
                Don't cross lunch.
                */

                if (
                    !$lunchAdded &&
                    $validStart < $lunchStart &&
                    $validEnd > $lunchStart
                ) {
                    continue;
                }

                /*
                Don't exceed daily limit.
                */

                if (
                    $validEnd > $dayEnd
                ) {
                    continue;
                }

                /*
                -----------------------------------------
                SCORE
                -----------------------------------------
                */

                $recommendationScore =
                    (float)(
                        $place[
                            "recommendation_score"
                        ] ?? 0
                    );

                $qualityScore =
                    0;

                if (
                    !empty(
                        $place["description"]
                    )
                ) {
                    $qualityScore += 3;
                }

                if (
                    !empty(
                        $place["website"]
                    )
                ) {
                    $qualityScore += 2;
                }

                if (
                    !empty(
                        $place["opening_hours"]
                    )
                ) {
                    $qualityScore += 2;
                }

                $distancePenalty =
                    min(
                        40,
                        $distance * 2
                    );

                $travelPenalty =
                    min(
                        20,
                        $travelMinutes * 0.2
                    );

                $returnDistance =
                    calculateDistance(
                        $placeLatitude,
                        $placeLongitude,
                        $baseLatitude,
                        $baseLongitude
                    );

                $futureDays =
                    $numberOfDays - $day;

                if (
                    $futureDays > 0
                ) {
                    $returnPenalty =
                        $returnDistance * 0.5;
                } else {
                    $returnPenalty =
                        $returnDistance * 3;
                }

                $distributionBonus =
                    $placesToday < $targetPlaces
                        ? 10
                        : 0;

                $score =
                    ($recommendationScore * 3)
                    +
                    $qualityScore
                    +
                    $distributionBonus
                    -
                    $distancePenalty
                    -
                    $travelPenalty
                    -
                    $returnPenalty;

                if (
                    $score > $bestScore
                ) {

                    $bestScore =
                        $score;

                    $bestIndex =
                        $index;

                    $bestData = [
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

            /*
            =========================================
            FALLBACK
            =========================================

            If opening-hours data prevents every
            candidate from fitting, try again without
            rejecting a place because its opening_hours
            string is unavailable/unparseable.
            =========================================
            */

            if (
                $bestIndex === null
            ) {

                $fallbackIndex =
                    null;

                $fallbackData =
                    null;

                $fallbackScore =
                    -INF;

                foreach (
                    $remainingPlaces as $index => $place
                ) {

                    if (
                        !itineraryValidCoordinates(
                            $place
                        )
                    ) {
                        continue;
                    }

                    $placeLatitude =
                        (float)$place["latitude"];

                    $placeLongitude =
                        (float)$place["longitude"];

                    $distance =
                        calculateDistance(
                            $currentLatitude,
                            $currentLongitude,
                            $placeLatitude,
                            $placeLongitude
                        );

                    $travelMinutes =
                        estimateTravelMinutes(
                            $distance,
                            $transport
                        );

                    $visitMinutes =
                        estimateVisitDuration(
                            $place
                        );

                    $candidateStart =
                        $currentTime +
                        $travelMinutes;

                    if (
                        !$lunchAdded &&
                        $candidateStart >= $lunchStart &&
                        $candidateStart < $lunchEnd
                    ) {

                        $candidateStart =
                            $lunchEnd;
                    }

                    $candidateEnd =
                        $candidateStart +
                        $visitMinutes;

                    if (
                        $candidateEnd > $dayEnd
                    ) {
                        continue;
                    }

                    if (
                        !$lunchAdded &&
                        $candidateStart < $lunchStart &&
                        $candidateEnd > $lunchStart
                    ) {
                        continue;
                    }

                    $recommendationScore =
                        (float)(
                            $place[
                                "recommendation_score"
                            ] ?? 0
                        );

                    $score =
                        ($recommendationScore * 3)
                        -
                        min(
                            40,
                            $distance * 2
                        )
                        -
                        min(
                            20,
                            $travelMinutes * 0.2
                        );

                    if (
                        $score >
                        $fallbackScore
                    ) {

                        $fallbackScore =
                            $score;

                        $fallbackIndex =
                            $index;

                        $fallbackData = [
                            "distance" =>
                                $distance,

                            "travel_minutes" =>
                                $travelMinutes,

                            "visit_minutes" =>
                                $visitMinutes,

                            "start_time" =>
                                $candidateStart,

                            "end_time" =>
                                $candidateEnd
                        ];
                    }
                }

                if (
                    $fallbackIndex !== null
                ) {

                    $bestIndex =
                        $fallbackIndex;

                    $bestData =
                        $fallbackData;

                } else {

                    /*
                    Nothing fits before 13:00 (for example the next
                    sight is a 2-3 hour visit). Previously the whole
                    day simply stopped here - one place and no lunch.
                    Take the lunch break now and keep planning.
                    */
                    if (!$lunchAdded) {
                        $insertLunch();
                        continue;
                    }

                    break;
                }
            }

            /*
            =========================================
            INSERT LUNCH BEFORE AFTERNOON PLACE
            =========================================
            */

            if (
                !$lunchAdded &&
                $bestData["start_time"] >= $lunchEnd
            ) {

                $insertLunch();

                continue;
            }

            /*
            =========================================
            ADD ACTIVITY
            =========================================
            */

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

                "recommendation_reason" =>
                    $place[
                        "recommendation_reason"
                    ] ?? "",

                "address" =>
                    $place[
                        "address"
                    ] ?? "",

                "website" =>
                    $place[
                        "website"
                    ] ?? "",

                "fee" =>
                    $place[
                        "fee"
                    ] ?? "",

                "phone" =>
                    $place[
                        "phone"
                    ] ?? "",

                "distance_km" =>
                    round(
                        $bestData[
                            "distance"
                        ],
                        2
                    ),

                "travel_minutes" =>
                    $bestData[
                        "travel_minutes"
                    ],

                "visit_minutes" =>
                    $bestData[
                        "visit_minutes"
                    ],

                "start_time" =>
                    formatItineraryTime(
                        $bestData[
                            "start_time"
                        ]
                    ),

                "end_time" =>
                    formatItineraryTime(
                        $bestData[
                            "end_time"
                        ]
                    ),

                "is_break" =>
                    false
            ];

            /*
            Remove from available places.
            */

            array_splice(
                $remainingPlaces,
                $bestIndex,
                1
            );

            /*
            Update current location.
            */

            $currentLatitude =
                (float)$place["latitude"];

            $currentLongitude =
                (float)$place["longitude"];
            $lastPlaceName =
                (string)($place["name"] ?? "");

            /*
            Update current time.
            */

            $currentTime =
                $bestData["end_time"];

            $placesToday++;

            /*
            -----------------------------------------
            LUNCH AFTER MORNING ACTIVITIES
            -----------------------------------------
            */

            if (
                !$lunchAdded &&
                $currentTime >= $lunchStart
            ) {

                $insertLunch();
            }

            /*
            -----------------------------------------
            Don't consume all places on the first day.
            -----------------------------------------
            */

            /*
            Stop once the day is nicely filled (about 18:00).
            */
            if (
                $lunchAdded &&
                $currentTime >= $softDayEnd
            ) {
                break;
            }
        }
        /*
        =============================================
        FORCE-FILL: never leave a day empty
        =============================================
        The normal rules skip a place when its opening hours do not
        fit or it is too far to reach before the day ends. If that
        leaves a day with fewer than 2 sights while unused places
        still exist, take the nearest ones anyway (interest match,
        opening hours and long travel times are ignored here).
        */
        $sightsToday = 0;

        foreach ($daySchedule as $scheduledItem) {
            if (empty($scheduledItem["is_break"])) {
                $sightsToday++;
            }
        }

        $forceTake = min(
            max(0, 2 - $sightsToday),
            max(0, count($remainingPlaces) - $daysAfterToday)
        );

        while (
            $forceTake > 0 &&
            !empty($remainingPlaces) &&
            $currentTime < ($dayEnd - 60)
        ) {

            if (!$lunchAdded && $currentTime >= $lunchStart) {
                $insertLunch();
                continue;
            }

            $forceIndex = null;
            $forceDistance = INF;

            foreach ($remainingPlaces as $index => $candidate) {

                if (!itineraryValidCoordinates($candidate)) {
                    continue;
                }

                $candidateDistance = calculateDistance(
                    $currentLatitude,
                    $currentLongitude,
                    (float)$candidate["latitude"],
                    (float)$candidate["longitude"]
                );

                if ($candidateDistance < $forceDistance) {
                    $forceDistance = $candidateDistance;
                    $forceIndex = $index;
                }
            }

            if ($forceIndex === null) {
                break;
            }

            $forcePlace = $remainingPlaces[$forceIndex];

            $forceTravel = min(
                90,
                (int)estimateTravelMinutes($forceDistance, $transport)
            );

            $forceVisit = min(
                120,
                max(45, (int)estimateVisitDuration($forcePlace))
            );

            $forceStart = $currentTime + $forceTravel;

            if (
                !$lunchAdded &&
                $forceStart + $forceVisit > $lunchStart
            ) {

                if (
                    $forceStart < $lunchStart &&
                    ($lunchStart - $forceStart) >= 45
                ) {
                    /* Shorter visit so it ends when lunch starts. */
                    $forceVisit = $lunchStart - $forceStart;
                } else {
                    $insertLunch();
                    continue;
                }
            }

            $forceEnd = $forceStart + $forceVisit;

            if ($forceEnd > $dayEnd) {
                break;
            }

            $daySchedule[] = [
                "name" => $forcePlace["name"] ?? "Unnamed Place",
                "category" => $forcePlace["category"] ?? "Tourist Attraction",
                "latitude" => (float)$forcePlace["latitude"],
                "longitude" => (float)$forcePlace["longitude"],
                "recommendation_score" => $forcePlace["recommendation_score"] ?? 0,
                "opening_hours" => $forcePlace["opening_hours"] ?? "",
                "description" => $forcePlace["description"] ?? "",
                "recommendation_reason" =>
                    $forcePlace["recommendation_reason"] ??
                    "Nearby place added so this day is not empty.",
                "address" => $forcePlace["address"] ?? "",
                "website" => $forcePlace["website"] ?? "",
                "fee" => $forcePlace["fee"] ?? "",
                "phone" => $forcePlace["phone"] ?? "",
                "distance_km" => round($forceDistance, 2),
                "travel_minutes" => $forceTravel,
                "visit_minutes" => $forceVisit,
                "start_time" => formatItineraryTime($forceStart),
                "end_time" => formatItineraryTime($forceEnd),
                "is_break" => false
            ];

            array_splice($remainingPlaces, $forceIndex, 1);

            $currentLatitude = (float)$forcePlace["latitude"];
            $currentLongitude = (float)$forcePlace["longitude"];
            $lastPlaceName = (string)($forcePlace["name"] ?? "");
            $currentTime = $forceEnd;
            $placesToday++;
            $forceTake--;

            if (!$lunchAdded && $currentTime >= $lunchStart) {
                $insertLunch();
            }
        }

        /*
        The day ran out of sights before lunch: still show lunch.
        */
        if (
            !$lunchAdded &&
            !empty($daySchedule) &&
            $currentTime <= $lunchStart
        ) {
            $insertLunch();
        }


        /*
        =============================================
        SAVE DAY
        =============================================
        */

        $itinerary[] = [
            "day" =>
                $day,

            "places" =>
                $daySchedule
        ];
    }

    /*
    ================================================
    FINAL VALIDATION
    ================================================
    */

    $hasActivity =
        false;

    foreach (
        $itinerary as $dayData
    ) {

        if (
            empty(
                $dayData["places"]
            )
        ) {
            continue;
        }

        foreach (
            $dayData["places"] as $place
        ) {

            if (
                empty(
                    $place["is_break"]
                )
            ) {

                $hasActivity =
                    true;

                break 2;
            }
        }
    }

    if (!$hasActivity) {
        return [];
    }

    return $itinerary;
}

?>