<?php

/* =====================================================
   WANDERAI - PRINTABLE / OFFLINE ITINERARY
   Self-contained page (no navbar, no live map links
   required) so it also works as an offline save: open
   it once while online, then File > Save Page or
   Print > Save as PDF for offline access on the trip.
   ===================================================== */

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once "db.php";
require_once "itinerary_helpers.php";

$user_id = (int)$_SESSION["user_id"];
$trip_id = isset($_GET["trip_id"]) ? (int)$_GET["trip_id"] : 0;

if ($trip_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

$stmt = $conn->prepare(
    "SELECT *
     FROM trips
     WHERE trip_id = ?
     AND user_id = ?"
);

$stmt->bind_param("ii", $trip_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

$trip = $result->fetch_assoc();
$stmt->close();

$generatedItinerary = json_decode($trip["generated_itinerary"] ?? "", true);

if (!is_array($generatedItinerary) || empty($generatedItinerary)) {
    header("Location: itinerary.php?trip_id=" . $trip_id);
    exit();
}

$destination = htmlspecialchars($trip["destination"] ?? "Your Trip");

$startDateDisplay = "";
if (!empty($trip["start_date"])) {
    $ts = strtotime($trip["start_date"]);
    if ($ts !== false) {
        $startDateDisplay = date("d M Y", $ts);
    }
}

$numberOfDays = (int)($trip["number_of_days"] ?? count($generatedItinerary));
$travelers = (int)($trip["travelers"] ?? 1);
$budget = (float)($trip["budget"] ?? 0);
$transport = htmlspecialchars($trip["transport_preference"] ?? "");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $destination; ?> Itinerary - WanderAI</title>

<style>
    * { box-sizing: border-box; }

    body {
        font-family: "Segoe UI", Arial, sans-serif;
        color: #1e293b;
        background: #f1f5f9;
        margin: 0;
        padding: 24px;
    }

    .print-actions {
        max-width: 820px;
        margin: 0 auto 16px auto;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .print-actions button,
    .print-actions a {
        font-family: inherit;
        font-weight: 700;
        font-size: 14px;
        padding: 10px 18px;
        border-radius: 10px;
        border: 1px solid #c7d2fe;
        background: #eef2ff;
        color: #4338ca;
        text-decoration: none;
        cursor: pointer;
    }

    .sheet {
        max-width: 820px;
        margin: 0 auto;
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(15,23,42,0.08);
        padding: 40px;
    }

    .sheet-header {
        border-bottom: 3px solid #6366f1;
        padding-bottom: 18px;
        margin-bottom: 24px;
    }

    .sheet-header h1 {
        margin: 0 0 6px 0;
        font-size: 26px;
    }

    .sheet-header .meta {
        color: #64748b;
        font-size: 14px;
    }

    .sheet-header .meta span {
        margin-right: 16px;
    }

    .day-block {
        margin-bottom: 28px;
        page-break-inside: avoid;
    }

    .day-block h2 {
        font-size: 18px;
        background: #eef2ff;
        color: #4338ca;
        padding: 8px 14px;
        border-radius: 8px;
        margin: 0 0 12px 0;
    }

    .place-row {
        display: flex;
        gap: 14px;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .place-time {
        flex: 0 0 110px;
        font-weight: 700;
        color: #6366f1;
        font-size: 13px;
    }

    .place-body strong {
        display: block;
        font-size: 15px;
    }

    .place-body .cat {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .place-body .reason {
        font-size: 13px;
        color: #475569;
        margin-top: 4px;
    }

    .print-footer {
        margin-top: 30px;
        font-size: 12px;
        color: #94a3b8;
        text-align: center;
    }

    @media print {
        body { background: white; padding: 0; }
        .print-actions { display: none; }
        .sheet { box-shadow: none; padding: 0; max-width: 100%; }
    }
</style>
</head>
<body>

    <div class="print-actions">
        <button onclick="window.print();">🖨️ Print / Save as PDF</button>
        <a href="itinerary.php?trip_id=<?php echo $trip_id; ?>">← Back to Itinerary</a>
    </div>

    <div class="sheet">

        <div class="sheet-header">
            <h1><?php echo $destination; ?> — Trip Itinerary</h1>
            <div class="meta">
                <?php if ($startDateDisplay): ?>
                    <span>📅 Starts <?php echo $startDateDisplay; ?></span>
                <?php endif; ?>
                <span>🗓️ <?php echo $numberOfDays; ?> day<?php echo $numberOfDays === 1 ? "" : "s"; ?></span>
                <span>👥 <?php echo $travelers; ?> traveler<?php echo $travelers === 1 ? "" : "s"; ?></span>
                <?php if ($transport): ?>
                    <span>🚗 <?php echo $transport; ?></span>
                <?php endif; ?>
                <?php if ($budget > 0): ?>
                    <span>💰 Budget ₹<?php echo number_format($budget); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($generatedItinerary as $dayData):

            if (
                !is_array($dayData) ||
                empty($dayData["places"]) ||
                !is_array($dayData["places"])
            ) {
                continue;
            }

            $dayNumber = (int)($dayData["day"] ?? 0);
        ?>

            <div class="day-block">
                <h2>Day <?php echo $dayNumber; ?></h2>

                <?php foreach ($dayData["places"] as $place):
                    if (!is_array($place)) continue;
                ?>
                    <div class="place-row">
                        <div class="place-time">
                            <?php echo htmlspecialchars($place["start_time"] ?? ""); ?>
                            &ndash;
                            <?php echo htmlspecialchars($place["end_time"] ?? ""); ?>
                        </div>
                        <div class="place-body">
                            <strong>
                                <?php echo !empty($place["is_break"]) ? "🍴 " : "📍 "; ?>
                                <?php echo htmlspecialchars($place["name"] ?? "Place"); ?>
                            </strong>
                            <div class="cat"><?php echo htmlspecialchars($place["category"] ?? ""); ?></div>
                            <?php if (!empty($place["recommendation_reason"])): ?>
                                <div class="reason"><?php echo htmlspecialchars($place["recommendation_reason"]); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>

        <?php endforeach; ?>

        <div class="print-footer">
            Generated by WanderAI — offline copy safe to keep on your device while travelling.
        </div>

    </div>

</body>
</html>
