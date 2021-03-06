<?php

namespace Zendesk\API\Traits\Resource;

use stdClass;
use Zendesk\API\Exceptions\ApiResponseException;
use Zendesk\API\Exceptions\AuthException;
use Zendesk\API\Exceptions\RouteException;

trait FindAll
{
    /**
     * List all of this resource
     *
     * @param array  $params
     *
     * @param string $routeKey
     *
     * @param bool   $raw
     *
     * @return stdClass | null
     */
    public function findAll(array $params = [], $raw = false, $routeKey = __FUNCTION__)
    {
        try {
            $route = $this->getRoute($routeKey, $params);
        } catch (RouteException $e) {
            if (! isset($this->resourceName)) {
                $this->resourceName = $this->getResourceNameFromClass();
            }

            $route = $this->resourceName . '.json';
            $this->setRoute(__FUNCTION__, $route);
        }

        return $this->client->get(
            $route,
            $params,
            $raw
        );
    }
}
