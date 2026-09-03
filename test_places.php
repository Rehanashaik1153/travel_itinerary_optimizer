<?php
/**
 * WanderAI - Place Discovery Diagnostic Test
 *
 * Purpose:
 * - Test places.php independently
 * - Test broad destinations such as Kerala, India
 * - Show API success/failure
 * - Show number of places discovered
 * - Show accommodation results
 * - Show categories and actual returned places
 *
 * IMPORTANT:
 * This file is only for testing.
 * It does NOT modify the database.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "places.php";

/* -------------------------------------------------------
   TEST SETTINGS
------------------------------------------------------- */

// Kerala coordinates
$latitude = 10.8505;
$longitude = 76.2711;

$destination = "Kerala, India";

/*
 * Use a large radius because Kerala is a broad destination.
 * places.php itself also handles broad destinations.
 */
$radius = 50000;


/* -------------------------------------------------------
   HELPER FUNCTIONS
------------------------------------------------------- */

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getCategoryClass($category)
{
    $category = strtolower(trim($category));

    if (strpos($category, 'nature') !== false) {
        return 'nature';
    }

    if (strpos($category, 'historical') !== false ||
        strpos($category, 'cultural') !== false) {
        return 'culture';
    }

    if (strpos($category, 'religious') !== false) {
        return 'religious';
    }

    if (strpos($category, 'beach') !== false) {
        return 'beach';
    }

    if (strpos($category, 'park') !== false) {
        return 'park';
    }

    if (strpos($category, 'entertainment') !== false) {
        return 'entertainment';
    }

    if (strpos($category, 'shopping') !== false) {
        return 'shopping';
    }

    if (strpos($category, 'food') !== false) {
        return 'food';
    }

    if (strpos($category, 'accommodation') !== false) {
        return 'accommodation';
    }

    return 'other';
}


/* -------------------------------------------------------
   RUN PLACE DISCOVERY
------------------------------------------------------- */

$startTime = microtime(true);

$result = null;
$errorMessage = '';

try {

    $result = getNearbyPlaces(
        $latitude,
        $longitude,
        $radius,
        $destination
    );

} catch (Throwable $e) {

    $errorMessage = $e->getMessage();
}

$endTime = microtime(true);

$executionTime = round($endTime - $startTime, 2);


/* -------------------------------------------------------
   PREPARE RESULT
------------------------------------------------------- */

$success = false;
$places = [];
$accommodationCount = 0;
$placesApiSuccess = false;
$accommodationApiSuccess = false;
$broadDestination = false;
$searchAreasUsed = 0;

if (is_array($result)) {

    $success = !empty($result['success']);

    if (isset($result['places']) && is_array($result['places'])) {
        $places = $result['places'];
    }

    if (isset($result['accommodation_count'])) {
        $accommodationCount = (int)$result['accommodation_count'];
    }

    if (isset($result['places_api_success'])) {
        $placesApiSuccess = !empty($result['places_api_success']);
    }

    if (isset($result['accommodation_api_success'])) {
        $accommodationApiSuccess = !empty($result['accommodation_api_success']);
    }

    if (isset($result['broad_destination'])) {
        $broadDestination = !empty($result['broad_destination']);
    }

    if (isset($result['search_areas_used'])) {
        $searchAreasUsed = (int)$result['search_areas_used'];
    }
}


/* -------------------------------------------------------
   COUNT CATEGORIES
------------------------------------------------------- */

$categoryCounts = [];

foreach ($places as $place) {

    $category = isset($place['category'])
        ? trim($place['category'])
        : 'Unknown';

    if ($category === '') {
        $category = 'Unknown';
    }

    if (!isset($categoryCounts[$category])) {
        $categoryCounts[$category] = 0;
    }

    $categoryCounts[$category]++;
}

arsort($categoryCounts);


/* -------------------------------------------------------
   SEPARATE ACCOMMODATION
------------------------------------------------------- */

$accommodationPlaces = [];
$normalPlaces = [];

foreach ($places as $place) {

    $category = strtolower(
        isset($place['category'])
            ? $place['category']
            : ''
    );

    $isAccommodation = false;

    if (strpos($category, 'accommodation') !== false) {
        $isAccommodation = true;
    }

    if (isset($place['_type']) &&
        strtolower($place['_type']) === 'accommodation') {
        $isAccommodation = true;
    }

    if ($isAccommodation) {
        $accommodationPlaces[] = $place;
    } else {
        $normalPlaces[] = $place;
    }
}


/* -------------------------------------------------------
   STATUS
------------------------------------------------------- */

if ($errorMessage !== '') {

    $overallStatus = 'PHP ERROR';
    $overallClass = 'error';

} elseif (!$placesApiSuccess && !$accommodationApiSuccess) {

    $overallStatus = 'BOTH API REQUESTS FAILED';
    $overallClass = 'error';

} elseif (!$placesApiSuccess) {

    $overallStatus = 'PLACE API FAILED';
    $overallClass = 'warning';

} elseif (count($normalPlaces) === 0) {

    $overallStatus = 'API WORKED BUT NO TOURIST PLACES FOUND';
    $overallClass = 'warning';

} else {

    $overallStatus = 'PLACE DISCOVERY WORKING';
    $overallClass = 'success';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>WanderAI - Place Discovery Test</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        .header {
            background: linear-gradient(
                135deg,
                #2563eb,
                #4f46e5
            );
            color: white;
            padding: 25px;
            text-align: center;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .header p {
            margin: 0;
            opacity: 0.9;
        }

        .container {
            width: 94%;
            max-width: 1250px;
            margin: 25px auto 50px;
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        }

        .card h2 {
            margin-top: 0;
            color: #111827;
        }

        .status {
            padding: 16px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .stat {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 18px;
        }

        .stat-title {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #111827;
        }

        .details {
            width: 100%;
            border-collapse: collapse;
        }

        .details td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .details td:first-child {
            width: 240px;
            font-weight: bold;
            color: #374151;
        }

        .yes {
            color: #15803d;
            font-weight: bold;
        }

        .no {
            color: #dc2626;
            font-weight: bold;
        }

        .category-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .category {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
            background: #fafafa;
        }

        .category-name {
            font-weight: bold;
            margin-bottom: 6px;
        }

        .category-count {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }

        .place-table-wrapper {
            overflow-x: auto;
        }

        table.places {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        table.places th {
            background: #111827;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 13px;
        }

        table.places td {
            padding: 11px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 14px;
        }

        table.places tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge.nature {
            background: #dcfce7;
            color: #166534;
        }

        .badge.culture {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.religious {
            background: #ede9fe;
            color: #5b21b6;
        }

        .badge.beach {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge.park {
            background: #d1fae5;
            color: #065f46;
        }

        .badge.entertainment {
            background: #fce7f3;
            color: #9d174d;
        }

        .badge.shopping {
            background: #f3e8ff;
            color: #7e22ce;
        }

        .badge.food {
            background: #ffedd5;
            color: #c2410c;
        }

        .badge.accommodation {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge.other {
            background: #e5e7eb;
            color: #374151;
        }

        .empty {
            padding: 25px;
            text-align: center;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 10px;
            color: #6b7280;
        }

        .error-box {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 10px;
            padding: 15px;
            color: #9f1239;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .raw-box {
            background: #111827;
            color: #d1d5db;
            padding: 15px;
            border-radius: 10px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
        }

        .footer {
            text-align: center;
            color: #6b7280;
            padding: 20px;
            font-size: 14px;
        }

        .note {
            padding: 15px;
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            margin-top: 15px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {

            .container {
                width: 96%;
            }

            .header h1 {
                font-size: 24px;
            }

            .card {
                padding: 16px;
            }

            .details td:first-child {
                width: 150px;
            }
        }

    </style>

</head>

<body>


<div class="header">

    <h1>✈️ WanderAI Place Discovery Test</h1>

    <p>
        Diagnostic test for places.php
    </p>

</div>


<div class="container">


    <!-- OVERALL STATUS -->

    <div class="card">

        <div class="status <?php echo h($overallClass); ?>">

            <?php echo h($overallStatus); ?>

        </div>


        <?php if ($errorMessage !== ''): ?>

            <div class="error-box">
                <?php echo h($errorMessage); ?>
            </div>

        <?php endif; ?>

    </div>



    <!-- TEST CONFIGURATION -->

    <div class="card">

        <h2>🔎 Test Configuration</h2>

        <table class="details">

            <tr>
                <td>Destination</td>
                <td>
                    <strong><?php echo h($destination); ?></strong>
                </td>
            </tr>

            <tr>
                <td>Latitude</td>
                <td>
                    <?php echo h($latitude); ?>
                </td>
            </tr>

            <tr>
                <td>Longitude</td>
                <td>
                    <?php echo h($longitude); ?>
                </td>
            </tr>

            <tr>
                <td>Search Radius</td>
                <td>
                    <?php echo number_format($radius / 1000, 1); ?> km
                </td>
            </tr>

            <tr>
                <td>Execution Time</td>
                <td>
                    <?php echo h($executionTime); ?> seconds
                </td>
            </tr>

        </table>

    </div>



    <!-- API STATUS -->

    <div class="card">

        <h2>🌐 API Status</h2>

        <div class="grid">


            <div class="stat">

                <div class="stat-title">
                    Tourist Places API
                </div>

                <div class="stat-value">

                    <?php if ($placesApiSuccess): ?>

                        <span class="yes">✓</span>

                    <?php else: ?>

                        <span class="no">✗</span>

                    <?php endif; ?>

                </div>

                <div>
                    <?php
                    echo $placesApiSuccess
                        ? 'Successful'
                        : 'Failed';
                    ?>
                </div>

            </div>



            <div class="stat">

                <div class="stat-title">
                    Accommodation API
                </div>

                <div class="stat-value">

                    <?php if ($accommodationApiSuccess): ?>

                        <span class="yes">✓</span>

                    <?php else: ?>

                        <span class="no">✗</span>

                    <?php endif; ?>

                </div>

                <div>
                    <?php
                    echo $accommodationApiSuccess
                        ? 'Successful'
                        : 'Failed';
                    ?>
                </div>

            </div>



            <div class="stat">

                <div class="stat-title">
                    Broad Destination
                </div>

                <div class="stat-value">

                    <?php if ($broadDestination): ?>

                        <span class="yes">✓</span>

                    <?php else: ?>

                        <span class="no">✗</span>

                    <?php endif; ?>

                </div>

                <div>
                    <?php
                    echo $broadDestination
                        ? 'Yes'
                        : 'No';
                    ?>
                </div>

            </div>



            <div class="stat">

                <div class="stat-title">
                    Search Areas Used
                </div>

                <div class="stat-value">
                    <?php echo h($searchAreasUsed); ?>
                </div>

            </div>


        </div>

    </div>



    <!-- RESULT COUNTS -->

    <div class="card">

        <h2>📊 Discovery Results</h2>

        <div class="grid">


            <div class="stat">

                <div class="stat-title">
                    Total Returned
                </div>

                <div class="stat-value">
                    <?php echo count($places); ?>
                </div>

            </div>



            <div class="stat">

                <div class="stat-title">
                    Tourist Places
                </div>

                <div class="stat-value">
                    <?php echo count($normalPlaces); ?>
                </div>

            </div>



            <div class="stat">

                <div class="stat-title">
                    Accommodation
                </div>

                <div class="stat-value">
                    <?php echo count($accommodationPlaces); ?>
                </div>

            </div>



            <div class="stat">

                <div class="stat-title">
                    Reported Accommodation Count
                </div>

                <div class="stat-value">
                    <?php echo h($accommodationCount); ?>
                </div>

            </div>


        </div>

    </div>



    <!-- CATEGORY COUNTS -->

    <div class="card">

        <h2>📂 Categories Found</h2>

        <?php if (count($categoryCounts) > 0): ?>

            <div class="category-grid">

                <?php foreach ($categoryCounts as $category => $count): ?>

                    <div class="category">

                        <div class="category-name">
                            <?php echo h($category); ?>
                        </div>

                        <div class="category-count">
                            <?php echo h($count); ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty">
                No categories were returned.
            </div>

        <?php endif; ?>

    </div>



    <!-- TOURIST PLACES -->

    <div class="card">

        <h2>
            🏞️ Tourist Places Returned
            (<?php echo count($normalPlaces); ?>)
        </h2>


        <?php if (count($normalPlaces) > 0): ?>

            <div class="place-table-wrapper">

                <table class="places">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Place Name</th>

                            <th>Category</th>

                            <th>Latitude</th>

                            <th>Longitude</th>

                            <th>Duration</th>

                            <th>Opening Hours</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($normalPlaces as $index => $place): ?>

                        <?php

                        $name = isset($place['name'])
                            ? $place['name']
                            : 'Unnamed';

                        $category = isset($place['category'])
                            ? $place['category']
                            : 'Unknown';

                        $placeLat = isset($place['lat'])
                            ? $place['lat']
                            : '';

                        $placeLon = isset($place['lon'])
                            ? $place['lon']
                            : '';

                        $duration = '';

                        if (isset($place['duration'])) {
                            $duration = $place['duration'];
                        } elseif (isset($place['visit_duration'])) {
                            $duration = $place['visit_duration'];
                        } elseif (isset($place['estimated_duration'])) {
                            $duration = $place['estimated_duration'];
                        }

                        $openingHours = '';

                        if (isset($place['opening_hours'])) {
                            $openingHours = $place['opening_hours'];
                        }

                        $categoryClass = getCategoryClass($category);

                        ?>

                        <tr>

                            <td>
                                <?php echo $index + 1; ?>
                            </td>

                            <td>
                                <strong>
                                    <?php echo h($name); ?>
                                </strong>
                            </td>

                            <td>

                                <span class="badge <?php echo h($categoryClass); ?>">

                                    <?php echo h($category); ?>

                                </span>

                            </td>

                            <td>
                                <?php echo h($placeLat); ?>
                            </td>

                            <td>
                                <?php echo h($placeLon); ?>
                            </td>

                            <td>
                                <?php
                                echo $duration !== ''
                                    ? h($duration)
                                    : 'Not available';
                                ?>
                            </td>

                            <td>
                                <?php
                                echo $openingHours !== ''
                                    ? h($openingHours)
                                    : 'Not available';
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">

                ❌ <strong>No tourist places were returned.</strong>

                <br><br>

                This is the important result we need to investigate.

            </div>

        <?php endif; ?>

    </div>



    <!-- ACCOMMODATION -->

    <div class="card">

        <h2>
            🏨 Accommodation Returned
            (<?php echo count($accommodationPlaces); ?>)
        </h2>


        <?php if (count($accommodationPlaces) > 0): ?>

            <div class="place-table-wrapper">

                <table class="places">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Name</th>

                            <th>Category</th>

                            <th>Latitude</th>

                            <th>Longitude</th>

                            <th>Stars</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($accommodationPlaces as $index => $place): ?>

                        <tr>

                            <td>
                                <?php echo $index + 1; ?>
                            </td>

                            <td>

                                <strong>

                                    <?php
                                    echo h(
                                        isset($place['name'])
                                            ? $place['name']
                                            : 'Unnamed'
                                    );
                                    ?>

                                </strong>

                            </td>

                            <td>

                                <span class="badge accommodation">

                                    <?php
                                    echo h(
                                        isset($place['category'])
                                            ? $place['category']
                                            : 'Accommodation'
                                    );
                                    ?>

                                </span>

                            </td>

                            <td>

                                <?php
                                echo h(
                                    isset($place['lat'])
                                        ? $place['lat']
                                        : ''
                                );
                                ?>

                            </td>

                            <td>

                                <?php
                                echo h(
                                    isset($place['lon'])
                                        ? $place['lon']
                                        : ''
                                );
                                ?>

                            </td>

                            <td>

                                <?php
                                echo h(
                                    isset($place['stars'])
                                        ? $place['stars']
                                        : 'N/A'
                                );
                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">
                No accommodation places were returned.
            </div>

        <?php endif; ?>

    </div>



    <!-- DIAGNOSTIC MESSAGE -->

    <div class="card">

        <h2>🧪 Diagnostic Result</h2>


        <?php if ($errorMessage !== ''): ?>

            <div class="error">

                <strong>PHP Error detected.</strong>

                <br><br>

                The error shown above needs to be fixed in
                <strong>places.php</strong>.

            </div>


        <?php elseif (!$placesApiSuccess): ?>

            <div class="error">

                <strong>The tourist-place API request failed.</strong>

                <br><br>

                The next fix should focus on the Overpass/API request
                inside <strong>places.php</strong>.

            </div>


        <?php elseif (count($normalPlaces) === 0): ?>

            <div class="warning">

                <strong>The API responded, but no usable tourist
                places were returned.</strong>

                <br><br>

                This means the problem is most likely in the
                Overpass query, destination/radius handling, or
                place filtering/category logic in
                <strong>places.php</strong>.

            </div>


        <?php else: ?>

            <div class="note">

                <strong>✅ Place discovery is working.</strong>

                <br><br>

                The API returned
                <strong>
                    <?php echo count($normalPlaces); ?>
                </strong>
                tourist places.

                <br>

                That means the next stage to inspect would be
                <strong>recommend_places.php</strong> if the main
                itinerary page still says
                <em>"No matching places found"</em>.

            </div>

        <?php endif; ?>

    </div>



    <!-- RAW RESULT -->

    <div class="card">

        <h2>🔧 Raw Diagnostic Data</h2>

        <?php if (is_array($result)): ?>

            <div class="raw-box">

                <?php

                echo h(
                    print_r(
                        [
                            'success' => $result['success'] ?? null,
                            'places_count' => count($places),
                            'accommodation_count' =>
                                $result['accommodation_count'] ?? null,
                            'places_api_success' =>
                                $result['places_api_success'] ?? null,
                            'accommodation_api_success' =>
                                $result['accommodation_api_success'] ?? null,
                            'broad_destination' =>
                                $result['broad_destination'] ?? null,
                            'search_areas_used' =>
                                $result['search_areas_used'] ?? null,
                            'destination' =>
                                $result['destination'] ?? null
                        ],
                        true
                    )
                );

                ?>

            </div>

        <?php else: ?>

            <div class="empty">
                No result array was returned from places.php.
            </div>

        <?php endif; ?>

    </div>



</div>


<div class="footer">

    ✈️ WanderAI — AI Travel Itinerary Optimizer

</div>


</body>

</html>