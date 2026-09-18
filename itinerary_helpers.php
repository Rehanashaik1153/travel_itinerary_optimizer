<?php

/* =====================================================
   WANDERAI - SHARED ITINERARY HELPERS
   Used by: reorder_itinerary.php, export_ics.php,
   itinerary_print.php, itinerary.php (weather badges)
   ===================================================== */

require_once __DIR__ . "/generate_itinerary.php";


/* =====================================================
   TIME STRING <-> MINUTES
   ===================================================== */

function wanderTimeToMinutes($hhmm)
{
    if (
        !is_string($hhmm) ||
        strpos($hhmm, ":") === false
    ) {
        return 9 * 60;
    }

    [$h, $m] = array_map("intval", explode(":", $hhmm, 2));

    return max(0, ($h * 60) + $m);
}


/* =====================================================
   RECOMPUTE A DAY'S SCHEDULE AFTER REORDERING
   Keeps each place's own visit_minutes (how long the
   person will spend there) but recalculates travel time,
   distance, and start/end times based on the new order,
   using the same haversine + speed logic the generator
   itself uses.
   ===================================================== */

function wanderRecomputeDaySchedule(
    array $places,
    $baseLatitude,
    $baseLongitude,
    $transportPreference,
    $dayStartMinutes = 540 // 09:00
) {
    $currentLatitude = (float)$baseLatitude;
    $currentLongitude = (float)$baseLongitude;
    $currentTime = (int)$dayStartMinutes;

    $recomputed = [];

    foreach ($places as $place) {

        if (!is_array($place)) {
            continue;
        }

        $placeLatitude = (float)($place["latitude"] ?? 0);
        $placeLongitude = (float)($place["longitude"] ?? 0);

        $distanceKm = calculateDistance(
            $currentLatitude,
            $currentLongitude,
            $placeLatitude,
            $placeLongitude
        );

        $travelMinutes = estimateTravelMinutes(
            $distanceKm,
            $transportPreference
        );

        $visitMinutes =
            (int)($place["visit_minutes"] ?? 60);

        $startMinutes =
            $currentTime + $travelMinutes;

        $endMinutes =
            $startMinutes + $visitMinutes;

        $place["distance_km"] = round($distanceKm, 2);
        $place["travel_minutes"] = $travelMinutes;
        $place["start_time"] = formatItineraryTime($startMinutes);
        $place["end_time"] = formatItineraryTime($endMinutes);

        $recomputed[] = $place;

        $currentLatitude = $placeLatitude;
        $currentLongitude = $placeLongitude;
        $currentTime = $endMinutes;
    }

    return $recomputed;
}


/* =====================================================
   OPEN-METEO WEATHER (no API key required)
   Returns null on any failure so callers can silently
   skip the weather badge rather than break the page.
   ===================================================== */

function wanderGetDailyWeather(
    $latitude,
    $longitude,
    $startDate,
    $numberOfDays
) {
    if (
        !is_numeric($latitude) ||
        !is_numeric($longitude) ||
        ((float)$latitude === 0.0 && (float)$longitude === 0.0) ||
        empty($startDate)
    ) {
        return null;
    }

    $numberOfDays = max(1, min(16, (int)$numberOfDays));

    try {
        $endDate = date(
            "Y-m-d",
            strtotime($startDate . " +" . ($numberOfDays - 1) . " days")
        );
    } catch (Exception $e) {
        return null;
    }

    $cacheKey =
        "wander_weather_" .
        round((float)$latitude, 3) . "_" .
        round((float)$longitude, 3) . "_" .
        $startDate . "_" . $numberOfDays;

    if (
        isset($_SESSION[$cacheKey]) &&
        isset($_SESSION[$cacheKey]["fetched_at"]) &&
        (time() - $_SESSION[$cacheKey]["fetched_at"]) < 3600
    ) {
        return $_SESSION[$cacheKey]["data"];
    }

    $url =
        "https://api.open-meteo.com/v1/forecast" .
        "?latitude=" . urlencode($latitude) .
        "&longitude=" . urlencode($longitude) .
        "&daily=weathercode,temperature_2m_max,temperature_2m_min,precipitation_probability_max" .
        "&timezone=auto" .
        "&start_date=" . urlencode($startDate) .
        "&end_date=" . urlencode($endDate);

    $result = null;

    if (function_exists("curl_init")) {

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $result = json_decode($response, true);
        }

    } elseif (ini_get("allow_url_fopen")) {

        $context = stream_context_create([
            "http" => ["timeout" => 5]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response !== false) {
            $result = json_decode($response, true);
        }
    }

    if (
        !is_array($result) ||
        empty($result["daily"]["time"])
    ) {
        return null;
    }

    $days = [];

    foreach ($result["daily"]["time"] as $i => $date) {

        $code = $result["daily"]["weathercode"][$i] ?? 0;

        $days[] = [
            "date" => $date,
            "code" => (int)$code,
            "max_c" => $result["daily"]["temperature_2m_max"][$i] ?? null,
            "min_c" => $result["daily"]["temperature_2m_min"][$i] ?? null,
            "rain_chance" => $result["daily"]["precipitation_probability_max"][$i] ?? 0,
            "is_rainy" => wanderWeatherCodeIsRainy((int)$code) ||
                (($result["daily"]["precipitation_probability_max"][$i] ?? 0) >= 55),
            "icon" => wanderWeatherCodeToIcon((int)$code),
            "label" => wanderWeatherCodeToLabel((int)$code),
        ];
    }

    $_SESSION[$cacheKey] = [
        "fetched_at" => time(),
        "data" => $days,
    ];

    return $days;
}


function wanderWeatherCodeIsRainy($code)
{
    // Open-Meteo WMO codes: 51-67 drizzle/rain, 80-82 showers, 95-99 storms
    return
        ($code >= 51 && $code <= 67) ||
        ($code >= 80 && $code <= 82) ||
        ($code >= 95 && $code <= 99);
}

function wanderWeatherCodeToIcon($code)
{
    if ($code === 0) return "☀️";
    if ($code <= 3) return "⛅";
    if ($code <= 48) return "🌫️";
    if ($code <= 67) return "🌧️";
    if ($code <= 77) return "❄️";
    if ($code <= 82) return "🌦️";
    if ($code <= 86) return "🌨️";
    return "⛈️";
}

function wanderWeatherCodeToLabel($code)
{
    if ($code === 0) return "Clear";
    if ($code <= 3) return "Partly cloudy";
    if ($code <= 48) return "Foggy";
    if ($code <= 67) return "Rain";
    if ($code <= 77) return "Snow";
    if ($code <= 82) return "Showers";
    if ($code <= 86) return "Snow showers";
    return "Thunderstorm";
}


/* =====================================================
   ICS CALENDAR EXPORT
   Builds an RFC5545 calendar body with one VEVENT per
   scheduled place, across all days of the trip.
   ===================================================== */

function wanderBuildIcsCalendar(
    array $generatedItinerary,
    $startDate,
    $destination,
    $tripId
) {
    $lines = [];
    $lines[] = "BEGIN:VCALENDAR";
    $lines[] = "VERSION:2.0";
    $lines[] = "PRODID:-//WanderAI//Itinerary Export//EN";
    $lines[] = "CALSCALE:GREGORIAN";
    $lines[] = "METHOD:PUBLISH";
    $lines[] = "X-WR-CALNAME:" . wanderIcsEscape("WanderAI Trip - " . $destination);

    foreach ($generatedItinerary as $dayData) {

        if (
            !is_array($dayData) ||
            empty($dayData["places"]) ||
            !is_array($dayData["places"])
        ) {
            continue;
        }

        $dayNumber = (int)($dayData["day"] ?? 1);

        $dayDate = date(
            "Ymd",
            strtotime($startDate . " +" . ($dayNumber - 1) . " days")
        );

        foreach ($dayData["places"] as $index => $place) {

            if (!is_array($place)) {
                continue;
            }

            $startMinutes = wanderTimeToMinutes($place["start_time"] ?? "09:00");
            $endMinutes = wanderTimeToMinutes($place["end_time"] ?? "10:00");

            $startStamp = $dayDate . "T" . sprintf(
                "%02d%02d00",
                intdiv($startMinutes, 60) % 24,
                $startMinutes % 60
            );

            $endStamp = $dayDate . "T" . sprintf(
                "%02d%02d00",
                intdiv($endMinutes, 60) % 24,
                $endMinutes % 60
            );

            $uid =
                "wanderai-" . $tripId . "-" .
                $dayNumber . "-" . $index . "@wanderai.local";

            $summary =
                (!empty($place["is_break"]) ? "Lunch: " : "") .
                ($place["name"] ?? "Place");

            $descriptionParts = [];

            if (!empty($place["category"])) {
                $descriptionParts[] = "Category: " . $place["category"];
            }

            if (!empty($place["recommendation_reason"])) {
                $descriptionParts[] = $place["recommendation_reason"];
            }

            $lines[] = "BEGIN:VEVENT";
            $lines[] = "UID:" . $uid;
            $lines[] = "DTSTAMP:" . gmdate("Ymd\THis\Z");
            $lines[] = "DTSTART:" . $startStamp;
            $lines[] = "DTEND:" . $endStamp;
            $lines[] = "SUMMARY:" . wanderIcsEscape($summary);

            if (!empty($descriptionParts)) {
                $lines[] = "DESCRIPTION:" . wanderIcsEscape(implode("\\n", $descriptionParts));
            }

            if (
                !empty($place["latitude"]) &&
                !empty($place["longitude"])
            ) {
                $lines[] = "GEO:" . $place["latitude"] . ";" . $place["longitude"];
            }

            $lines[] = "END:VEVENT";
        }
    }

    $lines[] = "END:VCALENDAR";

    return implode("\r\n", $lines);
}

function wanderIcsEscape($text)
{
    $text = (string)$text;
    $text = str_replace(["\\", ";", ","], ["\\\\", "\\;", "\\,"], $text);
    $text = str_replace(["\r\n", "\n"], "\\n", $text);
    return $text;
}

?>
