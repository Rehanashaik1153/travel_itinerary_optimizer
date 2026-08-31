<?php

session_start();

require_once "geocode.php";

$result = null;
$selectedPlace = null;

/* STEP 1: Search destination */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["destination"])
) {

    $destination = trim($_POST["destination"]);

    $result = geocodeDestination($destination);

    if ($result["success"]) {
        $_SESSION["geocode_results"] = $result["results"];
    }
}


/* STEP 2: Select destination */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["selected_place"])
) {

    if (isset($_SESSION["geocode_results"])) {

        $index = (int) $_POST["selected_place"];

        if (isset($_SESSION["geocode_results"][$index])) {

            $selectedPlace = $_SESSION["geocode_results"][$index];

        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Global Destination Search</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            text-align: center;
            padding: 40px;
        }

        .container {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        input[type="text"] {
            width: 80%;
            padding: 12px;
            margin: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            background: #007bff;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }

        .place {
            text-align: left;
            padding: 15px;
            margin-top: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }

        .place input[type="radio"] {
            width: auto;
            margin: 5px;
        }

        .error {
            margin-top: 20px;
            padding: 15px;
            background: #ffecec;
            color: red;
            border-radius: 8px;
        }

        .success {
            margin-top: 25px;
            padding: 20px;
            background: #eaffea;
            border-radius: 8px;
            text-align: left;
        }

        .continue {
            margin-top: 20px;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>🌍 Global Destination Search</h1>

    <p>Enter any destination in the world</p>


    <!-- SEARCH FORM -->

    <form method="POST">

        <input
            type="text"
            name="destination"
            placeholder="Example: London, Tokyo, Guntur..."
            required
        >

        <br>

        <button type="submit">
            Search Destination
        </button>

    </form>


    <!-- SEARCH RESULTS -->

    <?php if ($result !== null && $result["success"]): ?>

        <h2>Select Your Destination</h2>

        <form method="POST">

            <?php foreach ($result["results"] as $index => $place): ?>

                <div class="place">

                    <label>

                        <input
                            type="radio"
                            name="selected_place"
                            value="<?php echo $index; ?>"
                            required
                        >

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $place["display_name"]
                            );
                            ?>
                        </strong>

                        <br><br>

                        Latitude:
                        <?php echo htmlspecialchars($place["lat"]); ?>

                        <br>

                        Longitude:
                        <?php echo htmlspecialchars($place["lon"]); ?>

                    </label>

                </div>

            <?php endforeach; ?>


            <div class="continue">

                <button type="submit">
                    Continue
                </button>

            </div>

        </form>


    <?php elseif ($result !== null && !$result["success"]): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($result["message"]);
            ?>

        </div>

    <?php endif; ?>


    <!-- SELECTED DESTINATION -->

    <?php if ($selectedPlace !== null): ?>

        <div class="success">

            <h2>✅ Destination Selected</h2>

            <p>
                <strong>Place:</strong><br>
                <?php
                echo htmlspecialchars(
                    $selectedPlace["display_name"]
                );
                ?>
            </p>

            <p>
                <strong>Latitude:</strong>
                <?php
                echo htmlspecialchars(
                    $selectedPlace["lat"]
                );
                ?>
            </p>

            <p>
                <strong>Longitude:</strong>
                <?php
                echo htmlspecialchars(
                    $selectedPlace["lon"]
                );
                ?>
            </p>

        </div>

    <?php endif; ?>

</div>

</body>

</html>