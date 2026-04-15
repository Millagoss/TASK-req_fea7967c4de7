<?php

return [

    'profile_weights' => [
        'browse'   => (int) env('PROFILE_WEIGHT_BROWSE', 1),
        'search'   => (int) env('PROFILE_WEIGHT_SEARCH', 1),
        'click'    => (int) env('PROFILE_WEIGHT_CLICK', 2),
        'favorite' => (int) env('PROFILE_WEIGHT_FAVORITE', 3),
        'rate'     => (int) env('PROFILE_WEIGHT_RATE', 5),
        'comment'  => (int) env('PROFILE_WEIGHT_COMMENT', 2),
    ],

];
