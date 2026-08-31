<?php

require_once "places.php";

$result = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $latitude = $_POST["latitude"] ?? "";
    $longitude = $_POST["longitude"] ?? "";

    if ($latitude !== "" && $longitude !== "") {

        $result = getNearbyPlaces(
            (float)$latitude,
            (float)$longitude,
            10000
        );
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Dynamic Places Discovery</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 40px;
        }

        .container {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        h1, h2 {
            text-align: center;
        }

        input {
            padding: 10px;
            margin: 5px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            padding: 11px 22px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }

        .place {
            border: 1px solid #ddd;
            padding: 18px;
            margin: 15px 0;
            border-radius: 8px;
            background: #fafafa;
        }

        .place h3 {
            margin-top: 0;
        }

        .error {
            color: red;
            text-align: center;
            margin-top: 20px;
        }

        .success {
            color: green;
            text-align: center;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>📍 Dynamic Places Discovery</h1>

    <p style="text-align:center;">
        Enter the coordinates of your selected destination.
    </p>

    <form method="POST" style="text-align:center;">

        <input
            type="text"
            name="latitude"
            placeholder="Latitude"
            required
        >

        <input
            type="text"
            name="longitude"
            placeholder="Longitude"
            required
        >

        <br><br>

        <button type="submit">
            Find Tourist Places
        </button>

    </form>


    <?php if ($result !== null): ?>

        <?php if ($result["success"]): ?>

            <h2 class="success">
                Tourist Places Found
            </h2>

            <?php if (count($result["places"]) > 0): ?>

                <?php foreach ($result["places"] as $place): ?>

                    <div class="place">

                        <h3>
                            <?php
                            echo htmlspecialchars($place["name"]);
                            ?>
                        </h3>

                        <p>
                            <strong>Category:</strong>
                            <?php
                            echo htmlspecialchars($place["category"]);
                            ?>
                        </p>

                        <p>
                            <strong>Latitude:</strong>
                            <?php
                            echo htmlspecialchars($place["latitude"]);
                            ?>
                        </p>

                        <p>
                            <strong>Longitude:</strong>
                            <?php
                            echo htmlspecialchars($place["longitude"]);
                            ?>
                        </p>

                        <?php if ($place["fee"] !== ""): ?>

                            <p>
                                <strong>Fee:</strong>
                                <?php
                                echo htmlspecialchars($place["fee"]);
                                ?>
                            </p>

                        <?php endif; ?>

                        <?php if ($place["opening_hours"] !== ""): ?>

                            <p>
                                <strong>Opening Hours:</strong>
                                <?php
                                echo htmlspecialchars(
                                    $place["opening_hours"]
                                );
                                ?>
                            </p>

                        <?php endif; ?>

                        <?php if ($place["website"] !== ""): ?>

                            <p>
                                <strong>Website:</strong>
                                <?php
                                echo htmlspecialchars(
                                    $place["website"]
                                );
                                ?>
                            </p>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p style="text-align:center;">
                    No tourist places were found near this destination.
                </p>

            <?php endif; ?>

        <?php else: ?>

            <p class="error">
                <?php
                echo htmlspecialchars($result["message"]);
                ?>
            </p>

        <?php endif; ?>

    <?php endif; ?>

</div>

</body>

</html>