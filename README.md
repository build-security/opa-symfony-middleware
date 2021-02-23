# opa-symfony-middleware
PHP Symfony middleware that adds Open Policy Agent authorization to incoming requests.

This package is built for PHP v8.0 and above and Symfony v4.22 and above.

## Installation

In your Symfony app directory:

```
composer require buildsecurity/symfony-opa
```

## Usage

Add parameters that define how requests should be made to the Policy Decision Point (PDP) in your `services.yaml` file:

```
parameters:
    pdp.port: 8181
    pdp.hostname: http://localhost
    pdp.policy.path: /php
    pdp.readTimeout.milliseconds: 5000
    pdp.connectionTimeout.milliseconds: 5000
    pdp.retry.maxAttempts: 2
    pdp.retry.backoff.milliseconds: 250
```

Then register the `OpenPolicyAgent` service, again in `services.yaml`:

```
services:
    # Make the PDP configuration to the OpenPolicyAgent service.
    BuildSecurity\OpenPolicyAgentBundle\OpenPolicyAgent:
        arguments:
            $pdp_config:
                port: '%env(default:pdp.port:PDP_PORT)%'
                hostname: '%env(default:pdp.hostname:PDP_HOSTNAME)%'
                policy.path: '%env(default:pdp.policy.path:PDP_POLICY_PATH)%'
                readTimeout.milliseconds: '%env(default:pdp.readTimeout.milliseconds:PDP_READ_TIMEOUT_MS)%'
                connectionTimeout.milliseconds: '%env(default:pdp.connectionTimeout.milliseconds:PDP_CONNECTION_TIMEOUT_MS)%'
                retry.maxAttempts: '%env(default:pdp.retry.maxAttempts:PDP_RETRY_MAX_ATTEMPTS)%'
                retry.backoff.milliseconds: '%env(default:pdp.retry.backoff.milliseconds:PDP_RETRY_BACKOFF_MS)%'
```

The `PDP_HOSTNAME`, `PDP_PORT`, `PDP_POLICY_PATH`, `PDP_READ_TIMEOUT_MS`, `PDP_CONNECTION_TIMEOUT_MS`, `PDP_RETRY_MAX_ATTEMPTS` and `PDP_RETRY_BACKOFF_MS` environment variables, when added to your Symfony server environment, will override this service configurtion.

To add the authorization middleware to a controller method, just decorate it with the `Authorize` attribute.

##### Example
```
<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

// This is the attribute that the middleware looks for.
use BuildSecurity\OpenPolicyAgentBundle\Authorize;

// You can see some_method has been decorated using the
// Authorize attribute. The decoration resources, ['foo', 'bar']
// will be made available in the input to the OPA request.
class SomeController
{
    #[Authorize('foo', 'bar')]
    public function some_method(): Response
    {
        return new Response(
            '<html><body>Authorized!</body></html>'
        );
    }
}
```
