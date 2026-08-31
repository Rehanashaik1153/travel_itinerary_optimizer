<?php

function geocodeDestination($destination)
{
    $destination = trim($destination);

    if ($destination === "") {
        return [
            "success" => false,
            "message" => "Please enter a destination."
        ];
    }


    $url = "https://nominatim.openstreetmap.org/search?" .
        http_build_query([
            "q" => $destination,
            "format" => "jsonv2",
            "limit" => 5,
            "addressdetails" => 1
        ]);


    $options = [
        "http" => [
            "method" => "GET",

            "header" =>
                "User-Agent: WanderAI-Travel-Itinerary-Optimizer/1.0 (Educational Project)\r\n" .
                "Accept: application/json\r\n",

            "timeout" => 30,

            "ignore_errors" => true
        ]
    ];


    $context = stream_context_create($options);


    $response = @file_get_contents(
        $url,
        false,
        $context
    );


    if ($response === false) {

        $error = error_get_last();

        return [
            "success" => false,
            "message" =>
                "Unable to contact the location service. Please check your internet connection and try again.",
            "debug" => $error["message"] ?? ""
        ];
    }


    $data = json_decode($response, true);


    if (!is_array($data)) {

        return [
            "success" => false,
            "message" =>
                "The location service returned an invalid response."
        ];
    }


    if (count($data) === 0) {

        return [
            "success" => false,
            "message" =>
                "Destination not found. Please enter a valid location."
        ];
    }


    return [
        "success" => true,
        "results" => $data
    ];
}

?>