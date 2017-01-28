<?php

require_once '../../bootstrap.php';
$slimDefinitions = require_once '../../default.slim.config.php';


$dependencies = [
    MongoDB\Client::class => DI\object(MongoDB\Client::class)->constructorParameter('uri', 'mongodb://localhost')
];

$app = require_once '../../default.slim.app.php';

$app->get('/news/', function(\Psr\Http\Message\RequestInterface $request, Psr\Http\Message\ResponseInterface $response, MongoDB\Client $mongo) {
    $page = $request->getQueryParam('page', 1);
    $pageSize = $request->getQueryParam('pageSize', 10);
    $skip = $pageSize * $page;
    $desiredProperties = ['_id' => 0, 'title' => 1, 'news_id' => 1];

    $response->getBody()->write(\GuzzleHttp\json_encode(iterator_to_array($mongo->newsdb->news->find([], ['skip' => $skip, 'limit' => $pageSize, 'projection' => $desiredProperties]))));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/news/{id}', function(\Psr\Http\Message\RequestInterface $request,Psr\Http\Message\ResponseInterface $response, MongoDB\Client $mongo, $id) {
    $desiredProperties = ['_id' => 0, 'title' => 1, 'news_id' => 1];
    $result = $mongo->newsdb->news->findOne(['news_id' => $id],['projection'=>$desiredProperties]);
    
    if(!$result){
        throw new Slim\Exception\NotFoundException($request,$response);
    }

    $response->getBody()->write(\GuzzleHttp\json_encode($result));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
