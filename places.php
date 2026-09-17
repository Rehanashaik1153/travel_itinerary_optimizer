<?php

/* =====================================================
   WANDERAI - DYNAMIC PLACES FETCHER
   ===================================================== */

function getNearbyPlaces(
    $latitude,
    $longitude,
    $radius = 10000,
    $destination = ""
) {

    /* =============================================
       VALIDATE COORDINATES
       ============================================= */

    $latitude = (float)$latitude;
    $longitude = (float)$longitude;
    $radius = (int)$radius;

    if ($latitude == 0 || $longitude == 0) {

        return [
            "success" => false,
            "message" => "Invalid destination coordinates."
        ];

    }

    /* Limit radius */

    $radius = min(
        max($radius, 1000),
        10000
    );


    /* =============================================
       BROAD DESTINATION DETECTION
       ============================================= */

    $destinationText = strtolower(trim((string)$destination));
    $destinationText = preg_replace('/\s+/', ' ', $destinationText);

    $broadDestinationWords = [
        "india", "kerala", "tamil nadu", "karnataka", "andhra pradesh",
        "telangana", "maharashtra", "goa", "gujarat", "rajasthan",
        "punjab", "haryana", "uttar pradesh", "uttarakhand",
        "west bengal", "odisha", "bihar", "jharkhand", "assam",
        "madhya pradesh", "chhattisgarh", "himachal pradesh",
        "jammu and kashmir", "ladakh", "sikkim", "meghalaya",
        "manipur", "mizoram", "nagaland", "tripura", "nepal",
        "bhutan", "bangladesh", "sri lanka", "country", "state",
        "province", "region", "territory"
    ];

    $isBroadDestination = false;

    foreach ($broadDestinationWords as $broadWord) {

        if (strpos($destinationText, $broadWord) !== false) {
            $isBroadDestination = true;
            break;
        }

    }

    /*
       Broad destinations such as Kerala, India need a larger
       dynamic search radius than a normal city destination -
       but a huge radius combined with every category at once
       is exactly what overloads the free public Overpass
       mirrors (504 Gateway Timeout). Keep broad radius smaller
       than before and rely on the lighter query variant below
       for these.
    */

    if ($isBroadDestination) {
        $radius = max($radius, 40000);
    }


    /* =============================================
       OVERPASS SERVERS
       ============================================= */

    $servers = [

        "https://overpass-api.de/api/interpreter",

        "https://overpass.private.coffee/api/interpreter",

        "https://overpass.kumi.systems/api/interpreter",

        "https://overpass.nchc.org.tw/api/interpreter"

    ];


    /* =============================================
       LOCAL RESULT CACHE
       =============================================

       Overpass calls are the slowest part of itinerary
       generation. Cache the raw response per destination
       area for a few hours so re-generating (or the
       "Regenerate" button) does not re-hit the network
       every time.
       ============================================= */

    $cacheDir = __DIR__ . "/cache/places";

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }

    $cacheKey = sprintf(
        "%s_%.2f_%.2f_%d",
        $isBroadDestination ? "broad" : "local",
        round($latitude, 2),
        round($longitude, 2),
        $radius
    );

    $cacheFile =
        $cacheDir . "/" . md5($cacheKey) . ".json";

    $cacheMaxAgeSeconds = 7 * 24 * 60 * 60; // 7 days - tourist attractions
                                             // do not change hour to hour,
                                             // and a long TTL is what makes
                                             // repeat generation instant.

    if (
        is_file($cacheFile) &&
        (time() - filemtime($cacheFile)) < $cacheMaxAgeSeconds
    ) {

        $cached = json_decode(
            file_get_contents($cacheFile),
            true
        );

        if (is_array($cached) && isset($cached["elements"])) {

            $elements = $cached["elements"];

            $placesResponse = ["success" => true];
            $accommodationResponse = ["success" => true];

            goto placesCacheHit;
        }
    }


    /* =============================================
       SINGLE-FLIGHT LOCK
       =============================================

       The background prewarm and the itinerary page can ask
       for the same area at almost the same moment. Without a
       lock both would fire their own slow Overpass request
       and the user would wait for the second one for nothing.

       If a fetch for this exact area is already in progress,
       wait for its result instead of starting another.
       ============================================= */

    $lockFile = $cacheFile . ".lock";

    if (
        is_file($lockFile) &&
        (time() - filemtime($lockFile)) < 30
    ) {

        $waitUntil = microtime(true) + 20;

        while (microtime(true) < $waitUntil) {

            usleep(400000);

            clearstatcache(true, $cacheFile);

            if (is_file($cacheFile)) {

                $cached = json_decode(
                    @file_get_contents($cacheFile),
                    true
                );

                if (
                    is_array($cached) &&
                    !empty($cached["elements"])
                ) {

                    $elements = $cached["elements"];

                    $placesResponse = ["success" => true];
                    $accommodationResponse = ["success" => true];

                    goto placesCacheHit;
                }
            }

            clearstatcache(true, $lockFile);

            if (!is_file($lockFile)) {
                break;
            }
        }
    }

    @touch($lockFile);


    /* =============================================
       QUERY BUILDER - PLACES + ACCOMMODATION
       =============================================

       Previously this ran two separate Overpass requests
       (places, then accommodation), each retried across
       four mirror servers *sequentially*. Worst case that
       is 8 slow/blocked network attempts in a row.

       It is now ONE combined request, and it comes in two
       sizes:

       - "full"  : every category (used for normal city-size
                   searches, which the public mirrors handle
                   fine).
       - "light" : fewer, higher-value categories (used for
                   broad state/country searches, or as an
                   automatic retry when the full query times
                   out / gets a 504 - large radius + every
                   category at once is what overloads the
                   free public mirrors).
       ============================================= */

    $buildOverpassQuery = function (
        $radius,
        $latitude,
        $longitude,
        $light = false
    ) {

        $timeoutSeconds = $light ? 10 : 14;

        $clauses = [];

        $clauses[] =
            'nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')'
            . '[tourism~"attraction|museum|gallery|viewpoint|zoo|theme_park|aquarium"];';

        $clauses[] =
            'nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[historic];';

        $clauses[] =
            'nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[amenity=place_of_worship];';

        $clauses[] =
            'nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[natural~"beach|peak|waterfall|spring|cliff|valley|wood|forest"];';

        /* Food is always included, even in "light" mode -
           without it there is nothing for the lunch-break
           logic to pick a real restaurant/cafe from, and
           the itinerary falls back to a generic "Lunch
           Break" placeholder with no actual place. */

        $clauses[] =
            'nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[amenity~"restaurant|cafe|fast_food' . ($light ? '' : '|food_court') . '"];';

        if (!$light) {
            $clauses[] =
                'nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[leisure~"park|garden"];';

            $clauses[] =
                'nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[amenity~"cinema|theatre|arts_centre"];';

            $clauses[] =
                'nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[shop~"mall|department_store|market|supermarket|souvenir|gift"];';

            $clauses[] =
                'nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[leisure~"water_park|amusement_arcade"];';
        }

        /* Accommodation - always included, every category. */

        $accommodationTypes = $light
            ? ["hotel", "resort", "guest_house"]
            : ["hotel", "hostel", "guest_house", "motel", "resort", "apartment", "chalet", "camp_site", "alpine_hut"];

        foreach ($accommodationTypes as $type) {
            $clauses[] =
                'nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[tourism=' . $type . '];';
        }

        return
            "[out:json][timeout:" . $timeoutSeconds . "];\n(\n" .
            implode("\n", $clauses) .
            "\n);\nout center tags " . ($light ? 500 : 900) . ";\n";
    };


    $combinedQuery = $buildOverpassQuery(
        $radius,
        $latitude,
        $longitude,
        $isBroadDestination
    );


    /* =============================================
       FETCH FROM OVERPASS - ALL MIRRORS IN PARALLEL
       =============================================

       The old version tried each of the 4 mirror servers
       one after another, each allowed up to 25s + 8s
       connect timeout. If the first couple of mirrors were
       slow, blocked, or unreachable (very common on shared
       college wifi / campus networks / some local XAMPP
       setups), the request could sit there for over a
       minute before finally failing - matching the "takes
       forever and still gives nothing" symptom.

       Firing all mirrors at once with curl_multi and
       returning as soon as ONE succeeds means the real
       wait time is roughly "however long the fastest mirror
       takes", capped by a short timeout, instead of the sum
       of every mirror's timeout.
       ============================================= */

    $fetchOverpassParallel = function ($query, $servers) {

        $multiHandle = curl_multi_init();

        $channels = [];

        foreach ($servers as $url) {

            $ch = curl_init();

            curl_setopt_array($ch, [

                CURLOPT_URL => $url,

                CURLOPT_POST => true,

                CURLOPT_POSTFIELDS => http_build_query([
                    "data" => $query
                ]),

                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_TIMEOUT => 12,

                CURLOPT_CONNECTTIMEOUT => 3,

                CURLOPT_FOLLOWLOCATION => true,

                CURLOPT_HTTPHEADER => [

                    "Content-Type: application/x-www-form-urlencoded",

                    "User-Agent: WanderAI-Travel-Itinerary-Optimizer/1.0",

                    "Accept: application/json"

                ]

            ]);

            curl_multi_add_handle($multiHandle, $ch);

            $channels[(int)$ch] = [
                "handle" => $ch,
                "url" => $url
            ];
        }


        $serverErrors = [];

        $running = null;

        /* Kick off all requests. */

        do {

            $status = curl_multi_exec(
                $multiHandle,
                $running
            );

        } while ($status === CURLM_CALL_MULTI_PERFORM);


        /* Hard cap: never wait more than ~20s in total,
           no matter how many mirrors are unresponsive. */

        $deadline = microtime(true) + 12;

        while (
            $running > 0 &&
            microtime(true) < $deadline
        ) {

            curl_multi_select($multiHandle, 1.0);

            do {

                $status = curl_multi_exec(
                    $multiHandle,
                    $running
                );

            } while ($status === CURLM_CALL_MULTI_PERFORM);


            /* As soon as any handle finishes, check it. */

            while (
                $info = curl_multi_info_read($multiHandle)
            ) {

                $ch = $info["handle"];

                $meta = $channels[(int)$ch]
                    ?? ["url" => "unknown"];

                $httpCode = curl_getinfo(
                    $ch,
                    CURLINFO_HTTP_CODE
                );

                $curlError = curl_error($ch);

                $result = curl_multi_getcontent($ch);


                if (
                    $result !== false &&
                    $result !== null &&
                    $httpCode >= 200 &&
                    $httpCode < 300
                ) {

                    $decoded = json_decode(
                        $result,
                        true
                    );

                    if (
                        is_array($decoded) &&
                        isset($decoded["elements"]) &&
                        is_array($decoded["elements"])
                    ) {

                        /* Success! Clean up every handle
                           and return immediately without
                           waiting for the slower mirrors. */

                        foreach ($channels as $c) {
                            curl_multi_remove_handle(
                                $multiHandle,
                                $c["handle"]
                            );
                            curl_close($c["handle"]);
                        }

                        curl_multi_close($multiHandle);

                        return [
                            "success" => true,
                            "elements" => $decoded["elements"],
                            "server" => $meta["url"]
                        ];
                    }
                }


                $errorMessage =
                    $meta["url"] .
                    " - HTTP " .
                    $httpCode;

                if (!empty($curlError)) {
                    $errorMessage .= ". " . $curlError;
                }

                $serverErrors[] = $errorMessage;

                curl_multi_remove_handle(
                    $multiHandle,
                    $ch
                );

                curl_close($ch);

                unset($channels[(int)$ch]);
            }
        }


        /* Timed out or every mirror failed - clean up
           whatever is left. */

        foreach ($channels as $c) {
            curl_multi_remove_handle(
                $multiHandle,
                $c["handle"]
            );
            curl_close($c["handle"]);
        }

        curl_multi_close($multiHandle);

        return [
            "success" => false,
            "elements" => [],
            "errors" => $serverErrors
        ];
    };


    /* =============================================
       FETCH PLACES + ACCOMMODATION
       =============================================

       Try the appropriately-sized query first. If every
       mirror fails (504 / timeout / DNS issue - all seen in
       the wild with these free public servers), automatically
       retry once with the smaller "light" query, which is
       far less likely to make an already-struggling server
       time out again.
       ============================================= */

    $combinedResponse = $fetchOverpassParallel(
        $combinedQuery,
        $servers
    );

    if (!$combinedResponse["success"] && !$isBroadDestination) {

        $lightQuery = $buildOverpassQuery(
            $radius,
            $latitude,
            $longitude,
            true
        );

        $retryResponse = $fetchOverpassParallel(
            $lightQuery,
            $servers
        );

        if ($retryResponse["success"]) {
            $combinedResponse = $retryResponse;
        } else {
            $combinedResponse["errors"] = array_merge(
                $combinedResponse["errors"] ?? [],
                $retryResponse["errors"] ?? []
            );
        }

    } elseif (!$combinedResponse["success"] && $isBroadDestination) {

        /* Already tried the light query - retry once more
           with a smaller radius as a last resort. */

        $smallerRadius = max(15000, (int)($radius / 2));

        $fallbackQuery = $buildOverpassQuery(
            $smallerRadius,
            $latitude,
            $longitude,
            true
        );

        $retryResponse = $fetchOverpassParallel(
            $fallbackQuery,
            $servers
        );

        if ($retryResponse["success"]) {
            $combinedResponse = $retryResponse;
        } else {
            $combinedResponse["errors"] = array_merge(
                $combinedResponse["errors"] ?? [],
                $retryResponse["errors"] ?? []
            );
        }
    }


    /* =============================================
       CHECK RESULTS
       ============================================= */

    if (!$combinedResponse["success"]) {

        @unlink($cacheFile . ".lock");

        /* -----------------------------------------------
           STALE-CACHE FALLBACK
           -----------------------------------------------

           If an older cached copy of this area exists - even
           an expired one - use it instead of failing. Slightly
           out-of-date map data is far better for the user than
           an error page after a long wait, and it keeps the
           page fast when the public mirrors are down.
           ----------------------------------------------- */

        if (is_file($cacheFile)) {

            $stale = json_decode(
                @file_get_contents($cacheFile),
                true
            );

            if (
                is_array($stale) &&
                !empty($stale["elements"])
            ) {

                $elements = $stale["elements"];

                $placesResponse = ["success" => true];
                $accommodationResponse = ["success" => true];

                goto placesCacheHit;
            }
        }

        return [

            "success" => false,

            "message" =>
                "Unable to fetch places right now. The public map " .
                "data servers are temporarily overloaded or " .
                "unreachable from this network. Please wait a " .
                "minute and click Regenerate. (" .
                implode(
                    " | ",
                    $combinedResponse["errors"] ?? []
                ) .
                ")"

        ];

    }

    $placesResponse = $combinedResponse;

    $accommodationResponse = $combinedResponse;


    /* =============================================
       RESULTS
       ============================================= */

    $elements = $combinedResponse["elements"] ?? [];


    /* =============================================
       MULTI-POINT SAMPLING FOR BROAD DESTINATIONS
       =============================================

       A whole state/region geocodes to ONE central point.
       Searching only a ~40km circle around that single
       point covers a tiny fraction of the region, so very
       few places get found overall - which is why an
       itinerary for somewhere like "Jammu and Kashmir,
       India" could end up with barely enough places to
       fill even one per day, no matter how much daylight
       is available.

       For broad destinations, also sample a few extra
       points spread around the center to pick up places
       from other parts of the region. The mirror that
       already answered successfully is reused directly
       (a single fast request) instead of re-racing all 4
       mirrors for every extra point.
       ============================================= */

    if ($isBroadDestination && !empty($combinedResponse["server"])) {

        $stickyServer = $combinedResponse["server"];

        /* -----------------------------------------------
           All extra sample points are fetched AT THE SAME
           TIME with curl_multi. The old version fetched them
           one after another (4 x up to 15s = up to a full
           extra minute of waiting on broad destinations like
           "Kerala, India"). Now the whole sampling step costs
           roughly one request instead of four.
           ----------------------------------------------- */

        $fetchManyPoints = function (array $queries, $server) {

            $multi = curl_multi_init();
            $handles = [];

            foreach ($queries as $query) {

                $ch = curl_init();

                curl_setopt_array($ch, [
                    CURLOPT_URL => $server,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query(["data" => $query]),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 12,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/x-www-form-urlencoded",
                        "User-Agent: WanderAI-Travel-Itinerary-Optimizer/1.0",
                        "Accept: application/json"
                    ]
                ]);

                curl_multi_add_handle($multi, $ch);

                $handles[] = $ch;
            }

            $running = null;

            $deadline = microtime(true) + 13;

            do {

                curl_multi_exec($multi, $running);

                if ($running > 0) {
                    curl_multi_select($multi, 0.5);
                }

            } while ($running > 0 && microtime(true) < $deadline);

            $collected = [];

            foreach ($handles as $ch) {

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $body = curl_multi_getcontent($ch);

                if (
                    $body !== false &&
                    $httpCode >= 200 &&
                    $httpCode < 300
                ) {

                    $decoded = json_decode($body, true);

                    if (
                        is_array($decoded) &&
                        !empty($decoded["elements"])
                    ) {
                        $collected = array_merge(
                            $collected,
                            $decoded["elements"]
                        );
                    }
                }

                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
            }

            curl_multi_close($multi);

            return $collected;
        };


        /* Roughly +/-0.55 degrees ~= 60km. Spreads the
           search into a small cross pattern around the
           center instead of one single circle. */

        $offsetDegrees = 0.55;

        $extraPoints = [
            [$latitude + $offsetDegrees, $longitude],
            [$latitude - $offsetDegrees, $longitude],
            [$latitude, $longitude + $offsetDegrees],
            [$latitude, $longitude - $offsetDegrees]
        ];

        $pointRadius = max(20000, (int)($radius * 0.6));

        $pointQueries = [];

        foreach ($extraPoints as $point) {

            $pointQueries[] = $buildOverpassQuery(
                $pointRadius,
                $point[0],
                $point[1],
                true
            );
        }

        $extraElements = $fetchManyPoints(
            $pointQueries,
            $stickyServer
        );

        if (!empty($extraElements)) {
            $elements = array_merge($elements, $extraElements);
        }
    }


    /* Save to cache for next time. */

    @file_put_contents(
        $cacheFile,
        json_encode(["elements" => $elements])
    );

    @unlink($cacheFile . ".lock");

    placesCacheHit:


    /* =============================================
       PROCESS PLACES
       ============================================= */

    $places = [];

    $usedNames = [];


    foreach ($elements as $element) {

        $tags = $element["tags"] ?? [];


        /* =========================================
           IGNORE UNNAMED PLACES
           ========================================= */

        if (
            empty($tags["name"]) &&
            empty($tags["official_name"])
        ) {

            continue;

        }


        $name =
            trim(
                $tags["name"]
                ??
                $tags["official_name"]
                ??
                ""
            );


        if ($name === "") {

            continue;

        }


        /* =========================================
           GET COORDINATES
           ========================================= */

        if (
            isset($element["lat"]) &&
            isset($element["lon"])
        ) {

            $lat = (float)$element["lat"];
            $lon = (float)$element["lon"];

        }

        elseif (
            isset($element["center"]["lat"]) &&
            isset($element["center"]["lon"])
        ) {

            $lat =
                (float)$element["center"]["lat"];

            $lon =
                (float)$element["center"]["lon"];

        }

        else {

            continue;

        }


        /* =========================================
           LOWERCASE VALUES FOR CHECKING
           ========================================= */

        $lowerName =
            strtolower($name);

        $tourism =
            strtolower(
                trim(
                    $tags["tourism"] ?? ""
                )
            );

        $historic =
            strtolower(
                trim(
                    $tags["historic"] ?? ""
                )
            );

        $natural =
            strtolower(
                trim(
                    $tags["natural"] ?? ""
                )
            );

        $leisure =
            strtolower(
                trim(
                    $tags["leisure"] ?? ""
                )
            );

        $amenity =
            strtolower(
                trim(
                    $tags["amenity"] ?? ""
                )
            );

        $shop =
            strtolower(
                trim(
                    $tags["shop"] ?? ""
                )
            );


        /* =========================================
           IDENTIFY ACCOMMODATION
           ========================================= */

        $accommodationTypes = [

            "hotel",
            "hostel",
            "guest_house",
            "motel",
            "apartment",
            "resort",
            "chalet",
            "camp_site",
            "alpine_hut"

        ];


        $isAccommodation =
            in_array(
                $tourism,
                $accommodationTypes,
                true
            );


        /* =========================================
           FILTER GENERIC / NON-TOURIST PLACES
           ========================================= */

        if (!$isAccommodation) {

            $blockedWords = [

                "akshaya",
                "bank",
                "atm",
                "post office",
                "police station",
                "police",
                "hospital",
                "clinic",
                "pharmacy",
                "school",
                "college",
                "university",
                "office",
                "government",
                "panchayath office",
                "administrative",
                "bus stop",
                "bus station",
                "railway station",
                "parking",
                "petrol",
                "fuel station",
                "service centre",
                "service center",
                "mobile shop",
                "telecom",
                "warehouse",
                "hardware",
                "electrician",
                "plumber"
            ];


            $isBlocked = false;


            foreach ($blockedWords as $word) {

                if (
                    strpos(
                        $lowerName,
                        $word
                    ) !== false
                ) {

                    $isBlocked = true;
                    break;

                }

            }


            if ($isBlocked) {

                continue;

            }

        }


        /* =========================================
           DETERMINE CATEGORY
           ========================================= */

        $category =
            "Tourist Attraction";


        /* ACCOMMODATION */

        if ($isAccommodation) {

            $category =
                "Accommodation";

        }


        /* RELIGIOUS */

        elseif (
            $amenity === "place_of_worship"
        ) {

            $category =
                "Religious";

        }


        /* BEACH */

        elseif (
            $natural === "beach"
        ) {

            $category =
                "Beaches";

        }


        /* NATURE */

        elseif (
            in_array(
                $natural,
                [
                    "waterfall",
                    "spring",
                    "cliff",
                    "valley",
                    "peak",
                    "wood",
                    "forest"
                ],
                true
            )
        ) {

            $category =
                "Nature & Scenic";

        }


        /* PARKS */

        elseif (
            in_array(
                $leisure,
                [
                    "park",
                    "garden"
                ],
                true
            )
        ) {

            $category =
                "Parks";

        }


        /* WATER PARK / AMUSEMENT */

        elseif (
            in_array(
                $leisure,
                [
                    "water_park",
                    "amusement_arcade"
                ],
                true
            )
        ) {

            $category =
                "Entertainment";

        }


        /* FOOD */

        elseif (
            in_array(
                $amenity,
                [
                    "restaurant",
                    "cafe",
                    "fast_food",
                    "food_court"
                ],
                true
            )
        ) {

            $category =
                "Food";

        }


        /* ENTERTAINMENT */

        elseif (
            in_array(
                $amenity,
                [
                    "cinema",
                    "theatre",
                    "arts_centre"
                ],
                true
            )
        ) {

            $category =
                "Entertainment";

        }


        /* SHOPPING */

        elseif (
            $shop !== ""
        ) {

            $category =
                "Shopping";

        }


        /* HISTORICAL */

        elseif (
            $historic !== "" ||
            isset($tags["heritage"])
        ) {

            $category =
                "Historical & Cultural";

        }


        /* TOURISM */

        elseif ($tourism !== "") {

            switch ($tourism) {

                case "museum":

                    $category =
                        "Historical & Cultural";

                    break;


                case "gallery":

                    $category =
                        "Historical & Cultural";

                    break;


                case "viewpoint":

                    $category =
                        "Nature & Scenic";

                    break;


                case "zoo":

                    $category =
                        "Entertainment";

                    break;


                case "theme_park":

                    $category =
                        "Entertainment";

                    break;


                case "aquarium":

                    $category =
                        "Entertainment";

                    break;


                case "attraction":

                    $category =
                        "Tourist Attraction";

                    break;


                default:

                    $category =
                        "Tourist Attraction";

                    break;

            }

        }


        /* =========================================
           CHECK DUPLICATES
           ========================================= */

        $nameKey =
            strtolower(
                preg_replace(
                    "/\s+/",
                    " ",
                    $name
                )
            );


        if (
            isset($usedNames[$nameKey])
        ) {

            continue;

        }


        $usedNames[$nameKey] = true;


        /* =========================================
           STORE PLACE
           ========================================= */

        $places[] = [

            "name" =>
                $name,

            "category" =>
                $category,

            "latitude" =>
                $lat,

            "longitude" =>
                $lon,

            "website" =>
                $tags["website"]
                ??
                $tags["contact:website"]
                ??
                "",

            "fee" =>
                $tags["fee"]
                ??
                "",

            "opening_hours" =>
                $tags["opening_hours"]
                ??
                "",

            "phone" =>
                $tags["phone"]
                ??
                $tags["contact:phone"]
                ??
                "",

            "address" =>
                $tags["addr:full"]
                ??
                $tags["addr:street"]
                ??
                $tags["addr:place"]
                ??
                "",

            "cuisine" =>
                $tags["cuisine"]
                ??
                "",

            "stars" =>
                $tags["stars"]
                ??
                "",

            "description" =>
                $tags["description"]
                ??
                ""

        ];


        /* =========================================
           SAFETY LIMIT
           ========================================= */

        /*
           Do not stop while processing the first 150/200 results.
           Overpass result order is not a recommendation order and
           stopping here can fill the result with nearby duplicates
           from one category (for example several waterfalls).
           We collect a larger pool first and apply a deterministic
           final limit after processing.
        */

        if (count($places) >= 500) {
            break;
        }

    }


    /* =============================================
       FINAL DYNAMIC CLEANUP / LIMIT
       ============================================= */

    /*
       Keep the nearest useful places first, while preserving
       category diversity. This prevents the raw Overpass order
       from deciding which 200 places the recommendation engine sees.
    */

    $categoryPriority = [
        "Nature & Scenic" => 1,
        "Historical & Cultural" => 2,
        "Religious" => 3,
        "Beaches" => 4,
        "Parks" => 5,
        "Entertainment" => 6,
        "Tourist Attraction" => 7,
        "Shopping" => 8,
        "Food" => 9,
        "Accommodation" => 10
    ];

    foreach ($places as &$placeItem) {
        $pLat = (float)($placeItem["latitude"] ?? 0);
        $pLon = (float)($placeItem["longitude"] ?? 0);

        $lat1 = deg2rad($latitude);
        $lat2 = deg2rad($pLat);
        $dLat = deg2rad($pLat - $latitude);
        $dLon = deg2rad($pLon - $longitude);

        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos($lat1) * cos($lat2)
           * sin($dLon / 2) * sin($dLon / 2);

        $a = min(1, max(0, $a));
        $distanceKm = 6371 * 2 * asin(sqrt($a));

        $placeItem["distance_km"] = round($distanceKm, 2);
        $placeItem["_category_priority"] = $categoryPriority[$placeItem["category"] ?? "Tourist Attraction"] ?? 99;
    }
    unset($placeItem);

    usort($places, function ($a, $b) {
        $da = (float)($a["distance_km"] ?? 999999);
        $db = (float)($b["distance_km"] ?? 999999);

        if ($da != $db) {
            return $da <=> $db;
        }

        return strcmp(
            strtolower((string)($a["name"] ?? "")),
            strtolower((string)($b["name"] ?? ""))
        );
    });

    /*
       First pass: take nearby places, but do not let one category
       consume the entire result set.
    */
    $finalPlaces = [];
    $categoryCounts = [];
    $maxPerCategory = $isBroadDestination ? 45 : 35;

    foreach ($places as $placeItem) {
        $cat = $placeItem["category"] ?? "Tourist Attraction";
        $currentCount = $categoryCounts[$cat] ?? 0;

        if ($currentCount >= $maxPerCategory) {
            continue;
        }

        $finalPlaces[] = $placeItem;
        $categoryCounts[$cat] = $currentCount + 1;

        if (count($finalPlaces) >= ($isBroadDestination ? 200 : 150)) {
            break;
        }
    }

    /*
       If category balancing left the pool short, fill the remaining
       slots with the nearest unused places.
    */
    if (count($finalPlaces) < ($isBroadDestination ? 200 : 150)) {
        $existingKeys = [];

        foreach ($finalPlaces as $existingPlace) {
            $existingKey = strtolower(trim((string)($existingPlace["name"] ?? "")));
            $existingKeys[$existingKey] = true;
        }

        foreach ($places as $placeItem) {
            $existingKey = strtolower(trim((string)($placeItem["name"] ?? "")));

            if (isset($existingKeys[$existingKey])) {
                continue;
            }

            $finalPlaces[] = $placeItem;
            $existingKeys[$existingKey] = true;

            if (count($finalPlaces) >= ($isBroadDestination ? 200 : 150)) {
                break;
            }
        }
    }

    foreach ($finalPlaces as &$finalPlace) {
        unset($finalPlace["_category_priority"]);
    }
    unset($finalPlace);

    $places = $finalPlaces;


    /* =============================================
       SEPARATE ACCOMMODATION COUNT
       ============================================= */

    $accommodationCount = 0;

    foreach ($places as $place) {

        if (
            ($place["category"] ?? "")
            ===
            "Accommodation"
        ) {

            $accommodationCount++;

        }

    }


    /* =============================================
       RETURN RESULTS
       ============================================= */

    return [

        "success" =>
            true,

        "places" =>
            $places,

        "accommodation_count" =>
            $accommodationCount,

        "places_api_success" =>
            $placesResponse["success"],

        "accommodation_api_success" =>
            $accommodationResponse["success"],

        "search_areas_used" =>
            1,

        "broad_destination" =>
            $isBroadDestination,

        "destination" =>
            $destination

    ];

}

?>