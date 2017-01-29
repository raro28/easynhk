<?php

require_once '../../bootstrap.php';
$slimDefinitions = require_once '../../default.slim.config.php';

$dependencies = [
    MongoDB\Client::class => DI\object(MongoDB\Client::class)->constructorParameter('uri', 'mongodb://localhost')
];

$app = require_once '../../default.slim.app.php';

function getDesiredProperties($propertyQS) {
    $propertyKeys = $propertyQS != '' ? explode(',', $propertyQS) : [];
    foreach ($propertyKeys as $key) {
        yield $key => 1;
    }

    yield '_id' => 0;
}

$app->get('/news/', function(\Psr\Http\Message\RequestInterface $request, Psr\Http\Message\ResponseInterface $response, MongoDB\Client $mongo) {
    $page = intval($request->getQueryParam('page', 1));
    $pageSize = intval($request->getQueryParam('pageSize', 10));
    $skip = $pageSize * $page;
    $desiredProperties = iterator_to_array(getDesiredProperties($request->getQueryParam('properties', '')));

    if (key_exists('resources', $desiredProperties)) {
        unset($desiredProperties['resources']);
    }

    if (count($desiredProperties) == 1) {
        $desiredProperties['resources'] = 0;
    }

    $response->getBody()->write(\GuzzleHttp\json_encode(iterator_to_array($mongo->newsdb->news->find([], ['skip' => $skip, 'limit' => $pageSize, 'projection' => $desiredProperties]))));

    return $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
});

$app->get('/news/{id}', function(\Psr\Http\Message\RequestInterface $request, Psr\Http\Message\ResponseInterface $response, MongoDB\Client $mongo, $id) {
    $desiredProperties = iterator_to_array(getDesiredProperties($request->getQueryParam('properties', '')));
    $result = $mongo->newsdb->news->findOne(['news_id' => $id], ['projection' => $desiredProperties]);

    if (!$result) {
        throw new Slim\Exception\NotFoundException($request, $response);
    }

    $response->getBody()->write(\GuzzleHttp\json_encode($result));

    return $response->withHeader('Content-Type', 'application/json; charset=UTF-8');
});

$app->run();
