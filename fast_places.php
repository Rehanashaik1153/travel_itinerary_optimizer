<?php

/*
============================================================
TRIPNEST - FAST WORLDWIDE PLACE DISCOVERY
============================================================

Why this file exists
--------------------
The old flow asked ONE Overpass (OpenStreetMap) server at a
time, with a heavy query, and gave up with "service temporarily
unavailable" when both servers were slow. That is the main
reason itineraries were "not generating".

What this does instead
----------------------
1. Sends a LIGHTER Overpass query to several public mirrors AT
   THE SAME TIME and uses whichever answers first.
2. At the same time asks Wikipedia GeoSearch (fast, global,
   very reliable) for landmarks around the destination.
3. Merges both into the same "element" format the rest of the
   app already understands, so recommendPlaces() and
   generateItinerary() need no changes.
4. If every live source fails, the caller falls back to a
   previously cached result, and as a last resort to a clearly
   labelled generic plan - so an itinerary is ALWAYS produced
   for any destination in the world.
============================================================
*/

if (!defined("TRIPNEST_FAST_TOTAL_TIMEOUT")) {
    /* Hard cap (seconds) for all live lookups together. */
    define("TRIPNEST_FAST_TOTAL_TIMEOUT", 14);
}

if (!defined("TRIPNEST_FAST_CONNECT_TIMEOUT")) {
    define("TRIPNEST_FAST_CONNECT_TIMEOUT", 4);
}

if (!defined("TRIPNEST_FAST_GRACE")) {
    /* Seconds to keep waiting for slower servers after the first good answer. */
    define("TRIPNEST_FAST_GRACE", 3);
}


/*
============================================================
LIGHTER OVERPASS QUERY
============================================================
- Only NAMED features (skips thousands of unnamed objects,
  which is what made the old query slow in big cities).
- Sightseeing uses the full radius; food / shops / hotels use
  a smaller radius so dense cities do not time out.
*/

if (!function_exists("tripnestBuildFastOverpassQuery")) {

    function tripnestBuildFastOverpassQuery($latitude, $longitude, $radius)
    {
        $lat = (float) $latitude;
        $lon = (float) $longitude;
        $r   = (int) $radius;
        $near = (int) min($r, 4000);

        return "[out:json][timeout:9];\n(\n"
            . "nwr[\"tourism\"~\"attraction|museum|gallery|theme_park|zoo|aquarium|viewpoint|artwork\"][\"name\"](around:$r,$lat,$lon);\n"
            . "nwr[\"historic\"][\"name\"](around:$r,$lat,$lon);\n"
            . "nwr[\"amenity\"~\"place_of_worship|marketplace|arts_centre|theatre\"][\"name\"](around:$r,$lat,$lon);\n"
            . "nwr[\"leisure\"~\"park|garden|nature_reserve|water_park\"][\"name\"](around:$r,$lat,$lon);\n"
            . "nwr[\"natural\"~\"beach|waterfall|peak|cliff|cave|spring\"][\"name\"](around:$r,$lat,$lon);\n"
            . "nwr[\"boundary\"=\"national_park\"][\"name\"](around:$r,$lat,$lon);\n"
            . "nwr[\"tourism\"~\"hotel|hostel|guest_house|resort|apartment\"][\"name\"](around:$near,$lat,$lon);\n"
            . "nwr[\"amenity\"~\"restaurant|cafe|food_court\"][\"name\"](around:$near,$lat,$lon);\n"
            . "nwr[\"shop\"~\"mall|department_store|market\"][\"name\"](around:$near,$lat,$lon);\n"
            . ");\nout tags center 700;";
    }
}


/*
============================================================
PARALLEL RACE
============================================================
Fires all requests together. Returns as soon as one response
passes $validator (so we never wait for the slowest server).
Everything that finished earlier is also returned so callers
can use e.g. Wikipedia even though Overpass was the "winner".

$requests = [
    key => ["url" => ..., "post" => string|null, "headers" => [...]],
];
$validator = function ($key, $body) { return bool; }

Returns [
    "winner"  => key|null,
    "bodies"  => [key => body string],   // all valid bodies seen
    "failed"  => [key, ...]
]
*/

if (!function_exists("tripnestRaceRequests")) {

    function tripnestRaceRequests(array $requests, callable $mustWinKeys, $validator, $timeout)
    {
        $result = ["winner" => null, "bodies" => [], "failed" => []];

        if (!function_exists("curl_multi_init")) {
            /* No curl: run sequentially with short timeouts. */
            $deadline = microtime(true) + $timeout;
            foreach ($requests as $key => $req) {
                if (microtime(true) >= $deadline) {
                    $result["failed"][] = $key;
                    continue;
                }
                $body = tripnestSimpleFetch($req, max(2, (int) ($deadline - microtime(true))));
                if ($body !== false && $validator($key, $body)) {
                    $result["bodies"][$key] = $body;
                    if ($mustWinKeys($key)) {
                        $result["winner"] = $key;
                        return $result;
                    }
                } else {
                    $result["failed"][] = $key;
                }
            }
            return $result;
        }

        $multi   = curl_multi_init();
        $handles = [];

        foreach ($requests as $key => $req) {
            $ch = curl_init();
            $opts = [
                CURLOPT_URL            => $req["url"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => TRIPNEST_FAST_CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT        => (int) $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 2,
                CURLOPT_ENCODING       => "",
                CURLOPT_HTTPHEADER     => array_merge(
                    ["User-Agent: TripNest/1.0 (Travel Itinerary Optimizer; educational project)"],
                    $req["headers"] ?? []
                ),
            ];
            if (isset($req["post"]) && $req["post"] !== null) {
                $opts[CURLOPT_POST]       = true;
                $opts[CURLOPT_POSTFIELDS] = $req["post"];
            }
            curl_setopt_array($ch, $opts);
            curl_multi_add_handle($multi, $ch);
            $handles[$key] = $ch;
        }

        $deadline = microtime(true) + $timeout;
        $running  = 0;
        $done     = [];
        $winnerAt = 0;

        do {
            curl_multi_exec($multi, $running);

            while ($info = curl_multi_info_read($multi)) {
                $ch  = $info["handle"];
                $key = array_search($ch, $handles, true);

                if ($key === false || isset($done[$key])) {
                    continue;
                }
                $done[$key] = true;

                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $body = curl_multi_getcontent($ch);

                if (
                    $info["result"] === CURLE_OK &&
                    $code >= 200 && $code < 300 &&
                    is_string($body) && $body !== "" &&
                    $validator($key, $body)
                ) {
                    $result["bodies"][$key] = $body;

                    if ($result["winner"] === null && $mustWinKeys($key)) {
                        $result["winner"] = $key;
                        $winnerAt = microtime(true);
                    }
                } else {
                    $result["failed"][] = $key;
                }
            }

            /*
             * Different mirrors can hold different objects, so slower
             * servers get a short grace period (TRIPNEST_FAST_GRACE)
             * after the first good point-search answer. Waiting for
             * EVERY request made each search take up to the full
             * timeout whenever one server was slow.
             */
            if (
                $result["winner"] !== null &&
                (microtime(true) - $winnerAt) > TRIPNEST_FAST_GRACE
            ) {
                break;
            }
            if ($running > 0) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    break;
                }
                if (curl_multi_select($multi, min(0.2, $remaining)) === -1) {
                    usleep(20000); /* avoid a busy loop on platforms where select returns -1 */
                }
            }

        } while ($running > 0);

        foreach ($handles as $key => $ch) {
            if (!isset($done[$key]) && !isset($result["bodies"][$key])) {
                $result["failed"][] = $key;
            }
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);

        $result["failed"] = array_values(array_unique($result["failed"]));
        return $result;
    }
}

if (!function_exists("tripnestSimpleFetch")) {

    /* Sequential fallback used only when curl_multi is missing. */
    function tripnestSimpleFetch($req, $timeout)
    {
        $header = "User-Agent: TripNest/1.0 (Travel Itinerary Optimizer; educational project)\r\n";
        foreach (($req["headers"] ?? []) as $h) {
            $header .= $h . "\r\n";
        }
        $http = ["method" => "GET", "header" => $header, "timeout" => $timeout, "ignore_errors" => true];

        if (isset($req["post"]) && $req["post"] !== null) {
            $http["method"]  = "POST";
            $http["header"] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $http["content"] = $req["post"];
        }
        $body = @file_get_contents($req["url"], false, stream_context_create(["http" => $http]));
        return ($body === false || $body === "") ? false : $body;
    }
}


/*
============================================================
WIKIPEDIA GEOSEARCH  ->  OSM-STYLE ELEMENTS
============================================================
Wikipedia knows landmarks in virtually every town on Earth and
its API is fast and stable. Page descriptions are used to turn
each article into the same tags the OSM classifier expects.
*/

if (!function_exists("tripnestWikipediaUrl")) {

    function tripnestWikipediaUrl($latitude, $longitude, $radius)
    {
        return "https://en.wikipedia.org/w/api.php?" . http_build_query([
            "action"    => "query",
            "format"    => "json",
            "generator" => "geosearch",
            "ggscoord"  => ((float) $latitude) . "|" . ((float) $longitude),
            "ggsradius" => (int) min(10000, max(1000, (int) $radius)),
            "ggslimit"  => 50,
            "prop"      => "coordinates|description",
            "colimit"   => 50,
        ]);
    }
}

if (!function_exists("tripnestWikipediaToElements")) {

    function tripnestWikipediaToElements($body)
    {
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data["query"]["pages"]) || !is_array($data["query"]["pages"])) {
            return [];
        }

        /* Articles that are NOT visitable places. */
        $skip = '/\b(city|town|village|hamlet|municipality|district|mandal|taluk|tehsil|county|province|state|'
            . 'locality|suburb|neighbou?rhood|ward|census|railway|station|junction|airport|school|college|'
            . 'university|hospital|bank|company|politician|footballer|cricketer|actor|actress|singer|'
            . 'film|album|song|constituency|highway|road|river|canal|village in|city in|town in)\b/i';

        $elements = [];

        foreach ($data["query"]["pages"] as $page) {

            $title = trim((string) ($page["title"] ?? ""));
            $lat   = $page["coordinates"][0]["lat"] ?? null;
            $lon   = $page["coordinates"][0]["lon"] ?? null;
            $desc  = trim((string) ($page["description"] ?? ""));

            if ($title === "" || $lat === null || $lon === null) {
                continue;
            }

            $text = mb_strtolower($title . " " . $desc);
            $tags = ["name" => $title, "wikipedia" => "en:" . $title];

            if ($desc !== "") {
                $tags["description"] = $desc;
            }

            if (isset($page["pageid"])) {
                $tags["website"] = "https://en.wikipedia.org/?curid=" . (int) $page["pageid"];
            }

            if (preg_match('/temple|mosque|church|cathedral|shrine|monastery|gurdwara|synagogue|basilica|pagoda|mandir|dargah/', $text)) {
                $tags["amenity"] = "place_of_worship";
            } elseif (preg_match('/museum/', $text)) {
                $tags["tourism"] = "museum";
            } elseif (preg_match('/gallery/', $text)) {
                $tags["tourism"] = "gallery";
            } elseif (preg_match('/waterfall|falls/', $text)) {
                $tags["natural"] = "waterfall";
            } elseif (preg_match('/beach/', $text)) {
                $tags["natural"] = "beach";
            } elseif (preg_match('/national park|wildlife|sanctuary|reserve|forest/', $text)) {
                $tags["leisure"] = "nature_reserve";
            } elseif (preg_match('/park|garden/', $text)) {
                $tags["leisure"] = "park";
            } elseif (preg_match('/zoo/', $text)) {
                $tags["tourism"] = "zoo";
            } elseif (preg_match('/fort|castle|palace|ruins|monument|memorial|tomb|mausoleum|archaeolog|heritage|historic|gate|tower|lighthouse|stupa|cave/', $text)) {
                $tags["historic"] = "monument";
            } elseif (preg_match('/lake|reservoir|dam|hill|mountain|peak|viewpoint|island|bay|cliff/', $text)) {
                $tags["tourism"] = "viewpoint";
            } elseif (preg_match('/market|bazaar/', $text)) {
                $tags["amenity"] = "marketplace";
            } elseif (preg_match('/mall|shopping/', $text)) {
                $tags["shop"] = "mall";
            } elseif (preg_match('/stadium|arena/', $text)) {
                $tags["leisure"] = "stadium";
            } elseif (preg_match('/theatre|theater|cinema|auditorium|cultural centre|cultural center/', $text)) {
                $tags["amenity"] = "theatre";
            } elseif (preg_match($skip, $desc)) {
                continue; /* not a place a tourist visits */
            } elseif ($desc !== "") {
                $tags["tourism"] = "attraction";
            } else {
                continue;
            }

            $elements[] = [
                "type" => "",   /* not an OSM object: keeps osm_url empty */
                "id"   => "",
                "lat"  => (float) $lat,
                "lon"  => (float) $lon,
                "tags" => $tags,
            ];
        }

        return $elements;
    }
}



/*
============================================================
DESTINATION AREA DISCOVERY
============================================================
For a city/state/country, a single latitude/longitude is not
enough. Nominatim can tell us whether the destination is an OSM
relation. When it is a relation, Overpass can search the whole
administrative area instead of an arbitrary point.
============================================================
*/

if (!function_exists("tripnestFindNominatimAreaId")) {
    function tripnestFindNominatimAreaId($destination)
    {
        $destination = trim((string)$destination);

        if ($destination === "") {
            return null;
        }

        $url = "https://nominatim.openstreetmap.org/search?" .
            http_build_query([
                "q" => $destination,
                "format" => "jsonv2",
                "limit" => 5,
                "addressdetails" => 1,
                "accept-language" => "en"
            ]);

        $headers = [
            "User-Agent: TripNest-Travel-Itinerary-Optimizer/1.0 (Educational Project)",
            "Accept: application/json"
        ];

        $body = false;

        if (function_exists("curl_init")) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $headers
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                "http" => [
                    "method" => "GET",
                    "header" => implode("\r\n", $headers) . "\r\n",
                    "timeout" => 6,
                    "ignore_errors" => true
                ]
            ]);
            $body = @file_get_contents($url, false, $context);
        }

        if (!is_string($body) || $body === "") {
            return null;
        }

        $results = json_decode($body, true);

        if (!is_array($results)) {
            return null;
        }

        /*
         * Prefer administrative relations. This covers states,
         * provinces, counties, districts, municipalities and many
         * city relations.
         */
        foreach ($results as $item) {
            $osmType = strtolower((string)($item["osm_type"] ?? ""));
            $osmId   = (int)($item["osm_id"] ?? 0);
            $type    = strtolower((string)($item["type"] ?? ""));
            $category = strtolower((string)($item["category"] ?? ""));

            if (
                $osmType === "relation" &&
                $osmId > 0 &&
                (
                    $category === "boundary" ||
                    $type === "administrative" ||
                    strpos($category, "boundary") !== false
                )
            ) {
                return [
                    "area_id" => 3600000000 + $osmId,
                    "osm_id" => $osmId,
                    "osm_type" => $osmType,
                    "display_name" => (string)($item["display_name"] ?? $destination)
                ];
            }
        }

        /*
         * Second pass: accept any named relation if Nominatim did
         * not expose the administrative type cleanly.
         */
        foreach ($results as $item) {
            if (
                strtolower((string)($item["osm_type"] ?? "")) === "relation" &&
                (int)($item["osm_id"] ?? 0) > 0
            ) {
                $osmId = (int)$item["osm_id"];

                return [
                    "area_id" => 3600000000 + $osmId,
                    "osm_id" => $osmId,
                    "osm_type" => "relation",
                    "display_name" => (string)($item["display_name"] ?? $destination)
                ];
            }
        }

        return null;
    }
}

if (!function_exists("tripnestBuildAreaOverpassQuery")) {
    function tripnestBuildAreaOverpassQuery($areaId)
    {
        $areaId = (int)$areaId;

        if ($areaId <= 0) {
            return "";
        }

        return "[out:json][timeout:25];\n" .
            "area($areaId)->.tripnestArea;\n" .
            "(\n" .
            "nwr[\"tourism\"~\"attraction|museum|gallery|theme_park|zoo|aquarium|viewpoint|artwork|picnic_site\"][\"name\"](area.tripnestArea);\n" .
            "nwr[\"historic\"][\"name\"](area.tripnestArea);\n" .
            "nwr[\"heritage\"][\"name\"](area.tripnestArea);\n" .
            "nwr[\"amenity\"~\"place_of_worship|marketplace|arts_centre|theatre|cinema|restaurant|cafe|fast_food|food_court\"][\"name\"](area.tripnestArea);\n" .
            "nwr[\"leisure\"~\"park|garden|nature_reserve|wildlife_park|water_park|amusement_arcade|bowling_alley|stadium|sports_centre\"][\"name\"](area.tripnestArea);\n" .
            "nwr[\"natural\"~\"beach|waterfall|peak|lake|wood|forest|valley|cliff|cave|spring\"][\"name\"](area.tripnestArea);\n" .
            "nwr[\"boundary\"=\"national_park\"][\"name\"](area.tripnestArea);\n" .
            "nwr[\"shop\"~\"mall|department_store|market|shopping_centre\"][\"name\"](area.tripnestArea);\n" .
            ");\n" .
            "out center tags 500;";
    }
}

/*
============================================================
MAIN ENTRY POINT
============================================================
Returns:
[
  "elements" => [...],            // OSM-style elements (maybe empty)
  "source"   => "overpass" | "overpass+wikipedia" | "wikipedia" | "none",
  "server"   => "host that answered first",
  "failed"   => [hosts that failed / timed out]
]
*/

if (!function_exists("tripnestFetchElementsFast")) {

    function tripnestFetchElementsFast($latitude, $longitude, $radius, $destination = "")
    {
        $mirrors = [
            "op_de"      => "https://overpass-api.de/api/interpreter",
            "op_kumi"    => "https://overpass.kumi.systems/api/interpreter",
            "op_private" => "https://overpass.private.coffee/api/interpreter",
            "op_mailru"  => "https://maps.mail.ru/osm/tools/overpass/api/interpreter",
        ];

        $query = tripnestBuildFastOverpassQuery($latitude, $longitude, $radius);
        $post  = http_build_query(["data" => $query]);

        $requests = [];

        foreach ($mirrors as $key => $url) {
            $requests[$key] = [
                "url"     => $url,
                "post"    => $post,
                "headers" => ["Content-Type: application/x-www-form-urlencoded"],
            ];
        }

        $requests["wikipedia"] = [
            "url" => tripnestWikipediaUrl($latitude, $longitude, $radius),
        ];

        /*
         * For broad destinations such as "Tamil Nadu, India", also
         * query the complete OSM administrative area. This prevents
         * a state/region from being reduced to one arbitrary map
         * coordinate.
         */
        $areaInfo = tripnestFindNominatimAreaId($destination);

        if (
            is_array($areaInfo) &&
            !empty($areaInfo["area_id"])
        ) {
            $areaQuery = tripnestBuildAreaOverpassQuery(
                $areaInfo["area_id"]
            );

            if ($areaQuery !== "") {
                $areaPost = http_build_query([
                    "data" => $areaQuery
                ]);

                foreach ($mirrors as $key => $url) {
                    $requests["area_" . $key] = [
                        "url" => $url,
                        "post" => $areaPost,
                        "headers" => [
                            "Content-Type: application/x-www-form-urlencoded"
                        ]
                    ];
                }
            }
        }

        $validator = function ($key, $body) {

            $decoded = json_decode($body, true);

            if (!is_array($decoded)) {
                return false;
            }

            if ($key === "wikipedia") {
                return isset($decoded["query"]) || isset($decoded["batchcomplete"]);
            }

            /* Overpass */
            if (!isset($decoded["elements"]) || !is_array($decoded["elements"])) {
                return false;
            }

            /* HTTP 200 + a "remark" about a runtime error / timeout means
               the server gave up part-way: treat as failed and keep racing. */
            if (isset($decoded["remark"]) && preg_match('/error|timed out|out of memory/i', (string) $decoded["remark"])) {
                return false;
            }

            return true;
        };

        $isOverpass = function ($key) {
            return $key !== "wikipedia" && strpos($key, "area_") !== 0;
        };

        $race = tripnestRaceRequests(
            $requests,
            $isOverpass,
            $validator,
            TRIPNEST_FAST_TOTAL_TIMEOUT
        );

        $elements = [];
        $sources  = [];
        $server   = "";

        /*
         * Merge EVERY valid Overpass response. The old code used only
         * the first winner, which is exactly why a destination could
         * report "1 place discovered" even when other mirrors had many
         * more places.
         */
        foreach ($race["bodies"] as $key => $body) {
            if ($key === "wikipedia") {
                continue;
            }

            $decoded = json_decode($body, true);

            if (
                !is_array($decoded) ||
                empty($decoded["elements"]) ||
                !is_array($decoded["elements"])
            ) {
                continue;
            }

            foreach ($decoded["elements"] as $element) {
                if (is_array($element)) {
                    $elements[] = $element;
                }
            }

            if (strpos($key, "area_") === 0) {
                $sources[] = "overpass-area";
            } else {
                $sources[] = "overpass";
            }

            if ($server === "") {
                $baseKey = str_replace("area_", "", $key);
                $server = $mirrors[$baseKey] ?? "";
            }
        }

        /*
         * Wikipedia is an additional source, never a replacement for
         * map data.
         */
        if (isset($race["bodies"]["wikipedia"])) {
            $wiki = tripnestWikipediaToElements(
                $race["bodies"]["wikipedia"]
            );

            if (!empty($wiki)) {
                $elements = array_merge(
                    $elements,
                    $wiki
                );
                $sources[] = "wikipedia";

                if ($server === "") {
                    $server = "en.wikipedia.org";
                }
            }
        }

        /*
         * Remove exact duplicate OSM objects / duplicate coordinates
         * before sending the result to the slower place normalizer.
         */
        $uniqueElements = [];
        $seenElements = [];

        foreach ($elements as $element) {
            $tags = $element["tags"] ?? [];
            $name = trim((string)($tags["name"] ?? ""));
            $lat = $element["lat"] ?? ($element["center"]["lat"] ?? null);
            $lon = $element["lon"] ?? ($element["center"]["lon"] ?? null);

            $elementKey = strtolower(
                $name . "|" .
                round((float)$lat, 5) . "|" .
                round((float)$lon, 5)
            );

            if ($name === "" || isset($seenElements[$elementKey])) {
                continue;
            }

            $seenElements[$elementKey] = true;
            $uniqueElements[] = $element;
        }

        $elements = $uniqueElements;
        $sources = array_values(array_unique($sources));

        $failedHosts = [];
        foreach ($race["failed"] as $key) {
            if (strpos($key, "area_") === 0) {
                $baseKey = str_replace("area_", "", $key);
                $failedHosts[] = $mirrors[$baseKey] ?? $key;
            } else {
                $failedHosts[] = $mirrors[$key] ?? "en.wikipedia.org";
            }
        }

        return [
            "elements" => $elements,
            "source"   => empty($sources) ? "none" : implode("+", $sources),
            "server"   => $server,
            "failed"   => $failedHosts,
        ];
    }
}


/*
============================================================
LAST-RESORT GENERIC PLAN
============================================================
Used ONLY when every live source and the cache are unavailable,
so the user still gets a usable day-by-day structure for any
destination in the world instead of an empty page.

These are clearly labelled "suggested activities" (not claimed
to be specific verified venues) and are placed in a small ring
around the destination centre.
*/

if (!function_exists("tripnestGenericElements")) {

    function tripnestGenericElements($latitude, $longitude, $destination)
    {
        $d = trim((string) $destination);
        if ($d === "") {
            $d = "the destination";
        }
        /* Use just the first part ("Guntur, Andhra Pradesh, India" -> "Guntur"). */
        $short = trim(explode(",", $d)[0]);
        if ($short === "") {
            $short = $d;
        }

        $note = "Suggested activity (live place data was unavailable). "
            . "Ask locals or check a map app for the best spot in $short.";

        $templates = [
            ["$short - Old Town & City Centre Walk",        ["historic" => "monument"]],
            ["$short - Local Market & Shopping Streets",    ["amenity" => "marketplace"]],
            ["$short - Heritage & Religious Sites",         ["amenity" => "place_of_worship"]],
            ["$short - Main Park / Garden Stroll",          ["leisure" => "park"]],
            ["$short - Museum & Cultural Centre Visit",     ["tourism" => "museum"]],
            ["$short - Scenic Viewpoint & Sunset Spot",     ["tourism" => "viewpoint"]],
            ["$short - Local Food Street (Lunch)",          ["amenity" => "restaurant"]],
            ["$short - Nature Walk / Waterfront",           ["leisure" => "nature_reserve"]],
            ["$short - Cafe Hopping & Evening Promenade",   ["amenity" => "cafe"]],
            ["$short - Local Art & Craft Experience",       ["tourism" => "gallery"]],
            ["$short - Evening Cultural Show / Entertainment", ["amenity" => "theatre"]],
            ["$short - Day Trip to Nearby Villages",        ["tourism" => "attraction"]],
            ["$short - Traditional Cuisine Tasting",        ["amenity" => "restaurant"]],
            ["$short - Neighbourhood Photo Walk",           ["tourism" => "attraction"]],
        ];

        $elements = [];
        $count    = count($templates);

        foreach ($templates as $i => $tpl) {
            /* Spread points on a ring of ~0.4-1.2 km so map pins do not stack. */
            $angle  = (2 * M_PI * $i) / $count;
            $ringKm = 0.4 + 0.8 * (($i % 3) / 2);
            $dLat   = ($ringKm / 111.0) * sin($angle);
            $cosLat = max(0.2, cos(deg2rad((float) $latitude)));
            $dLon   = ($ringKm / (111.0 * $cosLat)) * cos($angle);

            $tags = array_merge(
                ["name" => $tpl[0], "description" => $note],
                $tpl[1]
            );

            $elements[] = [
                "type" => "",
                "id"   => "",
                "lat"  => (float) $latitude + $dLat,
                "lon"  => (float) $longitude + $dLon,
                "tags" => $tags,
            ];
        }

        return $elements;
    }
}
