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
        $radius = max($radius, 20000);
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
        "%s_%.3f_%.3f_%d",
        $isBroadDestination ? "broad" : "local",
        round($latitude, 3),
        round($longitude, 3),
        $radius
    );

    $cacheFile =
        $cacheDir . "/" . md5($cacheKey) . ".json";

    $cacheMaxAgeSeconds = 6 * 60 * 60; // 6 hours

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

        $timeoutSeconds = $light ? 12 : 15;

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
            "\n);\nout center tags;\n";
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

                CURLOPT_TIMEOUT => 10,

                CURLOPT_CONNECTTIMEOUT => 4,

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

        $deadline = microtime(true) + 20;

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

    /* Some PHP/XAMPP installations have cURL but do not have
       the cURL multi extension enabled. Keep the project usable
       there instead of throwing a fatal error. */

    if (function_exists("curl_multi_init")) {

        $combinedResponse = $fetchOverpassParallel(
            $combinedQuery,
            $servers
        );

    } elseif (function_exists("curl_init")) {

        $singleFetch = function ($query, $url) {

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    "data" => $query
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/x-www-form-urlencoded",
                    "User-Agent: WanderAI-Travel-Itinerary-Optimizer/1.0",
                    "Accept: application/json"
                ]
            ]);

            $result = curl_exec($ch);
            $httpCode = (int)curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );
            $error = curl_error($ch);
            curl_close($ch);

            if (
                $result !== false &&
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

                    return [
                        "success" => true,
                        "elements" => $decoded["elements"],
                        "server" => $url
                    ];
                }
            }

            return [
                "success" => false,
                "elements" => [],
                "errors" => [
                    $url .
                    " - HTTP " .
                    $httpCode .
                    (
                        $error !== ""
                        ? ". " . $error
                        : ""
                    )
                ]
            ];
        };

        $combinedResponse = $singleFetch(
            $combinedQuery,
            $servers[0]
        );

    } else {

        /* No cURL extension: skip Overpass and let the
           Nominatim fallback below provide dynamic data. */

        $combinedResponse = [
            "success" => false,
            "elements" => [],
            "errors" => [
                "PHP cURL extension is not enabled."
            ]
        ];
    }

    if (!$combinedResponse["success"] && !$isBroadDestination) {

        $lightQuery = $buildOverpassQuery(
            $radius,
            $latitude,
            $longitude,
            true
        );

        if (function_exists("curl_multi_init")) {
            $retryResponse = $fetchOverpassParallel(
                $lightQuery,
                $servers
            );
        } elseif (function_exists("curl_init")) {
            $retryResponse = $singleFetch(
                $lightQuery,
                $servers[0]
            );
        } else {
            $retryResponse = [
                "success" => false,
                "elements" => [],
                "errors" => [
                    "PHP cURL extension is not enabled."
                ]
            ];
        }

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
       FAST FALLBACK - NOMINATIM / OPENSTREETMAP SEARCH
       =============================================

       If every Overpass mirror is unreachable from the user's
       network, do not leave the itinerary empty. Nominatim is a
       separate OpenStreetMap search service and can still return
       named attractions/accommodation for many destinations.

       This is intentionally a small number of sequential requests
       so it remains friendly to the public Nominatim service.
       ============================================= */

    $fetchNominatimFallback = function ($destinationText, $latitude, $longitude) {

        $queries = [
            ["tourist attractions in " . $destinationText, "Tourist Attraction"],
            ["places to visit in " . $destinationText, "Tourist Attraction"],
            ["hotels in " . $destinationText, "Accommodation"],
            ["hostels in " . $destinationText, "Accommodation"],
            ["guest houses in " . $destinationText, "Accommodation"],
            ["restaurants in " . $destinationText, "Food"],
            ["parks in " . $destinationText, "Parks"],
            ["temples churches mosques in " . $destinationText, "Religious"],
            ["shopping in " . $destinationText, "Shopping"]
        ];

        $elements = [];
        $seen = [];

        foreach ($queries as $queryInfo) {

            $queryText = $queryInfo[0];
            $forcedCategory = $queryInfo[1];

            $url =
                "https://nominatim.openstreetmap.org/search?" .
                http_build_query([
                    "q" => $queryText,
                    "format" => "jsonv2",
                    "limit" => 15,
                    "addressdetails" => 1,
                    "extratags" => 1,
                    "namedetails" => 1
                ]);

            $raw = false;
            $httpCode = 0;

            if (function_exists("curl_init")) {

                $ch = curl_init();

                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTPHEADER => [
                        "User-Agent: WanderAI-Travel-Itinerary-Optimizer/1.0 (travel itinerary project)",
                        "Accept: application/json"
                    ]
                ]);

                $raw = curl_exec($ch);
                $httpCode = (int)curl_getinfo(
                    $ch,
                    CURLINFO_HTTP_CODE
                );

                curl_close($ch);

            } else {

                $context = stream_context_create([
                    "http" => [
                        "method" => "GET",
                        "timeout" => 5,
                        "header" =>
                            "User-Agent: WanderAI-Travel-Itinerary-Optimizer/1.0 (travel itinerary project)\r\n" .
                            "Accept: application/json\r\n"
                    ]
                ]);

                $raw = @file_get_contents(
                    $url,
                    false,
                    $context
                );

                if ($raw !== false) {
                    $httpCode = 200;
                }
            }

            if (
                $raw === false ||
                $httpCode < 200 ||
                $httpCode >= 300
            ) {
                continue;
            }

            $items = json_decode($raw, true);

            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {

                $name =
                    trim(
                        (string)(
                            $item["name"]
                            ?? ""
                        )
                    );

                if ($name === "") {

                    $displayName =
                        trim(
                            (string)(
                                $item["display_name"]
                                ?? ""
                            )
                        );

                    if ($displayName !== "") {
                        $parts = explode(",", $displayName);
                        $name = trim($parts[0]);
                    }
                }

                if ($name === "") {
                    continue;
                }

                $lat =
                    isset($item["lat"])
                    ? (float)$item["lat"]
                    : 0;

                $lon =
                    isset($item["lon"])
                    ? (float)$item["lon"]
                    : 0;

                if ($lat == 0 || $lon == 0) {
                    continue;
                }

                $nameKey =
                    strtolower(
                        preg_replace(
                            "/\s+/",
                            " ",
                            $name
                        )
                    );

                if (isset($seen[$nameKey])) {
                    continue;
                }

                $seen[$nameKey] = true;

                $address =
                    trim(
                        (string)(
                            $item["display_name"]
                            ?? ""
                        )
                    );

                $extratags =
                    is_array($item["extratags"] ?? null)
                    ? $item["extratags"]
                    : [];

                $category = $forcedCategory;

                /* Refine the broad Nominatim query using
                   whatever OSM metadata was returned. */

                $osmType =
                    strtolower(
                        (string)(
                            $extratags["tourism"]
                            ?? $item["type"]
                            ?? ""
                        )
                    );

                $amenity =
                    strtolower(
                        (string)(
                            $extratags["amenity"]
                            ?? ""
                        )
                    );

                $natural =
                    strtolower(
                        (string)(
                            $extratags["natural"]
                            ?? ""
                        )
                    );

                $leisure =
                    strtolower(
                        (string)(
                            $extratags["leisure"]
                            ?? ""
                        )
                    );

                $shop =
                    strtolower(
                        (string)(
                            $extratags["shop"]
                            ?? ""
                        )
                    );

                if (
                    in_array(
                        $osmType,
                        [
                            "hotel",
                            "hostel",
                            "guest_house",
                            "motel",
                            "resort",
                            "apartment",
                            "chalet",
                            "camp_site",
                            "alpine_hut"
                        ],
                        true
                    )
                ) {
                    $category = "Accommodation";
                } elseif ($amenity === "place_of_worship") {
                    $category = "Religious";
                } elseif ($natural === "beach") {
                    $category = "Beaches";
                } elseif (
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
                    $category = "Nature & Scenic";
                } elseif (
                    in_array(
                        $leisure,
                        ["park", "garden"],
                        true
                    )
                ) {
                    $category = "Parks";
                } elseif ($shop !== "") {
                    $category = "Shopping";
                } elseif (
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
                    $category = "Food";
                }

                $elements[] = [
                    "lat" => $lat,
                    "lon" => $lon,
                    "tags" => [
                        "name" => $name,
                        "tourism" => (
                            $category === "Accommodation"
                            ? (
                                $osmType !== ""
                                ? $osmType
                                : "hotel"
                            )
                            : (
                                $osmType !== ""
                                ? $osmType
                                : ""
                            )
                        ),
                        "amenity" => $amenity,
                        "natural" => $natural,
                        "leisure" => $leisure,
                        "shop" => $shop,
                        "website" =>
                            $extratags["website"]
                            ?? "",
                        "opening_hours" =>
                            $extratags["opening_hours"]
                            ?? "",
                        "phone" =>
                            $extratags["phone"]
                            ?? "",
                        "description" =>
                            $extratags["description"]
                            ?? "",
                        "addr:full" => $address
                    ],
                    "_fallback_category" => $category
                ];
            }
        }

        return $elements;
    };


    /* =============================================
       CHECK RESULTS
       ============================================= */

    if (!$combinedResponse["success"]) {

        $fallbackElements =
            $fetchNominatimFallback(
                $destinationText,
                $latitude,
                $longitude
            );

        if (!empty($fallbackElements)) {

            $elements = $fallbackElements;

            $combinedResponse = [
                "success" => true,
                "elements" => $elements,
                "server" => "Nominatim fallback"
            ];

        } else {

            return [

                "success" => false,

                "message" =>
                    "Unable to fetch places right now. " .
                    "The public map services are unreachable " .
                    "from this network. Please check your " .
                    "internet connection and try Regenerate again."

            ];

        }

    } else {

        $elements = $combinedResponse["elements"] ?? [];

        /* An HTTP-successful response containing no useful
           elements is also treated as a fallback condition. */

        if (count($elements) === 0) {

            $fallbackElements =
                $fetchNominatimFallback(
                    $destinationText,
                    $latitude,
                    $longitude
                );

            if (!empty($fallbackElements)) {
                $elements = $fallbackElements;
                $combinedResponse["elements"] = $elements;
            }
        }
    }

    $placesResponse = $combinedResponse;

    $accommodationResponse = $combinedResponse;


    /* =============================================
       RESULTS
       ============================================= */

    $elements = $combinedResponse["elements"] ?? [];


    /* =============================================
       FAST MODE FOR BROAD DESTINATIONS
       =============================================

       Do not perform several sequential extra searches here.
       Those additional Overpass requests were the main reason
       broad destinations could take a very long time in XAMPP.
       The first successful dynamic result is used immediately.
       ============================================= */

    /* Save to cache for next time. */

    @file_put_contents(
        $cacheFile,
        json_encode(["elements" => $elements])
    );

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


        /* Nominatim fallback can carry an explicit category
           selected from the search query. Use it when OSM tags
           do not provide enough metadata to classify the place. */

        if (!empty($element["_fallback_category"])) {
            $category =
                (string)$element["_fallback_category"];
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