<?php

/*
=========================================================
TRIPNEST - DESTINATION GEOCODING
Uses OpenStreetMap Nominatim
Supports cities, towns, villages, states and countries
=========================================================
*/

/*
=========================================================
FALLBACK GEOCODERS (worldwide)
Used when Nominatim is slow, rate-limited or returns nothing.
Results are converted to the same shape Nominatim returns so
the rest of the app works unchanged.
=========================================================
*/
if (!function_exists("tripnestGeocodeHttpJson")) {

    function tripnestGeocodeHttpJson($url)
    {
        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" =>
                    "User-Agent: TripNest-Travel-Itinerary-Optimizer/1.0 (Educational Project)\r\n" .
                    "Accept: application/json\r\n",
                "timeout" => 6,
                "ignore_errors" => true
            ]
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false || $body === "") {
            return null;
        }

        $data = json_decode($body, true);

        return is_array($data) ? $data : null;
    }
}

if (!function_exists("tripnestGeocodeFallback")) {

    function tripnestGeocodeFallback($destination)
    {
        $results = [];

        /* 1) Open-Meteo geocoding: fast, free, global. */
        $data = tripnestGeocodeHttpJson(
            "https://geocoding-api.open-meteo.com/v1/search?" .
            http_build_query([
                "name" => $destination,
                "count" => 10,
                "language" => "en",
                "format" => "json"
            ])
        );

        if (!empty($data["results"]) && is_array($data["results"])) {

            foreach ($data["results"] as $r) {

                if (!isset($r["latitude"], $r["longitude"])) {
                    continue;
                }

                $parts = [];
                foreach (["name", "admin1", "country"] as $k) {
                    $v = trim((string)($r[$k] ?? ""));
                    if ($v !== "" && !in_array($v, $parts, true)) {
                        $parts[] = $v;
                    }
                }

                $display = implode(", ", $parts);

                $results[] = [
                    "lat" => (string)$r["latitude"],
                    "lon" => (string)$r["longitude"],
                    "display_name" => $display,
                    "tripnest_original_display_name" => $display,
                    "tripnest_query" => $destination,
                    "importance" => min(
                        1,
                        log10(max(10, (float)($r["population"] ?? 0))) / 8
                    ),
                    "address" => [
                        "city" => $r["name"] ?? "",
                        "state" => $r["admin1"] ?? "",
                        "country" => $r["country"] ?? ""
                    ]
                ];
            }
        }

        if (!empty($results)) {
            return $results;
        }

        /* 2) Photon (OpenStreetMap-based): handles landmarks and villages. */
        $data = tripnestGeocodeHttpJson(
            "https://photon.komoot.io/api/?" .
            http_build_query([
                "q" => $destination,
                "limit" => 10,
                "lang" => "en"
            ])
        );

        if (!empty($data["features"]) && is_array($data["features"])) {

            foreach ($data["features"] as $f) {

                $coords = $f["geometry"]["coordinates"] ?? null;
                $props = $f["properties"] ?? [];

                if (!is_array($coords) || count($coords) < 2) {
                    continue;
                }

                $parts = [];
                foreach (["name", "city", "state", "country"] as $k) {
                    $v = trim((string)($props[$k] ?? ""));
                    if ($v !== "" && !in_array($v, $parts, true)) {
                        $parts[] = $v;
                    }
                }

                $display = implode(", ", $parts);

                if ($display === "") {
                    continue;
                }

                $results[] = [
                    "lat" => (string)$coords[1],
                    "lon" => (string)$coords[0],
                    "display_name" => $display,
                    "tripnest_original_display_name" => $display,
                    "tripnest_query" => $destination,
                    "importance" => 0.3,
                    "address" => [
                        "city" => $props["city"] ?? ($props["name"] ?? ""),
                        "state" => $props["state"] ?? "",
                        "country" => $props["country"] ?? ""
                    ]
                ];
            }
        }

        return $results;
    }
}

function geocodeDestination($destination)
{
    $destination = trim($destination);

    if ($destination === "") {
        return [
            "success" => false,
            "message" => "Please enter a destination."
        ];
    }

    /*
    -----------------------------------------------------
    Different search variations improve results for places
    whose common name differs from the official name.
    -----------------------------------------------------
    */

    $queries = [];

    $queries[] = $destination;

    /*
    Puducherry is commonly searched as Pondicherry.
    */

    $lowerDestination = mb_strtolower($destination);

    if ($lowerDestination === "puducherry") {
        $queries[] = "Puducherry, India";
        $queries[] = "Pondicherry, India";
    }

    if ($lowerDestination === "pondicherry") {
        $queries[] = "Pondicherry, India";
        $queries[] = "Puducherry, India";
    }

    /*
    -----------------------------------------------------
    Remove duplicate queries
    -----------------------------------------------------
    */

    $queries = array_values(array_unique($queries));

    $allResults = [];

    /*
    -----------------------------------------------------
    Try each query
    -----------------------------------------------------
    */

    foreach ($queries as $query) {

        $url = "https://nominatim.openstreetmap.org/search?" .
            http_build_query([
                "q" => $query,
                "format" => "jsonv2",
                "limit" => 10,
                "addressdetails" => 1,
                "accept-language" => "en"
            ]);

        $context = stream_context_create([
            "http" => [
                "method" => "GET",

                "header" =>
                    "User-Agent: TripNest-Travel-Itinerary-Optimizer/1.0 (Educational Project)\r\n" .
                    "Accept: application/json\r\n" .
                    "Accept-Language: en\r\n",

                "timeout" => 8,

                "ignore_errors" => true
            ]
        ]);

        $response = @file_get_contents(
            $url,
            false,
            $context
        );

        if ($response === false) {
            continue;
        }

        $data = json_decode(
            $response,
            true
        );

        if (!is_array($data)) {
            continue;
        }

        foreach ($data as $place) {

            if (
                !isset($place["lat"]) ||
                !isset($place["lon"])
            ) {
                continue;
            }

            $lat = (float)$place["lat"];
            $lon = (float)$place["lon"];

            if (
                $lat < -90 ||
                $lat > 90 ||
                $lon < -180 ||
                $lon > 180
            ) {
                continue;
            }

            /*
            -------------------------------------------------
            Prefer English / readable display names
            -------------------------------------------------
            */

            $displayName = "";

            if (
                isset($place["display_name"]) &&
                trim($place["display_name"]) !== ""
            ) {
                $displayName = trim(
                    $place["display_name"]
                );
            }

            /*
            -------------------------------------------------
            If address information exists, construct a
            cleaner destination name.
            -------------------------------------------------
            */

            $address = $place["address"] ?? [];

            $city = "";

            if (
                isset($address["city"]) &&
                trim($address["city"]) !== ""
            ) {
                $city = trim($address["city"]);
            } elseif (
                isset($address["town"]) &&
                trim($address["town"]) !== ""
            ) {
                $city = trim($address["town"]);
            } elseif (
                isset($address["village"]) &&
                trim($address["village"]) !== ""
            ) {
                $city = trim($address["village"]);
            } elseif (
                isset($address["municipality"]) &&
                trim($address["municipality"]) !== ""
            ) {
                $city = trim($address["municipality"]);
            }

            $state = "";

            if (
                isset($address["state"]) &&
                trim($address["state"]) !== ""
            ) {
                $state = trim($address["state"]);
            }

            $country = "";

            if (
                isset($address["country"]) &&
                trim($address["country"]) !== ""
            ) {
                $country = trim($address["country"]);
            }

            /*
            -------------------------------------------------
            Create a clean display name
            -------------------------------------------------
            */

            $cleanDisplay = "";

            if ($city !== "") {

                $cleanDisplay = $city;

                if (
                    $state !== "" &&
                    mb_strtolower($state) !== mb_strtolower($city)
                ) {
                    $cleanDisplay .= ", " . $state;
                }

                if (
                    $country !== "" &&
                    mb_strtolower($country) !== mb_strtolower($city)
                ) {
                    $cleanDisplay .= ", " . $country;
                }

            } elseif ($displayName !== "") {

                $cleanDisplay = $displayName;

            } else {

                $cleanDisplay = $query;
            }

            /*
            -------------------------------------------------
            Preserve useful Nominatim fields
            -------------------------------------------------
            */

            $place["display_name"] = $cleanDisplay;

            $place["tripnest_original_display_name"] =
                $displayName;

            $place["tripnest_query"] = $query;

            $allResults[] = $place;
        }

        /*
        -----------------------------------------------------
        If we already found good results, don't unnecessarily
        hammer Nominatim with more requests.
        -----------------------------------------------------
        */

        if (count($allResults) >= 10) {
            break;
        }

        /*
        Small delay between fallback searches.
        */

        usleep(300000);
    }

    /* Nominatim failed or found nothing: try the worldwide fallbacks. */
    if (empty($allResults)) {
        $allResults = tripnestGeocodeFallback($destination);
    }


    /*
    ---------------------------------------------------------
    Remove duplicate coordinates
    ---------------------------------------------------------
    */

    $unique = [];

    foreach ($allResults as $place) {

        $key =
            round((float)$place["lat"], 6) .
            "_" .
            round((float)$place["lon"], 6);

        if (!isset($unique[$key])) {
            $unique[$key] = $place;
        }
    }

    $results = array_values($unique);

    /*
    ---------------------------------------------------------
    Sort results so administrative/city/town/village results
    appear before minor objects.
    ---------------------------------------------------------
    */

    usort(
        $results,
        function ($a, $b) {

            $importanceA =
                isset($a["importance"])
                    ? (float)$a["importance"]
                    : 0;

            $importanceB =
                isset($b["importance"])
                    ? (float)$b["importance"]
                    : 0;

            if ($importanceA === $importanceB) {
                return 0;
            }

            return ($importanceA > $importanceB)
                ? -1
                : 1;
        }
    );

    /*
    ---------------------------------------------------------
    Final result
    ---------------------------------------------------------
    */

    if (empty($results)) {

        return [
            "success" => false,
            "message" =>
                "Destination not found. Please try the city, town, or country name."
        ];
    }

    return [
        "success" => true,
        "results" => $results
    ];
}