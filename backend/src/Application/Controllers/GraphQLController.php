<?php
declare(strict_types=1);

namespace App\Application\Controllers;

use App\GraphQL\DataLoaders;
use Doctrine\DBAL\Connection;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\BuildSchema;
use Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter;
use Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;

class GraphQLController
{
    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->db = $connection;
        $this->logger = $logger;
    }

    public function index(Request $request, Response $response)
    {
        $graphQLSyncPromiseAdapter = new SyncPromiseAdapter();
        $promiseAdapter = new WebonyxGraphQLSyncPromiseAdapter($graphQLSyncPromiseAdapter);

        $dataLoaders = new DataLoaders($this->db);

        $this->resolvers(include dirname(__DIR__, 3) . '/src/GraphQL/resolvers.php');
        $schema = BuildSchema::build(file_get_contents(dirname(__DIR__, 3) . '/src/GraphQL/schema.graphqls'));

        # Context, objects and data the resolver can then access. In this case the database object.
        $context = [
            'loaders' => $dataLoaders->build($promiseAdapter),
            'db'      => $this->db,
            'logger'  => $this->logger
        ];

        # Create server configuration
        $config = ServerConfig::create()
            ->setSchema($schema)
            ->setContext($context)
            ->setQueryBatching(true)
            ->setPromiseAdapter($graphQLSyncPromiseAdapter);

        # Allow GraphQL Server to handle the request and response
        $server = new StandardServer($config);
        $response = $server->processPsrRequest($request, $response, $response->getBody());

        $sqlQueryLogger = $this->db->getConfiguration()->getSQLLogger();
        $this->logger->info(json_encode($sqlQueryLogger->queries));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function resolvers($resolvers)
    {
        Executor::setDefaultFieldResolver(function ($source, $args, $context, ResolveInfo $info) use ($resolvers) {
            $fieldName = $info->fieldName;

            if (is_null($fieldName)) {
                throw new \Exception('Could not get $fieldName from ResolveInfo');
            }

            if (is_null($info->parentType)) {
                throw new \Exception('Could not get $parentType from ResolveInfo');
            }

            $parentTypeName = $info->parentType->name;

            if (isset($resolvers[$parentTypeName])) {
                $resolver = $resolvers[$parentTypeName];

                if (is_array($resolver)) {
                    if (array_key_exists($fieldName, $resolver)) {
                        $value = $resolver[$fieldName];

                        return is_callable($value) ? $value($source, $args, $context, $info) : $value;
                    }
                }

                if (is_object($resolver)) {
                    if (isset($resolver->{$fieldName})) {
                        $value = $resolver->{$fieldName};

                        return is_callable($value) ? $value($source, $args, $context, $info) : $value;
                    }
                }
            }

            return Executor::defaultFieldResolver($source, $args, $context, $info);
        });
    }
}
