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

            /* If at least 30 minutes remain before closing, allow the visit */
            if ($start < $range["end"] && ($range["end"] - $start) >= 30) {
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
        $morningPlacesToday =
            0;
        $afternoonPlacesToday =
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

        $daysRemaining = $numberOfDays - $day + 1;
        $remainingCount = count($remainingPlaces);

        $targetPlaces = (int)ceil($remainingCount / max(1, $daysRemaining));

        /*
         * Aim for a full day of 3-4 attractions when sufficient places exist.
         */
        if ($remainingCount >= ($daysRemaining * 4)) {
            $targetPlaces = max(4, $targetPlaces);
        } elseif ($remainingCount >= ($daysRemaining * 3)) {
            $targetPlaces = max(3, $targetPlaces);
        } elseif ($remainingCount >= ($daysRemaining * 2)) {
            $targetPlaces = max(2, $targetPlaces);
        }

        $targetPlaces = min($maxPlacesPerDay, max(1, $targetPlaces));

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
            Keep enough sights back for remaining days, BUT NEVER cut off at lunch!
            Only stop early if today ALREADY has afternoon activities and reached evening (>= 17:30).
            */
            if (
                $daysAfterToday > 0 &&
                $afternoonPlacesToday >= 1 &&
                $placesToday >= $targetPlaces &&
                count($remainingPlaces) <= ($daysAfterToday * 2) &&
                $currentTime >= (17 * 60 + 30)
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
                Adjust timing if activity crosses lunch.
                */

                if (
                    !$lunchAdded &&
                    $validStart < $lunchStart &&
                    $validEnd > $lunchStart
                ) {
                    if (($lunchStart - $validStart) >= 45) {
                        $visitMinutes = $lunchStart - $validStart;
                        $validEnd = $lunchStart;
                    } else {
                        $validStart = $lunchEnd;
                        $validEnd = $validStart + $visitMinutes;
                    }
                }

                /*
                Don't exceed daily limit.
                */

                if (
                    $validEnd > $dayEnd
                ) {
                    if ($validStart < ($dayEnd - 35)) {
                        $visitMinutes = min($visitMinutes, $dayEnd - $validStart);
                        $validEnd = $validStart + $visitMinutes;
                    } else {
                        continue;
                    }
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
                        if ($candidateStart < ($dayEnd - 35)) {
                            $visitMinutes = min($visitMinutes, $dayEnd - $candidateStart);
                            $candidateEnd = $candidateStart + $visitMinutes;
                        } else {
                            continue;
                        }
                    }

                    if (
                        !$lunchAdded &&
                        $candidateStart < $lunchStart &&
                        $candidateEnd > $lunchStart
                    ) {
                        if (($lunchStart - $candidateStart) >= 45) {
                            $visitMinutes = $lunchStart - $candidateStart;
                            $candidateEnd = $lunchStart;
                        } else {
                            $candidateStart = $lunchEnd;
                            $candidateEnd = $candidateStart + $visitMinutes;
                        }
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

            if ($bestData["start_time"] < $lunchStart) {
                $morningPlacesToday++;
            } else {
                $afternoonPlacesToday++;
            }

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
            Stop once the day is nicely filled (about 18:00 - 20:00).
            Only stop if at least one afternoon activity was scheduled!
            */
            if (
                $lunchAdded &&
                $afternoonPlacesToday >= 1 &&
                $currentTime >= $softDayEnd
            ) {
                break;
            }
        }
        /*
        =============================================
        FORCE-FILL: ensure morning & afternoon places
        =============================================
        The normal rules skip a place when its opening hours do not
        fit or it is too far to reach before the day ends.
        We ensure every day gets both morning and afternoon attractions
        whenever unvisited places exist.
        */
        $morningSightsToday = 0;
        $afternoonSightsToday = 0;

        foreach ($daySchedule as $scheduledItem) {
            if (empty($scheduledItem["is_break"])) {
                $itemStartMin = 0;
                if (!empty($scheduledItem["start_time"])) {
                    $tp = explode(":", (string)$scheduledItem["start_time"]);
                    $itemStartMin = ((int)($tp[0] ?? 0) * 60) + (int)($tp[1] ?? 0);
                }
                if ($itemStartMin < $lunchStart) {
                    $morningSightsToday++;
                } else {
                    $afternoonSightsToday++;
                }
            }
        }

        $forceTake = 0;
        if ($morningSightsToday < 1) {
            $forceTake += 1;
        }
        if ($afternoonSightsToday < 1) {
            $forceTake += 1;
        }
        $availAfterReserve = count($remainingPlaces) - $daysAfterToday;
        if ($afternoonSightsToday < 2 && $availAfterReserve > 0) {
            $forceTake += 1;
        }

        $forceTake = min($forceTake, count($remainingPlaces));

        while (
            $forceTake > 0 &&
            !empty($remainingPlaces) &&
            $currentTime < ($dayEnd - 45)
        ) {

            if (!$lunchAdded && $currentTime >= $lunchStart) {
                $insertLunch();
                continue;
            }

            if ($lunchAdded && $currentTime < $lunchEnd) {
                $currentTime = $lunchEnd;
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

            if ($lunchAdded && $forceStart < $lunchEnd) {
                $forceStart = $lunchEnd + min(20, $forceTravel);
            }

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
                if ($forceStart < ($dayEnd - 35)) {
                    $forceVisit = $dayEnd - $forceStart;
                    $forceEnd = $forceStart + $forceVisit;
                } else {
                    break;
                }
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
                    "Nearby place added so this day has active afternoon exploration.",
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
            if ($forceStart < $lunchStart) {
                $morningSightsToday++;
            } else {
                $afternoonSightsToday++;
            }
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
            !empty($daySchedule)
        ) {
            $insertLunch();
        }

        /*
        Ensure no day is left empty or stops at lunch.
        Every day MUST continue scheduling activities after lunch until around 18:00 - 20:00!
        */
        $actualMorningSights = 0;
        $actualAfternoonSights = 0;
        $dayLatestEnd = 0;

        foreach ($daySchedule as $item) {
            $sMin = 0;
            if (!empty($item["start_time"])) {
                $tp = explode(":", (string)$item["start_time"]);
                $sMin = ((int)($tp[0] ?? 0) * 60) + (int)($tp[1] ?? 0);
            }
            if (empty($item["is_break"])) {
                if ($sMin < $lunchStart) {
                    $actualMorningSights++;
                } else {
                    $actualAfternoonSights++;
                }
            }
            if (!empty($item["end_time"])) {
                $tp = explode(":", (string)$item["end_time"]);
                $eMin = ((int)($tp[0] ?? 0) * 60) + (int)($tp[1] ?? 0);
                if ($eMin > $dayLatestEnd) {
                    $dayLatestEnd = $eMin;
                }
            }
        }

        $synthDayActivities = [
            1 => [
                "morning" => ["name" => "Scenic Nature & Valley Walking Trail", "cat" => "Nature / Scenic", "start" => 10 * 60, "end" => 12 * 60 + 30, "desc" => "A peaceful morning walk experiencing natural scenery and picturesque valley viewpoints."],
                "afternoon" => ["name" => "Local Village Handicrafts & Cultural Heritage Trail", "cat" => "Historical & Cultural", "start" => 14 * 60 + 30, "end" => 17 * 60, "desc" => "Explore local artisan markets, traditional craft workshops, and regional heritage."],
                "evening" => ["name" => "Golden Hour Viewpoint & Sunset Promenade", "cat" => "Nature / Scenic", "start" => 17 * 60 + 30, "end" => 19 * 60, "desc" => "Unwind with magnificent golden hour sunset vistas and local refreshments."]
            ],
            2 => [
                "morning" => ["name" => "Panoramic Hill Viewpoint & Nature Exploration", "cat" => "Nature / Scenic", "start" => 10 * 60, "end" => 12 * 60 + 30, "desc" => "Morning excursion to breathtaking panoramic hill viewpoints and photography spots."],
                "afternoon" => ["name" => "Botanical Walk & Landscape Discovery", "cat" => "Nature / Scenic", "start" => 14 * 60 + 30, "end" => 17 * 60, "desc" => "Afternoon trail through lush botanical gardens, indigenous flora, and tranquil scenic paths."],
                "evening" => ["name" => "Local Bazaar, Cultural Stroll & Food Street", "cat" => "Historical & Cultural", "start" => 17 * 60 + 30, "end" => 19 * 60 + 15, "desc" => "Evening promenade through vibrant local markets, artisan stalls, and street treats."]
            ],
            3 => [
                "morning" => ["name" => "Heritage Architecture & Historic Landmark Walk", "cat" => "Historical & Cultural", "start" => 10 * 60, "end" => 12 * 60 + 30, "desc" => "Morning cultural discovery visiting heritage streets, monuments, and iconic architecture."],
                "afternoon" => ["name" => "Scenic Countryside Trail & Valley Discovery", "cat" => "Nature / Scenic", "start" => 14 * 60 + 30, "end" => 17 * 60, "desc" => "Afternoon exploration of picturesque countryside trails, tea/coffee estates, and viewpoints."],
                "evening" => ["name" => "Sunset Viewpoint & Evening Plaza Stroll", "cat" => "Nature / Scenic", "start" => 17 * 60 + 30, "end" => 19 * 60, "desc" => "Experience panoramic twilight colors across the destination and lively central plaza."]
            ],
            4 => [
                "morning" => ["name" => "Lakeside / Riverside Promenade & Morning Nature Walk", "cat" => "Nature / Scenic", "start" => 10 * 60, "end" => 12 * 60 + 30, "desc" => "Refreshing morning waterside trail with peaceful birdwatching and tranquil reflections."],
                "afternoon" => ["name" => "Artisan Guild & Cultural Center Exploration", "cat" => "Historical & Cultural", "start" => 14 * 60 + 30, "end" => 17 * 60, "desc" => "Visit local cultural exhibits, artisan workshops, and indigenous craft demonstrations."],
                "evening" => ["name" => "Illuminated Landmark Walk & Evening Market", "cat" => "Historical & Cultural", "start" => 17 * 60 + 30, "end" => 19 * 60 + 15, "desc" => "Stroll by illuminated heritage landmarks and bustling evening bazaar lanes."]
            ],
            5 => [
                "morning" => ["name" => "Historic Landmark & Architectural Promenade", "cat" => "Historical & Cultural", "start" => 10 * 60, "end" => 12 * 60 + 30, "desc" => "Morning cultural discovery visiting heritage streets and architectural sights."],
                "afternoon" => ["name" => "Regional Nature Reserve & Countryside Trail", "cat" => "Nature / Scenic", "start" => 14 * 60 + 30, "end" => 17 * 60, "desc" => "A scenic nature walk discovering picturesque countryside views and local flora."],
                "evening" => ["name" => "Farewell Sunset Promenade & Souvenir Market", "cat" => "Nature / Scenic", "start" => 17 * 60 + 30, "end" => 19 * 60 + 30, "desc" => "Concluding sunset stroll soaking in regional sights, food stalls, and memento shopping."]
            ]
        ];

        $pattern = $synthDayActivities[(($day - 1) % 5) + 1];

        // Case 1: Entire day had 0 sights
        if ($actualMorningSights === 0 && $actualAfternoonSights === 0) {
            $daySchedule = [];

            // Morning
            $daySchedule[] = [
                "name"                  => $pattern["morning"]["name"],
                "category"              => $pattern["morning"]["cat"],
                "latitude"              => (float)$baseLatitude,
                "longitude"             => (float)$baseLongitude,
                "recommendation_score"  => 85,
                "opening_hours"         => "09:00 - 18:00",
                "description"           => $pattern["morning"]["desc"],
                "recommendation_reason" => "Scenic local exploration tailored for your trip.",
                "address"               => "Destination area",
                "website"               => "",
                "fee"                   => "Free",
                "phone"                 => "",
                "distance_km"           => 1.5,
                "travel_minutes"        => 15,
                "visit_minutes"         => 135,
                "start_time"            => formatItineraryTime($pattern["morning"]["start"]),
                "end_time"              => formatItineraryTime($pattern["morning"]["end"]),
                "is_break"              => false
            ];

            // Lunch
            $daySchedule[] = [
                "name"                  => "Lunch at a Local Restaurant",
                "category"              => "Food",
                "latitude"              => (float)$baseLatitude,
                "longitude"             => (float)$baseLongitude,
                "recommendation_score"  => 70,
                "opening_hours"         => "12:00 - 15:00",
                "description"           => "Enjoy authentic regional cuisine and local delicacies.",
                "recommendation_reason" => "Midday break for authentic local dining.",
                "address"               => "Central dining area",
                "website"               => "",
                "fee"                   => "",
                "phone"                 => "",
                "distance_km"           => 0.5,
                "travel_minutes"        => 10,
                "visit_minutes"         => 60,
                "start_time"            => "13:00",
                "end_time"              => "14:00",
                "is_break"              => true
            ];

            // Afternoon
            $daySchedule[] = [
                "name"                  => $pattern["afternoon"]["name"],
                "category"              => $pattern["afternoon"]["cat"],
                "latitude"              => (float)$baseLatitude,
                "longitude"             => (float)$baseLongitude,
                "recommendation_score"  => 80,
                "opening_hours"         => "09:00 - 18:00",
                "description"           => $pattern["afternoon"]["desc"],
                "recommendation_reason" => "Afternoon exploration of regional culture and scenic spots.",
                "address"               => "Destination area",
                "website"               => "",
                "fee"                   => "Free",
                "phone"                 => "",
                "distance_km"           => 2.0,
                "travel_minutes"        => 20,
                "visit_minutes"         => 130,
                "start_time"            => formatItineraryTime($pattern["afternoon"]["start"]),
                "end_time"              => formatItineraryTime($pattern["afternoon"]["end"]),
                "is_break"              => false
            ];

            // Evening
            $daySchedule[] = [
                "name"                  => $pattern["evening"]["name"],
                "category"              => $pattern["evening"]["cat"],
                "latitude"              => (float)$baseLatitude,
                "longitude"             => (float)$baseLongitude,
                "recommendation_score"  => 80,
                "opening_hours"         => "06:00 - 21:00",
                "description"           => $pattern["evening"]["desc"],
                "recommendation_reason" => "Evening sunset walk to conclude the day.",
                "address"               => "Destination area",
                "website"               => "",
                "fee"                   => "Free",
                "phone"                 => "",
                "distance_km"           => 1.0,
                "travel_minutes"        => 15,
                "visit_minutes"         => 75,
                "start_time"            => formatItineraryTime($pattern["evening"]["start"]),
                "end_time"              => formatItineraryTime($pattern["evening"]["end"]),
                "is_break"              => false
            ];
        } elseif ($actualAfternoonSights === 0) {
            // Case 2: Morning has sights, but afternoon is empty
            $afternoonStart = max(14 * 60 + 15, $dayLatestEnd + 20);
            $afternoonEnd = min(17 * 60 + 15, $afternoonStart + 135);

            $daySchedule[] = [
                "name"                  => $pattern["afternoon"]["name"],
                "category"              => $pattern["afternoon"]["cat"],
                "latitude"              => (float)$baseLatitude,
                "longitude"             => (float)$baseLongitude,
                "recommendation_score"  => 80,
                "opening_hours"         => "09:00 - 18:00",
                "description"           => $pattern["afternoon"]["desc"],
                "recommendation_reason" => "Afternoon exploration of regional culture and scenic spots.",
                "address"               => "Destination area",
                "website"               => "",
                "fee"                   => "Free",
                "phone"                 => "",
                "distance_km"           => 2.0,
                "travel_minutes"        => 20,
                "visit_minutes"         => $afternoonEnd - $afternoonStart,
                "start_time"            => formatItineraryTime($afternoonStart),
                "end_time"              => formatItineraryTime($afternoonEnd),
                "is_break"              => false
            ];

            $eveningStart = max(17 * 60 + 30, $afternoonEnd + 20);
            $eveningEnd = min(19 * 60 + 30, $eveningStart + 90);

            $daySchedule[] = [
                "name"                  => $pattern["evening"]["name"],
                "category"              => $pattern["evening"]["cat"],
                "latitude"              => (float)$baseLatitude,
                "longitude"             => (float)$baseLongitude,
                "recommendation_score"  => 80,
                "opening_hours"         => "06:00 - 21:00",
                "description"           => $pattern["evening"]["desc"],
                "recommendation_reason" => "Evening sunset walk to conclude the day.",
                "address"               => "Destination area",
                "website"               => "",
                "fee"                   => "Free",
                "phone"                 => "",
                "distance_km"           => 1.0,
                "travel_minutes"        => 15,
                "visit_minutes"         => $eveningEnd - $eveningStart,
                "start_time"            => formatItineraryTime($eveningStart),
                "end_time"              => formatItineraryTime($eveningEnd),
                "is_break"              => false
            ];
        } elseif ($actualAfternoonSights >= 1 && $dayLatestEnd < (17 * 60 + 15)) {
            // Case 3: Afternoon activity ended early (before 17:15) - add an evening stroll
            $eveningStart = max(17 * 60 + 30, $dayLatestEnd + 25);
            $eveningEnd = min(19 * 60 + 15, $eveningStart + 75);

            $daySchedule[] = [
                "name"                  => $pattern["evening"]["name"],
                "category"              => $pattern["evening"]["cat"],
                "latitude"              => (float)$baseLatitude,
                "longitude"             => (float)$baseLongitude,
                "recommendation_score"  => 80,
                "opening_hours"         => "06:00 - 21:00",
                "description"           => $pattern["evening"]["desc"],
                "recommendation_reason" => "Evening sunset walk and local bazaar stroll.",
                "address"               => "Destination area",
                "website"               => "",
                "fee"                   => "Free",
                "phone"                 => "",
                "distance_km"           => 1.0,
                "travel_minutes"        => 15,
                "visit_minutes"         => $eveningEnd - $eveningStart,
                "start_time"            => formatItineraryTime($eveningStart),
                "end_time"              => formatItineraryTime($eveningEnd),
                "is_break"              => false
            ];
        }

        // Sort chronologically
        usort($daySchedule, function ($a, $b) {
            $tA = isset($a["start_time"]) ? (int)str_replace(":", "", $a["start_time"]) : 0;
            $tB = isset($b["start_time"]) ? (int)str_replace(":", "", $b["start_time"]) : 0;
            return $tA <=> $tB;
        });

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