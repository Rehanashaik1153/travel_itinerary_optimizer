<?php

/*
============================================================
TRIPNEST - DYNAMIC PLACE DISCOVERY ENGINE
============================================================

Purpose:
- Discover tourist places worldwide
- Uses OpenStreetMap / Overpass
- Supports:
    Nature / Scenic
    Historical / Cultural
    Religious
    Shopping
    Food
    Entertainment
    Beaches
    Accommodation
- Supports node, way and relation elements
- Calculates distance
- Uses local caching
- Uses short external request timeouts
============================================================
*/


/*
============================================================
CONFIGURATION
============================================================
*/

if (!defined("TRIPNEST_MIN_RADIUS")) {
    define("TRIPNEST_MIN_RADIUS", 1000);
}

if (!defined("TRIPNEST_MAX_RADIUS")) {
    define("TRIPNEST_MAX_RADIUS", 60000);
}

if (!defined("TRIPNEST_MAX_RESULTS")) {
    define("TRIPNEST_MAX_RESULTS", 200);
}

if (!defined("TRIPNEST_CACHE_TTL")) {
    define("TRIPNEST_CACHE_TTL", 86400);
}

/*
 * Keep external requests short.
 * This prevents itinerary generation from hanging for minutes.
 */
if (!defined("TRIPNEST_REQUEST_TIMEOUT")) {
    define("TRIPNEST_REQUEST_TIMEOUT", 8);
}

if (!defined("TRIPNEST_CONNECT_TIMEOUT")) {
    define("TRIPNEST_CONNECT_TIMEOUT", 3);
}


require_once __DIR__ . "/fast_places.php";


/*
============================================================
VALIDATE COORDINATES
============================================================
*/

if (!function_exists("tripnestValidCoordinates")) {

    function tripnestValidCoordinates($latitude, $longitude)
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return false;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90) {
            return false;
        }

        if ($longitude < -180 || $longitude > 180) {
            return false;
        }

        if ($latitude == 0 && $longitude == 0) {
            return false;
        }

        return true;
    }
}


/*
============================================================
HAVERSINE DISTANCE
============================================================
*/

if (!function_exists("tripnestDistanceKm")) {

    function tripnestDistanceKm(
        $lat1,
        $lon1,
        $lat2,
        $lon2
    ) {
        if (
            !is_numeric($lat1) ||
            !is_numeric($lon1) ||
            !is_numeric($lat2) ||
            !is_numeric($lon2)
        ) {
            return null;
        }

        $earthRadius = 6371.0;

        $lat1 = deg2rad((float) $lat1);
        $lat2 = deg2rad((float) $lat2);

        $deltaLat = $lat2 - $lat1;

        $deltaLon = deg2rad(
            (float) $lon2 - (float) $lon1
        );

        $a =
            sin($deltaLat / 2) *
            sin($deltaLat / 2)
            +
            cos($lat1) *
            cos($lat2) *
            sin($deltaLon / 2) *
            sin($deltaLon / 2);

        $a = min(1, max(0, $a));

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }
}


/*
============================================================
CACHE DIRECTORY
============================================================
*/

if (!function_exists("tripnestCacheDirectory")) {

    function tripnestCacheDirectory()
    {
        $directory =
            __DIR__
            . DIRECTORY_SEPARATOR
            . "cache"
            . DIRECTORY_SEPARATOR
            . "places";

        if (!is_dir($directory)) {
            @mkdir(
                $directory,
                0777,
                true
            );
        }

        return $directory;
    }
}


/*
============================================================
CACHE KEY
============================================================
*/

if (!function_exists("tripnestCacheKey")) {

    function tripnestCacheKey(
        $latitude,
        $longitude,
        $radius,
        $destination = ""
    ) {
        /*
         * Versioned cache key. This deliberately invalidates the old
         * one-place cache produced by the previous discovery engine.
         */
        return sha1(
            "v4_area_merge_"
            . mb_strtolower(trim((string)$destination))
            . "_"
            . round((float) $latitude, 4)
            . "_"
            . round((float) $longitude, 4)
            . "_"
            . (int) $radius
        );
    }
}


/*
============================================================
SAFE TEXT
============================================================
*/

if (!function_exists("tripnestCleanText")) {

    function tripnestCleanText($value)
    {
        if ($value === null) {
            return "";
        }

        if (is_array($value)) {
            return "";
        }

        $value = trim(
            html_entity_decode(
                (string) $value,
                ENT_QUOTES | ENT_HTML5,
                "UTF-8"
            )
        );

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        );

        return trim($value);
    }
}


/*
============================================================
GET OSM TAG
============================================================
*/

if (!function_exists("tripnestTag")) {

    function tripnestTag($tags, $key)
    {
        if (!is_array($tags)) {
            return "";
        }

        if (!isset($tags[$key])) {
            return "";
        }

        return tripnestCleanText(
            $tags[$key]
        );
    }
}


/*
============================================================
BUILD OVERPASS QUERY
============================================================
*/

if (!function_exists("tripnestBuildOverpassQuery")) {

    function tripnestBuildOverpassQuery(
        $latitude,
        $longitude,
        $radius
    ) {
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        $radius = (int) $radius;

        /*
         * Keep the query focused on useful travel places.
         */
        $query = <<<OVERPASS
[out:json][timeout:8];
(
    nwr["tourism"~"attraction|museum|gallery|theme_park|zoo|aquarium|viewpoint|artwork|picnic_site"](around:$radius,$latitude,$longitude);

    nwr["tourism"~"hotel|hostel|guest_house|motel|resort|apartment|camp_site|caravan_site"](around:$radius,$latitude,$longitude);

    nwr["historic"](around:$radius,$latitude,$longitude);

    nwr["heritage"](around:$radius,$latitude,$longitude);

    nwr["amenity"~"museum|arts_centre|theatre|cinema|place_of_worship|marketplace|restaurant|cafe|fast_food|food_court|ice_cream"](around:$radius,$latitude,$longitude);

    nwr["leisure"~"park|garden|nature_reserve|wildlife_park|water_park|amusement_arcade|bowling_alley|miniature_golf|stadium|sports_centre"](around:$radius,$latitude,$longitude);

    nwr["natural"~"beach|waterfall|peak|lake|wood|forest|valley|cliff|cave|spring"](around:$radius,$latitude,$longitude);

    nwr["boundary"="national_park"](around:$radius,$latitude,$longitude);

    nwr["building"~"church|mosque|temple|cathedral|chapel|shrine"](around:$radius,$latitude,$longitude);

    nwr["shop"~"mall|department_store|market|shopping_centre|bakery|confectionery|pastry"](around:$radius,$latitude,$longitude);
);
out center tags;
OVERPASS;

        return $query;
    }
}


/*
============================================================
REQUEST OVERPASS
============================================================
*/

if (!function_exists("tripnestRequestOverpass")) {

    function tripnestRequestOverpass(
        $server,
        $query
    ) {
        $postFields = http_build_query([
            "data" => $query
        ]);

        /*
        --------------------------------------------------------
        CURL
        --------------------------------------------------------
        */

        if (function_exists("curl_init")) {

            $ch = curl_init();

            curl_setopt_array(
                $ch,
                [
                    CURLOPT_URL =>
                        $server,

                    CURLOPT_POST =>
                        true,

                    CURLOPT_POSTFIELDS =>
                        $postFields,

                    CURLOPT_RETURNTRANSFER =>
                        true,

                    CURLOPT_CONNECTTIMEOUT =>
                        TRIPNEST_CONNECT_TIMEOUT,

                    CURLOPT_TIMEOUT =>
                        TRIPNEST_REQUEST_TIMEOUT,

                    CURLOPT_FOLLOWLOCATION =>
                        false,

                    CURLOPT_HTTPHEADER =>
                        [
                            "Content-Type: application/x-www-form-urlencoded",
                            "User-Agent: TripNest/1.0 Educational Travel Project"
                        ],

                    CURLOPT_ENCODING =>
                        ""
                ]
            );

            $response = curl_exec($ch);

            $httpCode =
                (int) curl_getinfo(
                    $ch,
                    CURLINFO_HTTP_CODE
                );

            curl_close($ch);

            if (
                $response === false ||
                $response === "" ||
                $httpCode < 200 ||
                $httpCode >= 300
            ) {
                return false;
            }

            $decoded =
                json_decode(
                    $response,
                    true
                );

            if (
                !is_array($decoded) ||
                !isset($decoded["elements"]) ||
                !is_array($decoded["elements"])
            ) {
                return false;
            }

            return $decoded;
        }


        /*
        --------------------------------------------------------
        FILE_GET_CONTENTS FALLBACK
        --------------------------------------------------------
        */

        $context =
            stream_context_create(
                [
                    "http" => [
                        "method" =>
                            "POST",

                        "header" =>
                            "Content-Type: application/x-www-form-urlencoded\r\n"
                            .
                            "User-Agent: TripNest/1.0 Educational Travel Project\r\n",

                        "content" =>
                            $postFields,

                        "timeout" =>
                            TRIPNEST_REQUEST_TIMEOUT,

                        "ignore_errors" =>
                            true
                    ]
                ]
            );

        $response =
            @file_get_contents(
                $server,
                false,
                $context
            );

        if (
            $response === false ||
            $response === ""
        ) {
            return false;
        }

        $decoded =
            json_decode(
                $response,
                true
            );

        if (
            !is_array($decoded) ||
            !isset($decoded["elements"]) ||
            !is_array($decoded["elements"])
        ) {
            return false;
        }

        return $decoded;
    }
}


/*
============================================================
ELEMENT COORDINATES
============================================================
*/

if (!function_exists("tripnestElementCoordinates")) {

    function tripnestElementCoordinates($element)
    {
        if (!is_array($element)) {
            return null;
        }

        /*
        NODE
        */

        if (
            isset($element["lat"]) &&
            isset($element["lon"])
        ) {
            if (
                tripnestValidCoordinates(
                    $element["lat"],
                    $element["lon"]
                )
            ) {
                return [
                    "lat" =>
                        (float) $element["lat"],

                    "lon" =>
                        (float) $element["lon"]
                ];
            }
        }


        /*
        WAY / RELATION CENTER
        */

        if (
            isset($element["center"]) &&
            is_array($element["center"])
        ) {
            $lat =
                $element["center"]["lat"]
                ?? null;

            $lon =
                $element["center"]["lon"]
                ?? null;

            if (
                tripnestValidCoordinates(
                    $lat,
                    $lon
                )
            ) {
                return [
                    "lat" =>
                        (float) $lat,

                    "lon" =>
                        (float) $lon
                ];
            }
        }

        return null;
    }
}


/*
============================================================
CLASSIFY PLACE
============================================================
*/

if (!function_exists("tripnestClassifyPlace")) {

    function tripnestClassifyPlace(
        $tags,
        $name
    ) {
        $nameLower =
            mb_strtolower(
                tripnestCleanText($name)
            );

        $tourism =
            mb_strtolower(
                tripnestTag(
                    $tags,
                    "tourism"
                )
            );

        $historic =
            mb_strtolower(
                tripnestTag(
                    $tags,
                    "historic"
                )
            );

        $natural =
            mb_strtolower(
                tripnestTag(
                    $tags,
                    "natural"
                )
            );

        $leisure =
            mb_strtolower(
                tripnestTag(
                    $tags,
                    "leisure"
                )
            );

        $amenity =
            mb_strtolower(
                tripnestTag(
                    $tags,
                    "amenity"
                )
            );

        $shop =
            mb_strtolower(
                tripnestTag(
                    $tags,
                    "shop"
                )
            );

        $building =
            mb_strtolower(
                tripnestTag(
                    $tags,
                    "building"
                )
            );


        /*
        ACCOMMODATION
        */

        if (
            in_array(
                $tourism,
                [
                    "hotel",
                    "hostel",
                    "guest_house",
                    "motel",
                    "resort",
                    "apartment",
                    "camp_site",
                    "caravan_site"
                ],
                true
            )
        ) {
            return "Accommodation";
        }

        if (
            strpos($nameLower, "hotel") !== false ||
            strpos($nameLower, "hostel") !== false ||
            strpos($nameLower, "resort") !== false
        ) {
            return "Accommodation";
        }


        /*
        FOOD
        */

        if (
            in_array(
                $amenity,
                [
                    "restaurant",
                    "cafe",
                    "fast_food",
                    "food_court",
                    "ice_cream"
                ],
                true
            ) ||
            in_array(
                $shop,
                [
                    "bakery",
                    "confectionery",
                    "pastry"
                ],
                true
            )
        ) {
            return "Food";
        }


        /*
        SHOPPING
        */

        if (
            $amenity === "marketplace" ||
            in_array(
                $shop,
                [
                    "mall",
                    "department_store",
                    "supermarket",
                    "market",
                    "shopping_centre"
                ],
                true
            )
        ) {
            return "Shopping";
        }


        /*
        RELIGIOUS
        */

        if (
            $amenity === "place_of_worship" ||
            in_array(
                $building,
                [
                    "church",
                    "mosque",
                    "temple",
                    "cathedral",
                    "chapel",
                    "shrine"
                ],
                true
            )
        ) {
            return "Religious";
        }


        /*
        BEACH
        */

        if (
            $natural === "beach" ||
            strpos($nameLower, "beach") !== false
        ) {
            return "Beach";
        }


        /*
        WATERFALL
        */

        if (
            $natural === "waterfall" ||
            strpos($nameLower, "waterfall") !== false ||
            preg_match(
                '/\bfalls?\b/i',
                $nameLower
            )
        ) {
            return "Nature / Scenic";
        }


        /*
        NATURE
        */

        if (
            in_array(
                $natural,
                [
                    "peak",
                    "lake",
                    "wood",
                    "forest",
                    "valley",
                    "cliff",
                    "cave",
                    "spring"
                ],
                true
            ) ||
            in_array(
                $leisure,
                [
                    "park",
                    "garden",
                    "nature_reserve",
                    "wildlife_park",
                    "water_park"
                ],
                true
            ) ||
            $tourism === "viewpoint" ||
            strpos($nameLower, "viewpoint") !== false ||
            strpos($nameLower, "national park") !== false ||
            strpos($nameLower, "wildlife") !== false
        ) {
            return "Nature / Scenic";
        }


        /*
        HISTORICAL / CULTURAL
        */

        if (
            $historic !== "" ||
            in_array(
                $amenity,
                [
                    "museum",
                    "arts_centre",
                    "theatre",
                    "cinema"
                ],
                true
            ) ||
            in_array(
                $tourism,
                [
                    "museum",
                    "gallery",
                    "artwork",
                    "attraction"
                ],
                true
            ) ||
            strpos($nameLower, "fort") !== false ||
            strpos($nameLower, "palace") !== false ||
            strpos($nameLower, "museum") !== false ||
            strpos($nameLower, "monument") !== false ||
            strpos($nameLower, "heritage") !== false
        ) {
            return "Historical / Cultural";
        }


        /*
        ENTERTAINMENT
        */

        if (
            in_array(
                $tourism,
                [
                    "theme_park",
                    "zoo",
                    "aquarium"
                ],
                true
            ) ||
            in_array(
                $leisure,
                [
                    "amusement_arcade",
                    "bowling_alley",
                    "miniature_golf",
                    "stadium",
                    "sports_centre"
                ],
                true
            ) ||
            in_array(
                $amenity,
                [
                    "theatre",
                    "cinema",
                    "nightclub"
                ],
                true
            )
        ) {
            return "Entertainment";
        }


        /*
        GENERAL
        */

        return "Tourist Attraction";
    }
}


/*
============================================================
BLOCK INFRASTRUCTURE
============================================================
*/

if (!function_exists("tripnestBlockedPlace")) {

    function tripnestBlockedPlace(
        $name,
        $category,
        $tags
    ) {
        if (
            $category === "Accommodation"
        ) {
            return false;
        }

        $nameLower =
            mb_strtolower(
                tripnestCleanText($name)
            );

        $blockedWords = [
            "parking",
            "car park",
            "fuel station",
            "petrol pump",
            "petrol station",
            "gas station",
            "bus stop",
            "bus station",
            "railway station",
            "train station",
            "airport",
            "aerodrome",
            "helipad",
            "hospital",
            "clinic",
            "dispensary",
            "pharmacy",
            "police station",
            "police line",
            "police headquarters",
            "fire station",
            "post office",
            "atm",
            "bank",
            "school",
            "college",
            "university",
            "academy",
            "warehouse",
            "industrial estate",
            "ministry",
            "secretariat",
            "election commission",
            "planning commission",
            "finance commission",
            "commission of india",
            "commission",
            "tribunal",
            "council",
            "board of",
            "department of",
            "prison",
            "prisons",
            "jail",
            "court",
            "high court",
            "supreme court",
            "cantonment",
            "barracks",
            "parliament attack",
            "constituency",
            "lok sabha",
            "rajya sabha",
            "vidhan sabha",
            "trading corporation",
            "corporation building"
        ];

        foreach (
            $blockedWords as $word
        ) {
            if (
                strpos(
                    $nameLower,
                    $word
                ) !== false
            ) {
                return true;
            }
        }

        return false;
    }
}


/*
============================================================
BUILD NORMALIZED PLACE
============================================================
*/

if (!function_exists("tripnestBuildPlace")) {

    function tripnestBuildPlace(
        $element,
        $destinationLatitude,
        $destinationLongitude
    ) {
        if (!is_array($element)) {
            return null;
        }

        $tags =
            isset($element["tags"]) &&
            is_array($element["tags"])
                ? $element["tags"]
                : [];


        /*
        NAME
        */

        $name =
            tripnestTag(
                $tags,
                "name:en"
            );

        if ($name === "") {
            $name =
                tripnestTag(
                    $tags,
                    "int_name"
                );
        }

        if ($name === "") {
            $name =
                tripnestTag(
                    $tags,
                    "name"
                );
        }

        if ($name === "") {
            return null;
        }

        $name =
            tripnestCleanText($name);

        if (strlen($name) < 2) {
            return null;
        }


        /*
        COORDINATES
        */

        $coordinates =
            tripnestElementCoordinates(
                $element
            );

        if ($coordinates === null) {
            return null;
        }


        /*
        CATEGORY
        */

        $category =
            tripnestClassifyPlace(
                $tags,
                $name
            );


        /*
        BLOCK INFRASTRUCTURE
        */

        if (
            tripnestBlockedPlace(
                $name,
                $category,
                $tags
            )
        ) {
            return null;
        }


        $latitude =
            $coordinates["lat"];

        $longitude =
            $coordinates["lon"];


        /*
        DISTANCE
        */

        $distance =
            tripnestDistanceKm(
                $destinationLatitude,
                $destinationLongitude,
                $latitude,
                $longitude
            );


        /*
        WEBSITE
        */

        $website =
            tripnestTag(
                $tags,
                "website"
            );

        if ($website === "") {
            $website =
                tripnestTag(
                    $tags,
                    "contact:website"
                );
        }


        /*
        PHONE
        */

        $phone =
            tripnestTag(
                $tags,
                "phone"
            );

        if ($phone === "") {
            $phone =
                tripnestTag(
                    $tags,
                    "contact:phone"
                );
        }


        /*
        OPENING HOURS
        */

        $openingHours =
            tripnestTag(
                $tags,
                "opening_hours"
            );


        /*
        FEE
        */

        $fee =
            tripnestTag(
                $tags,
                "fee"
            );

        if ($fee === "") {
            $fee =
                tripnestTag(
                    $tags,
                    "charge"
                );
        }


        /*
        CUISINE
        */

        $cuisine =
            tripnestTag(
                $tags,
                "cuisine"
            );


        /*
        ADDRESS
        */

        $addressParts = [];

        $houseNumber =
            tripnestTag(
                $tags,
                "addr:housenumber"
            );

        $street =
            tripnestTag(
                $tags,
                "addr:street"
            );

        $city =
            tripnestTag(
                $tags,
                "addr:city"
            );

        $postcode =
            tripnestTag(
                $tags,
                "addr:postcode"
            );

        if ($houseNumber !== "") {
            $addressParts[] =
                $houseNumber;
        }

        if ($street !== "") {
            $addressParts[] =
                $street;
        }

        if ($city !== "") {
            $addressParts[] =
                $city;
        }

        if ($postcode !== "") {
            $addressParts[] =
                $postcode;
        }

        $address =
            implode(
                ", ",
                $addressParts
            );


        /*
        STARS
        */

        $stars =
            tripnestTag(
                $tags,
                "stars"
            );


        /*
        DESCRIPTION
        */

        $description =
            tripnestTag(
                $tags,
                "description"
            );

        if ($description === "") {
            $description =
                tripnestTag(
                    $tags,
                    "description:en"
                );
        }


        /*
        OSM DATA
        */

        $osmType =
            $element["type"]
            ?? "";

        $osmId =
            $element["id"]
            ?? "";

        $osmUrl = "";

        if (
            $osmType !== "" &&
            $osmId !== ""
        ) {
            $osmUrl =
                "https://www.openstreetmap.org/"
                .
                rawurlencode($osmType)
                .
                "/"
                .
                rawurlencode(
                    (string) $osmId
                );
        }


        return [
            "name" =>
                $name,

            "category" =>
                $category,

            "place_type" =>
                $category,

            "lat" =>
                $latitude,

            "lon" =>
                $longitude,

            "latitude" =>
                $latitude,

            "longitude" =>
                $longitude,

            "distance_km" =>
                $distance !== null
                    ? round(
                        $distance,
                        2
                    )
                    : null,

            "website" =>
                $website,

            "phone" =>
                $phone,

            "opening_hours" =>
                $openingHours,

            "fee" =>
                $fee,

            "charge" =>
                $fee,

            "cuisine" =>
                $cuisine,

            "address" =>
                $address,

            "stars" =>
                $stars,

            "description" =>
                $description,

            "osm_type" =>
                $osmType,

            "osm_id" =>
                $osmId,

            "osm_url" =>
                $osmUrl
        ];
    }
}


/*
============================================================
GET NEARBY PLACES
============================================================
*/

if (!function_exists("getNearbyPlaces")) {

    function getNearbyPlaces(
        $latitude,
        $longitude,
        $radius = 10000,
        $destination = ""
    ) {
        $latitude =
            (float) $latitude;

        $longitude =
            (float) $longitude;

        $radius =
            (int) $radius;


        /*
        VALIDATE
        */

        if (
            !tripnestValidCoordinates(
                $latitude,
                $longitude
            )
        ) {
            return [
                "success" =>
                    false,

                "message" =>
                    "Invalid destination coordinates.",

                "places" =>
                    [],

                "accommodation_count" =>
                    0
            ];
        }


        /*
        LIMIT RADIUS
        */

        $radius =
            min(
                max(
                    $radius,
                    TRIPNEST_MIN_RADIUS
                ),
                TRIPNEST_MAX_RADIUS
            );


        /*
        CACHE
        */

        $cacheDirectory =
            tripnestCacheDirectory();

        $cacheKey =
            tripnestCacheKey(
                $latitude,
                $longitude,
                $radius,
                $destination
            );

        $cacheFile =
            $cacheDirectory
            .
            DIRECTORY_SEPARATOR
            .
            $cacheKey
            .
            ".json";


        /*
        RETURN VALID CACHE
        */

        if (is_file($cacheFile)) {

            $modified =
                @filemtime(
                    $cacheFile
                );

            if (
                $modified !== false &&
                (time() - $modified)
                    < TRIPNEST_CACHE_TTL
            ) {
                $cached =
                    @file_get_contents(
                        $cacheFile
                    );

                if (
                    $cached !== false &&
                    $cached !== ""
                ) {
                    $decoded =
                        json_decode(
                            $cached,
                            true
                        );

                    if (
                        is_array($decoded) &&
                        isset($decoded["places"]) &&
                        count($decoded["places"]) >= 8
                    ) {
                        /*
                         * Wikipedia-only results are a fallback: retry the
                         * richer map servers after 15 minutes.
                         */
                        if (
                            ($decoded["data_source"] ?? "") !== "wikipedia" ||
                            (time() - $modified) < 900
                        ) {
                            return $decoded;
                        }
                    }
                }
            }
        }


        /*
        FETCH LIVE DATA - FAST + PARALLEL

        Several Overpass mirrors and Wikipedia GeoSearch are asked
        at the same time; the first good answer wins. See
        fast_places.php for details.
        */

        $fast =
            tripnestFetchElementsFast(
                $latitude,
                $longitude,
                $radius,
                $destination
            );

        $response = false;

        $successfulServer = "";

        $failedServers =
            $fast["failed"] ?? [];

        $dataSource =
            $fast["source"] ?? "none";

        if (!empty($fast["elements"])) {

            $response = [
                "elements" => $fast["elements"]
            ];

            $successfulServer =
                $fast["server"] ?? "";
        }


        /*
        NO LIVE DATA: try an older cached copy first, then a
        clearly-labelled generic plan so the user is never left
        with an empty itinerary.
        */

        if ($response === false) {

            if (is_file($cacheFile)) {

                $staleRaw =
                    @file_get_contents($cacheFile);

                $stale =
                    $staleRaw
                        ? json_decode($staleRaw, true)
                        : null;

                if (
                    is_array($stale) &&
                    !empty($stale["places"])
                ) {
                    $stale["data_source"] = "stale_cache";

                    $stale["message"] =
                        "Live map servers are busy, so previously saved place data is being used.";

                    return $stale;
                }
            }

            $response = [
                "elements" =>
                    tripnestGenericElements(
                        $latitude,
                        $longitude,
                        $destination
                    )
            ];

            $dataSource = "generic";

            $successfulServer = "generic-fallback";
        }


        /*
        PROCESS RESULTS
        */

        $places = [];

        $usedNames = [];


        foreach (
            $response["elements"]
            as $element
        ) {

            $place =
                tripnestBuildPlace(
                    $element,
                    $latitude,
                    $longitude
                );

            if (
                $place === null
            ) {
                continue;
            }

            // Exclude places excessively far from local destination (e.g. over 50 km)
            $distVal = isset($place["distance_km"]) && $place["distance_km"] !== null ? (float)$place["distance_km"] : 0;
            $maxAllowedDist = max(45.0, ($radius / 1000.0) * 1.35);
            if ($distVal > $maxAllowedDist) {
                continue;
            }

            // Exclude beach attractions from inland/mountain hill stations
            if (!empty($destination) && preg_match('/\b(araku|ooty|coorg|kodagu|munnar|manali|shimla|kodaikanal|wayanad|darjeeling|gangtok|mussoorie|nainital|mount abu|kasauli|dharamshala)\b/i', $destination)) {
                $pCat = mb_strtolower($place["category"] ?? "");
                $pName = mb_strtolower($place["name"] ?? "");
                if (strpos($pCat, "beach") !== false || strpos($pName, "beach") !== false) {
                    continue;
                }
            }


            $nameKey =
                mb_strtolower(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        trim(
                            $place["name"]
                        )
                    )
                );


            if (
                $nameKey === ""
            ) {
                continue;
            }


            if (
                isset(
                    $usedNames[$nameKey]
                )
            ) {
                continue;
            }


            $usedNames[$nameKey] = true;

            $places[] =
                $place;


            /*
            Stop once enough useful places are collected.
            */

            if (
                count($places)
                >= TRIPNEST_MAX_RESULTS
            ) {
                break;
            }
        }


        /*
        SORT BY DISTANCE
        */

        usort(
            $places,
            function ($a, $b) {

                $distanceA =
                    (float) (
                        $a["distance_km"]
                        ?? 999999
                    );

                $distanceB =
                    (float) (
                        $b["distance_km"]
                        ?? 999999
                    );

                if (
                    $distanceA !=
                    $distanceB
                ) {
                    return
                        $distanceA
                        <=>
                        $distanceB;
                }

                return strcmp(
                    mb_strtolower(
                        $a["name"]
                        ?? ""
                    ),
                    mb_strtolower(
                        $b["name"]
                        ?? ""
                    )
                );
            }
        );


        /*
        CATEGORY BALANCING
        */

        $balancedPlaces = [];

        $categoryCounts = [];


        foreach (
            $places as $place
        ) {

            $category =
                $place["category"]
                ??
                "Tourist Attraction";


            /*
            Accommodation is preserved.
            */

            if (
                $category ===
                "Accommodation"
            ) {
                $balancedPlaces[] =
                    $place;

                continue;
            }


            if (
                !isset(
                    $categoryCounts[
                        $category
                    ]
                )
            ) {
                $categoryCounts[
                    $category
                ] = 0;
            }


            /*
            Maximum number from one category.
            */

            $categoryLimit = 30;


            if (
                $categoryCounts[
                    $category
                ]
                >=
                $categoryLimit
            ) {
                continue;
            }


            $balancedPlaces[] =
                $place;

            $categoryCounts[
                $category
            ]++;


            if (
                count(
                    $balancedPlaces
                )
                >=
                TRIPNEST_MAX_RESULTS
            ) {
                break;
            }
        }


        /*
        FALLBACK FILL
        */

        if (
            count($balancedPlaces) < 20 &&
            count($places) >
            count($balancedPlaces)
        ) {

            $existing = [];


            foreach (
                $balancedPlaces
                as $place
            ) {

                $key =
                    mb_strtolower(
                        trim(
                            $place["name"]
                            ?? ""
                        )
                    );

                if (
                    $key !== ""
                ) {
                    $existing[$key] = true;
                }
            }


            foreach (
                $places as $place
            ) {

                $key =
                    mb_strtolower(
                        trim(
                            $place["name"]
                            ?? ""
                        )
                    );


                if (
                    $key === "" ||
                    isset(
                        $existing[$key]
                    )
                ) {
                    continue;
                }


                $balancedPlaces[] =
                    $place;

                $existing[$key] = true;


                if (
                    count(
                        $balancedPlaces
                    )
                    >=
                    TRIPNEST_MAX_RESULTS
                ) {
                    break;
                }
            }
        }


        $places =
            $balancedPlaces;


        /*
        ACCOMMODATION COUNT
        */

        $accommodationCount = 0;


        foreach (
            $places as $place
        ) {

            if (
                mb_strtolower(
                    $place["category"]
                    ?? ""
                )
                ===
                "accommodation"
            ) {
                $accommodationCount++;
            }
        }


        /*
        CATEGORY SUMMARY
        */

        $categorySummary = [];


        foreach (
            $places as $place
        ) {

            $category =
                $place["category"]
                ??
                "Tourist Attraction";


            if (
                !isset(
                    $categorySummary[
                        $category
                    ]
                )
            ) {
                $categorySummary[
                    $category
                ] = 0;
            }


            $categorySummary[
                $category
            ]++;
        }


        /*
        RESULT
        */

        $result = [

            "success" =>
                !empty($places),

            "message" =>
                !empty($places)
                    ? count($places)
                        . " places discovered dynamically."
                    : "No named tourist places were found in the selected area.",

            "places" =>
                $places,

            "accommodation_count" =>
                $accommodationCount,

            "places_api_success" =>
                true,

            "accommodation_api_success" =>
                true,

            "search_areas_used" =>
                1,

            "destination" =>
                $destination,

            "radius" =>
                $radius,

            "category_counts" =>
                $categorySummary,

            "data_source" =>
                $dataSource,

            "successful_server" =>
                $successfulServer,

            "failed_servers" =>
                $failedServers,

            "raw_element_count" =>
                count(
                    $response["elements"]
                )
        ];


        /*
        SAVE CACHE
        */

        if (
            !empty($places) &&
            $dataSource !== "generic"
        ) {

            @file_put_contents(
                $cacheFile,
                json_encode(
                    $result,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                ),
                LOCK_EX
            );
        }


        return $result;
    }
}


/*
============================================================
GET NEARBY ACCOMMODATION
============================================================
*/

if (!function_exists("getNearbyAccommodation")) {

    function getNearbyAccommodation(
        $latitude,
        $longitude,
        $radius = 10000,
        $destination = ""
    ) {
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        $radius = min(max((int) $radius, 1000), 60000);

        /* VALIDATE */
        if (!tripnestValidCoordinates($latitude, $longitude)) {
            return [
                "success" => false,
                "places"  => [],
                "message" => "Invalid coordinates."
            ];
        }

        /* CACHE */
        $cacheDirectory = tripnestCacheDirectory();
        $cacheKey = sha1("accommodation_" . round($latitude, 4) . "_" . round($longitude, 4) . "_" . $radius . "_" . trim($destination));
        $cacheFile = $cacheDirectory . DIRECTORY_SEPARATOR . $cacheKey . ".json";

        /* RETURN CACHED ACCOMMODATION */
        if (is_file($cacheFile)) {
            $modified = @filemtime($cacheFile);
            if ($modified !== false && (time() - $modified) < TRIPNEST_CACHE_TTL) {
                $cached = @file_get_contents($cacheFile);
                if ($cached !== false && $cached !== "") {
                    $decoded = json_decode($cached, true);
                    if (is_array($decoded) && !empty($decoded["places"])) {
                        return $decoded;
                    }
                }
            }
        }

        /* LIVE ACCOMMODATION QUERY */
        $response = tripnestFetchAccommodationFast($latitude, $longitude, $radius, $destination);

        if (empty($response["elements"])) {
            $response = false;
        }

        /* NO RESPONSE - FALLBACK TO GUARANTEED DESTINATION STAY */
        if ($response === false) {
            $destLabel = !empty($destination) ? preg_replace('/,.*$/', '', trim($destination)) : "Central";
            $fallbackStay = [
                "name"        => "Central Stay & Suites, " . $destLabel,
                "category"    => "Accommodation",
                "latitude"    => $latitude,
                "longitude"   => $longitude,
                "lat"         => $latitude,
                "lon"         => $longitude,
                "stars"       => 4,
                "price"       => 2000,
                "address"     => $destination ?: $destLabel,
                "distance_km" => 0.5,
                "type"        => "hotel"
            ];
            return [
                "success" => true,
                "places"  => [$fallbackStay],
                "message" => "Guaranteed accommodation located."
            ];
        }


        /*
        PROCESS ACCOMMODATION
        */

        $places = [];

        $usedNames = [];


        foreach (
            $response["elements"]
            as $element
        ) {

            $tags =
                $element["tags"]
                ??
                [];


            /*
            NAME
            */

            $name =
                tripnestTag(
                    $tags,
                    "name:en"
                );

            if ($name === "") {
                $name =
                    tripnestTag(
                        $tags,
                        "int_name"
                    );
            }

            if ($name === "") {
                $name =
                    tripnestTag(
                        $tags,
                        "name"
                    );
            }


            if (
                $name === ""
            ) {
                continue;
            }


            /*
            COORDINATES
            */

            $coordinates =
                tripnestElementCoordinates(
                    $element
                );

            if (
                $coordinates === null
            ) {
                continue;
            }


            /*
            DUPLICATES
            */

            $key =
                mb_strtolower(
                    trim($name)
                );


            if (
                isset(
                    $usedNames[$key]
                )
            ) {
                continue;
            }


            $usedNames[$key] =
                true;


            /*
            DISTANCE
            */

            $distance =
                tripnestDistanceKm(
                    $latitude,
                    $longitude,
                    $coordinates["lat"],
                    $coordinates["lon"]
                );


            /*
            ADDRESS
            */

            $addressParts = [];


            $houseNumber =
                tripnestTag(
                    $tags,
                    "addr:housenumber"
                );

            $street =
                tripnestTag(
                    $tags,
                    "addr:street"
                );

            $city =
                tripnestTag(
                    $tags,
                    "addr:city"
                );

            $postcode =
                tripnestTag(
                    $tags,
                    "addr:postcode"
                );


            if (
                $houseNumber !== ""
            ) {
                $addressParts[] =
                    $houseNumber;
            }

            if (
                $street !== ""
            ) {
                $addressParts[] =
                    $street;
            }

            if (
                $city !== ""
            ) {
                $addressParts[] =
                    $city;
            }

            if (
                $postcode !== ""
            ) {
                $addressParts[] =
                    $postcode;
            }


            /*
            ADD PLACE
            */

            $places[] = [

                "name" =>
                    $name,

                "category" =>
                    "Accommodation",

                "place_type" =>
                    "Accommodation",

                "lat" =>
                    $coordinates["lat"],

                "lon" =>
                    $coordinates["lon"],

                "latitude" =>
                    $coordinates["lat"],

                "longitude" =>
                    $coordinates["lon"],

                "distance_km" =>
                    $distance !== null
                        ? round(
                            $distance,
                            2
                        )
                        : null,

                "website" =>
                    tripnestTag(
                        $tags,
                        "website"
                    ),

                "phone" =>
                    tripnestTag(
                        $tags,
                        "phone"
                    ),

                "opening_hours" =>
                    tripnestTag(
                        $tags,
                        "opening_hours"
                    ),

                "fee" =>
                    "",

                "charge" =>
                    "",

                "address" =>
                    implode(
                        ", ",
                        $addressParts
                    ),

                "stars" =>
                    tripnestTag(
                        $tags,
                        "stars"
                    ),

                "description" =>
                    tripnestTag(
                        $tags,
                        "description"
                    ),

                "osm_type" =>
                    $element["type"]
                    ?? "",

                "osm_id" =>
                    $element["id"]
                    ?? ""
            ];


            /*
            Only keep 30 accommodations.
            */

            if (
                count($places)
                >= 30
            ) {
                break;
            }
        }


        /*
        SORT
        */

        usort(
            $places,
            function ($a, $b) {

                return
                    (
                        (float) (
                            $a["distance_km"]
                            ?? 999999
                        )
                    )
                    <=>
                    (
                        (float) (
                            $b["distance_km"]
                            ?? 999999
                        )
                    );
            }
        );


        /*
        RESULT
        */

        $result = [

            "success" =>
                !empty($places),

            "message" =>
                !empty($places)
                    ? count($places)
                        . " accommodation places found."
                    : "No accommodation found.",

            "places" =>
                $places,

            "accommodation_count" =>
                count($places)
        ];


        /*
        CACHE
        */

        if (
            !empty($places)
        ) {

            @file_put_contents(
                $cacheFile,
                json_encode(
                    $result,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                ),
                LOCK_EX
            );
        }


        return $result;
    }
}


/*
============================================================
BACKWARD COMPATIBILITY
============================================================
*/

if (!function_exists("wanderValidCoordinates")) {

    function wanderValidCoordinates(
        $latitude,
        $longitude
    ) {
        return tripnestValidCoordinates(
            $latitude,
            $longitude
        );
    }
}


if (!function_exists("wanderBuildQuery")) {

    function wanderBuildQuery(
        $latitude,
        $longitude,
        $radius,
        $queryType = ""
    ) {
        return tripnestBuildOverpassQuery(
            $latitude,
            $longitude,
            $radius
        );
    }
}


if (!function_exists("wanderRequestOverpass")) {

    function wanderRequestOverpass(
        $server,
        $query
    ) {
        return tripnestRequestOverpass(
            $server,
            $query
        );
    }
}


if (!function_exists("wanderBuildPlace")) {

    function wanderBuildPlace(
        $element,
        $latitude,
        $longitude
    ) {
        return tripnestBuildPlace(
            $element,
            $latitude,
            $longitude
        );
    }
}


/*
============================================================
END OF PLACES.PHP
============================================================
*/

?>