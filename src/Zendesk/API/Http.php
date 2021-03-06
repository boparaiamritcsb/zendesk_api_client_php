<?php

namespace Zendesk\API;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\LazyOpenStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use stdClass;
use Zendesk\API\Exceptions\ApiResponseException;
use Zendesk\API\Exceptions\AuthException;

/**
 * HTTP functions via curl
 * @package Zendesk\API
 */
class Http
{
    public static $curl;
    
    /**
     * Use the send method to call every endpoint except for oauth/tokens
     *
     * @param HttpClient $client
     * @param string     $endPoint E.g. "/tickets.json"
     * @param array      $options
     *                             Available options are listed below:
     *                             array $queryParams Array of unencoded key-value pairs, e.g. ["ids" => "1,2,3,4"]
     *                             array $postFields Array of unencoded key-value pairs, e.g. ["filename" => "blah.png"]
     *                             string $method "GET", "POST", etc. Default is GET.
     *                             string $contentType Default is "application/json"
     *
     * @param bool       $raw
     *
     * @return ResponseInterface|StreamInterface|stdClass|null
     * @throws ApiResponseException
     * @throws AuthException
     * @throws GuzzleException
     */
    public static function send(
        HttpClient $client,
        string $endPoint,
        $options = [],
        $raw = false
    ) {
        $options = array_merge([
                                   'method'      => 'GET',
                                   'contentType' => 'application/json',
                                   'postFields'  => null,
                                   'queryParams' => null
                               ], $options);
        
        $headers = array_merge([
                                   'Accept'       => 'application/json',
                                   'Content-Type' => $options['contentType'],
                                   'User-Agent'   => $client->getUserAgent()
                               ], $client->getHeaders());
        
        $request = new Request($options['method'], $client->getApiUrl() . $client->getApiBasePath() . $endPoint,
                               $headers);
        
        $requestOptions = [];
        
        if (!empty($options['multipart'])) {
            $request                     = $request->withoutHeader('Content-Type');
            $requestOptions['multipart'] = $options['multipart'];
        } elseif (!empty($options['postFields'])) {
            $request = $request->withBody(Utils::streamFor(json_encode($options['postFields'])));
        } elseif (!empty($options['file'])) {
            if ($options['file'] instanceof StreamInterface) {
                $request = $request->withBody($options['file']);
            } elseif (is_file($options['file'])) {
                $fileStream = new LazyOpenStream($options['file'], 'r');
                $request    = $request->withBody($fileStream);
            }
        }
        
        if (!empty($options['queryParams'])) {
            foreach ($options['queryParams'] as $queryKey => $queryValue) {
                $uri     = $request->getUri();
                $uri     = $uri->withQueryValue($uri, $queryKey, $queryValue);
                $request = $request->withUri($uri, true);
            }
        }
        
        try {
            // enable anonymous access
            if ($client->getAuth()) {
                list ($request, $requestOptions) = $client->getAuth()
                                                          ->prepareRequest($request, $requestOptions);
            }
            $response = $client->guzzle->send($request, $requestOptions);
        } catch (RequestException $e) {
            $requestException = RequestException::create($e->getRequest(), $e->getResponse(), $e);
            throw new ApiResponseException($requestException);
        } finally {
            $client->setDebug($request->getHeaders(), $request->getBody(),
                              isset($response) ? $response->getStatusCode() : null, (string)isset($response),
                              isset($e) ? $e : null);
            
            $request->getBody()
                    ->rewind();
        }
        
        $client->setSideload(null);
        
        if ($raw) {
            return $response;
        }
        
        return json_decode($response->getBody());
    }
}
