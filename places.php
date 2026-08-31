<?php

/* =====================================================
   WANDERAI - DYNAMIC PLACES FETCHER
   ===================================================== */

function getNearbyPlaces(
    $latitude,
    $longitude,
    $radius = 10000
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
       BUILD OVERPASS QUERY
       ============================================= */

    $query = '
[out:json][timeout:25];

(
    /* Tourist places */
    nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[tourism~"attraction|museum|gallery|viewpoint|zoo|theme_park|aquarium"];

    /* Historical places */
    nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[historic];

    /* Religious places */
    nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[amenity=place_of_worship];

    /* Parks and gardens */
    nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[leisure~"park|garden"];

    /* Nature */
    nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[natural~"beach|peak|waterfall|spring|cliff|valley"];

    /* Food */
    nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[amenity~"restaurant|cafe"];

    /* Entertainment */
    nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[amenity~"cinema|theatre"];

    /* Shopping */
    nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[shop~"mall|department_store|market|souvenir"];

    /* Accommodation */
    nwr(around:' . $radius . ',' . $latitude . ',' . $longitude . ')[tourism~"hotel|hostel|guest_house|motel|apartment|resort|chalet"];
);

out center tags 150;
';


    /* =============================================
       OVERPASS API SERVERS
       ============================================= */

    $servers = [

        "https://overpass-api.de/api/interpreter",

        "https://overpass.private.coffee/api/interpreter",

        "https://maps.mail.ru/osm/tools/overpass/api/interpreter"

    ];


    $response = false;
    $serverErrors = [];


    /* =============================================
       TRY API SERVERS
       ============================================= */

    foreach ($servers as $url) {

        $ch = curl_init();

        curl_setopt_array($ch, [

            CURLOPT_URL => $url,

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => http_build_query([
                "data" => $query
            ]),

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_TIMEOUT => 35,

            CURLOPT_CONNECTTIMEOUT => 10,

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
                isset($decoded["elements"])
            ) {

                $response = $result;
                break;

            }

        }


        /* Save error */

        $errorMessage =
            $url .
            " - HTTP " .
            $httpCode;

        if (!empty($curlError)) {

            $errorMessage .=
                ". Connection error: " .
                $curlError;

        }

        $serverErrors[] = $errorMessage;

    }


    /* =============================================
       API FAILED
       ============================================= */

    if ($response === false) {

        return [

            "success" => false,

            "message" =>
                "Unable to fetch places right now. " .
                implode(" | ", $serverErrors)

        ];

    }


    /* =============================================
       DECODE RESPONSE
       ============================================= */

    $data = json_decode(
        $response,
        true
    );


    if (
        !is_array($data) ||
        !isset($data["elements"])
    ) {

        return [

            "success" => false,

            "message" =>
                "The places service returned an invalid response."

        ];

    }


    /* =============================================
       PROCESS PLACES
       ============================================= */

    $places = [];


    foreach ($data["elements"] as $element) {

        $tags = $element["tags"] ?? [];


        /* Ignore unnamed places */

        if (empty($tags["name"])) {

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

            $lat = (float)$element["center"]["lat"];
            $lon = (float)$element["center"]["lon"];

        }

        else {

            continue;

        }


        /* =========================================
           DETERMINE CATEGORY
           ========================================= */

        $category = "Tourist Attraction";


        /* ACCOMMODATION */

        if (
            isset($tags["tourism"]) &&
            in_array(
                $tags["tourism"],
                [
                    "hotel",
                    "hostel",
                    "guest_house",
                    "motel",
                    "apartment",
                    "resort",
                    "chalet"
                ]
            )
        ) {

            $category = "Accommodation";

        }


        /* RELIGIOUS */

        elseif (
            ($tags["amenity"] ?? "") ===
            "place_of_worship"
        ) {

            $category = "Religious";

        }


        /* BEACH */

        elseif (
            ($tags["natural"] ?? "") === "beach"
        ) {

            $category = "Beaches";

        }


        /* PARKS */

        elseif (
            isset($tags["leisure"]) &&
            in_array(
                $tags["leisure"],
                [
                    "park",
                    "garden"
                ]
            )
        ) {

            $category = "Parks";

        }


        /* FOOD */

        elseif (
            isset($tags["amenity"]) &&
            in_array(
                $tags["amenity"],
                [
                    "restaurant",
                    "cafe"
                ]
            )
        ) {

            $category = "Food";

        }


        /* ENTERTAINMENT */

        elseif (
            isset($tags["amenity"]) &&
            in_array(
                $tags["amenity"],
                [
                    "cinema",
                    "theatre"
                ]
            )
        ) {

            $category = "Entertainment";

        }


        /* SHOPPING */

        elseif (isset($tags["shop"])) {

            $category = "Shopping";

        }


        /* HISTORICAL */

        elseif (
            isset($tags["historic"]) ||
            isset($tags["heritage"])
        ) {

            $category = "Historical & Cultural";

        }


        /* NATURE */

        elseif (isset($tags["natural"])) {

            $category = "Nature & Scenic";

        }


        /* TOURISM */

        elseif (isset($tags["tourism"])) {

            switch ($tags["tourism"]) {

                case "museum":
                case "gallery":

                    $category =
                        "Historical & Cultural";

                    break;

                case "viewpoint":

                    $category =
                        "Nature & Scenic";

                    break;

                case "zoo":
                case "theme_park":
                case "aquarium":

                    $category =
                        "Entertainment";

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

        $duplicate = false;

        foreach ($places as $existingPlace) {

            if (
                strtolower($existingPlace["name"])
                ===
                strtolower($tags["name"])
            ) {

                $duplicate = true;
                break;

            }

        }


        if ($duplicate) {

            continue;

        }


        /* =========================================
           STORE PLACE
           ========================================= */

        $places[] = [

            "name" => $tags["name"],

            "category" => $category,

            "latitude" => $lat,

            "longitude" => $lon,

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
                ""

        ];


        /* Safety limit */

        if (count($places) >= 100) {

            break;

        }

    }


    /* =============================================
       RETURN RESULTS
       ============================================= */

    return [

        "success" => true,

        "places" => $places

    ];

}

?>