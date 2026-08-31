<?php

/* =====================================================
   WANDERAI - SMART PLACE RECOMMENDATION ENGINE
   ===================================================== */

function recommendPlaces(
    $allPlaces,
    $interests,
    $numberOfDays
) {

    /* =============================================
       VALIDATE INPUT
       ============================================= */

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
       Keep a reasonable number of recommended places.

       The itinerary generator will decide how many
       actually fit into each day.
    */

    $maximumPlaces = min(
        count($allPlaces),
        $numberOfDays * 4
    );


    /* =============================================
       PREPARE USER INTERESTS
       ============================================= */

    $cleanInterests = [];


    if (!empty($interests)) {

        if (is_array($interests)) {
            $interestList = $interests;
        } else {
            $interestList = explode(
                ",",
                strtolower($interests)
            );
        }


        foreach ($interestList as $interest) {

            $interest = strtolower(
                trim($interest)
            );

            if ($interest !== "") {
                $cleanInterests[] = $interest;
            }

        }

    }


    $hasInterests =
        count($cleanInterests) > 0;


    /* =============================================
       SCORE PLACES
       ============================================= */

    $scoredPlaces = [];


    foreach ($allPlaces as $place) {

        if (
            empty($place["name"])
        ) {
            continue;
        }


        $name = strtolower(
            trim($place["name"] ?? "")
        );


        $category = strtolower(
            trim($place["category"] ?? "")
        );


        /* -----------------------------------------
           NEVER INCLUDE ACCOMMODATION
           ----------------------------------------- */

        if (
            strpos(
                $category,
                "accommodation"
            ) !== false
        ) {
            continue;
        }


        $score = 0;


        /* =========================================
           DETECT PLACE TYPES
           ========================================= */


        /* CULTURE AND HISTORY */

        $isCultureHistory =

            strpos(
                $category,
                "historical"
            ) !== false

            ||

            strpos(
                $category,
                "cultural"
            ) !== false

            ||

            strpos(
                $category,
                "museum"
            ) !== false

            ||

            strpos(
                $category,
                "gallery"
            ) !== false

            ||

            strpos(
                $name,
                "fort"
            ) !== false

            ||

            strpos(
                $name,
                "palace"
            ) !== false

            ||

            strpos(
                $name,
                "heritage"
            ) !== false

            ||

            strpos(
                $name,
                "monument"
            ) !== false;


        /* NATURE */

        $isNature =

            strpos(
                $category,
                "nature"
            ) !== false

            ||

            strpos(
                $category,
                "scenic"
            ) !== false

            ||

            strpos(
                $category,
                "park"
            ) !== false

            ||

            strpos(
                $category,
                "beach"
            ) !== false

            ||

            strpos(
                $name,
                "waterfall"
            ) !== false

            ||

            strpos(
                $name,
                "falls"
            ) !== false

            ||

            strpos(
                $name,
                "garden"
            ) !== false

            ||

            strpos(
                $name,
                "lake"
            ) !== false

            ||

            strpos(
                $name,
                "hill"
            ) !== false

            ||

            strpos(
                $name,
                "viewpoint"
            ) !== false

            ||

            strpos(
                $name,
                "wildlife"
            ) !== false

            ||

            strpos(
                $name,
                "sanctuary"
            ) !== false;


        /* TOURIST ATTRACTION */

        $isTourist =

            strpos(
                $category,
                "tourist"
            ) !== false;


        /* FOOD */

        $isFood =

            strpos(
                $category,
                "food"
            ) !== false;


        /* SHOPPING */

        $isShopping =

            strpos(
                $category,
                "shopping"
            ) !== false;


        /* ENTERTAINMENT */

        $isEntertainment =

            strpos(
                $category,
                "entertainment"
            ) !== false;


        /* RELIGIOUS */

        $isReligious =

            strpos(
                $category,
                "religious"
            ) !== false;


        /* =========================================
           SCORE USER INTEREST MATCHES
           ========================================= */

        foreach (
            $cleanInterests
            as $interest
        ) {


            /* CULTURE AND HISTORY */

            if (

                (
                    strpos(
                        $interest,
                        "culture"
                    ) !== false

                    ||

                    strpos(
                        $interest,
                        "history"
                    ) !== false
                )

                &&

                $isCultureHistory

            ) {

                $score += 40;

            }


            /* NATURE */

            if (

                strpos(
                    $interest,
                    "nature"
                ) !== false

                &&

                $isNature

            ) {

                $score += 40;

            }


            /* ADVENTURE */

            if (

                strpos(
                    $interest,
                    "adventure"
                ) !== false

                &&

                (
                    $isNature ||
                    $isTourist
                )

            ) {

                $score += 35;

            }


            /* FOOD */

            if (

                strpos(
                    $interest,
                    "food"
                ) !== false

                &&

                $isFood

            ) {

                $score += 40;

            }


            /* SHOPPING */

            if (

                strpos(
                    $interest,
                    "shopping"
                ) !== false

                &&

                $isShopping

            ) {

                $score += 40;

            }


            /* ENTERTAINMENT */

            if (

                strpos(
                    $interest,
                    "entertainment"
                ) !== false

                &&

                $isEntertainment

            ) {

                $score += 40;

            }

        }


        /* =========================================
           FALLBACK SCORE FOR TOURIST ATTRACTIONS

           Only useful as a secondary recommendation.
           ========================================= */

        if ($isTourist) {

            $score += 8;

        }


        /*
           When nature or culture/history is selected,
           tourist attractions can still be included,
           but they receive lower priority.
        */

        if (
            $hasInterests
            &&
            $score === 8
        ) {

            $score = 8;

        }


        /* =========================================
           NO INTEREST SELECTED

           Use balanced recommendations.
           ========================================= */

        if (!$hasInterests) {

            if ($isTourist) {
                $score += 20;
            }

            if ($isNature) {
                $score += 20;
            }

            if ($isCultureHistory) {
                $score += 20;
            }

            if ($isFood) {
                $score += 12;
            }

            if ($isShopping) {
                $score += 12;
            }

            if ($isEntertainment) {
                $score += 15;
            }

            if ($isReligious) {
                $score += 10;
            }

        }


        /* =========================================
           IMPORTANT:
           IF USER SELECTED INTERESTS, DO NOT KEEP
           COMPLETELY IRRELEVANT ZERO-SCORE PLACES.
           ========================================= */

        if (
            $hasInterests
            &&
            $score <= 0
        ) {

            continue;

        }


        /* Store recommendation score */

        $place[
            "recommendation_score"
        ] = $score;


        $scoredPlaces[] = $place;

    }


    /* =============================================
       SORT BY HIGHEST SCORE
       ============================================= */

    usort(

        $scoredPlaces,

        function ($a, $b) {

            return

                (
                    $b[
                        "recommendation_score"
                    ]
                    ?? 0
                )

                <=>

                (
                    $a[
                        "recommendation_score"
                    ]
                    ?? 0
                );

        }

    );


    /* =============================================
       REMOVE DUPLICATES
       ============================================= */

    $recommendedPlaces = [];

    $usedNames = [];


    foreach (
        $scoredPlaces
        as $place
    ) {

        $name = strtolower(
            trim(
                $place["name"]
                ?? ""
            )
        );


        if ($name === "") {
            continue;
        }


        /* Exact duplicate */

        if (
            isset(
                $usedNames[$name]
            )
        ) {

            continue;

        }


        /*
           Similar duplicate detection.

           Example:
           Athirappalli
           Athirappalli Waterfalls
        */

        $isSimilar = false;


        foreach (
            $usedNames
            as $usedName => $value
        ) {

            $similarity = 0;


            similar_text(
                $name,
                $usedName,
                $similarity
            );


            if (
                $similarity >= 70
            ) {

                $isSimilar = true;
                break;

            }

        }


        if ($isSimilar) {
            continue;
        }


        /* Save unique place */

        $usedNames[$name] = true;

        $recommendedPlaces[] =
            $place;


        /* Stop at maximum recommendation count */

        if (

            count(
                $recommendedPlaces
            )

            >=

            $maximumPlaces

        ) {

            break;

        }

    }


    /* =============================================
       RETURN RECOMMENDATIONS
       ============================================= */

    return $recommendedPlaces;

}

?>