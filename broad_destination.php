<?php

/*
============================================================
TRIPNEST - REGION MODE (states, countries, large areas)
============================================================

Problem this solves
-------------------
"Tamil Nadu" is a whole state. The normal engine searches around
ONE map point (the state's middle), which is usually empty forest,
so only 1 place is found and the other days become "Free Day".

What region mode does
---------------------
1. Asks OpenStreetMap (Nominatim) how big the destination is.
   Small (a city / town) -> region mode is NOT used.
2. Asks Wikipedia for the region's top attractions with several
   searches at once (temples, hills, beaches, forts ... chosen
   from the traveller's interests).
3. Keeps only attractions inside the region and groups them into
   "areas" (hubs): about one area per two days, each area within
   reasonable driving distance of the previous one.
4. For every area, runs the normal TripNest pipeline (real places,
   restaurants for lunch, interest scoring, 9:00-18:00 schedule).
5. Returns one combined itinerary.

Nothing here changes the normal flow for ordinary destinations.
============================================================
*/

/* Only load the engines if itinerary.php has not already done so. */
if (!function_exists("getNearbyPlaces")) {
    require_once __DIR__ . "/places.php";
}

if (!function_exists("recommendPlaces")) {
    require_once __DIR__ . "/recommend_places.php";
}

if (!function_exists("generateItinerary")) {
    require_once __DIR__ . "/generate_itinerary.php";
}


/*
============================================================
PARALLEL FETCH (waits for all requests)
============================================================
*/

if (!function_exists("tripnestFetchAllParallel")) {

    function tripnestFetchAllParallel(array $urls, $timeout = 8)
    {
        $out = [];

        foreach ($urls as $key => $url) {
            $out[$key] = null;
        }

        if (empty($urls)) {
            return $out;
        }

        $userAgent =
            "User-Agent: TripNest/1.0 (Travel Itinerary Optimizer; educational project)";

        if (!function_exists("curl_multi_init")) {

            foreach ($urls as $key => $url) {

                $context = stream_context_create([
                    "http" => [
                        "method" => "GET",
                        "header" => $userAgent . "\r\nAccept: application/json\r\n",
                        "timeout" => $timeout,
                        "ignore_errors" => true
                    ]
                ]);

                $body = @file_get_contents($url, false, $context);

                $out[$key] =
                    ($body === false || $body === "")
                        ? null
                        : $body;
            }

            return $out;
        }

        $multi = curl_multi_init();
        $handles = [];

        foreach ($urls as $key => $url) {

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => (int)$timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_ENCODING => "",
                CURLOPT_HTTPHEADER => [
                    $userAgent,
                    "Accept: application/json"
                ]
            ]);

            curl_multi_add_handle($multi, $ch);
            $handles[$key] = $ch;
        }

        $running = 0;

        do {

            $status = curl_multi_exec($multi, $running);

            if ($running > 0) {

                if (curl_multi_select($multi, 0.5) === -1) {
                    usleep(20000);
                }
            }

        } while ($running > 0 && $status === CURLM_OK);

        foreach ($handles as $key => $ch) {

            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body = curl_multi_getcontent($ch);

            if (
                $code >= 200 &&
                $code < 300 &&
                is_string($body) &&
                $body !== ""
            ) {
                $out[$key] = $body;
            }

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);

        return $out;
    }
}


/*
============================================================
HOW BIG IS THE DESTINATION?
============================================================
Returns null when it cannot be determined, otherwise:
[ south, north, west, east, center_lat, center_lon, extent_km ]
Cached for 30 days.
*/

if (!function_exists("tripnestDestinationExtent")) {

    function tripnestDestinationExtent($destination)
    {
        $destination = trim((string)$destination);

        if ($destination === "") {
            return null;
        }

        $cacheFile =
            tripnestCacheDirectory() .
            DIRECTORY_SEPARATOR .
            "extent_" . sha1(mb_strtolower($destination)) . ".json";

        if (
            is_file($cacheFile) &&
            (time() - (int)@filemtime($cacheFile)) < 2592000
        ) {

            $cached = json_decode((string)@file_get_contents($cacheFile), true);

            if (is_array($cached) && isset($cached["extent_km"])) {
                return $cached;
            }
        }

        $urls = [
            "nominatim" =>
                "https://nominatim.openstreetmap.org/search?" .
                http_build_query([
                    "q" => $destination,
                    "format" => "jsonv2",
                    "limit" => 1,
                    "accept-language" => "en"
                ])
        ];

        $bodies = tripnestFetchAllParallel($urls, 6);

        if (empty($bodies["nominatim"])) {
            return null;
        }

        $data = json_decode($bodies["nominatim"], true);

        if (
            !is_array($data) ||
            empty($data[0]["boundingbox"]) ||
            count($data[0]["boundingbox"]) < 4
        ) {
            return null;
        }

        $box = $data[0]["boundingbox"];

        $south = (float)$box[0];
        $north = (float)$box[1];
        $west = (float)$box[2];
        $east = (float)$box[3];

        $centerLat = ($south + $north) / 2;
        $centerLon = ($west + $east) / 2;

        $latSpanKm = abs($north - $south) * 111.0;

        $lonSpanKm =
            abs($east - $west) *
            111.0 *
            max(0.2, cos(deg2rad($centerLat)));

        $info = [
            "south" => $south,
            "north" => $north,
            "west" => $west,
            "east" => $east,
            "center_lat" => $centerLat,
            "center_lon" => $centerLon,
            "extent_km" => max($latSpanKm, $lonSpanKm)
        ];

        @file_put_contents($cacheFile, json_encode($info), LOCK_EX);

        return $info;
    }
}


/*
============================================================
WIKIPEDIA SEARCH QUERIES (chosen from the interests)
============================================================
*/

if (!function_exists("tripnestBroadQueries")) {

    function tripnestBroadQueries($place, $interests)
    {
        $interests = mb_strtolower((string)$interests);

        $queries = ["tourist attractions in " . $place];

        $has = function ($words) use ($interests) {
            foreach ($words as $word) {
                if (strpos($interests, $word) !== false) {
                    return true;
                }
            }
            return false;
        };

        $any = false;

        if ($has(["nature", "scenic", "landscape", "adventure"])) {
            $any = true;
            $queries[] = "hill stations in " . $place;
            $queries[] = "waterfalls in " . $place;
            $queries[] = "national parks wildlife sanctuaries in " . $place;
            $queries[] = "beaches in " . $place;
            $queries[] = "lakes dams in " . $place;
        }

        if ($has(["culture", "history", "heritage"])) {
            $any = true;
            $queries[] = "forts palaces in " . $place;
            $queries[] = "historic monuments in " . $place;
            $queries[] = "museums in " . $place;
            $queries[] = "temples in " . $place;
        }

        if ($has(["religious", "spiritual", "temple", "church", "mosque"])) {
            $any = true;
            $queries[] = "temples in " . $place;
            $queries[] = "churches in " . $place;
            $queries[] = "pilgrimage sites in " . $place;
        }

        if ($has(["shopping"])) {
            $any = true;
            $queries[] = "markets shopping in " . $place;
        }

        if ($has(["entertainment", "fun", "amusement"])) {
            $any = true;
            $queries[] = "amusement parks zoos in " . $place;
        }

        if ($has(["food"])) {
            $any = true;
            $queries[] = "street food markets in " . $place;
        }

        if (!$any) {
            $queries[] = "historic temples in " . $place;
            $queries[] = "hill stations in " . $place;
            $queries[] = "beaches in " . $place;
            $queries[] = "waterfalls in " . $place;
            $queries[] = "forts palaces in " . $place;
            $queries[] = "national parks in " . $place;
        }

        return array_slice(array_values(array_unique($queries)), 0, 8);
    }
}


/*
============================================================
COLLECT CANDIDATE ATTRACTIONS FOR THE WHOLE REGION
============================================================
Returns a list of
[ "name", "lat", "lon", "score", "element" ] sorted best first.
*/

if (!function_exists("tripnestBroadCandidates")) {

    function tripnestBroadCandidates($place, $interests, $extent, $fallbackLat, $fallbackLon)
    {
        $queries = tripnestBroadQueries($place, $interests);

        $urls = [];

        foreach ($queries as $index => $query) {

            $urls["q" . $index] =
                "https://en.wikipedia.org/w/api.php?" .
                http_build_query([
                    "action" => "query",
                    "format" => "json",
                    "generator" => "search",
                    "gsrsearch" => $query,
                    "gsrnamespace" => 0,
                    "gsrlimit" => 30,
                    "prop" => "coordinates|description",
                    "coprimary" => "primary",
                    "colimit" => 50
                ]);
        }

        $bodies = tripnestFetchAllParallel($urls, 9);

        $pagesByTitle = [];
        $scoreByTitle = [];

        foreach ($bodies as $body) {

            if (!is_string($body) || $body === "") {
                continue;
            }

            $data = json_decode($body, true);

            if (
                !is_array($data) ||
                empty($data["query"]["pages"]) ||
                !is_array($data["query"]["pages"])
            ) {
                continue;
            }

            foreach ($data["query"]["pages"] as $page) {

                $title = trim((string)($page["title"] ?? ""));

                if (
                    $title === "" ||
                    !isset($page["coordinates"][0]["lat"]) ||
                    !isset($page["coordinates"][0]["lon"])
                ) {
                    continue;
                }

                $rank = (int)($page["index"] ?? 30);

                $points = max(1, 31 - $rank);

                if (!isset($scoreByTitle[$title])) {
                    $scoreByTitle[$title] = 0;
                }

                /* Appearing in several searches means it matters. */
                $scoreByTitle[$title] += $points;

                $pagesByTitle[$title] = $page;
            }
        }

        if (empty($pagesByTitle)) {
            return [];
        }

        /* Reuse the existing Wikipedia -> place conversion. */
        $elements = tripnestWikipediaToElements(
            json_encode(["query" => ["pages" => array_values($pagesByTitle)]])
        );

        $candidates = [];

        foreach ($elements as $element) {

            $name = trim((string)($element["tags"]["name"] ?? ""));

            if ($name === "" || !isset($scoreByTitle[$name])) {
                continue;
            }

            $candidates[] = [
                "name" => $name,
                "lat" => (float)$element["lat"],
                "lon" => (float)$element["lon"],
                "score" => (float)$scoreByTitle[$name],
                "element" => $element
            ];
        }

        /* Keep only attractions inside the region. */
        if (is_array($extent)) {

            $margin = 0.1;

            $inside = [];

            foreach ($candidates as $candidate) {

                if (
                    $candidate["lat"] >= $extent["south"] - $margin &&
                    $candidate["lat"] <= $extent["north"] + $margin &&
                    $candidate["lon"] >= $extent["west"] - $margin &&
                    $candidate["lon"] <= $extent["east"] + $margin
                ) {
                    $inside[] = $candidate;
                }
            }

            $candidates = $inside;

        } elseif (count($candidates) > 5) {

            /* No bounding box: drop far-away outliers instead. */
            $distances = [];

            foreach ($candidates as $candidate) {
                $distances[] = calculateDistance(
                    $fallbackLat,
                    $fallbackLon,
                    $candidate["lat"],
                    $candidate["lon"]
                );
            }

            sort($distances);

            $median = $distances[(int)floor(count($distances) / 2)];

            $limit = min(900, max(120, $median * 1.8));

            $near = [];

            foreach ($candidates as $candidate) {

                $d = calculateDistance(
                    $fallbackLat,
                    $fallbackLon,
                    $candidate["lat"],
                    $candidate["lon"]
                );

                if ($d <= $limit) {
                    $near[] = $candidate;
                }
            }

            $candidates = $near;
        }

        usort($candidates, function ($a, $b) {
            return $b["score"] <=> $a["score"];
        });

        return $candidates;
    }
}


/*
============================================================
PICK THE AREAS (HUBS)
============================================================
Best-scoring attractions first. Every next area is at least
$minSeparationKm from the others and within $maxHopKm of the
previous area, so the days stay drivable.
*/

if (!function_exists("tripnestPickHubs")) {

    function tripnestPickHubs(array $candidates, $count, $maxHopKm = 350, $minSeparationKm = 40)
    {
        $hubs = [];

        $relaxed = false;

        $count = max(1, (int)$count);

        while (count($hubs) < $count) {

            $bestKey = null;
            $bestValue = -INF;

            foreach ($candidates as $key => $candidate) {

                $tooClose = false;

                foreach ($hubs as $hub) {

                    $d = calculateDistance(
                        $hub["lat"],
                        $hub["lon"],
                        $candidate["lat"],
                        $candidate["lon"]
                    );

                    if ($d < $minSeparationKm) {
                        $tooClose = true;
                        break;
                    }
                }

                if ($tooClose) {
                    continue;
                }

                $value = $candidate["score"];

                if (!empty($hubs)) {

                    $last = $hubs[count($hubs) - 1];

                    $hop = calculateDistance(
                        $last["lat"],
                        $last["lon"],
                        $candidate["lat"],
                        $candidate["lon"]
                    );

                    if ($hop > $maxHopKm) {
                        continue;
                    }

                    $value -= 0.08 * $hop;
                }

                if ($value > $bestValue) {
                    $bestValue = $value;
                    $bestKey = $key;
                }
            }

            if ($bestKey === null) {

                if (!empty($hubs) && !$relaxed) {
                    $maxHopKm = $maxHopKm * 2;
                    $relaxed = true;
                    continue;
                }

                break;
            }

            $hubs[] = $candidates[$bestKey];
        }

        return $hubs;
    }
}


/*
============================================================
SMALL HELPERS
============================================================
*/

if (!function_exists("tripnestIsLodging")) {

    function tripnestIsLodging($place)
    {
        if (!is_array($place)) {
            return false;
        }

        $category = mb_strtolower(
            (string)($place["category"] ?? $place["place_type"] ?? "")
        );

        return strpos($category, "accommodation") !== false;
    }
}

if (!function_exists("tripnestIsFood")) {

    function tripnestIsFood($place)
    {
        if (!is_array($place)) {
            return false;
        }

        $category = mb_strtolower((string)($place["category"] ?? ""));

        return strpos($category, "food") !== false;
    }
}


/*
============================================================
SIMPLE PLACE FROM A WIKIPEDIA CANDIDATE
============================================================
Used when the normal place builder rejects an entry, and for
the no-map-server fallback. Produces the same fields the
scoring and scheduling code expect.
*/

if (!function_exists("tripnestSimplePlace")) {

    function tripnestSimplePlace($candidate, $hubLat, $hubLon)
    {
        $tags = [];

        if (isset($candidate["element"]["tags"]) && is_array($candidate["element"]["tags"])) {
            $tags = $candidate["element"]["tags"];
        }

        $amenity = (string)($tags["amenity"] ?? "");
        $natural = (string)($tags["natural"] ?? "");
        $leisure = (string)($tags["leisure"] ?? "");
        $tourism = (string)($tags["tourism"] ?? "");

        $category = "Tourist Attraction";

        if ($amenity === "place_of_worship") {
            $category = "Religious";
        } elseif ($natural === "beach") {
            $category = "Beach";
        } elseif ($natural !== "" || $leisure !== "" || $tourism === "viewpoint") {
            $category = "Nature / Scenic";
        } elseif (!empty($tags["historic"]) || $tourism === "museum" || $tourism === "gallery") {
            $category = "Historical / Cultural";
        } elseif ($amenity === "marketplace" || !empty($tags["shop"])) {
            $category = "Shopping";
        } elseif ($amenity === "theatre" || $tourism === "zoo") {
            $category = "Entertainment";
        }

        $distance = calculateDistance(
            $hubLat,
            $hubLon,
            $candidate["lat"],
            $candidate["lon"]
        );

        return [
            "name" => $candidate["name"],
            "category" => $category,
            "place_type" => $category,
            "latitude" => (float)$candidate["lat"],
            "longitude" => (float)$candidate["lon"],
            "lat" => (float)$candidate["lat"],
            "lon" => (float)$candidate["lon"],
            "description" => (string)($tags["description"] ?? ""),
            "address" => "",
            "website" => (string)($tags["website"] ?? ""),
            "phone" => "",
            "opening_hours" => "",
            "distance_km" => round($distance, 2),
            "recommendation_score" => min(100, 40 + (int)$candidate["score"]),
            "recommendation_reason" =>
                "Top attraction in the region (from Wikipedia).",
            "osm_url" => ""
        ];
    }
}


/*
============================================================
BUILD THE PLAN FOR A LIST OF AREAS
============================================================
$useNetwork = true  : real nearby places (OSM + Wikipedia) per area
$useNetwork = false : only the region's Wikipedia attractions
Returns the plan array, or null when nothing could be scheduled.
*/

if (!function_exists("tripnestBroadBuild")) {

    function tripnestBroadBuild(
        $shortName,
        array $hubs,
        array $daysPerHub,
        array $candidates,
        $transport,
        $interests,
        $budget,
        $travelers,
        $useNetwork
    ) {
        $itinerary = [];
        $allPlaces = [];
        $selectedSights = [];
        $accommodation = null;
        $messageParts = [];
        $dayNumber = 1;

        foreach ($hubs as $hubIndex => $hub) {

            $daysHere = max(1, (int)$daysPerHub[$hubIndex]);

            $pool = [];

            if ($useNetwork) {

                /* Real nearby places. Empty destination = plain point search. */
                $nearby = getNearbyPlaces(
                    $hub["lat"],
                    $hub["lon"],
                    12000,
                    ""
                );

                if (
                    is_array($nearby) &&
                    !empty($nearby["places"]) &&
                    ($nearby["data_source"] ?? "") !== "generic"
                ) {
                    $pool = $nearby["places"];
                }
            }

            $seen = [];

            foreach ($pool as $existing) {
                $seen[mb_strtolower(trim((string)($existing["name"] ?? "")))] = true;
            }

            /* Add this area's Wikipedia attractions. */
            foreach ($candidates as $candidate) {

                $d = calculateDistance(
                    $hub["lat"],
                    $hub["lon"],
                    $candidate["lat"],
                    $candidate["lon"]
                );

                if ($d > 30) {
                    continue;
                }

                $key = mb_strtolower(trim($candidate["name"]));

                if (isset($seen[$key])) {
                    continue;
                }

                $built = null;

                if ($useNetwork) {
                    $built = tripnestBuildPlace(
                        $candidate["element"],
                        $hub["lat"],
                        $hub["lon"]
                    );
                }

                if (!is_array($built)) {
                    $built = tripnestSimplePlace($candidate, $hub["lat"], $hub["lon"]);
                }

                $seen[$key] = true;

                $pool[] = $built;
            }

            /* Split the pool. */
            $lodging = [];
            $food = [];
            $sightPool = [];

            foreach ($pool as $poolPlace) {

                if (tripnestIsLodging($poolPlace)) {
                    $lodging[] = $poolPlace;
                } else {
                    $sightPool[] = $poolPlace;
                }

                if (tripnestIsFood($poolPlace)) {
                    $food[] = $poolPlace;
                }
            }

            /* The traveller's stay is shown near Day 1's area. */
            $baseLat = null;
            $baseLon = null;

            if ($hubIndex === 0) {

                if (empty($lodging) && $useNetwork) {

                    $wide = getNearbyAccommodation(
                        $hub["lat"],
                        $hub["lon"],
                        15000
                    );

                    if (!empty($wide["places"]) && is_array($wide["places"])) {
                        $lodging = $wide["places"];
                    }
                }

                if (!empty($lodging)) {

                    usort($lodging, function ($a, $b) use ($hub) {

                        $da = calculateDistance(
                            $hub["lat"],
                            $hub["lon"],
                            (float)($a["latitude"] ?? 0),
                            (float)($a["longitude"] ?? 0)
                        );

                        $db = calculateDistance(
                            $hub["lat"],
                            $hub["lon"],
                            (float)($b["latitude"] ?? 0),
                            (float)($b["longitude"] ?? 0)
                        );

                        return $da <=> $db;
                    });

                    $accommodation = $lodging[0];

                    if (
                        isset($accommodation["latitude"]) &&
                        (float)$accommodation["latitude"] != 0
                    ) {
                        $baseLat = (float)$accommodation["latitude"];
                        $baseLon = (float)$accommodation["longitude"];
                    }

                } else {

                    $accommodation = [
                        "name" => "Stay near " . $hub["name"],
                        "category" => "Accommodation",
                        "place_type" => "Accommodation",
                        "latitude" => (float)$hub["lat"],
                        "longitude" => (float)$hub["lon"],
                        "lat" => (float)$hub["lat"],
                        "lon" => (float)$hub["lon"],
                        "distance_km" => 0,
                        "description" =>
                            "No hotel was listed on the map for this area. " .
                            "Base yourself near " . $hub["name"] .
                            " and compare hotels on a booking site.",
                        "address" => "",
                        "website" => "",
                        "phone" => "",
                        "opening_hours" => "",
                        "is_suggestion" => true
                    ];
                }
            }

            /* Score the area's places against the interests. */
            $recommended = [];

            if (!empty($sightPool)) {

                if ($useNetwork) {

                    $recommended = recommendPlaces(
                        $sightPool,
                        $interests,
                        $daysHere,
                        $budget,
                        $travelers
                    );
                }

                if (empty($recommended)) {

                    foreach ($sightPool as $fallback) {

                        if (tripnestIsFood($fallback)) {
                            continue;
                        }

                        if (!isset($fallback["recommendation_score"])) {
                            $fallback["recommendation_score"] = 50;
                        }

                        if (!isset($fallback["recommendation_reason"])) {
                            $fallback["recommendation_reason"] =
                                "Nearby place in the " . $hub["name"] . " area.";
                        }

                        $recommended[] = $fallback;
                    }

                    usort($recommended, function ($a, $b) {
                        return ((float)($b["recommendation_score"] ?? 0))
                            <=> ((float)($a["recommendation_score"] ?? 0));
                    });

                    $recommended = array_slice($recommended, 0, $daysHere * 5);
                }
            }

            $sights = [];

            foreach ($recommended as $sight) {

                if (!tripnestIsLodging($sight)) {
                    $sights[] = $sight;
                }
            }

            /*
             * TOP-UP: if the interest-matched list is short, add the
             * area's other places (and wider Wikipedia attractions) so
             * no day is left empty.
             */
            $targetSights = $daysHere * 4;

            if (count($sights) < $targetSights) {

                $haveSights = [];

                foreach ($sights as $haveSight) {
                    $haveSights[mb_strtolower(trim((string)($haveSight["name"] ?? "")))] = true;
                }

                $extraSights = [];
                $extraFood = [];

                foreach ($sightPool as $extra) {

                    $extraKey = mb_strtolower(trim((string)($extra["name"] ?? "")));

                    if ($extraKey === "" || isset($haveSights[$extraKey])) {
                        continue;
                    }

                    if (!isset($extra["recommendation_score"])) {
                        $extra["recommendation_score"] = 20;
                    }

                    $extra["recommendation_reason"] =
                        $extra["recommendation_reason"] ??
                        "Nearby place added so every day has activities.";

                    if (tripnestIsFood($extra)) {
                        $extraFood[] = $extra;
                    } else {
                        $extraSights[] = $extra;
                    }
                }

                /* Wider Wikipedia attractions (up to 80 km from the area). */
                foreach ($candidates as $candidate) {

                    $key = mb_strtolower(trim($candidate["name"]));

                    if (isset($haveSights[$key])) {
                        continue;
                    }

                    $already = false;

                    foreach ($extraSights as $knownExtra) {
                        if (mb_strtolower(trim((string)($knownExtra["name"] ?? ""))) === $key) {
                            $already = true;
                            break;
                        }
                    }

                    if ($already) {
                        continue;
                    }

                    $d = calculateDistance(
                        $hub["lat"],
                        $hub["lon"],
                        $candidate["lat"],
                        $candidate["lon"]
                    );

                    if ($d > 80) {
                        continue;
                    }

                    $extraSights[] = tripnestSimplePlace(
                        $candidate,
                        $hub["lat"],
                        $hub["lon"]
                    );
                }

                $byDistance = function ($a, $b) {
                    return ((float)($a["distance_km"] ?? 0))
                        <=> ((float)($b["distance_km"] ?? 0));
                };

                usort($extraSights, $byDistance);
                usort($extraFood, $byDistance);

                foreach (array_merge($extraSights, $extraFood) as $extra) {

                    if (count($sights) >= $targetSights) {
                        break;
                    }

                    $sights[] = $extra;
                }
            }

            $hubDays = [];

            if (!empty($sights)) {

                $hubDays = generateItinerary(
                    $sights,
                    $daysHere,
                    $transport,
                    $hub["lat"],
                    $hub["lon"],
                    $baseLat,
                    $baseLon,
                    $food
                );
            }

            /* Renumber this area's days into the whole trip. */
            for ($i = 0; $i < $daysHere; $i++) {

                $dayPlaces = [];

                if (isset($hubDays[$i]["places"]) && is_array($hubDays[$i]["places"])) {
                    $dayPlaces = $hubDays[$i]["places"];
                }

                $itinerary[] = [
                    "day" => $dayNumber + $i,
                    "area" => $hub["name"],
                    "places" => $dayPlaces
                ];
            }

            $label = "Day " . $dayNumber;

            if ($daysHere > 1) {
                $label .= "-" . ($dayNumber + $daysHere - 1);
            }

            $messageParts[] = $label . ": around " . $hub["name"];

            $dayNumber += $daysHere;

            foreach ($sights as $sight) {
                $selectedSights[] = $sight;
            }

            foreach ($pool as $poolPlace) {
                $allPlaces[] = $poolPlace;
            }
        }

        /* Anything planned at all? */
        $hasActivity = false;

        foreach ($itinerary as $dayData) {

            foreach (($dayData["places"] ?? []) as $dayPlace) {

                if (empty($dayPlace["is_break"])) {
                    $hasActivity = true;
                    break 2;
                }
            }
        }

        if (!$hasActivity) {
            return null;
        }

        if (is_array($accommodation)) {
            array_unshift($allPlaces, $accommodation);
        }

        $message =
            $shortName . " is a large region, so each day is planned around a " .
            "different area: " . implode("; ", $messageParts) . ". " .
            "The stay shown is near your Day 1 area - allow travel time " .
            "between areas and book a hotel near each one as you move.";

        return [
            "itinerary" => $itinerary,
            "accommodation" => $accommodation,
            "places" => $selectedSights,
            "all_places" => $allPlaces,
            "discovered" => count($allPlaces),
            "message" => $message
        ];
    }
}


/*
============================================================
MAIN ENTRY POINT
============================================================
Returns null when region mode does not apply (so the normal
engine keeps working), otherwise the plan array.

Region mode is used when:
  - the destination is huge (150 km or more: a state / country), or
  - it is large (45-150 km) AND the normal search was sparse.
The reason it did / did not run is left in
$GLOBALS["TRIPNEST_BROAD_DEBUG"] for the page notice.
*/

if (!function_exists("tripnestPlanBroadDestination")) {

    function tripnestPlanBroadDestination(
        $destination,
        $latitude,
        $longitude,
        $numberOfDays,
        $transport,
        $interests,
        $budget,
        $travelers,
        $sparse = true
    ) {
        @set_time_limit(120);

        $GLOBALS["TRIPNEST_BROAD_DEBUG"] = "";

        $numberOfDays = max(1, (int)$numberOfDays);

        $parts = explode(",", (string)$destination);

        $shortName = trim($parts[0]);

        if ($shortName === "") {
            return null;
        }

        /* 1) Is it a large area? */
        $extent = tripnestDestinationExtent($destination);

        if (is_array($extent)) {

            $extentKm = (float)$extent["extent_km"];

            if ($extentKm < 45) {
                return null;
            }

            if ($extentKm < 150 && !$sparse) {
                return null;
            }

        } else {

            if (!$sparse) {
                return null;
            }

            $extentKm = 0;
        }

        $debug = "area size " . ($extentKm > 0 ? round($extentKm) . " km" : "unknown");

        /* 2) Region-wide attractions. */
        $candidates = tripnestBroadCandidates(
            $shortName,
            $interests,
            $extent,
            $latitude,
            $longitude
        );

        $debug .= "; Wikipedia attractions found " . count($candidates);

        if (count($candidates) < 2) {
            $GLOBALS["TRIPNEST_BROAD_DEBUG"] = $debug;
            return null;
        }

        /* 3) Areas: roughly one per two days. */
        $wantedHubs = min(
            $numberOfDays,
            max(1, (int)ceil($numberOfDays / 2))
        );

        $hubs = tripnestPickHubs($candidates, $wantedHubs);

        if (empty($hubs)) {
            $GLOBALS["TRIPNEST_BROAD_DEBUG"] = $debug . "; no areas could be chosen";
            return null;
        }

        $hubCount = count($hubs);

        $daysPerHub = [];

        for ($i = 0; $i < $hubCount; $i++) {
            $daysPerHub[$i] = intdiv($numberOfDays, $hubCount);
        }

        for ($i = 0; $i < ($numberOfDays % $hubCount); $i++) {
            $daysPerHub[$i]++;
        }

        /* 4) Full plan with real nearby places. */
        $plan = tripnestBroadBuild(
            $shortName,
            $hubs,
            $daysPerHub,
            $candidates,
            $transport,
            $interests,
            $budget,
            $travelers,
            true
        );

        /* 5) Map servers gave nothing usable: plan from Wikipedia alone. */
        if ($plan === null) {

            $plan = tripnestBroadBuild(
                $shortName,
                $hubs,
                $daysPerHub,
                $candidates,
                $transport,
                $interests,
                $budget,
                $travelers,
                false
            );

            if ($plan !== null) {
                $plan["message"] .=
                    " (Map servers were busy, so places come from Wikipedia only - " .
                    "press Regenerate later for restaurants and hotels.)";
            }
        }

        if ($plan === null) {
            $GLOBALS["TRIPNEST_BROAD_DEBUG"] = $debug . "; areas chosen but nothing could be scheduled";
        }

        return $plan;
    }
}
