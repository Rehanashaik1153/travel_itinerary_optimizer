<?php

/* ============================================================
   WANDERAI - SMART PLACE RECOMMENDATION ENGINE
   ------------------------------------------------------------
   Purpose:
   1. Remove accommodation/infrastructure from attractions.
   2. Score places according to user interests.
   3. Protect against duplicate and near-duplicate places.
   4. Balance categories when no interests are selected.
   5. Prevent one attraction type from dominating the trip.
   6. Use geographic proximity as a secondary selection factor.
   7. Return at most 4 recommended attractions per day.

   Public function used by the project:
       recommendPlaces($allPlaces, $interests, $numberOfDays)

   This file has no dependency on any other helper function.
   ============================================================ */


/* ============================================================
   TEXT NORMALIZATION
   ============================================================ */

function wanderRecommendNormalizeText($text)
{
    $text = strtolower(trim((string)$text));

    $text = preg_replace(
        '/[^a-z0-9\s]/i',
        ' ',
        $text
    );

    $text = preg_replace(
        '/\s+/',
        ' ',
        $text
    );

    return trim($text);
}


/* ============================================================
   INTEREST PREPARATION
   ============================================================ */

function wanderRecommendPrepareInterests($interests)
{
    $result = [];

    if (empty($interests)) {
        return $result;
    }

    if (is_array($interests)) {
        $interestList = $interests;
    } else {
        $interestList = preg_split(
            '/[,|]+/',
            strtolower((string)$interests)
        );
    }

    foreach ($interestList as $interest) {

        $interest = wanderRecommendNormalizeText($interest);

        if ($interest === '') {
            continue;
        }

        if (!in_array($interest, $result, true)) {
            $result[] = $interest;
        }
    }

    return $result;
}


/* ============================================================
   PLACE CATEGORY DETECTION
   ============================================================ */

function wanderRecommendClassifyPlace($place)
{
    $name = wanderRecommendNormalizeText(
        $place['name'] ?? ''
    );

    $category = wanderRecommendNormalizeText(
        $place['category'] ?? ''
    );

    $combined = trim(
        $name . ' ' . $category
    );


    /* --------------------------------------------
       ACCOMMODATION
       -------------------------------------------- */

    if (
        strpos($category, 'accommodation') !== false ||
        strpos($category, 'hotel') !== false ||
        strpos($category, 'hostel') !== false ||
        strpos($category, 'guest house') !== false ||
        strpos($category, 'guesthouse') !== false ||
        strpos($category, 'resort') !== false ||
        strpos($category, 'motel') !== false ||
        strpos($combined, 'hotel') !== false ||
        strpos($combined, 'hostel') !== false ||
        strpos($combined, 'resort') !== false
    ) {
        return 'accommodation';
    }


    /* --------------------------------------------
       FOOD
       -------------------------------------------- */

    if (
        strpos($category, 'food') !== false ||
        strpos($category, 'restaurant') !== false ||
        strpos($category, 'cafe') !== false ||
        strpos($category, 'fast food') !== false ||
        strpos($category, 'bar') !== false ||
        strpos($category, 'bakery') !== false ||
        strpos($category, 'food court') !== false
    ) {
        return 'food';
    }


    /* --------------------------------------------
       SHOPPING
       -------------------------------------------- */

    if (
        strpos($category, 'shopping') !== false ||
        strpos($category, 'mall') !== false ||
        strpos($category, 'market') !== false ||
        strpos($category, 'shop') !== false ||
        strpos($category, 'supermarket') !== false
    ) {
        return 'shopping';
    }


    /* --------------------------------------------
       RELIGIOUS
       -------------------------------------------- */

    if (
        strpos($category, 'religious') !== false ||
        strpos($category, 'religion') !== false ||
        strpos($category, 'worship') !== false ||
        strpos($category, 'church') !== false ||
        strpos($category, 'temple') !== false ||
        strpos($category, 'mosque') !== false ||
        strpos($category, 'shrine') !== false ||
        strpos($category, 'chapel') !== false ||
        strpos($combined, 'church') !== false ||
        strpos($combined, 'temple') !== false ||
        strpos($combined, 'mosque') !== false ||
        strpos($combined, 'shrine') !== false ||
        strpos($combined, 'chapel') !== false
    ) {
        return 'religious';
    }


    /* --------------------------------------------
       BEACH
       -------------------------------------------- */

    if (
        strpos($category, 'beach') !== false ||
        strpos($name, 'beach') !== false
    ) {
        return 'beach';
    }


    /* --------------------------------------------
       WATERFALL
       Keep waterfalls separate from general nature.
       This prevents many waterfall variants from filling
       the whole recommendation list.
       -------------------------------------------- */

    if (
        strpos($name, 'waterfall') !== false ||
        strpos($name, 'water falls') !== false ||
        strpos($name, 'waterfalls') !== false ||
        preg_match('/\bfalls\b/i', $name) ||
        strpos($combined, 'waterfall') !== false
    ) {
        return 'waterfall';
    }


    /* --------------------------------------------
       NATURE / SCENIC
       -------------------------------------------- */

    if (
        strpos($category, 'nature') !== false ||
        strpos($category, 'scenic') !== false ||
        strpos($category, 'park') !== false ||
        strpos($category, 'garden') !== false ||
        strpos($category, 'viewpoint') !== false ||
        strpos($category, 'beach') !== false ||
        strpos($category, 'wildlife') !== false ||
        strpos($category, 'sanctuary') !== false ||
        strpos($category, 'national park') !== false ||
        strpos($category, 'natural') !== false ||
        strpos($combined, 'wildlife') !== false ||
        strpos($combined, 'sanctuary') !== false ||
        strpos($combined, 'national park') !== false ||
        strpos($combined, 'viewpoint') !== false ||
        strpos($combined, 'lake') !== false ||
        strpos($combined, 'hill') !== false ||
        strpos($combined, 'forest') !== false ||
        strpos($combined, 'valley') !== false ||
        strpos($combined, 'reserve') !== false
    ) {
        return 'nature';
    }


    /* --------------------------------------------
       ENTERTAINMENT
       -------------------------------------------- */

    if (
        strpos($category, 'entertainment') !== false ||
        strpos($category, 'theme park') !== false ||
        strpos($category, 'water park') !== false ||
        strpos($category, 'amusement') !== false ||
        strpos($category, 'aquarium') !== false ||
        strpos($category, 'zoo') !== false ||
        strpos($category, 'cinema') !== false ||
        strpos($category, 'theatre') !== false ||
        strpos($category, 'theater') !== false ||
        strpos($combined, 'water park') !== false ||
        strpos($combined, 'theme park') !== false
    ) {
        return 'entertainment';
    }


    /* --------------------------------------------
       CULTURE / HISTORY
       -------------------------------------------- */

    if (
        strpos($category, 'historical') !== false ||
        strpos($category, 'history') !== false ||
        strpos($category, 'cultural') !== false ||
        strpos($category, 'culture') !== false ||
        strpos($category, 'museum') !== false ||
        strpos($category, 'gallery') !== false ||
        strpos($category, 'heritage') !== false ||
        strpos($combined, 'fort') !== false ||
        strpos($combined, 'palace') !== false ||
        strpos($combined, 'monument') !== false ||
        strpos($combined, 'heritage') !== false ||
        strpos($combined, 'museum') !== false
    ) {
        return 'culture';
    }


    /* --------------------------------------------
       TOURIST ATTRACTION
       -------------------------------------------- */

    if (
        strpos($category, 'tourist') !== false ||
        strpos($category, 'attraction') !== false ||
        strpos($category, 'tourism') !== false
    ) {
        return 'tourist';
    }


    return 'other';
}


/* ============================================================
   BLOCK INFRASTRUCTURE / NON-VISITABLE PLACES
   ============================================================ */

function wanderRecommendIsBlockedPlace($place)
{
    $name = wanderRecommendNormalizeText(
        $place['name'] ?? ''
    );

    $category = wanderRecommendNormalizeText(
        $place['category'] ?? ''
    );

    $combined = $name . ' ' . $category;

    $blockedWords = [
        'parking',
        'car park',
        'fuel station',
        'petrol pump',
        'petrol station',
        'gas station',
        'bus stop',
        'bus station',
        'railway station',
        'train station',
        'airport',
        'helipad',
        'hospital',
        'clinic',
        'pharmacy',
        'police station',
        'fire station',
        'post office',
        'bank',
        'atm',
        'school',
        'college',
        'university',
        'industrial',
        'warehouse',
        'cemetery'
    ];

    foreach ($blockedWords as $word) {

        if (
            strpos($combined, $word) !== false
        ) {
            return true;
        }
    }

    return false;
}


/* ============================================================
   INTEREST MATCHING
   ============================================================ */

function wanderRecommendInterestMatches(
    $type,
    $place,
    $interests
) {
    if (empty($interests)) {
        return 0;
    }

    $name = wanderRecommendNormalizeText(
        $place['name'] ?? ''
    );

    $category = wanderRecommendNormalizeText(
        $place['category'] ?? ''
    );

    $combined = $name . ' ' . $category;

    $matches = 0;

    foreach ($interests as $interest) {

        $interest = wanderRecommendNormalizeText(
            $interest
        );

        if ($interest === '') {
            continue;
        }


        /* Culture / history */

        if (
            (
                strpos($interest, 'culture') !== false ||
                strpos($interest, 'history') !== false ||
                strpos($interest, 'historical') !== false ||
                strpos($interest, 'heritage') !== false
            )
            &&
            $type === 'culture'
        ) {
            $matches++;
            continue;
        }


        /* Nature / scenic */

        if (
            (
                strpos($interest, 'nature') !== false ||
                strpos($interest, 'scenic') !== false ||
                strpos($interest, 'landscape') !== false
            )
            &&
            (
                $type === 'nature' ||
                $type === 'waterfall' ||
                $type === 'beach'
            )
        ) {
            $matches++;
            continue;
        }


        /* Waterfalls */

        if (
            (
                strpos($interest, 'waterfall') !== false ||
                strpos($interest, 'waterfalls') !== false
            )
            &&
            $type === 'waterfall'
        ) {
            $matches++;
            continue;
        }


        /* Beaches */

        if (
            strpos($interest, 'beach') !== false
            &&
            $type === 'beach'
        ) {
            $matches++;
            continue;
        }


        /* Adventure */

        if (
            strpos($interest, 'adventure') !== false
            &&
            (
                $type === 'nature' ||
                $type === 'waterfall' ||
                $type === 'beach' ||
                $type === 'tourist'
            )
        ) {
            $matches++;
            continue;
        }


        /* Religious */

        if (
            (
                strpos($interest, 'religious') !== false ||
                strpos($interest, 'religion') !== false ||
                strpos($interest, 'spiritual') !== false ||
                strpos($interest, 'temple') !== false ||
                strpos($interest, 'church') !== false ||
                strpos($interest, 'mosque') !== false
            )
            &&
            $type === 'religious'
        ) {
            $matches++;
            continue;
        }


        /* Entertainment */

        if (
            (
                strpos($interest, 'entertainment') !== false ||
                strpos($interest, 'fun') !== false ||
                strpos($interest, 'amusement') !== false
            )
            &&
            $type === 'entertainment'
        ) {
            $matches++;
            continue;
        }


        /* Food */

        if (
            strpos($interest, 'food') !== false
            &&
            $type === 'food'
        ) {
            $matches++;
            continue;
        }


        /* Shopping */

        if (
            strpos($interest, 'shopping') !== false
            &&
            $type === 'shopping'
        ) {
            $matches++;
            continue;
        }


        /* Generic text match */

        if (
            strpos($combined, $interest) !== false
        ) {
            $matches++;
        }
    }

    return $matches;
}


/* ============================================================
   BASE SCORE
   ============================================================ */

function wanderRecommendBaseScore($type)
{
    switch ($type) {

        case 'waterfall':
            return 96;

        case 'beach':
            return 94;

        case 'nature':
            return 90;

        case 'culture':
            return 86;

        case 'religious':
            return 76;

        case 'entertainment':
            return 74;

        case 'tourist':
            return 68;

        case 'food':
            return 60;

        case 'shopping':
            return 58;

        default:
            return 50;
    }
}


/* ============================================================
   PLACE QUALITY SCORE
   ============================================================ */

function wanderRecommendQualityScore($place)
{
    $score = 0;

    $name = trim(
        (string)($place['name'] ?? '')
    );

    $description = trim(
        (string)($place['description'] ?? '')
    );

    $lat = $place['lat']
        ?? ($place['latitude'] ?? null);

    $lon = $place['lon']
        ?? ($place['longitude'] ?? null);

    $openingHours = trim(
        (string)($place['opening_hours'] ?? '')
    );


    /* A real coordinate makes routing possible. */

    if (
        is_numeric($lat) &&
        is_numeric($lon)
    ) {
        $score += 5;
    }


    /* Useful descriptive data is a quality signal. */

    if ($description !== '') {
        $score += 2;
    }


    if ($openingHours !== '') {
        $score += 3;
    }


    /*
       Prefer meaningful names over very generic
       machine-generated names.
    */

    if (
        strlen($name) >= 5 &&
        strlen($name) <= 100
    ) {
        $score += 2;
    }


    return $score;
}


/* ============================================================
   IMPORTANT PLACE NAME BOOST
   ============================================================ */

function wanderRecommendImportantPlaceScore($place, $type)
{
    $name = wanderRecommendNormalizeText(
        $place['name'] ?? ''
    );

    $score = 0;

    $importantWords = [
        'waterfall',
        'falls',
        'beach',
        'fort',
        'palace',
        'museum',
        'heritage',
        'sanctuary',
        'national park',
        'wildlife',
        'viewpoint',
        'lake',
        'temple',
        'church',
        'mosque',
        'cathedral',
        'monument'
    ];

    foreach ($importantWords as $word) {

        if (
            strpos($name, $word) !== false
        ) {
            $score += 3;
        }
    }


    /*
       Famous-looking named attraction types receive
       a modest boost, not enough to override diversity.
    */

    if (
        $type === 'waterfall' ||
        $type === 'beach' ||
        $type === 'nature' ||
        $type === 'culture'
    ) {
        $score += 2;
    }

    return min($score, 12);
}


/* ============================================================
   HAVERSINE DISTANCE
   ============================================================ */

function wanderRecommendDistanceKm(
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

    $lat1 = deg2rad((float)$lat1);
    $lat2 = deg2rad((float)$lat2);

    $deltaLat = $lat2 - $lat1;
    $deltaLon = deg2rad(
        (float)$lon2 - (float)$lon1
    );

    $a =
        sin($deltaLat / 2) * sin($deltaLat / 2)
        +
        cos($lat1)
        * cos($lat2)
        * sin($deltaLon / 2)
        * sin($deltaLon / 2);

    $a = min(1, max(0, $a));

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    return $earthRadius * $c;
}


/* ============================================================
   GET PLACE COORDINATES
   ============================================================ */

function wanderRecommendCoordinates($place)
{
    $lat = $place['lat']
        ?? ($place['latitude'] ?? null);

    $lon = $place['lon']
        ?? ($place['longitude'] ?? null);

    if (
        !is_numeric($lat) ||
        !is_numeric($lon)
    ) {
        return null;
    }

    return [
        'lat' => (float)$lat,
        'lon' => (float)$lon
    ];
}


/* ============================================================
   NORMALIZED NAME KEY
   ============================================================ */

function wanderRecommendNameKey($name)
{
    $name = wanderRecommendNormalizeText($name);

    /*
       Remove common generic words before comparing names.
       This helps detect:
       "Athirappalli Waterfall"
       "Athirappalli Waterfalls"
       "Athirappalli Falls"
    */

    $remove = [
        'the',
        'and',
        'of',
        'at',
        'a',
        'an',
        'waterfall',
        'waterfalls',
        'water',
        'falls',
        'park',
        'garden',
        'viewpoint'
    ];

    $words = preg_split(
        '/\s+/',
        $name
    );

    $filtered = [];

    foreach ($words as $word) {

        if (
            $word === '' ||
            in_array($word, $remove, true)
        ) {
            continue;
        }

        $filtered[] = $word;
    }

    return implode(
        ' ',
        $filtered
    );
}


/* ============================================================
   DUPLICATE / SIMILARITY CHECK
   ============================================================ */

function wanderRecommendIsDuplicate(
    $candidate,
    $selectedPlaces
) {
    $candidateName = wanderRecommendNormalizeText(
        $candidate['name'] ?? ''
    );

    if ($candidateName === '') {
        return true;
    }

    $candidateKey =
        wanderRecommendNameKey(
            $candidateName
        );

    $candidateType =
        strtolower(
            trim(
                $candidate['place_type'] ??
                $candidate['category'] ??
                ''
            )
        );

    $candidateLatitude =
        isset($candidate['latitude'])
        ? (float)$candidate['latitude']
        : null;

    $candidateLongitude =
        isset($candidate['longitude'])
        ? (float)$candidate['longitude']
        : null;

    foreach ($selectedPlaces as $selected) {

        $selectedName =
            wanderRecommendNormalizeText(
                $selected['name'] ?? ''
            );

        if ($selectedName === '') {
            continue;
        }

        if ($candidateName === $selectedName) {
            return true;
        }

        $selectedKey =
            wanderRecommendNameKey(
                $selectedName
            );

        if (
            $candidateKey !== '' &&
            $candidateKey === $selectedKey
        ) {
            return true;
        }

        /*
         * Do not recommend two records that are really the same
         * attraction with slightly different names.
         */
        if (
            strlen($candidateName) >= 5 &&
            strlen($selectedName) >= 5 &&
            (
                strpos($candidateName, $selectedName) !== false ||
                strpos($selectedName, $candidateName) !== false
            )
        ) {
            return true;
        }

        /*
         * Strong fuzzy-name duplicate check.
         */
        $similarity = 0;

        similar_text(
            $candidateName,
            $selectedName,
            $similarity
        );

        if ($similarity >= 82) {
            return true;
        }

        if (
            strlen($candidateName) >= 12 &&
            strlen($selectedName) >= 12 &&
            $similarity >= 74
        ) {
            return true;
        }

        /*
         * Geographic duplicate protection for natural attractions.
         * This specifically prevents several OSM records for the same
         * waterfall/falls/river viewpoint from filling the itinerary.
         */
        $candidateNature =
            strpos($candidateType, 'waterfall') !== false ||
            strpos($candidateType, 'nature') !== false ||
            strpos($candidateType, 'scenic') !== false ||
            strpos($candidateName, 'waterfall') !== false ||
            strpos($candidateName, 'falls') !== false;

        $selectedType =
            strtolower(
                trim(
                    $selected['place_type'] ??
                    $selected['category'] ??
                    ''
                )
            );

        $selectedNature =
            strpos($selectedType, 'waterfall') !== false ||
            strpos($selectedType, 'nature') !== false ||
            strpos($selectedType, 'scenic') !== false ||
            strpos($selectedName, 'waterfall') !== false ||
            strpos($selectedName, 'falls') !== false;

        if (
            $candidateNature &&
            $selectedNature &&
            $candidateLatitude !== null &&
            $candidateLongitude !== null &&
            isset($selected['latitude']) &&
            isset($selected['longitude'])
        ) {
            $lat1 = deg2rad($candidateLatitude);
            $lat2 = deg2rad((float)$selected['latitude']);
            $dLat = deg2rad(
                (float)$selected['latitude'] -
                $candidateLatitude
            );
            $dLon = deg2rad(
                (float)$selected['longitude'] -
                $candidateLongitude
            );

            $a =
                sin($dLat / 2) * sin($dLat / 2) +
                cos($lat1) * cos($lat2) *
                sin($dLon / 2) * sin($dLon / 2);

            $a = min(1, max(0, $a));

            $distanceKm =
                6371 *
                2 *
                atan2(
                    sqrt($a),
                    sqrt(1 - $a)
                );

            if ($distanceKm <= 3.0) {
                return true;
            }
        }
    }

    return false;
}

/* ============================================================
   CATEGORY GROUPS
   ============================================================ */

function wanderRecommendCategoryGroup($type)
{
    switch ($type) {

        case 'waterfall':
        case 'beach':
        case 'nature':
            return 'nature';

        case 'culture':
            return 'culture';

        case 'religious':
            return 'religious';

        case 'entertainment':
            return 'entertainment';

        case 'food':
            return 'food';

        case 'shopping':
            return 'shopping';

        case 'tourist':
            return 'tourist';

        default:
            return 'other';
    }
}


/* ============================================================
   CATEGORY CAP
   ============================================================ */

function wanderRecommendCategoryCap(
    $type,
    $hasInterests,
    $matchingInterests,
    $availableCounts
) {
    /*
       When the user selected an interest, matching places
       can appear more often. Still, duplicate-like types
       are limited to keep the itinerary useful.
    */

    if ($hasInterests) {

        if ($matchingInterests > 0) {

            if ($type === 'waterfall') {
                return 2;
            }

            if ($type === 'nature') {
                return 2;
            }

            if ($type === 'culture') {
                return 4;
            }

            if ($type === 'religious') {
                return 3;
            }

            if ($type === 'beach') {
                return 3;
            }

            return 3;
        }

        /*
           Non-matching categories are secondary.
        */

        if ($type === 'food' || $type === 'shopping') {
            return 1;
        }

        return 2;
    }


    /*
       No interests:
       deliberately create a balanced itinerary.
    */

    if ($type === 'waterfall') {
        return 2;
    }

    if ($type === 'nature') {
        return 2;
    }

    if ($type === 'beach') {
        return 2;
    }

    if ($type === 'culture') {
        return 2;
    }

    if ($type === 'religious') {
        return 2;
    }

    if ($type === 'entertainment') {
        return 1;
    }

    if ($type === 'food') {
        return 1;
    }

    if ($type === 'shopping') {
        return 1;
    }

    if ($type === 'tourist') {
        return 2;
    }

    return 1;
}


/* ============================================================
   PROXIMITY BONUS
   ============================================================ */

function wanderRecommendProximityScore(
    $candidate,
    $selectedPlaces
) {
    if (empty($selectedPlaces)) {
        return 0;
    }

    $candidateCoordinates =
        wanderRecommendCoordinates(
            $candidate
        );

    if ($candidateCoordinates === null) {
        return 0;
    }

    $nearestDistance = null;

    foreach ($selectedPlaces as $selected) {

        $selectedCoordinates =
            wanderRecommendCoordinates(
                $selected
            );

        if ($selectedCoordinates === null) {
            continue;
        }

        $distance =
            wanderRecommendDistanceKm(
                $candidateCoordinates['lat'],
                $candidateCoordinates['lon'],
                $selectedCoordinates['lat'],
                $selectedCoordinates['lon']
            );

        if ($distance === null) {
            continue;
        }

        if (
            $nearestDistance === null ||
            $distance < $nearestDistance
        ) {
            $nearestDistance = $distance;
        }
    }

    if ($nearestDistance === null) {
        return 0;
    }


    /*
       This is only a secondary factor.
       It must not destroy interest/category ranking.
    */

    if ($nearestDistance <= 5) {
        return 10;
    }

    if ($nearestDistance <= 10) {
        return 8;
    }

    if ($nearestDistance <= 20) {
        return 5;
    }

    if ($nearestDistance <= 35) {
        return 2;
    }

    if ($nearestDistance <= 50) {
        return 0;
    }

    return -4;
}


/* ============================================================
   DIVERSITY BONUS
   ============================================================ */

function wanderRecommendDiversityBonus(
    $type,
    $selectedTypes,
    $hasInterests
) {
    if (empty($selectedTypes)) {
        return 8;
    }

    $countSameType = 0;

    foreach ($selectedTypes as $selectedType) {

        if ($selectedType === $type) {
            $countSameType++;
        }
    }


    /*
       Reward a new type.
    */

    if ($countSameType === 0) {
        return $hasInterests ? 7 : 12;
    }


    /*
       First repeat is acceptable.
    */

    if ($countSameType === 1) {
        return 2;
    }


    /*
       Further repeats are discouraged.
    */

    if ($countSameType === 2) {
        return -5;
    }

    return -12;
}


/* ============================================================
   MAIN RECOMMENDATION FUNCTION
   ============================================================ */

function recommendPlaces(
    $allPlaces,
    $interests,
    $numberOfDays
) {
    /* --------------------------------------------
       Validate input
       -------------------------------------------- */

    if (
        empty($allPlaces) ||
        !is_array($allPlaces)
    ) {
        return [];
    }

    $numberOfDays = max(
        1,
        (int)$numberOfDays
    );

    /*
       The itinerary generator works with a maximum
       of four attractions per day.
    */

    $maximumPlaces = min(
        count($allPlaces),
        $numberOfDays * 4
    );

    if ($maximumPlaces <= 0) {
        return [];
    }


    /* --------------------------------------------
       Prepare interests
       -------------------------------------------- */

    $cleanInterests =
        wanderRecommendPrepareInterests(
            $interests
        );

    $hasInterests =
        !empty($cleanInterests);


    /* --------------------------------------------
       Build scored candidate list
       -------------------------------------------- */

    $scoredPlaces = [];

    foreach ($allPlaces as $place) {

        if (
            !is_array($place)
        ) {
            continue;
        }


        $name = trim(
            (string)($place['name'] ?? '')
        );

        if ($name === '') {
            continue;
        }


        /*
           Never recommend accommodation as an attraction.
           Accommodation is handled separately by the
           itinerary module.
        */

        $type =
            wanderRecommendClassifyPlace(
                $place
            );

        if ($type === 'accommodation') {
            continue;
        }


        /*
           Do not recommend infrastructure.
        */

        if (
            wanderRecommendIsBlockedPlace(
                $place
            )
        ) {
            continue;
        }


        /*
           Food and shopping are useful only when:
           - explicitly requested, or
           - there are not enough normal attractions.
           This prevents a generic trip from becoming a
           restaurant/shopping itinerary.
        */

        if (
            (
                $type === 'food' ||
                $type === 'shopping'
            )
            &&
            !$hasInterests
        ) {
            continue;
        }


        /*
           Unknown places are allowed only if the dataset
           does not contain enough classified attractions.
           We initially keep them as low-score candidates.
        */


        $baseScore =
            wanderRecommendBaseScore(
                $type
            );

        $interestMatches =
            wanderRecommendInterestMatches(
                $type,
                $place,
                $cleanInterests
            );

        $qualityScore =
            wanderRecommendQualityScore(
                $place
            );

        $importantScore =
            wanderRecommendImportantPlaceScore(
                $place,
                $type
            );


        /*
           Interest matching has strong influence.
        */

        $interestScore =
            $interestMatches * 35;


        /*
           Generic tourist attractions receive only a modest
           boost when no specific interest is selected.
        */

        $noInterestBonus = 0;

        if (!$hasInterests) {

            switch ($type) {

                case 'waterfall':
                    $noInterestBonus = 16;
                    break;

                case 'beach':
                    $noInterestBonus = 15;
                    break;

                case 'nature':
                    $noInterestBonus = 14;
                    break;

                case 'culture':
                    $noInterestBonus = 13;
                    break;

                case 'religious':
                    $noInterestBonus = 8;
                    break;

                case 'entertainment':
                    $noInterestBonus = 5;
                    break;

                case 'tourist':
                    $noInterestBonus = 4;
                    break;

                default:
                    $noInterestBonus = 0;
            }
        }


        /*
           If interests exist and this place matches none,
           keep it as a lower-priority fallback rather than
           letting it dominate the list.
        */

        if (
            $hasInterests &&
            $interestMatches === 0
        ) {
            $baseScore -= 18;
        }


        $score =
            $baseScore
            +
            $interestScore
            +
            $qualityScore
            +
            $importantScore
            +
            $noInterestBonus;


        /*
           Store internal values only temporarily.
        */

        $place['recommendation_score'] =
            (int)$score;

        $place['_recommend_type'] =
            $type;

        $place['_interest_matches'] =
            (int)$interestMatches;

        $scoredPlaces[] =
            $place;
    }


    if (empty($scoredPlaces)) {
        return [];
    }


    /* --------------------------------------------
       Count available types.
       Used to avoid applying diversity rules too
       aggressively when the API returns only one type.
       -------------------------------------------- */

    $availableCounts = [];

    foreach ($scoredPlaces as $place) {

        $type =
            $place['_recommend_type'];

        if (
            !isset($availableCounts[$type])
        ) {
            $availableCounts[$type] = 0;
        }

        $availableCounts[$type]++;
    }


    /* --------------------------------------------
       Initial score sorting
       -------------------------------------------- */

    usort(
        $scoredPlaces,
        function ($a, $b) {

            $scoreA =
                (int)($a['recommendation_score'] ?? 0);

            $scoreB =
                (int)($b['recommendation_score'] ?? 0);

            if ($scoreA === $scoreB) {

                $matchA =
                    (int)($a['_interest_matches'] ?? 0);

                $matchB =
                    (int)($b['_interest_matches'] ?? 0);

                if ($matchA === $matchB) {
                    return 0;
                }

                return $matchB <=> $matchA;
            }

            return $scoreB <=> $scoreA;
        }
    );


    /* --------------------------------------------
       Selection
       -------------------------------------------- */

    $recommendedPlaces = [];

    $selectedTypes = [];

    $categoryCounts = [];

    $remaining = $scoredPlaces;


    while (
        count($recommendedPlaces) < $maximumPlaces &&
        !empty($remaining)
    ) {

        $bestIndex = null;
        $bestSelectionScore = null;


        foreach ($remaining as $index => $candidate) {

            $type =
                $candidate['_recommend_type'];

            $interestMatches =
                (int)(
                    $candidate['_interest_matches']
                    ?? 0
                );


            /*
               Duplicate protection before calculating
               expensive selection score.
            */

            if (
                wanderRecommendIsDuplicate(
                    $candidate,
                    $recommendedPlaces
                )
            ) {
                continue;
            }


            /*
               Determine category cap.
            */

            $cap =
                wanderRecommendCategoryCap(
                    $type,
                    $hasInterests,
                    $interestMatches,
                    $availableCounts
                );

            $currentCount =
                $categoryCounts[$type] ?? 0;


            /*
               If enough alternative types exist, enforce cap.
               If this is the only remaining type, relax the cap
               so the user still receives enough recommendations.
            */

            $alternativeTypesAvailable = false;

            foreach (
                $availableCounts as $otherType => $otherCount
            ) {

                if (
                    $otherType !== $type &&
                    $otherCount > 0
                ) {
                    $alternativeTypesAvailable = true;
                    break;
                }
            }


            if (
                $currentCount >= $cap &&
                $alternativeTypesAvailable
            ) {
                continue;
            }


            /*
               Main score.
            */

            $selectionScore =
                (float)(
                    $candidate['recommendation_score']
                    ?? 0
                );


            /*
               Reward category diversity.
            */

            $selectionScore +=
                wanderRecommendDiversityBonus(
                    $type,
                    $selectedTypes,
                    $hasInterests
                );


            /*
               Reward geographic proximity to already
               selected places. This is a secondary factor.
            */

            $selectionScore +=
                wanderRecommendProximityScore(
                    $candidate,
                    $recommendedPlaces
                );


            /*
               Matching interests always receive priority.
            */

            if (
                $hasInterests &&
                $interestMatches > 0
            ) {
                $selectionScore += 8;
            }


            /*
               Slight penalty for a third same-type selection.
            */

            if (
                $currentCount >= 2
            ) {
                $selectionScore -= 8;
            }


            /*
               Prefer candidates with coordinates because
               the later itinerary optimizer can calculate
               travel between them.
            */

            $coordinates =
                wanderRecommendCoordinates(
                    $candidate
                );

            if ($coordinates !== null) {
                $selectionScore += 2;
            }


            if (
                $bestSelectionScore === null ||
                $selectionScore > $bestSelectionScore
            ) {

                $bestSelectionScore =
                    $selectionScore;

                $bestIndex =
                    $index;
            }
        }


        /*
           No candidate survived the diversity rules.
           Use the highest-scoring remaining candidate as
           a safe fallback, still respecting duplicate checks.
        */

        if ($bestIndex === null) {

            foreach ($remaining as $index => $candidate) {

                if (
                    !wanderRecommendIsDuplicate(
                        $candidate,
                        $recommendedPlaces
                    )
                ) {
                    $bestIndex = $index;
                    break;
                }
            }
        }


        if ($bestIndex === null) {
            break;
        }


        $selected =
            $remaining[$bestIndex];


        $type =
            $selected['_recommend_type'];


        /*
           Remove internal recommendation fields before
           returning the place.
        */

        unset(
            $selected['_recommend_type'],
            $selected['_interest_matches']
        );


        $recommendedPlaces[] =
            $selected;


        $selectedTypes[] =
            $type;


        if (
            !isset($categoryCounts[$type])
        ) {
            $categoryCounts[$type] = 0;
        }

        $categoryCounts[$type]++;


        /*
           Remove selected candidate from remaining list.
        */

        array_splice(
            $remaining,
            $bestIndex,
            1
        );
    }


    /* --------------------------------------------
       Final cleanup
       -------------------------------------------- */

    foreach (
        $recommendedPlaces as $index => $place
    ) {

        /*
           Keep recommendation_score because the itinerary
           page can use/display it if required.
        */

        if (
            isset($place['_recommend_type'])
        ) {
            unset(
                $recommendedPlaces[$index]['_recommend_type']
            );
        }

        if (
            isset($place['_interest_matches'])
        ) {
            unset(
                $recommendedPlaces[$index]['_interest_matches']
            );
        }
    }


    /*
       Final sort:
       Keep interest-relevant recommendations first,
       then score. This does not undo the diversity
       selection performed above.
    */

    usort(
        $recommendedPlaces,
        function ($a, $b) {

            $scoreA =
                (int)($a['recommendation_score'] ?? 0);

            $scoreB =
                (int)($b['recommendation_score'] ?? 0);

            return $scoreB <=> $scoreA;
        }
    );


    /*
       Safety limit.
    */

    if (
        count($recommendedPlaces) >
        $maximumPlaces
    ) {
        $recommendedPlaces =
            array_slice(
                $recommendedPlaces,
                0,
                $maximumPlaces
            );
    }


    return $recommendedPlaces;
}

?>
