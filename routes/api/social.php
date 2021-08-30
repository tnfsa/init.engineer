<?php

use App\Domains\Social\Http\Controllers\Api\Cards\ReviewController;

/**
 * All route names are prefixed with 'api.social'.
 */
Route::group([
    'prefix' => 'social',
    'as' => 'social.',
    'namespace' => 'Social',
], function () {
    /**
     * All route names are prefixed with 'api.social.cards'.
     */
    Route::group([
        'prefix' => 'cards',
        'as' => 'cards.',
        'namespace' => 'Cards',
    ], function () {
        // ...
    });

    /**
     * All route names are prefixed with 'api.social.reviews'.
     */
    Route::group([
        'prefix' => 'reviews',
        'as' => 'reviews.',
        'namespace' => 'Reviews',
    ], function () {
        // ...
    });
});