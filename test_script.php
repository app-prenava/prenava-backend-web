<?php

require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";

$request = Illuminate\Http\Request::create(
    "/api/recomendation/sports/create",
    "POST",
    [],
    [],
    [],
    ["HTTP_CONTENT_TYPE" => "application/json"],
    json_encode([
        "bmi" => 24.5,
        "hypertension" => false,
        "is_diabetes" => false,
        "gestational_diabetes" => false,
        "is_fever" => false,
        "is_high_heart_rate" => false,
        "previous_complications" => false,
        "mental_health_issue" => false,
        "low_impact_pref" => false,
        "water_access" => false,
        "back_pain" => false,
        "placenta_position_restriction" => false
    ])
);

// We simulate an authenticated user with role ibu_hamil by binding to the request
// Actually it is easier to just find the `AuthToken::assertRoleFresh` and comment it out temporarily to test the ML service connection
// Let me look at RecomendationSportController.php again

