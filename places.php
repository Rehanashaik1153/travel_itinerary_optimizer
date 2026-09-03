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
       dynamic search radius than a normal city destination.
    */

    if ($isBroadDestination) {
        $radius = max($radius, 50000);
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
       QUERY 1 - TOURIST / PLACES
       ============================================= */

    $placesQuery = '
[out:json][timeout:20];

(
    /* Tourist attractions */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[tourism~"attraction|museum|gallery|viewpoint|zoo|theme_park|aquarium"];

    /* Historical places */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[historic];

    /* Religious places */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[amenity=place_of_worship];

    /* Parks and gardens */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[leisure~"park|garden"];

    /* Nature */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[natural~"beach|peak|waterfall|spring|cliff|valley|wood|forest"];

    /* Food */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[amenity~"restaurant|cafe|fast_food|food_court"];

    /* Entertainment */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[amenity~"cinema|theatre|arts_centre"];

    /* Shopping */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[shop~"mall|department_store|market|supermarket|souvenir|gift"];

    /* Water / amusement */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[leisure~"water_park|amusement_arcade"];

);

out center tags;
';


    /* =============================================
       QUERY 2 - ACCOMMODATION
       ============================================= */

    $accommodationQuery = '
[out:json][timeout:20];

(
    /* Hotels */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[tourism=hotel];

    /* Hostels */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[tourism=hostel];

    /* Guest houses */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[tourism=guest_house];

    /* Motels */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[tourism=motel];

    /* Resorts */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[tourism=resort];

    /* Apartments */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[tourism=apartment];

    /* Chalets */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[tourism=chalet];

    /* Campsites */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[tourism=camp_site];

    /* Alpine huts / lodges */
    nwr(
        around:' . $radius . ',' . $latitude . ',' . $longitude . '
    )[tourism=alpine_hut];

);

out center tags;
';


    /* =============================================
       FETCH FROM OVERPASS
       ============================================= */

    $fetchOverpass = function($query, $servers) {

        $serverErrors = [];

        foreach ($servers as $url) {

            $ch = curl_init();

            curl_setopt_array($ch, [

                CURLOPT_URL => $url,

                CURLOPT_POST => true,

                CURLOPT_POSTFIELDS => http_build_query([
                    "data" => $query
                ]),

                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_TIMEOUT => 25,

                CURLOPT_CONNECTTIMEOUT => 8,

                CURLOPT_FOLLOWLOCATION => true,

                CURLOPT_HTTPHEADER => [

                    "Content-Type: application/x-www-form-urlencoded",

                    "User-Agent: WanderAI-Travel-Itinerary-Optimizer/1.0",

                    "Accept: application/json"

                ]

            ]);


            $result = curl_exec($ch);

            $httpCode = curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

            $curlError = curl_error($ch);

            curl_close($ch);


            /* Successful response */

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
                        "elements" => $decoded["elements"]
                    ];

                }

            }


            /* Store error */

            $errorMessage =
                $url .
                " - HTTP " .
                $httpCode;

            if (!empty($curlError)) {

                $errorMessage .=
                    ". " .
                    $curlError;

            }

            $serverErrors[] = $errorMessage;

        }


        return [

            "success" => false,

            "elements" => [],

            "errors" => $serverErrors

        ];

    };


    /* =============================================
       FETCH TOURIST PLACES
       ============================================= */

    $placesResponse = $fetchOverpass(
        $placesQuery,
        $servers
    );


    /* =============================================
       FETCH ACCOMMODATION SEPARATELY
       ============================================= */

    $accommodationResponse = $fetchOverpass(
        $accommodationQuery,
        $servers
    );


    /* =============================================
       CHECK RESULTS
       ============================================= */

    if (
        !$placesResponse["success"] &&
        !$accommodationResponse["success"]
    ) {

        $allErrors = array_merge(

            $placesResponse["errors"] ?? [],

            $accommodationResponse["errors"] ?? []

        );

        return [

            "success" => false,

            "message" =>
                "Unable to fetch places and accommodation right now. " .
                implode(" | ", $allErrors)

        ];

    }


    /* =============================================
       COMBINE RESULTS
       ============================================= */

    $elements = array_merge(

        $placesResponse["elements"] ?? [],

        $accommodationResponse["elements"] ?? []

    );


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