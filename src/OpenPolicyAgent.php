<?php

namespace BuildSecurity\OpenPolicyAgentBundle;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\HeaderUtils;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;

/**
 * Authorize attribute to denote controllers that should be
 * integrated with the Open Policy Agent authorization middleware.
 */
#[Attribute(Attribute::TARGET_METHOD|Attribute::TARGET_FUNCTION)]
class Authorize
{
    public array $resources;

    public function __construct(string ...$resources)
    {
        $this->resources = $resources;
    }
}

/**
 * OpenPolicyAgent implements an event subscriber and registers it.
 * This listener receives events for every incoming request after
 * it has been routed to a controller.
 */
class OpenPolicyAgent implements EventSubscriberInterface, LoggerAwareInterface
{
    // Load Policy Decision Point configuration and create an HTTP client.
    private $pdp_config;
    private $client;

    public function __construct($pdp_config, HttpClientInterface $client)
    {
        foreach (array(
            'port',
            'hostname',
            'policy.path',
            'readTimeout.milliseconds',
            'connectionTimeout.milliseconds',
            'retry.maxAttempts',
            'retry.backoff.milliseconds',
        ) as $config_key) {
            if (!isset($config_key)) {
                throw new RuntimeException('Invalid Policy Decision Point configuration');
            }
        }

        $this->pdp_config = $pdp_config;

        $this->client = new RetryableHttpClient(
            $client,
            strategy: new GenericRetryStrategy(
                // Retry backoff base.
                delayMs: $this->pdp_config['retry.backoff.milliseconds'],
                // Retry backoff exponent.
                multiplier: 2.0,
            ),
            maxRetries: $this->pdp_config['retry.maxAttempts'],
        );
    }

    // Make the configured logger available.
    private $logger;

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    // onKernelController is the registered listener method.
    public function onKernelController(KernelEvent $event)
    {
        $controller = $event->getController();

        // When a controller class defines multiple action methods, the controller
        // is returned as [$controllerInstance, 'methodName']
        $method = NULL;

        if (is_array($controller)) {
            $obj = new \ReflectionObject($controller[0]);
            $method = $obj->getMethod($controller[1]);
        } else {
            $obj = new \ReflectionObject($controller);
            $method = $obj->getMethods()[0];
        }

        // Check if the received controller method has the Authorize attribute.
        $attribute = $method->getAttributes(
            Authorize::class,
            \ReflectionAttribute::IS_INSTANCEOF,
        )[0] ?? null;

        if ($attribute == null) {
            return;
        }
        
        if (!$this->authorize($event, $attribute->getArguments())) {
            throw new AccessDeniedHttpException('OPA Authz: Deny');
        }
    }

    // authorize makes the call to the PDP.
    private function authorize($event, $resources): bool {
        $request = $event->getRequest();

        

        $payload = array(
            'input' => array(
                'request' => array(
                    'headers' => $request->headers->all(),
                    'method' => $request->getMethod(),
                    'path' => $request->getPathInfo(),
                    'query' => HeaderUtils::parseQuery($request->getQueryString() ?? ''),
                    'scheme' => $request->getScheme(),
                ),
                'resources' => array(
                    'requirements' => $resources,
                ),
            ),
        );

        $response = $this->client->request(
            'POST',
            $this->pdp_config['hostname'].':'.$this->pdp_config['port'].'/v1/data'.$this->pdp_config['policy.path'],
            array(
                'json' => $payload,
                'timeout' => $this->pdp_config['connectionTimeout.milliseconds'],
            ),
        );

        return ($response->toArray()['result']['allow'] == true);
    }

    // getSubscribedEvents registers the listener during compile time.
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}