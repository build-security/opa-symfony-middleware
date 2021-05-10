# opa-symfony-middleware
## Abstract
opa-symfony-middleware is a PHP Symfony middleware meant for authorizing API requests using a 3rd party policy engine (OPA) as the Policy Decision Point (PDP).
If you're not familiar with OPA, please [learn more](https://www.openpolicyagent.org/).

This package is built for PHP v8.0 and above and Symfony v4.22 and above.
## Usage

### Prerequisites 
- Finish our "onboarding" tutorial
- Run a pdp instance
- Install the Symfony app - In your symfony app run
```
composer require buildsecurity/symfony-opa
```
---
**Important note**

In the following example we used our aws managed pdp instance to ease your first setup, but if you feel comfortable you are recommended to use your own pdp instance instead.
In that case, don't forget to change the **hostname** and the **port** in your code.

---
### Simple usage

Edit your `services.yaml` file - 
edit the configuration for OPA:

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

### Mandatory configuration

 1. `hostname`: The hostname of the Policy Decision Point (PDP)
 2. `port`: The port at which the OPA service is running
 3. `policyPath`: Full path to the policy (including the rule) that decides whether requests should be authorized
   
The `PDP_HOSTNAME`, `PDP_PORT`, `PDP_POLICY_PATH`, `PDP_READ_TIMEOUT_MS`, `PDP_CONNECTION_TIMEOUT_MS`, `PDP_RETRY_MAX_ATTEMPTS` and `PDP_RETRY_BACKOFF_MS` environment variables, when added to your Symfony server environment, will override this service configuration.

### Optional configuration
 1. `allowOnFailure`: Boolean. "Fail open" mechanism to allow access to the API in case the policy engine is not reachable. **Default is false**.
 2. `includeBody`: Boolean. Whether or not to pass the request body to the policy engine. **Default is true**.
 3. `includeHeaders`: Boolean. Whether or not to pass the request headers to the policy engine. **Default is true**
 4. `timeout`: Boolean. Amount of time to wait before request is abandoned and request is declared as failed. **Default is 1000ms**.
 5. `enable`: Boolean. Whether or not to consult with the policy engine for the specific request. **Default is true**
##### Example
To add the authorization middleware to a controller method, just decorate it with the `Authorize` attribute.
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
### PDP Request example

This is what the input received by the PDP would look like.

```
{
    "input": {
        "request": {
            "method": "GET",
            "query": {
                "querykey": "queryvalue"
            },
            "path": "/some/path",
            "scheme": "http",
            "host": "localhost",
            "body": {
                "bodykey": "bodyvalue"
            },
            "headers": {
                "content-type": "application/json",
                "user-agent": "PostmanRuntime/7.26.5",
                "accept": "*/*",
                "cache-control": "no-cache",
                "host": "localhost:3000",
                "accept-encoding": "gzip, deflate, br",
                "connection": "keep-alive",
                "content-length": "24"
            }
        },
        "source": {
            "port": 63405,
            "address": "::1"
        },
        "destination": {
            "port": 3000,
            "address": "::1"
        },
        "resources": {
            "attributes": {
                "region": "israel",
                "userId": "buildsec"
            },
            "permissions": [
                "user.read"
            ]
        },
        "serviceId": 1
    }
}
```
