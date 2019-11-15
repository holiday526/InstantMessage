<?php
/**
 * Created by PhpStorm.
 * User: holi_mac
 * Date: 11/11/2019
 * Time: 5:57 PM
 */

return [
    'status' => [
        'ok' => 200,
        'created' => 201,
        'noContent' => 204,
        'notModified' => 304,
        'badRequest' => 400,
        'unauthorized' => 401,
        'forbidden' => 403,
        'notFound' => 404,
        'conflict' => 409,
        'internalServerError' => 500,
    ],
    'jsonContentType' => [
        'Content-Type' => 'application/json'
    ],
    'kmsClient' => [
        'version' => 'latest',
        'region' => env('AWS_DEFAULT_REGION'),
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY')
        ]
    ],
];