<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Required Daily Working Time
    |--------------------------------------------------------------------------
    |
    | The number of seconds an employee must work per day to meet the requirement.
    | 7 hours = 25,200 seconds.
    |
    */

    'required_seconds' => 25200,

    /*
    |--------------------------------------------------------------------------
    | Attendance Counting Window
    |--------------------------------------------------------------------------
    |
    | Only time between window_start and window_end counts toward the daily
    | requirement. Time outside this window is excluded from calculations.
    | Values should be in H:i:s format.
    |
    */

    'window_start' => '05:00:00',
    'window_end' => '21:00:00',

];
