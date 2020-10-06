<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-documentation-apiblueprint for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-documentation-apiblueprint/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-documentation-apiblueprint/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\Documentation\ApiBlueprint;

use Laminas\ApiTools\Documentation\Operation as BaseOperation;
use Laminas\ApiTools\Documentation\Service as BaseService;
use Laminas\Http\Request;

class Resource
{
    const RESOURCE_TYPE_ENTITY = 'entity';
    const RESOURCE_TYPE_COLLECTION = 'collection';
    const RESOURCE_TYPE_RPC = 'rpc';

    /**
     * @var BaseOperation[]
     */
    private $operations;

    /**
     * @var BaseService[]
     */
    private $service;

    /**
     * @var string
     */
    private $uri;

    /**
     * @var Action[]
     */
    private $actions = [];

    /**
     * @var self::RESOURCE_TYPE_*
     */
    private $resourceType;

    private $typeMapping = [
        self::RESOURCE_TYPE_ENTITY => 'Entity',
        self::RESOURCE_TYPE_COLLECTION => 'Collection',
        self::RESOURCE_TYPE_RPC => 'Procedure',
    ];

    private $verbMapping = [
        self::RESOURCE_TYPE_ENTITY => [
            Request::METHOD_GET => 'Retrieve',
            Request::METHOD_PATCH => 'Update',
            Request::METHOD_DELETE => 'Remove',
        ],
        self::RESOURCE_TYPE_COLLECTION => [
            Request::METHOD_GET => 'List all',
            Request::METHOD_POST => 'Create new',
        ],
        self::RESOURCE_TYPE_RPC => 'Requests',
    ];

    /**
     * @param BaseService $service
     * @param BaseOperation[] $operations
     * @param string $uri
     * @param string $resourceType self::RESOURCE_TYPE_*
     */
    public function __construct(BaseService $service, array $operations, $uri, $resourceType)
    {
        $this->service = $service;
        $this->operations = $operations;
        $this->uri = $uri;
        $this->resourceType = $resourceType;

        if ($this->getResourceType() == self::RESOURCE_TYPE_COLLECTION && $this->getParameter()) {
            $this->uri .= "?page={page}&limit={limit}";
            if (true) {
                $this->uri .= "&filter={filter}&order-by={order%2Dby}";
            }
        }

        foreach ($operations as $operation) {
            if ($operation->getDescription()) {
                continue;
            }

            $operation->setDescription($this->verbMapping[$this->resourceType] ?? '');
            $operation->setDescription($this->verbMapping[$this->resourceType][$operation->getHttpMethod()] ?? '');
        }

        $this->createActions();
    }

    /**
     * @return string
     */
    public function getName()
    {
        if ($this->resourceType === self::RESOURCE_TYPE_COLLECTION) {
            return
                $this->service->getOperationsName()
                ?? $this->typeMapping[$this->resourceType]
                ?? $this->service->getName();
        }

        if ($this->resourceType === self::RESOURCE_TYPE_ENTITY) {
            return
                $this->service->getEntityOperationsName()
                ?? $this->typeMapping[$this->resourceType]
                ?? $this->service->getName();
        }

        return $this->typeMapping[$this->resourceType] ?? $this->service->getName();
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @return string self::RESOURCE_TYPE_*
     */
    public function getResourceType()
    {
        return $this->resourceType;
    }

    /**
     * @return string
     */
    public function getParameter()
    {
        return $this->service->getRouteIdentifierName();
    }

    /**
     * @return \Laminas\ApiTools\Documentation\Field[]
     */
    public function getBodyProperties()
    {
        return $this->service->getFields('input_filter');
    }

    /**
     * @return Action[]
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * Iterate operations to create actions.
     *
     * @return void
     */
    private function createActions()
    {
        foreach ($this->operations as $operation) {
            $action = new Action($operation);
            if ($action->allowsChangingEntity()) {
                $action->setBodyProperties(array_filter($this->service->getFields('input_filter'), function ($field) {
                    return $field;
                }));
                if ($this->service->getFields($action->getHttpMethod())) {
                    $action->setBodyProperties(array_filter($this->service->getFields($action->getHttpMethod()), function ($field) {
                        return $field;
                    }));
                }
            }
            if (!$action->allowsChangingEntity()) {
                $action->setBodyProperties(array_filter($this->service->getFields('doctrine'), function ($field) {
                    return $field;
                }));
            }

            foreach ($action->getBodyProperties() as $field) {
                if ($action->getHttpMethod() === Request::METHOD_PATCH) {
                    $field->setRequired(false);
                }
            }

            $this->actions[] = $action;
        }
    }
}
